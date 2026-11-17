<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Application\Identity\FederatedDomains;
use function array_filter;
use function count;
use Beffroi\Core\Application\Totp\ActiveTotp;
use Beffroi\Core\Domain\Credential\BackupCode;
use Beffroi\Core\Domain\Credential\TotpCredential;
use Beffroi\Core\Domain\Credential\TotpProvisioning;
use Beffroi\Core\Domain\Identity\User;
use function implode;
use ParagonIE\ConstantTime\Base32;
use function str_split;
use Symfony\Component\HttpFoundation\Response;

final readonly class TwoFactorPage
{
    public function __construct(
        private ActiveTotp $activeTotp,
        private QrCode $qrCode,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function render(User $user, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $totp = $this->activeTotp->of($user);

        return $this->pages->render(
            'account/two_factor.html.twig',
            [
                'user' => $user,
                'totp' => $totp,
                'backup_codes_left' => $totp === null ? 0 : count(array_filter($totp->backupCodes, static fn (BackupCode $code): bool => ! $code->isSpent())),
                'admitted' => $user->tenant->loginFloor->admits([TotpCredential::method()]),
                'federated' => ! $this->federated->admitsLocalCredentials($user),
                'error' => $error,
            ],
            $status,
        );
    }

    public function renderEnrolment(User $user, TotpProvisioning $provisioning, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        return $this->pages->render(
            'account/two_factor_enrol.html.twig',
            [
                'user' => $user,
                'qr_code' => $this->qrCode->svg($provisioning->uri),
                'key' => implode(' ', str_split(Base32::encodeUpperUnpadded($provisioning->secret->bytes), 4)),
                'error' => $error,
            ],
            $status,
        );
    }

    /**
     * @param list<string> $backupCodes in clear, this once
     */
    public function renderEnrolled(User $user, array $backupCodes): Response
    {
        return $this->pages->render('account/two_factor_enrolled.html.twig', [
            'user' => $user,
            'backup_codes' => $backupCodes,
        ]);
    }
}
