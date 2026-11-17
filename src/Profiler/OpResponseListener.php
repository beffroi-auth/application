<?php

declare(strict_types=1);

namespace App\Profiler;

use function is_array;
use function json_decode;
use JsonException;
use const JSON_THROW_ON_ERROR;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: self::PRIORITY)]
final readonly class OpResponseListener
{
    /**
     * Above the profiler, which collects at -100.
     */
    public const int PRIORITY = 0;

    public function __construct(
        private OpProfile $profile,
        private PanelValues $values,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (! $event->isMainRequest() || ! $this->profile->hasTouchedTheProvider()) {
            return;
        }
        $this->profile->record(new Observation('Response', 'Answer', [
            'status' => (string) $response->getStatusCode(),
            'content type' => $response->headers->get('Content-Type') ?? 'none',
            'body' => $this->body($response),
        ]));
    }

    private function body(Response $response): string
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return 'empty';
        }
        try {
            $members = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'not JSON: a page, a redirection or a document signed for the client';
        }
        if (! is_array($members)) {
            return 'JSON that is not an object';
        }
        return $this->values->all($members, 'an empty object');
    }
}
