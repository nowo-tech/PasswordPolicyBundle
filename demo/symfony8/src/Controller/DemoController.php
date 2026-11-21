<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    #[Route('/home', name: 'demo_home')]
    public function home(Request $request): Response
    {
        $session = $request->getSession();

        // Add informational flash messages about testing password expiry listener (only once per session)
        // Use session attribute to track if messages were already shown
        if (!$session->has('demo_info_shown')) {
            $session->set('demo_info_shown', true);
            $this->addFlash('info', [
                'title'   => '🧪 Testing Password Expiry Listener',
                'message' => 'To test the password expiry listener, authenticate with expired@example.com / expired123 (password expired 100 days ago). When accessing routes in notified_routes (like this home page), the listener will display a flash message warning about the expired password.',
            ]);

            $this->addFlash('warning', [
                'title'   => '⚠️ Demo User Credentials',
                'message' => 'Available test users: demo@example.com / demo123 (expires in 5 days), admin@example.com / admin123 (active), expired@example.com / expired123 (expired). View all users in Users Management.',
            ]);
        }

        return $this->render('demo/home.html.twig', [
            'title'    => 'Password Policy Bundle - Demo',
            'message'  => 'This is a demo page to showcase the Password Policy Bundle functionality with Symfony 8.0.',
            'features' => [
                'Password history tracking',
                'Password expiry enforcement',
                'Configurable password policies',
                'Doctrine lifecycle events integration',
                'Running on Symfony 8.0',
            ],
            'useCasesUrl' => $this->generateUrl('use_cases_index'),
        ]);
    }
}
