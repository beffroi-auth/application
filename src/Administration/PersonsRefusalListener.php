<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Oidc\Exception\PersonsBudgetSpentException;
use function sprintf;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * The 429 of RFC 6585 §4, with the delay of RFC 9110 §10.2.3, that a read of the administration API past the budget of
 * the administrator answers (decision 0059). The body carries no "error": no value of RFC 6749 §11.4 says a budget is
 * spent, as on every refusal of a budget of this provider (decision 0014).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: self::PRIORITY)]
final readonly class PersonsRefusalListener
{
    /**
     * Above the ErrorListener of the framework (-128), which renders what nothing else answered.
     */
    public const int PRIORITY = 0;

    /**
     * A printf pattern, %d being the seconds of the Retry-After header, which a terminal does not show.
     */
    public const string DESCRIPTION = 'Too many persons read in this window; nobody was read this time. Try again in %d seconds.';

    public function __invoke(ExceptionEvent $event): void
    {
        $spent = self::budgetBehind($event->getThrowable());
        if ($spent === null) {
            return;
        }
        $refusal = AdministrationAnswer::of(['error_description' => sprintf(self::DESCRIPTION, $spent->retryAfter)], Response::HTTP_TOO_MANY_REQUESTS);
        $refusal->headers->set('Retry-After', (string) $spent->retryAfter);
        $event->setResponse($refusal);
    }

    private static function budgetBehind(Throwable $throwable): ?PersonsBudgetSpentException
    {
        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof PersonsBudgetSpentException) {
                return $cause;
            }
        }

        return null;
    }
}
