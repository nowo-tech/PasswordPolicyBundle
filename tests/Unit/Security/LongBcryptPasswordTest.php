<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Security;

use DateTime;
use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyService;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordHashVerifier;
use Nowo\PasswordPolicyBundle\Subject\PasswordReuseChecker;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryPasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\PasswordPolicyBundle\Util\NativeHashVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function str_repeat;

use const PASSWORD_BCRYPT;

/**
 * JZ-M1: Symfony's bcrypt hasher pre-hashes passwords over 72 bytes or with a NUL byte (base64(sha512(p))); the reuse
 * checks must still match them, on the entity path and on the read-only subject path.
 */
final class LongBcryptPasswordTest extends UnitTestCase
{
    private function symfonyHash(string $plain): string
    {
        return (new NativePasswordHasher(null, null, 4, PASSWORD_BCRYPT))->hash($plain);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function passwords(): iterable
    {
        // 84 + 3 = 87 bytes, above bcrypt's 72-byte limit.
        yield '87 bytes' => [str_repeat('correct-horse-', 6) . 'bat'];
        yield 'NUL byte' => ["pass\0word-with-nul"];
    }

    #[DataProvider('passwords')]
    public function testNativeVerifierMatchesSymfonyPreHashedPasswords(string $password): void
    {
        $hash = $this->symfonyHash($password);

        self::assertTrue((new NativePasswordHasher(null, null, 4, PASSWORD_BCRYPT))->verify($hash, $password));
        self::assertTrue(NativeHashVerifier::verify($password, $hash));
        self::assertFalse(NativeHashVerifier::verify($password . 'x', $hash));
        self::assertTrue((new DefaultPasswordHashVerifier())->verify(KernelUser::class, $hash, $password));
    }

    public function testPlainPasswordHashBcryptStillMatches(): void
    {
        $password = str_repeat('a', 80);
        // password_hash() without pre-hashing truncates at 72 bytes: the first attempt still answers it.
        self::assertTrue(NativeHashVerifier::verify($password, password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])));
    }

    #[DataProvider('passwords')]
    public function testEntityPathMatchesHistoryCurrentAndExtension(string $password): void
    {
        $service = new PasswordPolicyService(Mockery::mock(UserPasswordHasherInterface::class));

        $user    = new KernelUser($this->symfonyHash($password));
        $history = new PasswordHistoryMock();
        $history->setPassword($this->symfonyHash($password));
        $history->setCreatedAt(new DateTime('-5 days'));
        $user->addPasswordHistory($history);

        self::assertTrue($service->matchesCurrentPassword($password, $user));
        self::assertSame($history, $service->getHistoryByPassword($password, $user));
        self::assertSame($history, $service->getHistoryByPasswordExtension($password . '1', $user));
        self::assertNull($service->getHistoryByPassword($password . 'x', $user));
    }

    #[DataProvider('passwords')]
    public function testSubjectPathMatchesHistoryCurrentAndExtension(string $password): void
    {
        $repository = new InMemoryPasswordHistoryRepository();
        $repository->add(KernelUser::class, '7', $this->symfonyHash($password), new DateTimeImmutable('-5 days'));
        $checker = new PasswordReuseChecker($repository, new DefaultPasswordHashVerifier());

        $historyOnly = new PasswordPolicySubject(KernelUser::class, '7');
        self::assertSame(PasswordReuseMatch::TYPE_EXACT, $checker->findReuse($password, $historyOnly)?->type);
        self::assertSame(PasswordReuseMatch::TYPE_EXTENSION, $checker->findReuse($password . '1', $historyOnly, detectExtensions: true)?->type);

        $currentOnly = new PasswordPolicySubject(KernelUser::class, '8', $this->symfonyHash($password));
        self::assertSame(PasswordReuseMatch::TYPE_CURRENT, $checker->findReuse($password, $currentOnly)?->type);
    }
}
