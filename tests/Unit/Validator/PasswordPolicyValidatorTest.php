<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Validator;

use Carbon\Carbon;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class PasswordPolicyValidatorTest extends UnitTestCase
{
    /**
     * @var HasPasswordPolicyInterface|Mock
     */
    private $entityMock;

    /**
     * @var ExecutionContextInterface|Mock
     */
    private $contextMock;

    /**
     * @var PasswordPolicyValidator|Mock
     */
    private $validator;

    /**
     * @var PasswordPolicyServiceInterface|Mock
     */
    private $passwordPolicyServiceMock;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        $translatorMock = Mockery::mock(TranslatorInterface::class);
        $translatorMock->shouldReceive('getLocale')
          ->andReturn('en');

        $this->passwordPolicyServiceMock = Mockery::mock(PasswordPolicyServiceInterface::class);
        $this->validator = Mockery::mock(PasswordPolicyValidator::class, [
          $this->passwordPolicyServiceMock,
          $translatorMock,
        ])->makePartial();
        $this->contextMock = Mockery::mock(ExecutionContextInterface::class);
        $this->entityMock = Mockery::mock(HasPasswordPolicyInterface::class);
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
        $this->assertTrue($this->validator->validate('pwd', $passwordPolicy));
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
        $this->assertFalse($this->validator->validate('pwd', $passwordPolicy));
    }

    public function testValidateNullValue(): void
    {
        $this->assertTrue($this->validator->validate(null, new PasswordPolicy()));
    }

    public function testValidateBadEntity(): void
    {
        $this->contextMock->shouldReceive('getObject')
          ->once()
          ->andReturn(new self());

        $passwordPolicy = new PasswordPolicy();

        $this->validator->initialize($this->contextMock);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected validation entity to implements ' . HasPasswordPolicyInterface::class);
        $this->assertTrue($this->validator->validate('pwd', $passwordPolicy));
    }
}
