<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\AskedDocument;
use App\Administration\InitialAccessTokenDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Exception\InvalidArgumentException as InvalidDocumentException;
use Beffroi\Oidc\Application\Client\Registration\InitialAccessTokenIssuance;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Domain\Client\Metadata\GrantedMembers;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use DateInterval;
use Psr\Clock\ClockInterface;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Issues an initial access token of the tenant, what `beffroi:client:token:issue` does (decision 0038), the token for
 * its administration included. The answer carries the value once, and nothing shows it again; the issuance is an act
 * of the tenant that names the administrator.
 */
#[AsController]
final readonly class InitialAccessTokenIssuanceController
{
    private const string ASKED = 'An initial access token is issued from a JSON object naming it, "name", with how many clients it may register, "registrations", and for how many days, "days"; "granted" gives its clients members of the deployment, and "administration" makes them clients of this API.';

    /**
     * As json_decode() counts it: the object, "granted", "token_exchange" in it, the list of the types it may exchange,
     * and those types.
     */
    private const int DEPTH = 5;

    public function __construct(
        private InitialAccessTokenIssuance $issuance,
        private RequestContextFactory $contexts,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/initial-access-tokens', name: 'beffroi_administration_initial_access_token_issuance', methods: ['POST'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        $asked = AskedDocument::of($request, 'initial access token asked for', self::DEPTH);
        if ($asked === null) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
        }
        try {
            $name = $asked->nullableString('name');
            $registrations = $asked->nullableInt('registrations');
            $days = $asked->nullableInt('days');
            if ($name === null || $registrations === null || $days === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            if ($days < 1) {
                return AdministrationAnswer::refusal(
                    Response::HTTP_BAD_REQUEST,
                    'invalid_request',
                    'An initial access token is worth something for a day at least.',
                );
            }
            $granted = $asked->nested('granted');
            $issued = $this->issuance->issueByAnAdministrator(
                $administrator->tenant,
                $name,
                $registrations,
                new DateInterval(sprintf('P%dD', $days)),
                $administrator->user->subject,
                $this->contexts->fromRequest($request),
                $granted === null ? null : GrantedMembers::fromDocument($granted),
                $asked->nullableBool('administration') ?? false,
            );
        } catch (InvalidDocumentException|InvalidArgumentException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $exception->getMessage());
        }

        return AdministrationAnswer::of(
            InitialAccessTokenDocument::of($issued->token, $this->clock->now()) + ['value' => $issued->value],
            Response::HTTP_CREATED,
        );
    }
}
