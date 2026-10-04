<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use RawClient\McpClient;

require __DIR__ . '/../vendor/autoload.php';

const MODERN = '2026-07-28';
const LEGACY = '2025-11-25';
const CALLS = 100;
const HTTP_ENDPOINT = 'http://nginx/mcp';

$silent = static function (string $line): void {
};

$meta = ['_meta' => [
    'io.modelcontextprotocol/protocolVersion' => MODERN,
    'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
]];
$searchDune = ['name' => 'search_books', 'arguments' => ['query' => 'dune']];
$initialize = [
    'protocolVersion' => LEGACY,
    'capabilities' => new stdClass(),
    'clientInfo' => ['name' => 'benchmark', 'version' => '0.1.0'],
];

$milliseconds = static function (Closure $work): float {
    $start = hrtime(true);
    $work();

    return (hrtime(true) - $start) / 1e6;
};

/**
 * @param list<float> $samples
 *
 * @return array{median: float, p95: float}
 */
function summarize(array $samples): array
{
    sort($samples);

    return [
        'median' => $samples[intdiv(count($samples), 2)],
        'p95' => $samples[(int) floor(count($samples) * 0.95)],
    ];
}

$http = curl_init(HTTP_ENDPOINT);
$post = static function (string $method, array $params, bool $modern) use ($http): void {
    $body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];
    $isRequest = ! str_starts_with($method, 'notifications/');

    if ($isRequest) {
        $body['id'] = 1;
    }

    $headers = ['Content-Type: application/json', 'Accept: application/json, text/event-stream'];

    if ($modern) {
        $headers[] = 'MCP-Protocol-Version: ' . MODERN;
        $headers[] = 'Mcp-Method: ' . $method;

        if (isset($params['name']) && is_string($params['name'])) {
            $headers[] = 'Mcp-Name: ' . $params['name'];
        }
    }

    curl_setopt_array($http, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($http);
    $status = curl_getinfo($http, CURLINFO_RESPONSE_CODE);

    if ($response === false || $status !== ($isRequest ? 200 : 202)) {
        throw new RuntimeException("{$method} answered {$status}: " . (is_string($response) ? $response : curl_error($http)));
    }
};

$launch = static fn (): McpClient => McpClient::launch([PHP_BINARY, __DIR__ . '/../artisan', 'mcp:start', 'library'], $silent);

$results = [];

$results['stdio, legacy'] = $milliseconds(static function () use ($launch, $initialize, $searchDune): void {
    $client = $launch();
    $client->request('initialize', $initialize);
    $client->notify('notifications/initialized');
    $client->request('tools/call', $searchDune);
    $client->close();
});
$results['stdio, modern'] = $milliseconds(static function () use ($launch, $meta, $searchDune): void {
    $client = $launch();
    $client->request('tools/call', [...$searchDune, ...$meta]);
    $client->close();
});
$results['HTTP, legacy'] = $milliseconds(static function () use ($post, $initialize, $searchDune): void {
    $post('initialize', $initialize, false);
    $post('notifications/initialized', [], false);
    $post('tools/call', $searchDune, false);
});
$results['HTTP, modern'] = $milliseconds(static function () use ($post, $meta, $searchDune): void {
    $post('tools/call', [...$searchDune, ...$meta], true);
});

echo "First result, from nothing (one run):\n";
foreach ($results as $label => $elapsed) {
    printf("  %-14s %8.1f ms\n", $label, $elapsed);
}

/**
 * @return list<float>
 */
$warm = static function (Closure $call, bool $spaced) use ($milliseconds): array {
    $samples = [];

    for ($i = 0; $i < CALLS; $i++) {
        if ($spaced) {
            Sleep::usleep(random_int(0, 20_000));
        }

        $samples[] = $milliseconds($call);
    }

    return $samples;
};

$stdio = $launch();
$stdioCall = static fn (): array => $stdio->request('tools/call', [...$searchDune, ...$meta]);
$httpCall = static fn () => $post('tools/call', [...$searchDune, ...$meta], true);
$stdioCall();

foreach (['back to back' => false, 'spaced at random, 0 to 20 ms apart' => true] as $scenario => $spaced) {
    printf("\nOne more tools/call, once warm, %s (%d calls each):\n", $scenario, CALLS);

    foreach (['stdio' => $stdioCall, 'HTTP' => $httpCall] as $label => $call) {
        ['median' => $median, 'p95' => $p95] = summarize($warm($call, $spaced));
        printf("  %-14s median %6.1f ms   p95 %6.1f ms\n", $label, $median, $p95);
    }
}

$stdio->close();
