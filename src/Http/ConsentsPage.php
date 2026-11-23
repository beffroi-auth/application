<?php

declare(strict_types=1);

namespace App\Http;

use function array_map;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Consent\ConsentQuestion;
use Beffroi\Oidc\Domain\Consent\GrantedAccess;
use Beffroi\Oidc\Domain\Scope\Scope;
use Beffroi\Oidc\Port\ActiveConsentsInterface;
use Beffroi\Oidc\Port\CanPageActiveConsentsInterface;
use function explode;
use Symfony\Component\HttpFoundation\Response;

/**
 * An access is asked for by one word, its kind before a colon and its name after it ("scope:email",
 * "claim:given_name", "type:payment_initiation"), since a scope, a claim and a type may share a name.
 */
final readonly class ConsentsPage
{
    public const string ACCESS = 'access';

    public const string APPLICATION = 'application';

    public const string SCOPE = 'scope';

    public const string CLAIM = 'claim';

    public const string DETAIL_TYPE = 'type';

    public function __construct(
        private CanPageActiveConsentsInterface $consents,
        private ActiveConsentsInterface $active,
        private AskedPage $asked,
        private AskedPeriod $period,
        private AskedWord $word,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user): Response
    {
        $granted = GrantedAccess::of($this->active->activeOf($user));
        $access = $this->word->among(self::ACCESS, [
            ...array_map(static fn (Scope $scope): string => self::SCOPE . ':' . $scope->value, $granted->scopes),
            ...array_map(static fn (string $claim): string => self::CLAIM . ':' . $claim, $granted->claims),
            ...array_map(static fn (string $type): string => self::DETAIL_TYPE . ':' . $type, $granted->types),
        ]);
        [$kind, $name] = $access === null ? ['', ''] : explode(':', $access, 2) + [1 => ''];
        $question = new ConsentQuestion(
            granted: $this->period->asked($user),
            scope: $kind === self::SCOPE ? new Scope($name) : null,
            claim: $kind === self::CLAIM ? $name : null,
            detailType: $kind === self::DETAIL_TYPE ? $name : null,
            applicationName: $this->word->text(self::APPLICATION),
        );

        return $this->pages->render('account/consents.html.twig', [
            'user' => $user,
            'consents' => $this->consents->pageOfActiveConsents($user, $question, $this->asked->asked()),
            'question' => $question,
            'access' => $access,
            'granted' => $granted,
            'narrowed' => $question->granted !== null || $access !== null || $question->applicationName !== null,
        ]);
    }
}
