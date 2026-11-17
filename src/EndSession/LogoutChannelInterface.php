<?php

declare(strict_types=1);

namespace App\EndSession;

use Beffroi\Oidc\Domain\EndSession\LogoutChannel;

interface LogoutChannelInterface
{
    public function channel(): LogoutChannel;
}
