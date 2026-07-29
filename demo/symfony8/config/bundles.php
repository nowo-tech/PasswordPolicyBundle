<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Nowo\PasswordPolicyBundle\NowoPasswordPolicyBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

return [
    FrameworkBundle::class          => ['all' => true],
    DoctrineBundle::class           => ['all' => true],
    DoctrineFixturesBundle::class   => ['dev' => true, 'test' => true],
    SecurityBundle::class           => ['all' => true],
    NowoPasswordPolicyBundle::class => ['all' => true],
    TwigBundle::class               => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    WebProfilerBundle::class        => ['dev' => true],
    NowoTwigInspectorBundle::class  => ['dev' => true, 'test' => true],
];
