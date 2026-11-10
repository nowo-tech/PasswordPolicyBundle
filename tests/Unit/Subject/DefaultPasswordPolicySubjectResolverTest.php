<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Subject;

use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordPolicySubjectResolver;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\ChangePasswordCommand;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DefaultPasswordPolicySubjectResolverTest extends TestCase
{
    public function testResolvesSubjectsEntitiesAndAwareObjects(): void
    {
        $resolver = new DefaultPasswordPolicySubjectResolver();
        $subject  = new PasswordPolicySubject(KernelUser::class, '7', 'hash');
        $user     = new KernelUser();

        self::assertSame($subject, $resolver->resolve($subject));
        self::assertSame($user, $resolver->resolve($user));
        self::assertSame($subject, $resolver->resolve(new ChangePasswordCommand('new', $subject)));
        self::assertSame($user, $resolver->resolve(new ChangePasswordCommand('new', $user)));
        self::assertNull($resolver->resolve(new ChangePasswordCommand('new', null)));
        self::assertNull($resolver->resolve(new stdClass()));
    }
}
