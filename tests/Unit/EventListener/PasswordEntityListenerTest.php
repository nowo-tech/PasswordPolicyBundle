<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use ReflectionClass;
use stdClass;

use function sprintf;

final class PasswordEntityListenerTest extends UnitTestCase
{
    private \Mockery\MockInterface&PasswordHistoryServiceInterface $passwordHistoryServiceMock;

    private \Mockery\MockInterface&PasswordEntityListener $passwordEntityListener;

    private \Doctrine\ORM\EntityManagerInterface&MockInterface $emMock;

    private \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface&MockInterface $entityMock;

    private \Mockery\MockInterface&UnitOfWork $uowMock;

    /**
     * @param ClassMetadata<stdClass> $metadata
     */
    private function setAssociationMapping(ClassMetadata $metadata, string $field, string $targetEntity, string $mappedBy): void
    {
        $ref  = new ReflectionClass($metadata);
        $prop = $ref->getProperty('associationMappings');
        /** @var array<string, mixed> $current */
        $current         = $prop->getValue($metadata);
        $current[$field] = ['targetEntity' => $targetEntity, 'mappedBy' => $mappedBy];
        $prop->setValue($metadata, $current);
    }

    protected function setUp(): void
    {
        $this->passwordHistoryServiceMock = Mockery::mock(PasswordHistoryServiceInterface::class);

        $this->emMock = Mockery::mock(EntityManagerInterface::class);

        $this->entityMock = Mockery::mock(HasPasswordPolicyInterface::class);

        $this->uowMock = Mockery::mock(UnitOfWork::class);

        $this->passwordEntityListener = Mockery::mock(PasswordEntityListener::class, [
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            '3',
            $this->entityMock::class,
        ])->makePartial();
    }

    /**
     * @throws RuntimeException
     */
    public function testOnFlushUpdates(): void
    {
        // Mock getIdentityMap to return entities grouped by class
        $this->uowMock->shouldReceive('getIdentityMap')
                      ->once()
                      ->andReturn([
                          $this->entityMock::class => [
                              $this->entityMock,
                          ],
                      ]);
        $this->uowMock->shouldReceive('getEntityChangeSet')
                      ->once()
                      ->with($this->entityMock)
                      ->andReturn([
                          'password' => [
                              'pwd_1',
                              'pwd_2',
                          ],
                      ]);

        $this->passwordEntityListener->shouldReceive('createPasswordHistory')
                                     ->once()
                                     ->withArgs([$this->emMock, $this->entityMock, 'pwd_1']);

        $this->emMock->shouldReceive('getUnitOfWork')
                     ->andReturn($this->uowMock);

        $onFlushEventArgs = new OnFlushEventArgs($this->emMock);

        $this->passwordEntityListener->onFlush($onFlushEventArgs);

        $this->addToAssertionCount(1);
    }

    public function testCreatePasswordHistory(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata(stdClass::class);
        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'user');

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->entityMock->shouldReceive('addPasswordHistory')
                         ->once();

        $this->entityMock->shouldReceive('getSalt')
                         ->andReturn('salt');

        $pwdHistoryMock = Mockery::mock(PasswordHistoryMock::class);
        $this->passwordHistoryServiceMock->shouldReceive('getHistoryItemsForCleanup')
                                         ->once()
                                         ->withArgs([$this->entityMock, 3])
                                         ->andReturn([$pwdHistoryMock]);

        $this->emMock->shouldReceive('remove')
                     ->once()
                     ->with($pwdHistoryMock);

        $this->emMock->shouldReceive('persist')
                     ->once();

        $classMetadataMock = Mockery::mock(ClassMetadata::class);

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->andReturn($classMetadataMock);

        $this->uowMock->shouldReceive('recomputeSingleEntityChangeSet')
                      ->once();

        $this->uowMock->shouldReceive('computeChangeSet')
                      ->once();

        $this->entityMock->shouldReceive('setPasswordChangedAt')
                         ->once();

        $history = $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
        $this->assertInstanceOf(PasswordHistoryInterface::class, $history);

        $this->assertSame('old_pwd', $history->getPassword());
        $this->assertInstanceOf(DateTime::class, $history->getCreatedAt());
        $this->assertInstanceOf(PasswordHistoryMock::class, $history);
        $this->assertEquals($this->entityMock, $history->getUser());
        // Salt is not set in createPasswordHistory (line is commented out)
        $this->assertNull($history->getSalt());
    }

    public function testCreatePasswordHistoryWithCacheInvalidationAndEventDispatcher(): void
    {
        $expiryServiceMock = Mockery::mock(\Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface::class);
        $expiryServiceMock->shouldReceive('invalidateCache')
                          ->once()
                          ->with($this->entityMock);

        $eventDispatcherMock = Mockery::mock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcherMock->shouldReceive('dispatch')
                            ->twice()
                            ->with(Mockery::type(\Symfony\Contracts\EventDispatcher\Event::class));

        $listener = new PasswordEntityListener(
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            3,
            $this->entityMock::class,
            null,
            true,
            'info',
            $eventDispatcherMock,
            $expiryServiceMock,
        );

        $this->emMock->shouldReceive('getUnitOfWork')->once()->andReturn($this->uowMock);
        $classMetadata = new ClassMetadata(stdClass::class);
        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'user');
        $this->emMock->shouldReceive('getClassMetadata')
                     ->twice()
                     ->andReturn($classMetadata, Mockery::mock(ClassMetadata::class));

        $this->entityMock->shouldReceive('addPasswordHistory')->once();
        $this->entityMock->shouldReceive('getSalt')->andReturn(null);
        $this->passwordHistoryServiceMock->shouldReceive('getHistoryItemsForCleanup')
                                         ->once()
                                         ->withArgs([$this->entityMock, 3])
                                         ->andReturn([]);
        $this->emMock->shouldReceive('persist')->once();
        $this->uowMock->shouldReceive('computeChangeSet')->once();
        $this->uowMock->shouldReceive('recomputeSingleEntityChangeSet')->once();
        $this->entityMock->shouldReceive('setPasswordChangedAt')->once();

        $history = $listener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
        $this->assertInstanceOf(PasswordHistoryInterface::class, $history);
    }

    public function testCreatePasswordHistoryNullPassword(): void
    {
        $this->uowMock->shouldReceive('recomputeSingleEntityChangeSet')
                      ->once();

        $this->uowMock->shouldReceive('computeChangeSet')
                      ->once();

        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata(stdClass::class);
        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'user');

        $classMetadataMock = Mockery::mock(ClassMetadata::class);

        $this->emMock->shouldReceive('getClassMetadata')
                     ->twice()
                     ->andReturnValues([$classMetadata, $classMetadataMock]);

        $this->entityMock->shouldReceive('addPasswordHistory')
                         ->once();

        $this->entityMock->shouldReceive('getSalt')
                         ->andReturn('salt');

        $pwdHistoryMock = Mockery::mock(PasswordHistoryMock::class);
        $this->passwordHistoryServiceMock->shouldReceive('getHistoryItemsForCleanup')
                                         ->once()
                                         ->withArgs([$this->entityMock, 3])
                                         ->andReturn([$pwdHistoryMock]);

        $this->emMock->shouldReceive('remove')
                     ->once()
                     ->with($pwdHistoryMock);

        $this->emMock->shouldReceive('persist')
                     ->once();

        $this->entityMock->shouldReceive('setPasswordChangedAt')
                         ->once();

        $this->entityMock->shouldReceive('getPassword')
                         ->twice()
                         ->andReturnValues(['pwd', '']);

        $history = $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, null);
        $this->assertInstanceOf(PasswordHistoryInterface::class, $history);

        $this->assertSame('pwd', $history->getPassword());
        $this->assertInstanceOf(DateTime::class, $history->getCreatedAt());
        $this->assertInstanceOf(PasswordHistoryMock::class, $history);
        $this->assertEquals($this->entityMock, $history->getUser());
        // Salt is not set in createPasswordHistory (line is commented out)
        $this->assertNull($history->getSalt());

        $this->assertNotInstanceOf(PasswordHistoryInterface::class, $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, null));
    }

    public function testCreatePasswordHistoryBadInstance(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata(stdClass::class);

        // Use stdClass instead of self::class to avoid ArgumentCountError
        $this->setAssociationMapping($classMetadata, 'passwordHistory', stdClass::class, 'user');

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(stdClass::class . ' must implement ' . PasswordHistoryInterface::class);

        $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
    }

    public function testCreatePasswordHistoryBadSetter(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata(stdClass::class);

        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'foo');

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Cannot set user relation in password history class %s because method %s is missing',
                PasswordHistoryMock::class,
                'setFoo',
            ),
        );

        $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
    }

    public function testCreatePasswordHistoryReturnsNullForEmptyOrZeroPassword(): void
    {
        $this->entityMock->shouldReceive('getPassword')
                         ->andReturn('');

        $this->assertNull($this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, ''));
        $this->assertNull($this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, '0'));
    }

    public function testLoggingWithDifferentLevels(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);

        // Test debug level
        $loggerMock->shouldReceive('debug')
                   ->once()
                   ->with('Test debug message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $listener = new PasswordEntityListener(
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            3,
            $this->entityMock::class,
            $loggerMock,
            true,
            'debug',
        );

        // Use reflection to call private log method
        $reflection = new ReflectionClass($listener);
        $logMethod  = $reflection->getMethod('log');
        $logMethod->invoke($listener, 'debug', 'Test debug message');

        // Test info level
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test info message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($listener, 'info', 'Test info message');

        // Test notice level
        $loggerMock->shouldReceive('notice')
                   ->once()
                   ->with('Test notice message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($listener, 'notice', 'Test notice message');

        // Test warning level
        $loggerMock->shouldReceive('warning')
                   ->once()
                   ->with('Test warning message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($listener, 'warning', 'Test warning message');

        // Test error level
        $loggerMock->shouldReceive('error')
                   ->once()
                   ->with('Test error message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($listener, 'error', 'Test error message');

        // Test default level (unknown level should default to info)
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test unknown level message', Mockery::on(static fn ($context): bool => isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle'));

        $logMethod->invoke($listener, 'unknown', 'Test unknown level message');

        $this->addToAssertionCount(1);
    }

    public function testLoggingWithNullLogger(): void
    {
        $listener = new PasswordEntityListener(
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            3,
            $this->entityMock::class,
            null,
            true,
            'info',
        );

        // Use reflection to call private log method with null logger
        $reflection = new ReflectionClass($listener);
        $logMethod  = $reflection->getMethod('log');

        // Should not throw exception when logger is null
        $logMethod->invoke($listener, 'info', 'Test message');

        $this->addToAssertionCount(1);
    }

    /**
     * Covers createPasswordHistory returning null when the same password was already processed (processedPasswords).
     */
    public function testCreatePasswordHistoryReturnsNullWhenPasswordAlreadyProcessed(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')->andReturn($this->uowMock);
        $classMetadata = new ClassMetadata(stdClass::class);
        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'user');
        $this->emMock->shouldReceive('getClassMetadata')
                     ->andReturn($classMetadata);
        $this->entityMock->shouldReceive('addPasswordHistory')->once();
        $this->passwordHistoryServiceMock->shouldReceive('getHistoryItemsForCleanup')
                                         ->andReturn([]);
        $this->emMock->shouldReceive('persist')->once();
        $this->uowMock->shouldReceive('computeChangeSet')->once();
        $this->uowMock->shouldReceive('recomputeSingleEntityChangeSet')->once();
        $this->entityMock->shouldReceive('setPasswordChangedAt')->once();

        $first = $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'same_pwd');
        $this->assertInstanceOf(PasswordHistoryInterface::class, $first);

        $second = $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'same_pwd');
        $this->assertNull($second);
    }

    /**
     * Covers the logging block inside createPasswordHistory (enableLogging && logger).
     */
    public function testCreatePasswordHistoryLogsWhenLoggerProvided(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Password changed successfully', Mockery::on(static fn(array $context): bool => isset($context['bundle'], $context['user_id'], $context['entity_class'], $context['history_entries_removed'])
                       && $context['bundle'] === 'PasswordPolicyBundle'));

        $listener = new PasswordEntityListener(
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            3,
            $this->entityMock::class,
            $loggerMock,
            true,
            'info',
        );

        $this->emMock->shouldReceive('getUnitOfWork')->andReturn($this->uowMock);
        $classMetadata = new ClassMetadata(stdClass::class);
        $this->setAssociationMapping($classMetadata, 'passwordHistory', PasswordHistoryMock::class, 'user');
        $this->emMock->shouldReceive('getClassMetadata')->andReturn($classMetadata);
        $this->entityMock->shouldReceive('addPasswordHistory')->once();
        $this->entityMock->shouldReceive('getId')->andReturn(1);
        $this->passwordHistoryServiceMock->shouldReceive('getHistoryItemsForCleanup')->andReturn([]);
        $this->emMock->shouldReceive('persist')->once();
        $this->uowMock->shouldReceive('computeChangeSet')->once();
        $this->uowMock->shouldReceive('recomputeSingleEntityChangeSet')->once();
        $this->entityMock->shouldReceive('setPasswordChangedAt')->once();

        $history = $listener->createPasswordHistory($this->emMock, $this->entityMock, 'new_pwd');
        $this->assertInstanceOf(PasswordHistoryInterface::class, $history);
    }
}
