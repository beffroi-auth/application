<?php

declare(strict_types=1);

namespace App\Form;

use Beffroi\Core\Domain\Identity\Claim\ClaimInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One claim of a person, edited on its own (issue beffroi-php/op#17): one field, one button, one line of
 * the page. A form per claim and not one form for all of them, because what this page does is let somebody
 * correct one thing where they read it, and a single form would make them save thirteen others to do it.
 *
 * What kind of field it is, the claim says (beffroi-php/beffroi#476); what type that is, ClaimFieldTypes
 * maps. Nothing here knows the claims by name.
 */
final class ClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $claim = $options['claim'];
        $builder->add('value', ClaimFieldTypes::of($claim->field()), ClaimFieldTypes::optionsOf($claim->field(), 'profile.' . $claim->name()));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('claim');
        $resolver->setAllowedTypes('claim', ClaimInterface::class);
        // nothing else is set: the token id is the one of config/packages/csrf.yaml, which is the stateless
        // protection the form component is installed with here
    }
}
