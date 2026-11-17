<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use Beffroi\Core\Domain\PersonalData\ClassifiedFieldsInterface;
use Beffroi\Core\Domain\PersonalData\FieldClassificationInterface;
use Beffroi\Core\Domain\PersonalData\NoPersonalDatum;
use Beffroi\Core\Domain\PersonalData\Reason;

/**
 * Classifies the columns of the data key table, the one table no class of the model holds a field of.
 */
final readonly class DataKeyColumns implements ClassifiedFieldsInterface
{
    /**
     * @return array<string, FieldClassificationInterface>
     */
    public static function classifiedFields(): array
    {
        return [
            'id' => new NoPersonalDatum('the reference a sealed value carries, drawn as a UUIDv7 so that the newest key of a scope is the last row'),
            'scope' => new NoPersonalDatum('the slug of a tenant and the family of columns its key seals, which names an organisation and a set of columns'),
            'key_material' => new NoPersonalDatum(Reason::AN_ENVELOPE),
            'master_key_id' => new NoPersonalDatum('the key of the KMS that wrapped this row, written here so that a read resolves whatever wrote it'),
            'client' => new NoPersonalDatum('the name the configuration gives the KMS that wrapped this row, for the same reason'),
            'binding' =>
                new NoPersonalDatum('a 32-byte proof, over the reference and the scope this row states and the key it holds, that the key was minted for them: a row copied under another reference or another scope is refused once its key is open, which is the parade of the bridge to a sealed value crossing a tenant'),
        ];
    }
}
