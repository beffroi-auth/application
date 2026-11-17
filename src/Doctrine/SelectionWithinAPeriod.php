<?php

declare(strict_types=1);

namespace App\Doctrine;

use Beffroi\Core\Domain\Listing\Period;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

final readonly class SelectionWithinAPeriod
{
    /**
     * @param string $column the column that dates a row, with the alias of the selection ("a.occurredAt")
     */
    public static function narrow(QueryBuilder $selection, string $column, ?Period $period): QueryBuilder
    {
        if ($period?->from !== null) {
            $selection->andWhere($column . ' >= :period_from')->setParameter(
                'period_from',
                $period->from->setTimezone(new DateTimeZone('UTC')),
                Types::DATETIME_IMMUTABLE,
            );
        }
        if ($period?->to !== null) {
            $selection->andWhere($column . ' <= :period_to')->setParameter(
                'period_to',
                $period->to->setTimezone(new DateTimeZone('UTC')),
                Types::DATETIME_IMMUTABLE,
            );
        }

        return $selection;
    }
}
