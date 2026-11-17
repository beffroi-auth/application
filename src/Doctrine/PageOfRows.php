<?php

declare(strict_types=1);

namespace App\Doctrine;

use Beffroi\Core\Domain\Listing\Listing;
use Beffroi\Core\Domain\Listing\Pagination;
use Beffroi\Core\Exception\RuntimeException;
use Doctrine\ORM\QueryBuilder;
use SortDirection;
use function sprintf;

/**
 * The order is applied to the page alone: PostgreSQL refuses a count ordered by a column it does not count.
 */
final readonly class PageOfRows
{
    /**
     * @template T of object
     *
     * @param class-string<T> $rows
     * @param QueryBuilder    $selection with no order
     * @param string          $orderBy   with its alias
     *
     * @return Listing<T>
     *
     * @throws RuntimeException when the selection reads no alias
     */
    public function of(
        string $rows,
        QueryBuilder $selection,
        string $orderBy,
        Pagination $pagination,
        SortDirection $direction = SortDirection::Descending,
    ): Listing {
        $alias = $selection->getRootAliases()[0] ?? throw new RuntimeException(sprintf(
            'A selection of %s to be read in pages names no alias.',
            $rows,
        ));
        $total = (int) (clone $selection)->select(sprintf('COUNT(%s)', $alias))->getQuery()->getSingleScalarResult();
        $page = $pagination->within($total);
        /** @var list<T> $rows */
        $rows = $selection->orderBy($orderBy, $direction)->setFirstResult($page->offset())->setMaxResults($page->size)->getQuery()->getResult();

        return new Listing($rows, $page, $total);
    }
}
