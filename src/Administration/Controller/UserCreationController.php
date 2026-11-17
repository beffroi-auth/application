<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\PersonDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Identity\UserAlreadyExistsException;
use Beffroi\Core\Domain\Encoding\Document;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Application\Administration\AdministeredPersonCreation;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creates a person of the tenant, what `beffroi:user:create` does (decision 0038), from a JSON object naming their
 * username and, when they are known, their email address and whether it was verified. The person holds no credential:
 * an enrolment ticket lets them pose a passkey. The creation is an act of theirs that names the administrator, and the
 * answer writes the person as a read of them does, so that nothing reads them again.
 */
#[AsController]
final readonly class UserCreationController
{
    private const string ASKED = 'A person is created from a JSON object naming their "username", and their "email" and whether it is "email_verified" when they are known.';

    public function __construct(
        private AdministeredPersonCreation $persons,
        private RequestContextFactory $contexts,
        private PersonDocument $documents,
    ) {
    }

    #[Route('/users', name: 'beffroi_administration_user_creation', methods: ['POST'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        $asked = self::asked($request);
        if ($asked === null) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
        }
        try {
            $username = $asked->nullableString('username');
            if ($username === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            $email = $asked->nullableString('email');
            $user = $this->persons->create(
                $administrator,
                $username,
                $this->contexts->fromRequest($request),
                $email === null ? null : new EmailAddress($email),
                $asked->nullableBool('email_verified') ?? false,
            );
        } catch (InvalidArgumentException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $exception->getMessage());
        } catch (UserAlreadyExistsException) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', 'The tenant has a person of this username already.');
        }

        return AdministrationAnswer::of($this->documents->of($user, [], []), Response::HTTP_CREATED);
    }

    private static function asked(Request $request): ?Document
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return null;
        }
        try {
            return Document::of(json_decode($request->getContent(), true, 2, JSON_THROW_ON_ERROR), 'person asked for');
        } catch (JsonException|InvalidArgumentException) {
            return null;
        }
    }
}
