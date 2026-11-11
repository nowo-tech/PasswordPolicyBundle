<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\PersonalData;

use InvalidArgumentException;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyErasureResult;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class PasswordPolicyPersonalDataSubjectTest extends TestCase
{
    public function testKeepsValuesByteForByte(): void
    {
        $subject = PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', ' UserA ');

        self::assertSame('App\User', $subject->subjectClass);
        self::assertSame(' UserA ', $subject->subjectId);
        self::assertTrue($subject->equals(PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', ' UserA ')));
        self::assertFalse($subject->equals(PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', ' usera ')));
        self::assertFalse($subject->equals(PasswordPolicyPersonalDataSubject::fromSubjectId('App\Admin', ' UserA ')));
    }

    public function testFromEntityAndFromPolicySubject(): void
    {
        $fromEntity = PasswordPolicyPersonalDataSubject::fromEntity(new KernelUser());
        self::assertSame(KernelUser::class, $fromEntity->subjectClass);
        self::assertSame('7', $fromEntity->subjectId);

        $fromSubject = PasswordPolicyPersonalDataSubject::fromPolicySubject(new PasswordPolicySubject('App\User', 'u-1'));
        self::assertSame(['App\User', 'u-1'], [$fromSubject->subjectClass, $fromSubject->subjectId]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty class' => ['', '1'];
        yield 'empty id' => ['App\User', ''];
        yield 'NUL in class' => ["App\\User\0", '1'];
        yield 'NUL in id' => ['App\User', "1\0"];
        yield 'class too long' => [str_repeat('a', 256), '1'];
        yield 'id too long' => ['App\User', str_repeat('a', 129)];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string $class, string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        PasswordPolicyPersonalDataSubject::fromSubjectId($class, $id);
    }

    public function testAcceptsTheMaximumLengths(): void
    {
        $subject = PasswordPolicyPersonalDataSubject::fromSubjectId(str_repeat('a', 255), str_repeat('b', 128));

        self::assertSame(128, \strlen($subject->subjectId));
    }

    public function testErasureResult(): void
    {
        $result = new PasswordPolicyErasureResult(3, 1);

        self::assertSame(['password_history' => 3, 'expiry_flash_throttle' => 1], $result->toArray());
        self::assertSame(4, $result->total());
        self::assertSame(0, (new PasswordPolicyErasureResult())->total());

        $this->expectException(InvalidArgumentException::class);
        new PasswordPolicyErasureResult(-1);
    }
}
