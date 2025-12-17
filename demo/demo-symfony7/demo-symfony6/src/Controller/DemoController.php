<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_home')]
    public function home(): Response
    {
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
        ]);
    }
}

