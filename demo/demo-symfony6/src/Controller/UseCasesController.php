<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller demonstrating all Password Policy Bundle use cases.
 */
#[Route('/use-cases')]
class UseCasesController extends AbstractController
{
    public function __construct(
        private readonly PasswordExpiryServiceInterface $passwordExpiryService,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/', name: 'use_cases_index')]
    public function index(): Response
    {
        return $this->render('use_cases/index.html.twig', [
            'use_cases' => $this->getUseCases(),
        ]);
    }

    #[Route('/password-expiry', name: 'use_case_password_expiry')]
    public function passwordExpiry(): Response
    {
        $user = $this->getUser();
        $isExpired = false;
        $daysRemaining = null;
        $daysSinceExpiry = null;

        if ($user instanceof User) {
            $isExpired = $this->passwordExpiryService->isPasswordExpired();
            $passwordChangedAt = $user->getPasswordChangedAt();
            
            if ($passwordChangedAt) {
                $now = new \DateTime();
                $diff = $now->diff($passwordChangedAt);
                $daysSinceChange = (int) $diff->format('%a');
                $expiryDays = 90; // From configuration
                
                if ($daysSinceChange >= $expiryDays) {
                    $daysSinceExpiry = $daysSinceChange - $expiryDays;
                } else {
                    $daysRemaining = $expiryDays - $daysSinceChange;
                }
            }
        }

        return $this->render('use_cases/password_expiry.html.twig', [
            'user' => $user,
            'isExpired' => $isExpired,
            'daysRemaining' => $daysRemaining,
            'daysSinceExpiry' => $daysSinceExpiry,
            'lockedRoutes' => $this->passwordExpiryService->getLockedRoutes(),
            'excludedRoutes' => $this->passwordExpiryService->getExcludedRoutes(),
            'resetPasswordRoute' => $this->passwordExpiryService->getResetPasswordRouteName(),
        ]);
    }

    #[Route('/password-history', name: 'use_case_password_history')]
    public function passwordHistory(): Response
    {
        $user = $this->getUser();
        $history = [];
        $historyCount = 0;

        if ($user instanceof User) {
            $history = $user->getPasswordHistory()->toArray();
            $historyCount = count($history);
        }

        return $this->render('use_cases/password_history.html.twig', [
            'user' => $user,
            'history' => $history,
            'historyCount' => $historyCount,
            'passwordsToRemember' => 5, // From configuration
        ]);
    }

    #[Route('/password-reuse', name: 'use_case_password_reuse')]
    public function passwordReuse(): Response
    {
        $users = $this->userRepository->findAll();
        $testCases = [];

        foreach ($users as $user) {
            $history = $user->getPasswordHistory()->toArray();
            if (count($history) > 0) {
                $testCases[] = [
                    'user' => $user,
                    'historyCount' => count($history),
                    'oldestPassword' => $history[count($history) - 1] ?? null,
                ];
            }
        }

        return $this->render('use_cases/password_reuse.html.twig', [
            'testCases' => $testCases,
        ]);
    }

    #[Route('/validation', name: 'use_case_validation')]
    public function validation(): Response
    {
        return $this->render('use_cases/validation.html.twig', [
            'validationRules' => [
                'Password cannot be reused from history',
                'Password must be different from current password',
                'Validation occurs automatically via @PasswordPolicy constraint',
            ],
        ]);
    }

    #[Route('/excluded-routes', name: 'use_case_excluded_routes')]
    public function excludedRoutes(): Response
    {
        return $this->render('use_cases/excluded_routes.html.twig', [
            'excludedRoutes' => $this->passwordExpiryService->getExcludedRoutes(),
            'lockedRoutes' => $this->passwordExpiryService->getLockedRoutes(),
            'note' => 'Routes in excluded_notified_routes are not checked for password expiry, even if they are in notified_routes.',
        ]);
    }

    #[Route('/redirect-on-expiry', name: 'use_case_redirect_on_expiry')]
    public function redirectOnExpiry(): Response
    {
        $user = $this->getUser();
        $isExpired = false;

        if ($user instanceof User) {
            $isExpired = $this->passwordExpiryService->isPasswordExpired();
        }

        return $this->render('use_cases/redirect_on_expiry.html.twig', [
            'user' => $user,
            'isExpired' => $isExpired,
            'redirectEnabled' => false, // From configuration - currently disabled
            'resetPasswordRoute' => $this->passwordExpiryService->getResetPasswordRouteName(),
        ]);
    }

    private function getUseCases(): array
    {
        return [
            [
                'name' => 'Password Expiry Detection',
                'route' => 'use_case_password_expiry',
                'description' => 'Test password expiry detection and see expiry status, locked routes, and excluded routes.',
                'features' => [
                    'Check if password is expired',
                    'View days remaining or days since expiry',
                    'See locked routes configuration',
                    'See excluded routes configuration',
                ],
            ],
            [
                'name' => 'Password History Tracking',
                'route' => 'use_case_password_history',
                'description' => 'View password history for the current user and see how many passwords are tracked.',
                'features' => [
                    'View complete password history',
                    'See password change timestamps',
                    'Understand passwords_to_remember limit',
                ],
            ],
            [
                'name' => 'Password Reuse Prevention',
                'route' => 'use_case_password_reuse',
                'description' => 'See how the bundle prevents users from reusing old passwords.',
                'features' => [
                    'View password history for all users',
                    'Understand reuse prevention mechanism',
                    'See validation in action',
                ],
            ],
            [
                'name' => 'Password Validation',
                'route' => 'use_case_validation',
                'description' => 'Learn about the @PasswordPolicy validator constraint and how it works.',
                'features' => [
                    'Automatic validation via constraint',
                    'Password history checking',
                    'Integration with Symfony Validator',
                ],
            ],
            [
                'name' => 'Excluded Routes',
                'route' => 'use_case_excluded_routes',
                'description' => 'Understand how excluded routes work to prevent redirect loops.',
                'features' => [
                    'See configured excluded routes',
                    'Understand route exclusion logic',
                    'Prevent redirect loops',
                ],
            ],
            [
                'name' => 'Redirect on Expiry',
                'route' => 'use_case_redirect_on_expiry',
                'description' => 'Learn about automatic redirection when password expires (currently disabled in demo).',
                'features' => [
                    'Automatic redirection configuration',
                    'Reset password route configuration',
                    'Graceful error handling',
                ],
            ],
        ];
    }
}

