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
        $this->uowMock->shouldReceive('getScheduledEntityUpdates')
                      ->once()
                      ->andReturn([
                          $this->entityMock,
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
        $this->assertEquals('salt', $history->getSalt());
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
                         ->andReturnValues(['pwd', null]);

        $history = $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, null);
        $this->assertInstanceof(PasswordHistoryInterface::class, $history);

        $this->assertSame('pwd', $history->getPassword());
        $this->assertInstanceOf(DateTime::class, $history->getCreatedAt());
        $this->assertEquals($this->entityMock, $history->getUser());
        $this->assertEquals('salt', $history->getSalt());

        $this->assertNotInstanceOf(PasswordHistoryInterface::class, $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, null));
    }

    public function testCreatePasswordHistoryBadInstance(): void
    {
        $this->uowMock->shouldNotReceive('computeChangeSets');

        $this->emMock->shouldNotReceive('getUnitOfWork')
                     ->once()
                     ->andReturn($this->uowMock);

        $classMetadata = new ClassMetadata('foo');

        $classMetadata->associationMappings['passwordHistory']['targetEntity'] = self::class;
        $classMetadata->associationMappings['passwordHistory']['mappedBy'] = 'user';

        $this->emMock->shouldReceive('getClassMetadata')
                     ->once()
                     ->withArgs([$this->entityMock::class])
                     ->andReturn($classMetadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(self::class.' must implement '.PasswordHistoryInterface::class);

        $this->passwordEntityListener->createPasswordHistory($this->emMock, $this->entityMock, 'old_pwd');
    }

    public function testCreatePasswordHistoryBadSetter(): void
    {
        $this->uowMock->shouldNotReceive('computeChangeSets');

        $this->emMock->shouldNotReceive('getUnitOfWork')
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
}
