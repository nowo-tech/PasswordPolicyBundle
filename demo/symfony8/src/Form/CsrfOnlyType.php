<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`): CSRF-only POST (demo user delete) with a submit button. Empty block
 * prefix keeps {@code _token} flat.
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('demo')]
final class CsrfOnlyType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => $options['submit_label'],
            'translation_domain' => false,
            'attr'               => $options['submit_attr'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id'   => 'demo',
            'csrf_field_name' => '_token',
            'submit_label'    => 'Submit',
            'submit_attr'     => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('submit_attr', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
