<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;


use DateTime;
use Mockery\Mock;
use Mockery;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;

final class PasswordEntityListenerTest extends UnitTestCase
{

    /**
     * @var PasswordHistoryServiceInterface|Mock
     */
    private $passwordHistoryServiceMock;

    /**
     * @var PasswordEntityListener|Mock
     */
    private $passwordEntityListener;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface|Mock
     */
    private $emMock;

    /**
     * @var HasPasswordPolicyInterface|Mock
     */
    private $entityMock;

    /**
     * @var \Doctrine\ORM\UnitOfWork|Mock
     */
    private $uowMock;

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

        $this->assertTrue(true);
    }

    public function testCreatePasswordHistory(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata('foo');

        $classMetadata->associationMappings['passwordHistory']['targetEntity'] = PasswordHistoryMock::class;
        $classMetadata->associationMappings['passwordHistory']['mappedBy'] = 'user';

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
        $this->assertInstanceof(PasswordHistoryInterface::class, $history);

        $this->assertSame('old_pwd', $history->getPassword());
        $this->assertInstanceOf(DateTime::class, $history->getCreatedAt());
        $this->assertEquals($this->entityMock, $history->getUser());
        // Salt is not set in createPasswordHistory (line is commented out)
        $this->assertNull($history->getSalt());
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

        $classMetadata = new ClassMetadata('foo');

        $classMetadata->associationMappings['passwordHistory']['targetEntity'] = PasswordHistoryMock::class;
        $classMetadata->associationMappings['passwordHistory']['mappedBy'] = 'user';

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
        $this->assertInstanceof(PasswordHistoryInterface::class, $history);

        $this->assertSame('pwd', $history->getPassword());
        $this->assertInstanceOf(DateTime::class, $history->getCreatedAt());
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

        $classMetadata = new ClassMetadata('foo');

        // Use stdClass instead of self::class to avoid ArgumentCountError
        $classMetadata->associationMappings['passwordHistory']['targetEntity'] = \stdClass::class;
        $classMetadata->associationMappings['passwordHistory']['mappedBy'] = 'user';

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\stdClass::class.' must implement '.PasswordHistoryInterface::class);

        $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
    }

    public function testCreatePasswordHistoryBadSetter(): void
    {
        $this->emMock->shouldReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata('foo');

        $classMetadata->associationMappings['passwordHistory']['targetEntity'] = PasswordHistoryMock::class;
        $classMetadata->associationMappings['passwordHistory']['mappedBy'] = 'foo';

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf("Cannot set user relation in password history class %s because method %s is missing",
                PasswordHistoryMock::class, 'setFoo'
            ));

        $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
    }

    public function testLoggingWithDifferentLevels(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        
        // Test debug level
        $loggerMock->shouldReceive('debug')
                   ->once()
                   ->with('Test debug message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $listener = new PasswordEntityListener(
            $this->passwordHistoryServiceMock,
            'password',
            'passwordHistory',
            3,
            $this->entityMock::class,
            $loggerMock,
            true,
            'debug'
        );
        
        // Use reflection to call private log method
        $reflection = new \ReflectionClass($listener);
        $logMethod = $reflection->getMethod('log');
        $logMethod->invoke($listener, 'debug', 'Test debug message');
        
        // Test info level
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test info message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'info', 'Test info message');
        
        // Test notice level
        $loggerMock->shouldReceive('notice')
                   ->once()
                   ->with('Test notice message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'notice', 'Test notice message');
        
        // Test warning level
        $loggerMock->shouldReceive('warning')
                   ->once()
                   ->with('Test warning message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'warning', 'Test warning message');
        
        // Test error level
        $loggerMock->shouldReceive('error')
                   ->once()
                   ->with('Test error message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'error', 'Test error message');
        
        // Test default level (unknown level should default to info)
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test unknown level message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'unknown', 'Test unknown level message');
        
        $this->assertTrue(true);
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
            'info'
        );
        
        // Use reflection to call private log method with null logger
        $reflection = new \ReflectionClass($listener);
        $logMethod = $reflection->getMethod('log');
        
        // Should not throw exception when logger is null
        $logMethod->invoke($listener, 'info', 'Test message');
        
        $this->assertTrue(true);
    }
}
