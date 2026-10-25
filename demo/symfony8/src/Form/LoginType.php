<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo form_login credentials (REQ-TWIG-005 rule 8): flat _username / _password fields and the _csrf_token field
 * with the "authenticate" token id, as the form_login authenticator (enable_csrf) expects.
 *
 * @extends AbstractType<array{_username?: string, _password?: string}>
 */
final class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class, [
                'label'    => 'Email',
                'row_attr' => ['class' => 'form-group'],
                'attr'     => ['class' => 'form-control', 'autocomplete' => 'email', 'autofocus' => true],
            ])
            ->add('_password', PasswordType::class, [
                'label'    => 'Password',
                'row_attr' => ['class' => 'form-group'],
                'attr'     => ['class' => 'form-control', 'autocomplete' => 'current-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'authenticate',
        ]);
    }

    /**
     * Empty prefix so field names match the form_login authenticator.
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
