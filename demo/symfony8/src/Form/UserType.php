<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF: create / edit a demo user.
 *
 * @extends AbstractType<User>
 */
#[FormKitConfig('demo')]
final class UserType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addEmail($builder, 'email', [
            'label'              => 'Email',
            'required'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addPassword($builder, 'plainPassword', [
            'label'              => 'Password',
            'mapped'             => false,
            'required'           => !$options['is_edit'],
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => $options['is_edit'] ? 'Leave blank to keep current password' : false,
            'constraints'        => [
                new NotBlank(message: 'Please enter a password'),
                new Length(
                    min: 6,
                    max: 4096,
                    minMessage: 'Your password should be at least {{ limit }} characters',
                ),
            ],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => $options['submit_label'],
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => User::class,
            'is_edit'      => false,
            'submit_label' => 'Create User',
        ]);
        $resolver->setAllowedTypes('submit_label', 'string');
    }
}
