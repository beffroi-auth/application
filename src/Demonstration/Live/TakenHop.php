<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

use App\Demonstration\Trace\FlowTrace;
use App\Demonstration\Trace\ProviderSideRecorder;
use App\Demonstration\Trace\TracedHop;
use function http_build_query;
use function is_array;
use function is_string;
use function json_decode;
use Psr\Clock\ClockInterface;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The secret of a client authenticates its calls and is never written into the trace.
 */
final readonly class TakenHop
{
    public function __construct(
        private HttpClientInterface $http,
        private ClockInterface $clock,
    ) {
    }

    public function take(DrivenHopInterface $driven, DrivenCall $call, FlowTrace $trace, DemonstrationClient $client): FlowTrace
    {
        [$requestHop, $answerHop] = $driven->hops();
        $fields = $call->fields;
        if ($client->secret === null && $call->bearer === null) {
            $fields['client_id'] = $client->id;
        }
        $payload = http_build_query($fields);
        $url = $call->method === Request::METHOD_GET && $payload !== '' ? sprintf('%s?%s', $call->url, $payload) : $call->url;
        $trace = $trace->with(TracedHop::sent($requestHop, $this->clock->now(), $call->method, $url, $payload));

        $options = [
            'headers' => $this->headers($call),
        ];
        if ($call->method !== Request::METHOD_GET) {
            $options['body'] = $fields;
        }
        if ($client->secret !== null) {
            $options['auth_basic'] = [$client->id, $client->secret];
        }

        try {
            $response = $this->http->request($call->method, $url, $options);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            return $trace->with(TracedHop::unreachable($answerHop, $this->clock->now(), $exception->getMessage()));
        }

        $answer = json_decode($body, true);
        $answer = is_array($answer) ? $answer : [];

        return $trace->with(TracedHop::answered($answerHop, $this->clock->now(), $status, $body, self::refusal($answer)))->holding($driven->keeps($answer));
    }

    /**
     * @return array<string, string>
     */
    private function headers(DrivenCall $call): array
    {
        $headers = [
            'User-Agent' => ProviderSideRecorder::AGENT,
            'Accept' => 'application/json',
        ];
        if ($call->bearer !== null) {
            $headers['Authorization'] = sprintf('Bearer %s', $call->bearer);
        }

        return $headers;
    }

    /**
     * @param array<array-key, mixed> $answer
     */
    private static function refusal(array $answer): ?string
    {
        $error = $answer['error'] ?? null;
        if (! is_string($error) || $error === '') {
            return null;
        }
        $description = $answer['error_description'] ?? null;

        return is_string($description) && $description !== ''
            ? sprintf('%s: %s', $error, $description)
            : $error;
    }
}
