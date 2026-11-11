<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\PersonalData;

use Mockery;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectAwareInterface;
use Nowo\PasswordPolicyBundle\PersonalData\DefaultPasswordPolicyPersonalDataSubjectResolver;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyErasureResult;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporterInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubjectResolverInterface;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitEraser;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitExportProvider;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordPolicySubjectResolver;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;
use stdClass;

use function interface_exists;

final class UserKitBridgeTest extends UnitTestCase
{
    public function testDefaultResolverUsesTheBundleSubjectResolver(): void
    {
        $resolver = new DefaultPasswordPolicyPersonalDataSubjectResolver(new DefaultPasswordPolicySubjectResolver());

        self::assertEquals([PasswordPolicyPersonalDataSubject::fromSubjectId(KernelUser::class, '7')], $resolver->subjectsFor(new KernelUser(), 'kernel-user'));
        self::assertEquals([PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'u-1')], $resolver->subjectsFor(new PasswordPolicySubject('App\User', 'u-1')));
        self::assertSame([], $resolver->subjectsFor(new stdClass()));
        // Not persisted yet: no id, nothing stored.
        self::assertSame([], $resolver->subjectsFor(new PasswordPolicySubject('App\User', '')));

        $aware = Mockery::mock(PasswordPolicySubjectAwareInterface::class);
        $aware->shouldReceive('getPasswordPolicySubject')->andReturn(new PasswordPolicySubject('App\User', 'u-2'));
        self::assertEquals([PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'u-2')], $resolver->subjectsFor($aware));
    }

    public function testEraserRunsThePortOncePerDistinctSubject(): void
    {
        if (!interface_exists(PersonalDataEraserInterface::class)) {
            self::markTestSkipped('nowo-tech/user-kit-bundle is not installed.');
        }

        $user = new KernelUser();
        $a    = PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'a');
        $b    = PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'b');

        $subjects = Mockery::mock(PasswordPolicyPersonalDataSubjectResolverInterface::class);
        $subjects->shouldReceive('subjectsFor')->with($user, null)->andReturn([$a]);
        $subjects->shouldReceive('subjectsFor')->with($user, 'original@example.test')->andReturn([$a, $b, PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'a')]);
        $subjects->shouldReceive('subjectsFor')->with(Mockery::type(stdClass::class), null)->andReturn([]);

        $port = Mockery::mock(PasswordPolicyPersonalDataEraserInterface::class);
        $port->shouldReceive('erase')->with($a)->once()->andReturn(new PasswordPolicyErasureResult(1));
        $port->shouldReceive('erase')->with($b)->once()->andReturn(new PasswordPolicyErasureResult());

        $eraser = new PasswordHistoryUserKitEraser($port, $subjects);

        self::assertSame('password_history', $eraser->getKey());
        self::assertTrue($eraser->supports($user));
        self::assertFalse($eraser->supports(new stdClass()));
        $eraser->erase(new ErasureRequest($user, null, ['previous_email' => 'old@example.test'], 'original@example.test'));
    }

    public function testExportProviderMergesSubjectsNewestFirst(): void
    {
        if (!interface_exists(PersonalDataEraserInterface::class)) {
            self::markTestSkipped('nowo-tech/user-kit-bundle is not installed.');
        }

        $user     = new KernelUser();
        $a        = PasswordPolicyPersonalDataSubject::fromSubjectId('App\User', 'a');
        $b        = PasswordPolicyPersonalDataSubject::fromSubjectId('App\Admin', 'a');
        $subjects = Mockery::mock(PasswordPolicyPersonalDataSubjectResolverInterface::class);
        $subjects->shouldReceive('subjectsFor')->with($user)->andReturn([$a, $b]);
        $subjects->shouldReceive('subjectsFor')->with(Mockery::type(stdClass::class))->andReturn([]);
        $exporter = Mockery::mock(PasswordPolicyPersonalDataExporterInterface::class);
        $exporter->shouldReceive('export')->with($a)->andReturn(['count' => 2, 'entries' => [['changed_at' => '2026-03-01T00:00:00+00:00'], ['changed_at' => null]]]);
        $exporter->shouldReceive('export')->with($b)->andReturn(['count' => 1, 'entries' => [['changed_at' => '2026-04-01T02:00:00+02:00']]]);

        $provider = new PasswordHistoryUserKitExportProvider($exporter, $subjects);

        self::assertSame('password_history', $provider->getSectionKey());
        self::assertTrue($provider->supports($user));
        self::assertFalse($provider->supports(new stdClass()));
        self::assertSame([
            'count'   => 3,
            'entries' => [
                ['changed_at' => '2026-04-01T02:00:00+02:00'],
                ['changed_at' => '2026-03-01T00:00:00+00:00'],
                ['changed_at' => null],
            ],
        ], $provider->export($user));
    }
}
