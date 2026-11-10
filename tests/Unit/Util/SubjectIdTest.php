<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Util;

use Nowo\PasswordPolicyBundle\Util\SubjectId;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

final class SubjectIdTest extends TestCase
{
    public function testOfCastsScalarsAndStringables(): void
    {
        self::assertSame('7', SubjectId::of(7));
        self::assertSame('abc', SubjectId::of('abc'));
        self::assertSame('uuid-1', SubjectId::of(new class implements Stringable {
            public function __toString(): string
            {
                return 'uuid-1';
            }
        }));
        self::assertSame('', SubjectId::of(null));
        self::assertSame('', SubjectId::of(new stdClass()));
    }

    public function testClassOfStripsDoctrineProxyPrefix(): void
    {
        self::assertSame(stdClass::class, SubjectId::classOf(new stdClass()));

        if (!class_exists('Proxies\__CG__\Nowo\PasswordPolicyBundle\Tests\Unit\Util\ProxiedUser', false)) {
            eval('namespace Proxies\__CG__\Nowo\PasswordPolicyBundle\Tests\Unit\Util; final class ProxiedUser {}');
        }
        $class = 'Proxies\__CG__\\' . __NAMESPACE__ . '\ProxiedUser';
        self::assertTrue(class_exists($class));
        $proxy = new $class();
        self::assertSame('Nowo\PasswordPolicyBundle\Tests\Unit\Util\ProxiedUser', SubjectId::classOf($proxy));
    }
}
