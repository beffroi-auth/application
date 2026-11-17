<?php

declare(strict_types=1);

namespace App\Http;

use App\Doctrine\TenantProvider;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Domain\Ticket\Ticket;
use Beffroi\Core\Exception\TicketRefusedException;
use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The ticket the identifier and the value of a URL of tickets open, or nothing, never why (decision 0006).
 * A malformed identifier pays no hash, there being no ticket to tell it apart from.
 */
final readonly class PresentedTicket
{
    public const string TICKET_PARAMETER = 'ticket';

    public const string VALUE_PARAMETER = 'value';

    public function __construct(
        private TenantProvider $tenants,
    ) {
    }

    /**
     * @template T of Ticket
     *
     * @param Closure(Tenant, Uuid, string): T $redeem the redemption of the kind of ticket the route serves, which throws TicketRefusedException
     *
     * @return T|null
     */
    public function of(Request $request, Closure $redeem): ?Ticket
    {
        $id = $request->attributes->getString(self::TICKET_PARAMETER);
        if (! Uuid::isValid($id)) {
            return null;
        }
        try {
            return $redeem($this->tenants->provide(), Uuid::fromString($id), $request->attributes->getString(self::VALUE_PARAMETER));
        } catch (TicketRefusedException) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function parameters(Request $request): array
    {
        return [
            self::TICKET_PARAMETER => $request->attributes->getString(self::TICKET_PARAMETER),
            self::VALUE_PARAMETER => $request->attributes->getString(self::VALUE_PARAMETER),
        ];
    }
}
