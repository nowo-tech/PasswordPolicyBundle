<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Validator;

use Carbon\Carbon;
use DateTime;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator;
use ReflectionClass;
use stdClass;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PasswordPolicyValidatorTest extends UnitTestCase
{
    private \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface|Mock $entityMock;

    private \Symfony\Component\Validator\Context\ExecutionContextInterface|Mock $contextMock;

    private \Mockery\Mock|PasswordPolicyValidator $validator;

    private \Mockery\Mock|PasswordPolicyServiceInterface $passwordPolicyServiceMock;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')
          ->andReturn('en');

        $this->passwordPolicyServiceMock = Mockery::mock(PasswordPolicyServiceInterface::class);
        $this->validator                 = Mockery::mock(PasswordPolicyValidator::class, [
            $this->passwordPolicyServiceMock,
            $translatorMock,
        ])->makePartial();
        $this->contextMock = Mockery::mock(ExecutionContextInterface::class);
        $this->entityMock  = Mockery::mock(HasPasswordPolicyInterface::class);
    }

    public function testValidatePass(): void
    {
        $this->contextMock->shouldReceive('getObject')
          ->once()
          ->andReturn($this->entityMock);

        $passwordPolicy = new PasswordPolicy();

        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $this->entityMock])
          ->andReturn(null);

        $this->validator->initialize($this->contextMock);
        $this->validator->validate('pwd', $passwordPolicy);
        // If no exception is thrown and no violation is added, validation passes
        $this->assertTrue(true);
    }

    public function testValidateFail(): void
    {
        $this->contextMock->shouldReceive('getObject')
          ->once()
          ->andReturn($this->entityMock);

        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);

        $constraintBuilderMock->shouldReceive('setParameter')
          ->once()
          ->andReturnSelf();

        $constraintBuilderMock->shouldReceive('setCode')
          ->once()
          ->andReturnSelf();

        $constraintBuilderMock->shouldReceive('addViolation')
          ->once();

        $this->contextMock->shouldReceive('buildViolation')
          ->once()
          ->andReturn($constraintBuilderMock);

        $passwordPolicy = new PasswordPolicy();

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')
          ->andReturn(Carbon::now()->subDays(2));

        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $this->entityMock])
          ->andReturn($historyMock);

        $this->validator->initialize($this->contextMock);
        $this->validator->validate('pwd', $passwordPolicy);
        // If violation is added, validation fails (verified by buildViolation being called)
        $this->assertTrue(true);
    }

    public function testValidateNullValue(): void
    {
        $this->validator->validate(null, new PasswordPolicy());
        // Null values should return early without validation
        $this->assertTrue(true);
    }

    public function testValidateBadEntity(): void
    {
        $badEntity = new stdClass();

        $this->contextMock->shouldReceive('getObject')
          ->once()
          ->andReturn($badEntity);

        $passwordPolicy = new PasswordPolicy();

        $this->validator->initialize($this->contextMock);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected validation entity to implements ' . HasPasswordPolicyInterface::class);
        $this->validator->validate('pwd', $passwordPolicy);
    }

    public function testLoggingWithDifferentLevels(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);

        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')
          ->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            $loggerMock,
            true,
            'debug',
        );

        // Use reflection to call private log method
        $reflection = new ReflectionClass($validator);
        $logMethod  = $reflection->getMethod('log');

        // Test debug level
        $loggerMock->shouldReceive('debug')
                   ->once()
                   ->with('Test debug message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'debug', 'Test debug message');

        // Test info level
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test info message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'info', 'Test info message');

        // Test notice level
        $loggerMock->shouldReceive('notice')
                   ->once()
                   ->with('Test notice message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'notice', 'Test notice message');

        // Test warning level
        $loggerMock->shouldReceive('warning')
                   ->once()
                   ->with('Test warning message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'warning', 'Test warning message');

        // Test error level
        $loggerMock->shouldReceive('error')
                   ->once()
                   ->with('Test error message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'error', 'Test error message');

        // Test default level (unknown level should default to info)
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test unknown level message', Mockery::on(static fn ($context) => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($validator, 'unknown', 'Test unknown level message');

        $this->assertTrue(true);
    }

    public function testLoggingWithNullLogger(): void
    {
        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')
          ->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            null,
            true,
            'info',
        );

        // Use reflection to call private log method with null logger
        $reflection = new ReflectionClass($validator);
        $logMethod  = $reflection->getMethod('log');

        // Should not throw exception when logger is null
        $logMethod->invoke($validator, 'info', 'Test message');

        $this->assertTrue(true);
    }

    public function testValidateFailWithExtensionDetection(): void
    {
        $this->contextMock->shouldReceive('getObject')
          ->once()
          ->andReturn($this->entityMock);

        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $constraintBuilderMock->shouldReceive('setParameter')
          ->once()
          ->andReturnSelf();
        $constraintBuilderMock->shouldReceive('setCode')
          ->once()
          ->with(PasswordPolicy::PASSWORD_EXTENSION)
          ->andReturnSelf();
        $constraintBuilderMock->shouldReceive('addViolation')
          ->once();

        $this->contextMock->shouldReceive('buildViolation')
          ->once()
          ->with(Mockery::on(static fn ($msg) => str_contains($msg, 'extension') || $msg !== ''))
          ->andReturn($constraintBuilderMock);

        $constraint                   = new PasswordPolicy();
        $constraint->detectExtensions = true;
        $constraint->extensionMessage = 'Extension message {{ days }}';

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')
          ->andReturn(Carbon::now()->subDays(2));

        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pass123', $this->entityMock])
          ->andReturn(null);
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPasswordExtension')
          ->withArgs(['pass123', $this->entityMock, 4])
          ->andReturn($historyMock);

        $this->validator->initialize($this->contextMock);
        $this->validator->validate('pass123', $constraint);
        $this->assertTrue(true);
    }

    public function testValidateFailWithExtensionUsesDefaultMessageWhenExtensionMessageEmpty(): void
    {
        $this->contextMock->shouldReceive('getObject')->once()->andReturn($this->entityMock);

        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $constraintBuilderMock->shouldReceive('setParameter')->once()->andReturnSelf();
        $constraintBuilderMock->shouldReceive('setCode')
          ->once()
          ->with(PasswordPolicy::PASSWORD_EXTENSION)
          ->andReturnSelf();
        $constraintBuilderMock->shouldReceive('addViolation')->once();

        $constraint                   = new PasswordPolicy();
        $constraint->detectExtensions = true;
        $constraint->extensionMessage = '';

        $this->contextMock->shouldReceive('buildViolation')
          ->once()
          ->with($constraint->message)
          ->andReturn($constraintBuilderMock);

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')->andReturn(Carbon::now()->subDays(1));

        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pass99', $this->entityMock])
          ->andReturn(null);
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPasswordExtension')
          ->withArgs(['pass99', $this->entityMock, 4])
          ->andReturn($historyMock);

        $this->validator->initialize($this->contextMock);
        $this->validator->validate('pass99', $constraint);
        $this->assertTrue(true);
    }

    public function testValidateWithEventDispatcherDispatchesReuseEvent(): void
    {
        $eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcherMock->shouldReceive('dispatch')
          ->once()
          ->with(Mockery::type(\Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent::class));

        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            null,
            true,
            'info',
            $eventDispatcherMock,
        );

        $this->contextMock->shouldReceive('getObject')->once()->andReturn($this->entityMock);
        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $constraintBuilderMock->shouldReceive('setParameter')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('setCode')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('addViolation')->once();
        $this->contextMock->shouldReceive('buildViolation')->once()->andReturn($constraintBuilderMock);

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $this->entityMock])
          ->andReturn($historyMock);

        $validator->initialize($this->contextMock);
        $validator->validate('pwd', new PasswordPolicy());
        $this->assertTrue(true);
    }

    public function testValidateUsesConfigServiceForExtensionSettings(): void
    {
        $configService = new PasswordPolicyConfigurationService();
        $configService->setEntityConfiguration($this->entityMock::class, [
            'detect_password_extensions' => true,
            'extension_min_length'       => 5,
        ]);

        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            null,
            true,
            'info',
            null,
            $configService,
        );

        $this->contextMock->shouldReceive('getObject')->once()->andReturn($this->entityMock);
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $this->entityMock])
          ->andReturn(null);
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPasswordExtension')
          ->withArgs(['pwd', $this->entityMock, 5])
          ->andReturn(null);

        $validator->initialize($this->contextMock);
        $validator->validate('pwd', new PasswordPolicy());
        $this->assertTrue(true);
    }

    public function testValidateFailWithLoggingCallsLogger(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')
          ->once()
          ->with('Password reuse attempt detected', Mockery::on(static function (array $context) {
              return isset($context['bundle'], $context['user_id'], $context['user_identifier'], $context['match_type'])
                  && $context['match_type'] === 'exact';
          }));

        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            $loggerMock,
            true,
            'info',
        );

        $this->entityMock->shouldReceive('getId')->andReturn(42);
        $this->entityMock->shouldReceive('getUserIdentifier')->andReturn('user@test.com');

        $this->contextMock->shouldReceive('getObject')->once()->andReturn($this->entityMock);
        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $constraintBuilderMock->shouldReceive('setParameter')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('setCode')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('addViolation')->once();
        $this->contextMock->shouldReceive('buildViolation')->once()->andReturn($constraintBuilderMock);

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $this->entityMock])
          ->andReturn($historyMock);

        $validator->initialize($this->contextMock);
        $validator->validate('pwd', new PasswordPolicy());
        $this->assertTrue(true);
    }

    public function testValidateFailWithLoggingUsesGetEmailWhenNoUserIdentifier(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')
          ->once()
          ->with('Password reuse attempt detected', Mockery::on(static function (array $context) {
              return isset($context['user_identifier']) && $context['user_identifier'] === 'email@example.com';
          }));

        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')->andReturn('en');

        $validator = new PasswordPolicyValidator(
            $this->passwordPolicyServiceMock,
            $translatorMock,
            $loggerMock,
            true,
            'info',
        );

        $entityWithEmailOnly = new class implements HasPasswordPolicyInterface {
            public function getId(): int
            {
                return 1;
            }

            public function getEmail(): string
            {
                return 'email@example.com';
            }

            public function getPassword(): string
            {
                return '';
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return new \Doctrine\Common\Collections\ArrayCollection();
            }

            public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
            {
                return $this;
            }
        };

        $this->contextMock->shouldReceive('getObject')->once()->andReturn($entityWithEmailOnly);
        $constraintBuilderMock = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $constraintBuilderMock->shouldReceive('setParameter')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('setCode')->andReturnSelf();
        $constraintBuilderMock->shouldReceive('addViolation')->once();
        $this->contextMock->shouldReceive('buildViolation')->once()->andReturn($constraintBuilderMock);

        $historyMock = Mockery::mock(PasswordHistoryInterface::class);
        $historyMock->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $this->passwordPolicyServiceMock->shouldReceive('getHistoryByPassword')
          ->withArgs(['pwd', $entityWithEmailOnly])
          ->andReturn($historyMock);

        $validator->initialize($this->contextMock);
        $validator->validate('pwd', new PasswordPolicy());
        $this->assertTrue(true);
    }
}
