<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a client gets when it goes over the kit's rate limit. A bare 429 reads
 * to an assistant as "the server is down": Codex gave up after one burst of
 * parallel calls. Each JSON-RPC request in the body gets an answer the model
 * reads instead: a tools/call comes back as a tool error, anything else as a
 * JSON-RPC error, both saying how long to wait and to slow down.
 * A body of notifications alone has nothing to answer and keeps the 429.
 */
class ThrottledCall
{
    public static function render(ThrottleRequestsException $exception, Request $request): ?JsonResponse
    {
        if (! in_array('throttle:mcp-kit', $request->route()?->gatherMiddleware() ?? [], true)) {
            return null;
        }

        $body = json_decode((string) $request->getContent(), true);

        if (! is_array($body) || $body === []) {
            return null;
        }

        $batch = array_is_list($body);
        $messages = $batch ? $body : [$body];
        $headers = $exception->getHeaders();
        $wait = max(1, (int) ($headers['Retry-After'] ?? 60));
        $text = static::message($wait);

        $answers = [];

        foreach ($messages as $message) {
            if (! is_array($message) || ! array_key_exists('id', $message)) {
                continue;
            }

            $answers[] = ($message['method'] ?? null) === 'tools/call'
                ? ['jsonrpc' => '2.0', 'id' => $message['id'], 'result' => ['content' => [['type' => 'text', 'text' => $text]], 'isError' => true]]
                : ['jsonrpc' => '2.0', 'id' => $message['id'], 'error' => ['code' => -32000, 'message' => $text, 'data' => ['retry_after' => $wait]]];
        }

        if ($answers === []) {
            return null;
        }

        return new JsonResponse($batch ? $answers : $answers[0], 200, $headers);
    }

    public static function message(int $wait): string
    {
        $limit = (int) (config('mcp-kit.routes.throttle') ?? 120);

        $calls = $limit === 1 ? 'call' : 'calls';

        return "Rate limit reached: this server takes {$limit} {$calls} a minute per connection, and this call was not run. "
            ."Wait {$wait} seconds, then send it again. Nothing is wrong with the server. "
            .'Make calls one after another rather than in parallel, and use a tool that takes a list where the server has one.';
    }
}
