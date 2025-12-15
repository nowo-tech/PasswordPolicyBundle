<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;

use Carbon\Carbon;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordPolicyValidator extends ConstraintValidator
{
    public function __construct(private readonly PasswordPolicyServiceInterface $passwordPolicyService, private TranslatorInterface $translator)
    {
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @throws ValidationException
     *
     * @return bool
     */
    public function validate($value, Constraint $constraint)
    {
        if (is_null($value)) {
            return true;
        }

        $entity = $this->context->getObject();

        if (!$entity instanceof HasPasswordPolicyInterface) {
            throw new ValidationException(message: sprintf(
                'Expected validation entity to implements %s',
                HasPasswordPolicyInterface::class
            ));
        }

        Carbon::setLocale($this->translator->getLocale());

        $history = $this->passwordPolicyService->getHistoryByPassword($value, $entity);
        if ($history instanceof PasswordHistoryInterface) {
            $this->context->buildViolation($constraint->message)
                          ->setParameter('{{ days }}', Carbon::instance($history->getCreatedAt())->diffForHumans())
                          ->setCode(PasswordPolicy::PASSWORD_IN_HISTORY)
                          ->addViolation();

            return false;
        }

        return true;
    }
}
