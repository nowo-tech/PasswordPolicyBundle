<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

//
use Symfony\Component\HttpKernel\Event\RequestEvent;
//
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
// services
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
// attributes
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest')]
class PasswordExpiryListener
{

  /**
   * The function is a constructor that initializes properties and dependencies for a class.
   *
   * @param string errorMessageType A string representing the type of error message. This could be used
   * to differentiate between different types of error messages, such as "error", "warning", or "info".
   * @param string errorMessage The `errorMessage` parameter is a string that represents the error
   * message to be displayed.
   */
  public function __construct(
      public PasswordExpiryServiceInterface $passwordExpiryService,
      public SessionInterface $session,
      public UrlGeneratorInterface $urlGenerator,
      public TranslatorInterface $translator,
      private readonly string $errorMessageType,
      /**
       * @var string
       */
      private string|array $errorMessage
  )
  {
  }

  /**
   * The function checks if a route is locked and if the password has expired, and if so, it adds an
   * error message to the session flash bag and redirects the user to the current page.
   *
   * @param RequestEvent event The `` parameter is an instance of the `RequestEvent` class. It
   * represents an event that occurs when a request is made to the application.
   *
   * @return The code returns either nothing (null) or a RedirectResponse object.
   */
  public function onKernelRequest(RequestEvent $requestEvent): void
  {
    //
    if (!$requestEvent->isMainRequest()) {
      return;
    }

    $request = $requestEvent->getRequest();
    $route = $request->get('_route');
    //
    $isLockedRoute = $this->passwordExpiryService->isLockedRoute($route);

    if (!$isLockedRoute) {
      return;
    }

    //
    $excludeRoutes = $this->passwordExpiryService->getExcludedRoutes();
    $isPasswordExpired = $this->passwordExpiryService->isPasswordExpired();
    //
    if ( !in_array($route, $excludeRoutes) && $isPasswordExpired ) {
      if ($this->session instanceof Session) {

        if (is_array($this->errorMessage)) {
          foreach($this->errorMessage as $key => $value){
            $this->errorMessage[$key] = $this->translator->trans($value, [], 'PasswordPolicyBundle');
          }
        } else {
          $this->errorMessage = $this->translator->trans($this->errorMessage, [], 'PasswordPolicyBundle');
        }

        $this->session->getFlashBag()->add($this->errorMessageType, $this->errorMessage);
      }

      // TODO: check if this is the correct way to get the reset password route name
      // $resetPasswordRouteName = $this->passwordExpiryService->getResetPasswordRouteName();
      // $resetPasswordUrl = $this->router->generate($resetPasswordRouteName);
      // $event->setResponse(new RedirectResponse($resetPasswordUrl));
    }
  }
}
