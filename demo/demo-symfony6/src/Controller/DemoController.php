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
                'title' => '🧪 Testing Password Expiry Listener',
                'message' => 'To test the password expiry listener, authenticate with <strong>expired@example.com</strong> / <strong>expired123</strong> (password expired 100 days ago). When accessing routes in <code>notified_routes</code> (like this home page), the listener will display a flash message warning about the expired password.',
            ]);
            
            $this->addFlash('warning', [
                'title' => '⚠️ Demo User Credentials',
                'message' => 'Available test users: <strong>demo@example.com</strong> / <strong>demo123</strong> (expires in 5 days), <strong>admin@example.com</strong> / <strong>admin123</strong> (active), <strong>expired@example.com</strong> / <strong>expired123</strong> (expired). View all users in <a href="' . $this->generateUrl('user_index') . '">Users Management</a>.',
            ]);
        }
        
        return $this->render('demo/home.html.twig', [
            'title' => 'Password Policy Bundle - Demo',
            'message' => 'This is a demo page to showcase the Password Policy Bundle functionality with Symfony 6.4.',
            'features' => [
                'Password history tracking',
                'Password expiry enforcement',
                'Configurable password policies',
                'Doctrine lifecycle events integration',
                'Running on Symfony 6.4',
            ],
            'useCasesUrl' => $this->generateUrl('use_cases_index'),
        ]);
    }
}

