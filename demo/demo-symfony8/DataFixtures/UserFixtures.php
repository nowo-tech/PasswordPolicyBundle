<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create demo user with password that will expire soon
        $user1 = new User();
        $user1->setEmail('demo@example.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'demo123'));
        // Set password changed 85 days ago (will expire in 5 days if expiry_days is 90)
        $user1->setPasswordChangedAt((new DateTime())->modify('-85 days'));
        $manager->persist($user1);

        // Create demo user with recently changed password
        $user2 = new User();
        $user2->setEmail('admin@example.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'admin123'));
        $user2->setPasswordChangedAt(new DateTime());
        $manager->persist($user2);

        // Create demo user with expired password
        $user3 = new User();
        $user3->setEmail('expired@example.com');
        $user3->setPassword($this->passwordHasher->hashPassword($user3, 'expired123'));
        // Set password changed 100 days ago (expired if expiry_days is 90)
        $user3->setPasswordChangedAt((new DateTime())->modify('-100 days'));
        $manager->persist($user3);

        $manager->flush();
    }
}

