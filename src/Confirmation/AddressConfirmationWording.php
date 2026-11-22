<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Controller\AccountAddressLinkController;
use App\Locale\Moments;
use App\Message\SendAddressConfirmation;
use Beffroi\Core\Domain\Identity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What every channel tells of a confirmation, in the language of the person and on their clock: the code, until when
 * it holds, and the link to the page where the person confirms, which confirms nothing by being opened.
 */
final readonly class AddressConfirmationWording
{
    private const string TRANSLATION_DOMAIN = 'beffroi';

    public function __construct(
        private TranslatorInterface $translator,
        private Moments $moments,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function subject(User $user, string $locale): string
    {
        return $this->translator->trans('confirmation.notice.subject', ['provider' => $user->tenant->name], self::TRANSLATION_DOMAIN, $locale);
    }

    public function title(User $user, string $locale): string
    {
        return $this->translator->trans('confirmation.notice.title', ['provider' => $user->tenant->name], self::TRANSLATION_DOMAIN, $locale);
    }

    public function body(User $user, SendAddressConfirmation $confirmation, string $locale): string
    {
        return $this->translator->trans(
            'confirmation.notice.body',
            [
                'code' => $confirmation->code,
                'until' => $this->moments->write($confirmation->expiresAt, $user, $locale),
            ],
            self::TRANSLATION_DOMAIN,
            $locale,
        );
    }

    public function link(SendAddressConfirmation $confirmation): string
    {
        return (
            $confirmation->tenant->issuer->origin()
            . $this->router->generate(
                AccountAddressLinkController::ROUTE,
                [
                    'claim' => $confirmation->claim,
                    'confirmation' => $confirmation->confirmation->toRfc4122(),
                    'value' => $confirmation->value,
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            )
        );
    }
}
