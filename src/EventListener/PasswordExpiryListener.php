<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Event listener for handling password expiry checks on kernel requests.
 *
 * This listener checks if a password has expired when accessing locked routes
 * and displays appropriate error messages to the user.
 */
class PasswordExpiryListener
{
    /**
     * PasswordExpiryListener constructor.
     *
     * @param PasswordExpiryServiceInterface $passwordExpiryService The service for checking password expiry
     * @param RequestStack                   $requestStack          The request stack for accessing the current request and session
     * @param UrlGeneratorInterface          $urlGenerator          The URL generator for generating routes
     * @param TranslatorInterface            $translator            The translator service for translating messages
     * @param string                         $errorMessageType      The type of flash message (e.g., 'error', 'warning', 'info')
     * @param string|array                   $errorMessage          The error message(s) to display when password is expired
     * @param bool                           $redirectOnExpiry      Whether to redirect to reset password route when password expires
     * @param LoggerInterface|null           $logger                The logger service (optional, uses NullLogger if not provided)
     * @param bool                           $enableLogging         Whether logging is enabled
     * @param string                         $logLevel              The logging level to use
     * @param EventDispatcherInterface|null  $eventDispatcher       The event dispatcher (optional)
     */
    public function __construct(
        public PasswordExpiryServiceInterface $passwordExpiryService,
        public RequestStack $requestStack,
        public UrlGeneratorInterface $urlGenerator,
        public TranslatorInterface $translator,
        private readonly string $errorMessageType,
        /**
         * @var string
         */
        private string|array $errorMessage,
        private readonly bool $redirectOnExpiry = false,
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
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $requestEvent): void
    {

        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();
        $route = $request->attributes->get('_route');

        // Skip if route is null (anonymous routes or routes without names)
        if ($route === null) {
            return;
        }

        $isLockedRoute = $this->passwordExpiryService->isLockedRoute($route);

        if (!$isLockedRoute) {
            return;
        }

        $excludeRoutes = $this->passwordExpiryService->getExcludedRoutes();
        $isPasswordExpired = $this->passwordExpiryService->isPasswordExpired();

        if (!in_array($route, $excludeRoutes) && $isPasswordExpired) {
            $token = $this->passwordExpiryService->tokenStorage->getToken();
            $user = $token?->getUser();

            // Dispatch PasswordExpiredEvent if user is available and event dispatcher is set
            if ($user instanceof \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface && $this->eventDispatcher) {
                $event = new PasswordExpiredEvent($user, $route, $this->redirectOnExpiry);
                $this->eventDispatcher->dispatch($event);
            }

            $userId = $user && method_exists($user, 'getId') ? $user->getId() : 'unknown';
            $userIdentifier = 'unknown';
            if ($user && method_exists($user, 'getUserIdentifier')) {
                /** @var callable $getUserIdentifier */
                $getUserIdentifier = [$user, 'getUserIdentifier'];
                $userIdentifier = $getUserIdentifier();
            } elseif ($user && method_exists($user, 'getEmail')) {
                /** @var callable $getEmail */
                $getEmail = [$user, 'getEmail'];
                $userIdentifier = $getEmail();
            }

            // Log password expiry detection
            if ($this->enableLogging && $this->logger) {
                $this->log($this->logLevel, 'Password expired detected', [
                  'user_id' => $userId,
                  'user_identifier' => $userIdentifier,
                  'route' => $route,
                  'redirect_on_expiry' => $this->redirectOnExpiry,
                ]);
            }

            $session = $this->requestStack->getSession();
            if ($session instanceof Session) {
                // Translate error message(s) - use local variable to avoid modifying property
                $translatedMessage = $this->errorMessage;
                if (is_array($translatedMessage)) {
                    foreach ($translatedMessage as $key => $value) {
                        $translatedMessage[$key] = $this->translator->trans($value, [], 'PasswordPolicyBundle');
                    }
                } else {
                    $translatedMessage = $this->translator->trans($translatedMessage, [], 'PasswordPolicyBundle');
                }

                $session->getFlashBag()->add($this->errorMessageType, $translatedMessage);
            }

            // Redirect to reset password route if configured
            if ($this->redirectOnExpiry) {
                $resetPasswordRouteName = $this->passwordExpiryService->getResetPasswordRouteName();
                if (!empty($resetPasswordRouteName)) {
                    try {
                        $resetPasswordUrl = $this->urlGenerator->generate($resetPasswordRouteName);
                        $requestEvent->setResponse(new RedirectResponse($resetPasswordUrl));

                        // Log redirect
                        if ($this->enableLogging && $this->logger) {
                            $this->log($this->logLevel, 'Redirecting to password reset route', [
                              'user_id' => $userId,
                              'user_identifier' => $userIdentifier,
                              'route' => $route,
                              'reset_password_route' => $resetPasswordRouteName,
                            ]);
                        }

                        return;
                    } catch (\Exception $e) {
                        // If route doesn't exist, log error but don't break the application
                        if ($this->enableLogging && $this->logger) {
                            $this->logger->error('Failed to generate reset password route', [
                              'user_id' => $userId,
                              'user_identifier' => $userIdentifier,
                              'route' => $resetPasswordRouteName,
                              'exception' => $e->getMessage(),
                            ]);
                        }
                        // The flash message will still be shown
                    }
                }
            }
        }
    }

    /**
     * Logs a message with the configured log level.
     *
     * @param string $level   The log level (debug, info, notice, warning, error)
     * @param string $message The log message
     * @param array  $context Additional context data
     *
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logger) {
            return;
        }

        $context['bundle'] = 'PasswordPolicyBundle';

        match ($level) {
            'debug' => $this->logger->debug($message, $context),
            'info' => $this->logger->info($message, $context),
            'notice' => $this->logger->notice($message, $context),
            'warning' => $this->logger->warning($message, $context),
            'error' => $this->logger->error($message, $context),
            default => $this->logger->info($message, $context),
        };
    }
}
