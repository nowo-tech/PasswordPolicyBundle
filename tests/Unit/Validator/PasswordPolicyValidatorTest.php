<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Validator;


use Carbon\Carbon;
use Mockery\Mock;
use Mockery;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator;
use Symfony\Contracts\Translation\TranslatorInterface;
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
    $badEntity = new \stdClass();
    
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
      'debug'
    );
    
    // Use reflection to call private log method
    $reflection = new \ReflectionClass($validator);
    $logMethod = $reflection->getMethod('log');
    
    // Test debug level
    $loggerMock->shouldReceive('debug')
               ->once()
               ->with('Test debug message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
    $logMethod->invoke($validator, 'debug', 'Test debug message');
    
    // Test info level
    $loggerMock->shouldReceive('info')
               ->once()
               ->with('Test info message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
    $logMethod->invoke($validator, 'info', 'Test info message');
    
    // Test notice level
    $loggerMock->shouldReceive('notice')
               ->once()
               ->with('Test notice message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
    $logMethod->invoke($validator, 'notice', 'Test notice message');
    
    // Test warning level
    $loggerMock->shouldReceive('warning')
               ->once()
               ->with('Test warning message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
    $logMethod->invoke($validator, 'warning', 'Test warning message');
    
    // Test error level
    $loggerMock->shouldReceive('error')
               ->once()
               ->with('Test error message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
    $logMethod->invoke($validator, 'error', 'Test error message');
    
    // Test default level (unknown level should default to info)
    $loggerMock->shouldReceive('info')
               ->once()
               ->with('Test unknown level message', Mockery::on(function ($context) {
                   return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
               }));
    
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
      'info'
    );
    
    // Use reflection to call private log method with null logger
    $reflection = new \ReflectionClass($validator);
    $logMethod = $reflection->getMethod('log');
    
    // Should not throw exception when logger is null
    $logMethod->invoke($validator, 'info', 'Test message');
    
    $this->assertTrue(true);
  }
}
