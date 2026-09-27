<?php

declare(strict_types=1);

namespace App\AuthorizationDetails;

use function array_is_list;
use function array_values;
use Heidi\Oidc\Domain\AuthorizationDetails\AuthorizationDetail;
use Heidi\Oidc\Domain\AuthorizationDetails\AuthorizationDetailTypeInterface;
use Heidi\Oidc\Exception\InvalidArgumentException;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sort;
use function sprintf;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The one type of authorization detail this deployment serves (RFC 9396 §2), which is what "castor demo --rar"
 * asks for. The bundle wires none and cannot: a detail says what a client may do, to what and how much in the
 * words of one domain, so the type belongs to the deployment and not to the provider.
 *
 * It is what a scope cannot say. "invoices.read" says a kind of thing; a detail of this type names the invoice,
 * what may be done to it and the most that may be moved, and what a user reads before agreeing is those three
 * and not a category. Nothing here is a permission of the application: the detail travels into the access
 * token (§7) and the introspection (§9), and the resource server is what enforces it.
 *
 * Every member is required and every value is checked, because an unchecked member is a member a page cannot
 * show as it was sent. The members come back in the order this class builds them, so two requests that ask for
 * the same thing produce the same detail whatever order they were written in, which is what lets a consent
 * given for one cover the other (AuthorizationDetail::equals()).
 */
#[AutoconfigureTag('heidi.oidc.authorization_detail_type')]
final readonly class InvoicePaymentType implements AuthorizationDetailTypeInterface
{
    public const string TYPE = 'invoice_payment';

    /**
     * RFC 9396 §2.2: the common members this type uses, and the one of its own.
     */
    public const string LOCATIONS = 'locations';

    public const string ACTIONS = 'actions';

    public const string IDENTIFIER = 'identifier';

    public const string MAXIMUM_AMOUNT = 'maximumAmount';

    public const string CURRENCY = 'currency';

    public const string AMOUNT = 'amount';

    /**
     * What may be asked of an invoice here, and nothing else: a closed list of this domain, because an action
     * nobody implements is an action a user would be agreeing to for nothing.
     */
    private const array KNOWN_ACTIONS = ['read', 'pay'];

    /**
     * ISO 4217: three letters, upper case.
     */
    private const string CURRENCY_PATTERN = '/^[A-Z]{3}$/D';

    /**
     * The shape RFC 9396 §9.1 writes an amount in: a decimal in a string, so that nothing is read as a float
     * and nothing rounds on its way into a token.
     */
    private const string AMOUNT_PATTERN = '/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/D';

    public function type(): string
    {
        return self::TYPE;
    }

    public function of(array $members): AuthorizationDetail
    {
        $locations = self::locations($members);
        $actions = self::actions($members);
        $identifier = $members[self::IDENTIFIER] ?? null;
        if (! is_string($identifier) || preg_match('/^[A-Za-z0-9-]{1,64}$/D', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'A detail of the type "%s" names the invoice it is about in an "%s" member of at most 64 letters, digits and hyphens.',
                self::TYPE,
                self::IDENTIFIER,
            ));
        }
        [$currency, $amount] = self::maximumAmount($members);

        return new AuthorizationDetail(
            self::TYPE,
            [
                self::LOCATIONS => $locations,
                self::ACTIONS => $actions,
                self::IDENTIFIER => $identifier,
                self::MAXIMUM_AMOUNT => [
                    self::CURRENCY => $currency,
                    self::AMOUNT => $amount,
                ],
            ],
            [
                self::IDENTIFIER => $identifier,
                self::ACTIONS => implode(', ', $actions),
                self::CURRENCY => $currency,
                self::AMOUNT => $amount,
            ],
        );
    }

    /**
     * RFC 9396 §2.2 "locations": where the detail is to be used, as absolute URIs. A detail whose locations
     * name nothing is a detail no resource server can tell is addressed to it.
     *
     * @param array<string, mixed> $members
     *
     * @throws InvalidArgumentException
     *
     * @return non-empty-list<string>
     */
    private static function locations(array $members): array
    {
        $locations = $members[self::LOCATIONS] ?? null;
        if (! is_array($locations) || ! array_is_list($locations) || $locations === []) {
            throw new InvalidArgumentException(sprintf(
                'A detail of the type "%s" names where it is to be used in a non-empty "%s" list (RFC 9396 section 2.2).',
                self::TYPE,
                self::LOCATIONS,
            ));
        }
        $kept = [];
        foreach ($locations as $location) {
            if (! is_string($location) || preg_match('#^https?://[^\s]+$#D', $location) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'The locations of a detail of the type "%s" are absolute HTTP URIs (RFC 9396 section 2.2).',
                    self::TYPE,
                ));
            }
            $kept[] = $location;
        }
        sort($kept);

        return array_values($kept);
    }

    /**
     * RFC 9396 §2.2 "actions": what is asked of the invoice, among the ones this domain implements, each once.
     *
     * @param array<string, mixed> $members
     *
     * @throws InvalidArgumentException
     *
     * @return non-empty-list<string>
     */
    private static function actions(array $members): array
    {
        $actions = $members[self::ACTIONS] ?? null;
        if (! is_array($actions) || ! array_is_list($actions) || $actions === []) {
            throw new InvalidArgumentException(sprintf(
                'A detail of the type "%s" names what it asks of the invoice in a non-empty "%s" list (RFC 9396 section 2.2).',
                self::TYPE,
                self::ACTIONS,
            ));
        }
        $kept = [];
        foreach ($actions as $action) {
            if (! is_string($action) || ! in_array($action, self::KNOWN_ACTIONS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'The actions of a detail of the type "%s" are among %s.',
                    self::TYPE,
                    implode(', ', self::KNOWN_ACTIONS),
                ));
            }
            if (! in_array($action, $kept, true)) {
                $kept[] = $action;
            }
        }
        sort($kept);

        return array_values($kept);
    }

    /**
     * How much may be moved at most: the member that makes this a detail and not a scope. A currency of
     * ISO 4217 and a decimal written as a string, the shape RFC 9396 §9.1 uses, so that nothing rounds and
     * nothing is read as a float.
     *
     * @param array<string, mixed> $members
     *
     * @throws InvalidArgumentException
     *
     * @return array{string, string}
     */
    private static function maximumAmount(array $members): array
    {
        $maximum = $members[self::MAXIMUM_AMOUNT] ?? null;
        if (! is_array($maximum)) {
            throw new InvalidArgumentException(sprintf(
                'A detail of the type "%s" names the most it may move in a "%s" object.',
                self::TYPE,
                self::MAXIMUM_AMOUNT,
            ));
        }
        $currency = $maximum[self::CURRENCY] ?? null;
        if (! is_string($currency) || preg_match(self::CURRENCY_PATTERN, $currency) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" of a detail of the type "%s" names a currency of ISO 4217 in three upper-case letters.',
                self::MAXIMUM_AMOUNT,
                self::TYPE,
            ));
        }
        $amount = $maximum[self::AMOUNT] ?? null;
        if (! is_string($amount) || preg_match(self::AMOUNT_PATTERN, $amount) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" of a detail of the type "%s" is a decimal written as a string, with at most two decimals (RFC 9396 section 9.1).',
                self::AMOUNT,
                self::TYPE,
            ));
        }

        return [$currency, $amount];
    }
}
