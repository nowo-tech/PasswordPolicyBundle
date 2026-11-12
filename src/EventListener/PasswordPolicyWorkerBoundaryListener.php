<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Nowo\PasswordPolicyBundle\Worker\UnitOfWorkFrames;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use function class_exists;

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
 * - `console.terminate`, only when it ends the OUTERMOST command and no Messenger message or main request is in flight:
 *   a command run in-process from another command, from a message handler or from a controller must not clear the
 *   outer unit (a flush in progress would lose its `PasswordChangeRecordedEvent`).
 *
 * Which unit is outermost is tracked by {@see UnitOfWorkFrames} (1.11.2, audits NM / NL2-v), fed at priority
 * `PHP_INT_MAX` so another listener that throws or stops propagation cannot unbalance it: one frame per console run
 * (Command + input), saving and restoring the outer message / request state, failing runs healed at the next unit;
 * a main-request depth (`kernel.request` → `kernel.finish_request`); a Messenger in-flight flag. The clears keep their
 * priorities (4096 at the start, -4096 at the end).
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
final readonly class PasswordPolicyWorkerBoundaryListener implements EventSubscriberInterface, ResetInterface
{
    /** Messenger skip event (class name; only dispatched by `messenger:failed:retry`, symfony/messenger 7.2+). */
    public const MESSENGER_WORKER_MESSAGE_SKIPPED = WorkerMessageSkipEvent::class;

    /** Units of work running in this process (deliberately not tagged `kernel.reset`). */
    private UnitOfWorkFrames $frames;

    /**
     * @param iterable<ResetInterface> $flushState the per-entity {@see PasswordEntityListener} services
     */
    public function __construct(
        private iterable $flushState,
    ) {
        $this->frames = new UnitOfWorkFrames();
    }

    /**
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST        => [['onKernelRequestStarted', PHP_INT_MAX], ['onKernelRequest', 4096]],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', PHP_INT_MAX],
            KernelEvents::TERMINATE      => ['onKernelTerminate', -4096],
        ];

        if (class_exists(WorkerMessageReceivedEvent::class)) {
            $events[WorkerMessageReceivedEvent::class]      = [['onMessageReceivedStarted', PHP_INT_MAX], ['onMessageReceived', 4096]];
            $events[WorkerMessageHandledEvent::class]       = [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]];
            $events[WorkerMessageFailedEvent::class]        = [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]];
            $events[self::MESSENGER_WORKER_MESSAGE_SKIPPED] = ['onMessageFinishedStarted', PHP_INT_MAX];
            // A listener may skip a received message (no handled / failed event follows).
            $events[WorkerStoppedEvent::class] = ['onWorkerStopped', PHP_INT_MAX];
        }

        if (class_exists(ConsoleEvents::class)) {
            // Bookkeeping first (PHP_INT_MAX), the clear late: a listener in between cannot skip the bookkeeping.
            $events[ConsoleEvents::COMMAND]   = ['onConsoleCommand', PHP_INT_MAX];
            $events[ConsoleEvents::ERROR]     = ['onConsoleError', PHP_INT_MAX];
            $events[ConsoleEvents::TERMINATE] = [['onConsoleTerminateStarted', PHP_INT_MAX], ['onConsoleTerminate', -4096]];
        }

        return $events;
    }

    /**
     * Main request starts (bookkeeping).
     */
    public function onKernelRequestStarted(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->frames->requestStarted();
        }
    }

    /**
     * Main request: starts with empty listeners; sub-requests share the main request's unit of work.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->clear();
    }

    /**
     * Main request finished (bookkeeping).
     */
    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->frames->requestFinished();
        }
    }

    /**
     * End of the main request (after the response was sent).
     */
    public function onKernelTerminate(): void
    {
        $this->clear();
    }

    /**
     * A Messenger message is received (bookkeeping).
     */
    public function onMessageReceivedStarted(): void
    {
        $this->frames->messageReceived();
    }

    /**
     * Start of a Messenger message: nothing from the previous message or the worker's idle time is kept.
     */
    public function onMessageReceived(): void
    {
        $this->clear();
    }

    /**
     * A Messenger message is handled, failed or skipped (bookkeeping).
     */
    public function onMessageFinishedStarted(): void
    {
        $this->frames->messageFinished();
    }

    /**
     * End of a message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        $this->clear();
    }

    /**
     * The worker stopped (a received message may have been skipped without a handled / failed event).
     */
    public function onWorkerStopped(): void
    {
        $this->frames->workerStopped();
    }

    /**
     * A console run starts (bookkeeping).
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->frames->commandStarted($event);
    }

    /**
     * A console run failed (bookkeeping: its frame is marked failing until its own terminate).
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->frames->commandFailed($event);
    }

    /**
     * A console run ends (bookkeeping: its frame is removed, the outermost verdict kept for the late clear).
     */
    public function onConsoleTerminateStarted(ConsoleTerminateEvent $event): void
    {
        $this->frames->commandTerminated($event);
    }

    /**
     * Clears only when the outermost command ends outside a Messenger message and outside a main request.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->frames->isOutermost($event)) {
            $this->clear();
        }
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

    /**
     * Forgets the unit-of-work bookkeeping. Not tagged `kernel.reset` and not relied on in worker mode.
     */
    public function reset(): void
    {
        $this->frames->reset();
    }
}
