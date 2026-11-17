<?php

declare(strict_types=1);

namespace App\Form;

use Beffroi\Core\Domain\Identity\Claim\ClaimField;
use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use LogicException;

/**
 * @extends AbstractType<mixed>
 */
final class ClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $claim = $options['claim'];
        $field = $options['field'];
        if (! $claim instanceof ClaimInterface || ! $field instanceof ClaimField) {
            throw new LogicException('A claim form is built for a claim and the field it is offered as.');
        }
        $builder->add('value', ClaimFieldTypes::of($field), ClaimFieldTypes::optionsOf($field, $claim->name()));
    }

    /**
     * Carries the field to the view, where the page renders its `<datalist>`: symfony/form has no type for one.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['field'] = $options['field'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['claim', 'field']);
        $resolver->setAllowedTypes('claim', ClaimInterface::class);
        $resolver->setAllowedTypes('field', ClaimField::class);

        // No CSRF option is set: the token id is the stateless one of config/packages/csrf.yaml.
    }
}
