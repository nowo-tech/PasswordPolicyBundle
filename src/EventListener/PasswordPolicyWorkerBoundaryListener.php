<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use function array_slice;
use function class_exists;
use function count;

use const PHP_INT_MAX;

/**
 * Starts every unit of work with empty per-flush state in the Doctrine password listeners (REQ-RUNTIME-002).
 *
 * {@see PasswordEntityListener} keeps the entities of the current flush between `onFlush` and `postFlush`. When a flush
 * fails, `postFlush` never runs and those entities (users with their password hashes) stayed in the shared listener
 * until the next flush or `kernel.reset`. This listener clears them at every boundary, so they never outlive the unit
 * of work that loaded them:
 *
 * - main `kernel.request` (sub-requests share the main request's unit of work) and `kernel.terminate`;
 * - Messenger `WorkerMessageReceivedEvent`, `WorkerMessageHandledEvent` / `WorkerMessageFailedEvent`;
 * - `console.terminate`, only when it ends the OUTERMOST command and no Messenger message or HTTP request is in flight:
 *   a command run in-process from another command, from a message handler or from a controller must not clear the
 *   outer unit (a flush in progress would lose its `PasswordChangeRecordedEvent`). The message flag is set on message
 *   received and unset on handled / failed / worker stopped; the request flag is set on the main `kernel.request` and
 *   unset on `kernel.terminate`.
 *
 * Console bookkeeping (1.11.2, audit NM): the running commands are a stack pushed on `console.command` and popped on
 * `console.terminate`, both at `PHP_INT_MAX`, so another listener that throws or stops propagation cannot skip it. A
 * terminate pops its own command and every command started after it (a nested run whose terminate was skipped); a
 * terminate of a command that is not on the stack changes nothing (never a blind decrement). Whether the command was
 * the outermost unit is decided there; the clear itself still runs at the late priority (-4096).
 *
 * The listeners stay tagged `kernel.reset`; this subscriber keeps them correct where the services resetter does not
 * run: a host that removes or bypasses `services_resetter` (or drives `http_kernel` directly), Messenger consumers
 * started with `--no-reset`, and long-running console commands.
 *
 * The listeners arrive as a lazy iterator of `IGNORE_ON_UNINITIALIZED_REFERENCE` references: a boundary only clears
 * instances that already exist and never builds one.
 *
 * @internal registered by the bundle; the constructor is not a public API
 */
final class PasswordPolicyWorkerBoundaryListener implements EventSubscriberInterface, ResetInterface
{
    /**
     * Console commands currently running in this process, outermost first (nested in-process runs count). Bounded by
     * the nesting depth; deliberately not tagged `kernel.reset` (a reset between messages of a `messenger:consume`
     * command would lose the outer command).
     *
     * @var list<Command>
     */
    private array $runningCommands = [];

    /** The outermost command whose `console.terminate` is under way: cleared at the late priority. */
    private ?Command $terminatingOutermost = null;

    /** A Messenger message is being handled (between received and handled / failed). */
    private bool $messageInFlight = false;

    /** A main HTTP request is being handled (between the main `kernel.request` and `kernel.terminate`). */
    private bool $requestInFlight = false;

    /**
     * @param iterable<ResetInterface> $flushState the per-entity {@see PasswordEntityListener} services
     */
    public function __construct(
        private readonly iterable $flushState,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST   => ['onKernelRequest', 4096],
            KernelEvents::TERMINATE => ['onKernelTerminate', -4096],
        ];

        if (class_exists(WorkerMessageReceivedEvent::class)) {
            $events[WorkerMessageReceivedEvent::class] = ['onMessageReceived', 4096];
            $events[WorkerMessageHandledEvent::class]  = ['onMessageFinished', -4096];
            $events[WorkerMessageFailedEvent::class]   = ['onMessageFinished', -4096];
            // A listener may skip a received message (no handled / failed event follows).
            $events[WorkerStoppedEvent::class] = ['onWorkerStopped', -4096];
        }

        if (class_exists(ConsoleEvents::class)) {
            // Bookkeeping first (PHP_INT_MAX), the clear late: a listener in between cannot skip the bookkeeping.
            $events[ConsoleEvents::COMMAND]   = ['onConsoleCommand', PHP_INT_MAX];
            $events[ConsoleEvents::TERMINATE] = [['onConsoleTerminateStart', PHP_INT_MAX], ['onConsoleTerminate', -4096]];
        }

        return $events;
    }

    /**
     * Main request: starts with empty listeners; sub-requests share the main request's unit of work.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // @igor-ignore - Unit-of-work flag, set and unset at the HTTP boundaries.
        $this->requestInFlight = true;
        $this->clear();
    }

    /**
     * End of the main request (after the response was sent).
     */
    public function onKernelTerminate(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the HTTP boundaries.
        $this->requestInFlight = false;
        $this->clear();
    }

    /**
     * Start of a Messenger message: nothing from the previous message or the worker's idle time is kept.
     */
    public function onMessageReceived(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
        $this->clear();
    }

    /**
     * End of a message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->clear();
    }

    /**
     * The worker stopped (a received message may have been skipped without a handled / failed event).
     */
    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
    }

    /**
     * A command starts (nested in-process runs count): pushed on the running-command stack.
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command instanceof Command) {
            return;
        }

        // @igor-ignore - Nesting stack of the running commands (no request data).
        $this->runningCommands[] = $command;
    }

    /**
     * A command ends: pops it and every command started after it; remembers whether it was the outermost unit
     * (outside a Messenger message and an HTTP request). A command that is not on the stack changes nothing.
     */
    public function onConsoleTerminateStart(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        $index   = null;
        for ($i = count($this->runningCommands) - 1; $i >= 0; --$i) {
            if ($this->runningCommands[$i] === $command) {
                $index = $i;

                break;
            }
        }

        if ($index === null) {
            return;
        }

        // @igor-ignore - Nesting stack of the running commands (no request data).
        $this->runningCommands = array_slice($this->runningCommands, 0, $index);
        // @igor-ignore - Outermost command whose terminate is under way (cleared at the late priority).
        $this->terminatingOutermost = $index === 0 && !$this->messageInFlight && !$this->requestInFlight ? $command : null;
    }

    /**
     * Clears only when the outermost command ends outside a Messenger message and outside an HTTP request.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->terminatingOutermost instanceof Command || $this->terminatingOutermost !== $event->getCommand()) {
            return;
        }

        // @igor-ignore - Outermost command whose terminate is under way (cleared at the late priority).
        $this->terminatingOutermost = null;
        $this->clear();
    }

    /**
     * Forgets the console and unit-of-work bookkeeping. Not tagged `kernel.reset` and not relied on in worker mode.
     */
    public function reset(): void
    {
        $this->runningCommands      = [];
        $this->terminatingOutermost = null;
        $this->messageInFlight      = false;
        $this->requestInFlight      = false;
    }

    /**
     * Drops the per-flush state of every password listener already built.
     */
    public function clear(): void
    {
        foreach ($this->flushState as $state) {
            $state->reset();
        }
    }
}
