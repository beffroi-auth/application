<?php

declare(strict_types=1);

namespace App\Form;

use function array_keys;
use Beffroi\Core\Domain\Identity\Claim\ClaimField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormTypeInterface;
use function sprintf;

final readonly class ClaimFieldTypes
{
    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    public static function of(ClaimField $field): string
    {
        if ($field->isPicked()) {
            return TextType::class;
        }
        if ($field->choices !== []) {
            return ChoiceType::class;
        }

        return match ($field->kind) {
            'tel' => TelType::class,
            'url' => UrlType::class,
            'date' => DateType::class,
            default => TextType::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionsOf(ClaimField $field, string $claim): array
    {
        $options = [
            'label' => 'profile.' . $claim,
            'required' => false,
            'translation_domain' => 'beffroi',
        ];
        if ($field->isPicked()) {
            return $options
            + [
                'attr' => [
                    'autocomplete' => 'off',
                    'list' => self::suggestions($claim),
                ],
            ];
        }
        if ($field->choices !== []) {
            return $options
            + [
                // Not the labels as keys: two values that read the same would otherwise be one choice.
                'choices' => array_keys($field->choices),
                'choice_label' => static fn (string $value): string => $field->choices[$value] ?? $value,
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
        if ($field->kind === 'date') {
            return $options
            + [
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'Y-m-d',
                'html5' => true,
                'attr' => $attributes,
            ];
        }

        return $options
        + [
            'attr' => $attributes,
            'help' => $field->example,
            'help_translation_parameters' => [],
        ];
    }

    /**
     * The identifier the field and its `<datalist>` are joined by.
     */
    public static function suggestions(string $claim): string
    {
        return sprintf('claim-%s-values', $claim);
    }
}
