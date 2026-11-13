<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF: change a demo user's password. FormKit has no `addRepeated()`
 * helper, so the repeated field goes through `addWithDefaults()`.
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('demo')]
final class ChangePasswordType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'plainPassword', RepeatedType::class, [
            'type'          => PasswordType::class,
            'mapped'        => false,
            'first_options' => [
                'label'              => 'New Password',
                'translation_domain' => false,
                'row_attr'           => ['class' => 'nowo-ui-form-row'],
                'constraints'        => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                    ),
                ],
            ],
            'second_options' => [
                'label'              => 'Repeat Password',
                'translation_domain' => false,
                'row_attr'           => ['class' => 'nowo-ui-form-row'],
            ],
            'invalid_message'    => 'The password fields must match.',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => 'Change Password',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }
}
