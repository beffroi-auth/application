<?php

declare(strict_types=1);

namespace App\Command;

use function count;
use function file_get_contents;
use function hash;
use App\Webauthn\DocumentDownload;
use App\Webauthn\DownloadRefusedException;
use Beffroi\Core\Bridge\Webauthn\MetadataBlobVerifier;
use Beffroi\Core\Exception\MetadataBlobRefusedException;
use Beffroi\Core\Port\MetadataBlobStoreInterface;
use function is_string;
use function parse_url;
use const PHP_URL_HOST;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Webauthn\MetadataService\CertificateChain\CertificateToolbox;

/**
 * FIDO Metadata Service v3.1.1 §3.1, §3.2
 */
#[AsCommand(
    name: 'beffroi:webauthn:mds-refresh',
    description: 'Download the FIDO Metadata BLOB, verify its signature under the pinned root and store it for the passkey ceremonies; --url for another service, --root for another root.',
)]
final readonly class WebauthnMdsRefreshCommand
{
    /**
     * Not the "mds3" host of the documentation, which redirects here: no outbound request follows a redirect.
     */
    public const string FIDO_METADATA_SERVICE = 'https://mds.fidoalliance.org/';

    public const string FIDO_ROOT_CERTIFICATE = __DIR__ . '/../../config/beffroi/webauthn/globalsign-root-r46.pem';

    private const string PEM_BLOCK = '-----BEGIN ';

    public function __construct(
        private HttpClientInterface $http,
        private DocumentDownload $download,
        private MetadataBlobVerifier $verifier,
        private MetadataBlobStoreInterface $store,
    ) {
    }

    /**
     * @param list<string> $url
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The URL of a Metadata BLOB, repeatable; the FIDO Metadata Service unless given.')]
        array $url = [],
        #[Option(
            description: 'The root certificate the chain of a blob must lead to: a PEM or DER file, or an https URL; the GlobalSign Root R46 the FIDO Alliance names unless given.',
        )]
        ?string $root = null,
    ): int {
        try {
            $rootCertificate = $this->rootCertificate($root ?? self::FIDO_ROOT_CERTIFICATE);
        } catch (Throwable $exception) {
            $io->error(sprintf('The root certificate cannot be read: %s', PrintedText::inBlock($exception->getMessage())));

            return Command::FAILURE;
        }
        $refused = 0;
        foreach ($url === [] ? [self::FIDO_METADATA_SERVICE] : $url as $source) {
            if (! is_string($source)) {
                continue;
            }
            try {
                $blob = $this->verifier->verify($this->download->of($source), $rootCertificate);
            } catch (DownloadRefusedException|MetadataBlobRefusedException $exception) {
                ++$refused;
                $io->error(sprintf('The blob of %s is refused, nothing of it is kept: %s', $source, PrintedText::inBlock($exception->getMessage())));
                continue;
            }
            $name = self::name($source);
            $this->store->write($name, $blob->payload);
            $io->success(sprintf(
                'The blob of %s is stored as "%s": serial number %d, %d entries, next update due on %s.',
                $source,
                $name,
                $blob->content->no,
                count($blob->content->entries),
                $blob->content->nextUpdate,
            ));
        }

        return $refused === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * A file or an address gives the root as PEM or as DER, and convertDERToPEM() reads whatever it is given as DER, a PEM
     * included.
     */
    private function rootCertificate(string $root): string
    {
        if (str_starts_with($root, 'https://')) {
            $response = $this->http->request('GET', $root, [
                'max_redirects' => 0,
            ]);
            $certificate = $response->getContent();
        } else {
            $certificate = file_get_contents($root);
            if ($certificate === false) {
                throw new MetadataBlobRefusedException(sprintf('"%s" cannot be read.', $root));
            }
        }

        return str_contains($certificate, self::PEM_BLOCK) ? $certificate : CertificateToolbox::convertDERToPEM($certificate);
    }

    /**
     * @return non-empty-string
     */
    private static function name(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return sprintf('mds-%s-%s', is_string($host) ? $host : 'unnamed', substr(hash('sha256', $url), 0, 8));
    }
}
