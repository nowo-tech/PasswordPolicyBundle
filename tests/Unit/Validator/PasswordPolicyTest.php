<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Validator;

use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The constraint must accept the documented named arguments, e.g. `#[PasswordPolicy(checkCurrentPassword: false)]`.
 */
final class PasswordPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $constraint = new PasswordPolicy();

        self::assertNull($constraint->detectExtensions);
        self::assertNull($constraint->extensionMinLength);
        self::assertTrue($constraint->checkCurrentPassword);
        self::assertStringStartsWith('Cannot change your password to an old one.', $constraint->message);
        self::assertStringStartsWith('Cannot change your password to an extension of an old one.', $constraint->extensionMessage);
        self::assertSame(['Default'], $constraint->groups);
    }

    public function testNamedArguments(): void
    {
        $constraint = new PasswordPolicy(
            message: 'reused',
            extensionMessage: 'extended',
            detectExtensions: true,
            extensionMinLength: 6,
            checkCurrentPassword: false,
            groups: ['password'],
            payload: 'p',
        );

        self::assertSame('reused', $constraint->message);
        self::assertSame('extended', $constraint->extensionMessage);
        self::assertTrue($constraint->detectExtensions);
        self::assertSame(6, $constraint->extensionMinLength);
        self::assertFalse($constraint->checkCurrentPassword);
        self::assertSame(['password'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
    }

    public function testAttributeWithNamedArguments(): void
    {
        $fixture = new class {
            #[PasswordPolicy(detectExtensions: true, checkCurrentPassword: false)]
            public ?string $plainPassword = null;
        };

        $attributes = (new ReflectionProperty($fixture, 'plainPassword'))->getAttributes(PasswordPolicy::class);
        self::assertCount(1, $attributes);

        $constraint = $attributes[0]->newInstance();

        self::assertFalse($constraint->checkCurrentPassword);
        self::assertTrue($constraint->detectExtensions);
    }
}
