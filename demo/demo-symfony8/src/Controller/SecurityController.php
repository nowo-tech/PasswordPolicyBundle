<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller for handling authentication (login/logout).
 */
class SecurityController extends AbstractController
{
    /**
     * Handles the login form display and processing.
     *
     * @param AuthenticationUtils $authenticationUtils The authentication utilities service
     * @return Response The login page response
     */
    #[Route('/', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // Redirect authenticated users to home page
        if ($this->getUser()) {
            return $this->redirectToRoute('demo_home');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        $session = $request->getSession();
        
        // Add informational flash messages about demo credentials (only once per session)
        if (!$session->has('login_info_shown')) {
            $session->set('login_info_shown', true);
            $this->addFlash('info', [
                'title' => '🔐 Demo Login Credentials',
                'message' => 'Use these credentials to test password expiry: <strong>expired@example.com</strong> / <strong>expired123</strong> (expired - will trigger listener), <strong>demo@example.com</strong> / <strong>demo123</strong> (expires soon), <strong>admin@example.com</strong> / <strong>admin123</strong> (active).',
            ]);
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Handles logout (route is configured in security.yaml).
     *
     * This method can be blank - it will never be executed!
     * The logout will be intercepted by the logout key on your firewall.
     *
     * @return void
     */
    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

