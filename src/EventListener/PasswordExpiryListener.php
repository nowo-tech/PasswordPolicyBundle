<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Exception;
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Nowo\PasswordPolicyBundle\Model\ExpiryFlashStrategy;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\ExpiryFlashThrottleStorageInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function is_array;
use function is_object;

/**
 * Event listener for handling password expiry checks on kernel requests.
 *
 * This listener checks if a password has expired when accessing locked routes
 * and displays appropriate error messages to the user.
 */
class PasswordExpiryListener
{
    /**
     * Request attribute used to avoid adding duplicate expiry flash messages
     * when the listener is triggered multiple times within the same request lifecycle.
     */
    private const FLASH_ALREADY_ADDED_ATTRIBUTE = '_nowo_password_policy.expiry_flash_added';

    /**
     * PasswordExpiryListener constructor.
     *
     * @param PasswordExpiryServiceInterface $passwordExpiryService The service for checking password expiry
     * @param TokenStorageInterface $tokenStorage The token storage for accessing the current user
     * @param RequestStack $requestStack The request stack for accessing the current request and session
     * @param UrlGeneratorInterface $urlGenerator The URL generator for generating routes
     * @param TranslatorInterface $translator The translator service for translating messages
     * @param string $errorMessageType The type of flash message (e.g., 'error', 'warning', 'info')
     * @param array<string, string>|string $errorMessage The error message(s) to display when password is expired
     * @param ExpiryFlashThrottleStorageInterface $flashThrottleStorage Shared or session storage for throttle state
     * @param bool $redirectOnExpiry Whether to redirect to reset password route when password expires
     * @param string $flashStrategy How often to add the expiry flash (see ExpiryFlashStrategy)
     * @param int $flashIntervalMinutes Minutes between flashes when flash_strategy is interval
     * @param LoggerInterface|null $logger The logger service (optional, uses NullLogger if not provided)
     * @param bool $enableLogging Whether logging is enabled
     * @param string $logLevel The logging level to use
     * @param EventDispatcherInterface|null $eventDispatcher The event dispatcher (optional)
     */
    public function __construct(
        public PasswordExpiryServiceInterface $passwordExpiryService,
        private readonly TokenStorageInterface $tokenStorage,
        public RequestStack $requestStack,
        public UrlGeneratorInterface $urlGenerator,
        public TranslatorInterface $translator,
        private readonly string $errorMessageType,
        /** @var array<string, string>|string */
        private readonly string|array $errorMessage,
        private readonly ExpiryFlashThrottleStorageInterface $flashThrottleStorage,
        private readonly bool $redirectOnExpiry = false,
        private readonly string $flashStrategy = ExpiryFlashStrategy::ALWAYS,
        private readonly int $flashIntervalMinutes = 30,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $enableLogging = true,
        private readonly string $logLevel = 'info',
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {
    }

    /**
     * Handles the kernel request event to check for password expiry on locked routes.
     *
     * If the current route is locked and the password has expired, this method adds
     * an error message to the session flash bag.
     *
     * @param RequestEvent $requestEvent The request event containing the current request
     */
    public function onKernelRequest(RequestEvent $requestEvent): void
    {

        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();
        $route   = $request->attributes->get('_route');

        // Skip if route is null (anonymous routes or routes without names)
        if ($route === null) {
            return;
        }

        // Guard against duplicate handling in the same request (FrankenPHP-safe: request-scoped only).
        // Restrict to concrete Request to keep backward compatibility with test doubles.
        if ($request::class === Request::class && $this->isExpiryFlashAlreadyHandled($request)) {
            return;
        }

        $isLockedRoute = $this->passwordExpiryService->isLockedRoute($route);

        if (!$isLockedRoute) {
            return;
        }

        $isPasswordExpired = $this->passwordExpiryService->isPasswordExpired();

        if (!$this->passwordExpiryService->isRouteExcluded($route) && $isPasswordExpired) {
            if ($request::class === Request::class) {
                $this->markExpiryFlashAsHandled($request);
            }

            $token = $this->tokenStorage->getToken();
            $user  = $token?->getUser();
            if (!is_object($user)) {
                $user = null;
            }

            // Dispatch PasswordExpiredEvent if user is available and event dispatcher is set
            if ($user instanceof HasPasswordPolicyInterface && $this->eventDispatcher) {
                $event = new PasswordExpiredEvent($user, $route, $this->redirectOnExpiry);
                $this->eventDispatcher->dispatch($event);
            }

            $userId         = $user instanceof HasPasswordPolicyInterface ? (string) $user->getId() : 'unknown';
            $userIdentifier = $user instanceof UserInterface
                ? $user->getUserIdentifier()
                : 'unknown';
            $subjectKey = $this->resolveSubjectKey($user);

            // Log password expiry detection
            if ($this->enableLogging && $this->logger) {
                $this->log($this->logLevel, 'Password expired detected', [
                    'user_id'            => $userId,
                    'user_identifier'    => $userIdentifier,
                    'route'              => $route,
                    'redirect_on_expiry' => $this->redirectOnExpiry,
                ]);
            }

            $session = $this->requestStack->getSession();
            if ($session instanceof Session && $this->shouldAddExpiryFlash($subjectKey)) {
                // Translate error message(s) - use local variable to avoid modifying property
                $translatedMessage = $this->errorMessage;
                if (is_array($translatedMessage)) {
                    foreach ($translatedMessage as $key => $value) {
                        $translatedMessage[$key] = $this->translator->trans($value, [], 'PasswordPolicyBundle');
                    }
                } else {
                    $translatedMessage = $this->translator->trans($translatedMessage, [], 'PasswordPolicyBundle');
                }

                $flashBag         = $session->getFlashBag();
                $existingMessages = $flashBag->peek($this->errorMessageType, []);
                if (!in_array($translatedMessage, $existingMessages, true)) {
                    $flashBag->add($this->errorMessageType, $translatedMessage);
                    $this->markExpiryFlashShown($subjectKey);
                }
            }

            // Redirect to reset password route if configured
            if ($this->redirectOnExpiry) {
                $resetPasswordRouteName = $this->passwordExpiryService->getResetPasswordRouteName();
                if ($resetPasswordRouteName !== '' && $resetPasswordRouteName !== '0') {
                    try {
                        $resetPasswordUrl = $this->urlGenerator->generate($resetPasswordRouteName);
                        $requestEvent->setResponse(new RedirectResponse($resetPasswordUrl));

                        // Log redirect
                        if ($this->enableLogging && $this->logger) {
                            $this->log($this->logLevel, 'Redirecting to password reset route', [
                                'user_id'              => $userId,
                                'user_identifier'      => $userIdentifier,
                                'route'                => $route,
                                'reset_password_route' => $resetPasswordRouteName,
                            ]);
                        }

                        return;
                    } catch (Exception $e) {
                        // If route doesn't exist, log error but don't break the application
                        if ($this->enableLogging && $this->logger) {
                            $this->logger->error('Failed to generate reset password route', [
                                'user_id'         => $userId,
                                'user_identifier' => $userIdentifier,
                                'route'           => $resetPasswordRouteName,
                                'exception'       => $e->getMessage(),
                            ]);
                        }
                        // The flash message will still be shown
                    }
                }
            }
        }
    }

    private function isExpiryFlashAlreadyHandled(Request $request): bool
    {
        return $request->attributes->get(self::FLASH_ALREADY_ADDED_ATTRIBUTE, false) === true;
    }

    private function markExpiryFlashAsHandled(Request $request): void
    {
        $request->attributes->set(self::FLASH_ALREADY_ADDED_ATTRIBUTE, true);
    }

    private function shouldAddExpiryFlash(?string $subjectKey): bool
    {
        if ($subjectKey === null) {
            return $this->flashStrategy === ExpiryFlashStrategy::ALWAYS;
        }

        return match ($this->flashStrategy) {
            ExpiryFlashStrategy::NEVER            => false,
            ExpiryFlashStrategy::ONCE_PER_SESSION => $this->flashThrottleStorage->getLastShownAt($subjectKey) === null,
            ExpiryFlashStrategy::INTERVAL         => $this->isFlashIntervalElapsed($subjectKey),
            default                               => true,
        };
    }

    private function isFlashIntervalElapsed(string $subjectKey): bool
    {
        $lastShown = $this->flashThrottleStorage->getLastShownAt($subjectKey);
        if ($lastShown === null) {
            return true;
        }

        return (time() - $lastShown) >= ($this->flashIntervalMinutes * 60);
    }

    private function markExpiryFlashShown(?string $subjectKey): void
    {
        if ($subjectKey === null
            || $this->flashStrategy === ExpiryFlashStrategy::ALWAYS
            || $this->flashStrategy === ExpiryFlashStrategy::NEVER) {
            return;
        }

        $this->flashThrottleStorage->markShown($subjectKey, time());
    }

    private function resolveSubjectKey(?object $user): ?string
    {
        if ($user instanceof HasPasswordPolicyInterface) {
            return $user::class . ':' . $user->getId();
        }

        if ($user instanceof UserInterface) {
            return 'user:' . $user->getUserIdentifier();
        }

        try {
            return 'session:' . $this->requestStack->getSession()->getId();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Logs a message with the configured log level.
     *
     * @param string $level The log level (debug, info, notice, warning, error)
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $context['bundle'] = 'PasswordPolicyBundle';

        match ($level) {
            'debug'   => $this->logger->debug($message, $context),
            'info'    => $this->logger->info($message, $context),
            'notice'  => $this->logger->notice($message, $context),
            'warning' => $this->logger->warning($message, $context),
            'error'   => $this->logger->error($message, $context),
            default   => $this->logger->info($message, $context),
        };
    }
}
