<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Validator;

use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseDetectedEvent;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordHashVerifier;
use Nowo\PasswordPolicyBundle\Subject\DefaultPasswordPolicySubjectResolver;
use Nowo\PasswordPolicyBundle\Subject\PasswordReuseChecker;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\ChangePasswordCommand;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryPasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use const PASSWORD_BCRYPT;

/**
 * #[PasswordPolicy] on a command/DTO: the subject comes from the resolver, the history from the port.
 */
final class PasswordPolicyValidatorSubjectTest extends UnitTestCase
{
    private const SUBJECT_CLASS = 'App\Domain\User';

    private ExecutionContextInterface&MockInterface $context;

    private PasswordPolicyServiceInterface&MockInterface $policyService;

    private TranslatorInterface&MockInterface $translator;

    private InMemoryPasswordHistoryRepository $repository;

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->context       = Mockery::mock(ExecutionContextInterface::class);
        $this->policyService = Mockery::mock(PasswordPolicyServiceInterface::class);
        $this->translator    = Mockery::mock(TranslatorInterface::class);
        $this->translator->shouldReceive('getLocale')->andReturn('en');
        $this->repository = new InMemoryPasswordHistoryRepository();
        $this->repository->add(self::SUBJECT_CLASS, 'u-1', password_hash('Old-password1', PASSWORD_BCRYPT, ['cost' => 4]), new DateTimeImmutable('-10 days'));
    }

    private function validator(?LoggerInterface $logger = null): PasswordPolicyValidator
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(function (object $event): object {
            $this->events[] = $event;

            return $event;
        });

        $validator = new PasswordPolicyValidator(
            $this->policyService,
            $this->translator,
            $logger,
            $logger instanceof LoggerInterface,
            'info',
            $dispatcher,
            null,
            null,
            new DefaultPasswordPolicySubjectResolver(),
            new PasswordReuseChecker($this->repository, new DefaultPasswordHashVerifier()),
        );
        $validator->initialize($this->context);

        return $validator;
    }

    private function expectViolation(string $code): void
    {
        $builder = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $builder->shouldReceive('setParameter')->once()->with('{{ days }}', Mockery::type('string'))->andReturnSelf();
        $builder->shouldReceive('setCode')->once()->with($code)->andReturnSelf();
        $builder->shouldReceive('addViolation')->once();
        $this->context->shouldReceive('buildViolation')->once()->andReturn($builder);
    }

    public function testReusedPasswordOfAReadOnlySubjectIsRejected(): void
    {
        $subject = new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1');
        $this->context->shouldReceive('getObject')->andReturn(new ChangePasswordCommand('Old-password1', $subject));
        $this->expectViolation(PasswordPolicy::PASSWORD_IN_HISTORY);
        $this->policyService->shouldNotReceive('getHistoryByPassword');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with('Password reuse attempt detected', Mockery::on(
            static fn (array $context): bool => $context['user_id'] === 'u-1' && $context['match_type'] === 'exact' && $context['password_used_days_ago'] >= 9,
        ));

        $this->validator($logger)->validate('Old-password1', new PasswordPolicy());

        self::assertCount(1, $this->events);
        self::assertInstanceOf(PasswordReuseDetectedEvent::class, $this->events[0]);
        self::assertSame('u-1', $this->events[0]->getSubjectId());
        self::assertSame('exact', $this->events[0]->getMatchType());
    }

    public function testExtensionUsesTheExtensionMessage(): void
    {
        $subject = new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1');
        $this->context->shouldReceive('getObject')->andReturn($subject);

        $builder = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $builder->shouldReceive('setParameter')->andReturnSelf();
        $builder->shouldReceive('setCode')->once()->with(PasswordPolicy::PASSWORD_EXTENSION)->andReturnSelf();
        $builder->shouldReceive('addViolation')->once();
        $this->context->shouldReceive('buildViolation')->once()->with('ext')->andReturn($builder);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with('Password extension detected (new password is an extension of an old password)', Mockery::type('array'));

        $this->validator($logger)->validate('Old-password1!', new PasswordPolicy(extensionMessage: 'ext', detectExtensions: true));
        self::assertSame('extension', $this->events[0] instanceof PasswordReuseDetectedEvent ? $this->events[0]->getMatchType() : null);
    }

    public function testCurrentPasswordWithoutDateHasEmptyDays(): void
    {
        $subject = new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1', password_hash('Current-1', PASSWORD_BCRYPT, ['cost' => 4]));
        $this->context->shouldReceive('getObject')->andReturn(new ChangePasswordCommand('Current-1', $subject));

        $builder = Mockery::mock(ConstraintViolationBuilderInterface::class);
        $builder->shouldReceive('setParameter')->once()->with('{{ days }}', '')->andReturnSelf();
        $builder->shouldReceive('setCode')->once()->with(PasswordPolicy::PASSWORD_IN_HISTORY)->andReturnSelf();
        $builder->shouldReceive('addViolation')->once();
        $this->context->shouldReceive('buildViolation')->once()->andReturn($builder);

        $this->validator()->validate('Current-1', new PasswordPolicy());
        self::assertSame('current', $this->events[0] instanceof PasswordReuseDetectedEvent ? $this->events[0]->getMatchType() : null);
    }

    public function testNewPasswordPasses(): void
    {
        $this->context->shouldReceive('getObject')->andReturn(new ChangePasswordCommand('Brand-new-1', new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1')));
        $this->context->shouldNotReceive('buildViolation');

        $this->validator()->validate('Brand-new-1', new PasswordPolicy());
        self::assertSame([], $this->events);
    }

    public function testCommandCarryingTheEntityUsesTheEntityPath(): void
    {
        $user = new KernelUser();
        $this->context->shouldReceive('getObject')->andReturn(new ChangePasswordCommand('pwd', $user));
        $this->context->shouldNotReceive('buildViolation');
        $this->policyService->shouldReceive('getHistoryByPassword')->once()->with('pwd', $user)->andReturnNull();

        $this->validator()->validate('pwd', new PasswordPolicy(checkCurrentPassword: false));
        $this->addToAssertionCount(1);
    }

    public function testUnresolvedObjectStillThrows(): void
    {
        $this->context->shouldReceive('getObject')->andReturn(new stdClass());

        $this->expectException(ValidationException::class);
        $this->validator()->validate('pwd', new PasswordPolicy());
    }

    public function testReadOnlySubjectWithoutCheckerThrows(): void
    {
        $this->context->shouldReceive('getObject')->andReturn(new PasswordPolicySubject(self::SUBJECT_CLASS, 'u-1'));
        $validator = new PasswordPolicyValidator($this->policyService, $this->translator, subjectResolver: new DefaultPasswordPolicySubjectResolver());
        $validator->initialize($this->context);

        $this->expectException(ValidationException::class);
        $validator->validate('pwd', new PasswordPolicy());
    }
}
