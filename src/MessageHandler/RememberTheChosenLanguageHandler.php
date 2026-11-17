<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RememberTheChosenLanguage;
use Beffroi\Core\Application\Identity\LanguageRecording;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RememberTheChosenLanguageHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private LanguageRecording $recording,
    ) {
    }

    public function __invoke(RememberTheChosenLanguage $command): void
    {
        $user = $this->users->find($command->user);
        if ($user === null) {
            return;
        }
        $this->recording->record($user, $command->locale);
    }
}
