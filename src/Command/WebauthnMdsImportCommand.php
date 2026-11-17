<?php

declare(strict_types=1);

namespace App\Command;

use function file_get_contents;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Port\MetadataBlobStoreInterface;
use function is_array;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use Psr\Clock\ClockInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\MetadataService\Statement\MetadataStatement;

#[AsCommand(
    name: 'beffroi:webauthn:mds-import',
    description: 'Store a metadata statement file (unsigned, trusted as the file is) next to the FIDO Metadata BLOB, for the keys of a manufacturer the service does not list or the authenticators of the FIDO conformance tools.',
)]
final readonly class WebauthnMdsImportCommand
{
    private SerializerInterface $serializer;

    public function __construct(
        private MetadataBlobStoreInterface $store,
        private ClockInterface $clock,
    ) {
        $this->serializer = new WebauthnSerializerFactory(AttestationStatementSupportManager::create())->create();
    }

    /**
     * @param list<string> $files
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Metadata statement files, JSON, one authenticator each.')]
        array $files,
    ): int {
        $refused = 0;
        foreach ($files as $file) {
            try {
                [$aaguid, $description, $payload] = $this->wrap($file);
            } catch (Throwable $exception) {
                ++$refused;
                $io->error(sprintf('"%s" is not a metadata statement, nothing of it is kept: %s', $file, $exception->getMessage()));
                continue;
            }
            $name = 'statement-' . $aaguid;
            $this->store->write($name, $payload);
            $io->success(sprintf('The statement of "%s" (%s) is stored as "%s".', $description, $aaguid, $name));
        }

        return $refused === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{string, string, string}
     */
    private function wrap(string $file): array
    {
        $json = file_get_contents($file);
        if ($json === false) {
            throw new InvalidArgumentException('the file cannot be read');
        }
        $statement = $this->serializer->deserialize($json, MetadataStatement::class, JsonEncoder::FORMAT);
        $aaguid = $statement->aaguid ?? throw new InvalidArgumentException('the statement names no AAGUID');
        $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($document)) {
            throw new InvalidArgumentException('the file is not a JSON object');
        }
        $today = $this->clock->now()->format('Y-m-d');
        $payload = json_encode([
            'legalHeader' => $statement->legalHeader,
            'no' => 0,
            'nextUpdate' => $today,
            'entries' => [[
                'aaguid' => $aaguid,
                'metadataStatement' => $document,
                'statusReports' => [],
                'timeOfLastStatusChange' => $today,
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [$aaguid, $statement->description, $payload];
    }
}
