<?php

declare(strict_types=1);

namespace App\Credential;

use App\Controller\AccountController;
use App\Locale\Moments;
use App\Locale\UserLocale;
use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * NIST SP 800-63B-4 §4.6
 *
 * Written outside any request of the person, in their language and on their clock; an act is told by being translated,
 * so an act told later edits nothing here.
 */
final readonly class CredentialChangeNotices
{
    public const string TRANSLATION_DOMAIN = 'beffroi';

    public function __construct(
        private TranslatorInterface $translator,
        private UserLocale $locales,
        private Moments $moments,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function of(User $user, CredentialChange $change): CredentialChangeNotice
    {
        $locale = $this->locales->of($user);
        $tenant = $user->tenant;
        $what = $this->translator->trans(
            'credential_change.' . $change->act,
            [
                'kind' => $change->kind ?? '',
                'when' => $this->moments->write($change->occurredAt, $user, $locale),
            ],
            self::TRANSLATION_DOMAIN,
            $locale,
        );
        $contact = $tenant->contact;

        return new CredentialChangeNotice(
            $this->translator->trans('credential_change.title', ['provider' => $tenant->name], self::TRANSLATION_DOMAIN, $locale),
            $this->translator->trans('credential_change.subject', ['provider' => $tenant->name], self::TRANSLATION_DOMAIN, $locale),
            $contact === null
                ? $this->translator->trans('credential_change.body', ['what' => $what], self::TRANSLATION_DOMAIN, $locale)
                : $this->translator->trans(
                    'credential_change.body_with_contact',
                    [
                        'what' => $what,
                        'contact' => $contact->value,
                    ],
                    self::TRANSLATION_DOMAIN,
                    $locale,
                ),
            $tenant->issuer->origin() . $this->router->generate(AccountController::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_PATH),
        );
    }
}
