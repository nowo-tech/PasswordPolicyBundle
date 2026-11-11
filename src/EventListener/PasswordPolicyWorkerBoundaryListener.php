<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use function class_exists;
use function max;

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
 *   outer unit (a flush in progress would lose its `PasswordChangeRecordedEvent`). The depth is counted on
 *   `console.command` / `console.terminate`; the message flag is set on message received and unset on handled /
 *   failed / worker stopped; the request flag is set on the main `kernel.request` and unset on `kernel.terminate`.
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
final class PasswordPolicyWorkerBoundaryListener implements EventSubscriberInterface
{
    /**
     * Console commands currently running in this process (nested in-process runs count). Process-lifetime counter,
     * bounded by the nesting depth; intentionally not reset by `kernel.reset` (a reset between messages of a
     * `messenger:consume` command would lose the outer command).
     */
    private int $consoleDepth = 0;

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
     * @return array<string, array{0: string, 1: int}>
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
            $events[ConsoleEvents::COMMAND]   = ['onConsoleCommand', 4096];
            $events[ConsoleEvents::TERMINATE] = ['onConsoleTerminate', -4096];
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
     * A command starts (nested in-process runs count).
     */
    public function onConsoleCommand(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        ++$this->consoleDepth;
    }

    /**
     * Clears only when the outermost command ends outside a Messenger message and outside an HTTP request.
     */
    public function onConsoleTerminate(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        $this->consoleDepth = max(0, $this->consoleDepth - 1);
        if ($this->consoleDepth > 0 || $this->messageInFlight || $this->requestInFlight) {
            return;
        }

        $this->clear();
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
