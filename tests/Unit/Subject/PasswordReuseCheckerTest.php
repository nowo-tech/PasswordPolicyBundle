<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Subject;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordHashVerifier;
use Nowo\PasswordPolicyBundle\Subject\PasswordReuseChecker;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryPasswordHistoryRepository;
use PHPUnit\Framework\TestCase;

use const PASSWORD_BCRYPT;

/**
 * Host without a Doctrine user entity: a domain class name, a string id and an in-memory history adapter.
 */
final class PasswordReuseCheckerTest extends TestCase
{
    private const SUBJECT_CLASS = 'App\Domain\User';

    private InMemoryPasswordHistoryRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPasswordHistoryRepository();
        $this->repository->add(self::SUBJECT_CLASS, 'u-1', $this->hash('Old-password1'), new DateTimeImmutable('2026-01-01'));
        $this->repository->add(self::SUBJECT_CLASS, 'u-1', $this->hash('Older-pass'), new DateTimeImmutable('2025-01-01'));
    }

    private function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function checker(?PasswordPolicyConfigurationService $config = null): PasswordReuseChecker
    {
        return new PasswordReuseChecker($this->repository, new DefaultPasswordHashVerifier(), $config);
    }

    private function subject(?string $hash = null): PasswordPolicySubject
    {
        return new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1', $hash, new DateTimeImmutable('2026-02-01'));
    }

    public function testFindsAPasswordOfTheHistory(): void
    {
        $match = $this->checker()->findReuse('Old-password1', $this->subject());

        self::assertInstanceOf(PasswordReuseMatch::class, $match);
        self::assertSame(PasswordReuseMatch::TYPE_EXACT, $match->type);
        self::assertSame('2026-01-01', $match->usedAt?->format('Y-m-d'));
    }

    public function testOnlyTheNewestPasswordsToRememberAreChecked(): void
    {
        $config = new PasswordPolicyConfigurationService();
        $config->setEntityConfiguration(self::SUBJECT_CLASS, ['passwords_to_remember' => 1]);

        self::assertNull($this->checker($config)->findReuse('Older-pass', $this->subject()));
        self::assertNotNull($this->checker()->findReuse('Older-pass', $this->subject()));
    }

    public function testFindsTheCurrentPassword(): void
    {
        $subject = $this->subject($this->hash('Current-1'));

        $match = $this->checker()->findReuse('Current-1', $subject);
        self::assertInstanceOf(PasswordReuseMatch::class, $match);
        self::assertSame(PasswordReuseMatch::TYPE_CURRENT, $match->type);
        self::assertSame('2026-02-01', $match->usedAt?->format('Y-m-d'));

        self::assertNull($this->checker()->findReuse('Current-1', $subject, checkCurrentPassword: false));
        self::assertNull($this->checker()->findReuse('Current-1', $this->subject('')));
    }

    public function testExtensionsFollowTheOptionOrTheClassSetting(): void
    {
        self::assertNull($this->checker()->findReuse('Old-password12', $this->subject()));

        $match = $this->checker()->findReuse('Old-password12', $this->subject(), detectExtensions: true);
        self::assertSame(PasswordReuseMatch::TYPE_EXTENSION, $match?->type);

        $config = new PasswordPolicyConfigurationService();
        $config->setEntityConfiguration(self::SUBJECT_CLASS, ['detect_password_extensions' => true, 'extension_min_length' => 4]);
        self::assertSame(PasswordReuseMatch::TYPE_EXTENSION, $this->checker($config)->findReuse('Old-password12', $this->subject())?->type);
        self::assertNull($this->checker($config)->findReuse('Brand-new-123', $this->subject()));
    }

    public function testOtherSubjectsDoNotShareTheHistory(): void
    {
        $other = new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-2');

        self::assertNull($this->checker()->findReuse('Old-password1', $other));
    }
}
