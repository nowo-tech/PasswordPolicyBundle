<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Subject;

use Mockery;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordHashVerifier;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

use const PASSWORD_BCRYPT;

final class DefaultPasswordHashVerifierTest extends UnitTestCase
{
    public function testPasswordHashFormatsAreAnsweredByPasswordVerify(): void
    {
        $factory = Mockery::mock(PasswordHasherFactoryInterface::class);
        $factory->shouldNotReceive('getPasswordHasher');
        $verifier = new DefaultPasswordHashVerifier($factory);
        $hash     = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue($verifier->verify('App\User', $hash, 'secret'));
        self::assertFalse($verifier->verify('App\User', $hash, 'other'));
        self::assertFalse($verifier->verify('App\User', '', 'secret'));
    }

    public function testOtherFormatsUseTheSymfonyHasherOfTheClass(): void
    {
        $hasher = Mockery::mock(PasswordHasherInterface::class);
        $hasher->shouldReceive('verify')->with('legacy-hash', 'secret')->andReturnTrue();
        $factory = Mockery::mock(PasswordHasherFactoryInterface::class);
        $factory->shouldReceive('getPasswordHasher')->with('App\User')->andReturn($hasher);

        self::assertTrue((new DefaultPasswordHashVerifier($factory))->verify('App\User', 'legacy-hash', 'secret'));
    }

    public function testOtherFormatsWithoutHasherDoNotMatch(): void
    {
        self::assertFalse((new DefaultPasswordHashVerifier())->verify('App\User', 'legacy-hash', 'secret'));

        $factory = Mockery::mock(PasswordHasherFactoryInterface::class);
        $factory->shouldReceive('getPasswordHasher')->andThrow(new RuntimeException('No password hasher'));
        self::assertFalse((new DefaultPasswordHashVerifier($factory))->verify('App\User', 'legacy-hash', 'secret'));
    }
}
