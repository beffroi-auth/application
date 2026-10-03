<?php

declare(strict_types=1);

namespace App\Form;

use function array_combine;
use Beffroi\Core\Domain\Identity\Claim\ClaimField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

/**
 * The form type a claim of the provider is edited with (issue beffroi-php/op#17): the one place the kinds
 * a claim may be are mapped onto the types of symfony/form.
 *
 * The bundle says what a claim is and never what a form is: its own pages are bare HTML with a form token,
 * which is what lets this application dress them by one layout override (architecture §5.2). So the
 * mapping lives here, in the deployment that chose the component, and a kind the bundle adds is a line
 * here and nothing else.
 *
 * A closed set is a choice of its values; anything else is typed into the type its kind names, with what
 * the browser may check before anything is sent carried on the attributes.
 */
final readonly class ClaimFieldTypes
{
    /**
     * @return class-string
     */
    public static function of(ClaimField $field): string
    {
        if ($field->choices !== []) {
            return ChoiceType::class;
        }

        return match ($field->kind) {
            'tel' => TelType::class,
            'url' => UrlType::class,
            default => TextType::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionsOf(ClaimField $field, string $label): array
    {
        $options = [
            'label' => $label,
            'required' => false,
            'translation_domain' => 'beffroi',
        ];
        if ($field->choices !== []) {
            return $options + [
                'choices' => array_combine($field->choices, $field->choices),
                'placeholder' => 'profile.nothing',
                'choice_translation_domain' => false,
            ];
        }
        $attributes = [
            'autocomplete' => 'off',
        ];
        if ($field->pattern !== null) {
            $attributes['pattern'] = $field->pattern;
        }

        return $options + [
            'attr' => $attributes,
            // the example is a key of the catalogue of the bundle, as every word of these pages is
            'help' => $field->example,
            'help_translation_parameters' => [],
        ];
    }
}
