<?php

declare(strict_types=1);

use RawClient\McpClient;

require __DIR__ . '/../vendor/autoload.php';

$client = McpClient::launch(
    [PHP_BINARY, __DIR__ . '/../artisan', 'mcp:start', 'library'],
    static function (string $line): void {
        echo $line, "\n";
    },
);

$step = static function (string $title): void {
    echo "\n# ", $title, "\n";
};

$step('Open the session: versions, capabilities, instructions');
$client->request('initialize', [
    'protocolVersion' => '2025-11-25',
    'capabilities' => new stdClass(),
    'clientInfo' => ['name' => 'raw-client', 'version' => '0.1.0'],
]);
$client->notify('notifications/initialized');

$step('Check the server is alive');
$client->request('ping');

$step('Discover the tools, then call one');
$client->request('tools/list');
$found = $client->request('tools/call', ['name' => 'search_books', 'arguments' => ['query' => 'dune']]);

$step('A business refusal is a result, flagged isError');
$dig = static function (mixed $value, string|int ...$path): mixed {
    foreach ($path as $key) {
        $value = is_array($value) ? ($value[$key] ?? null) : null;
    }

    return $value;
};
$bookId = $dig($found, 'result', 'structuredContent', 'books', 0, 'id');
$bookId = is_string($bookId) ? $bookId : 'no-book-found';
$client->request('tools/call', ['name' => 'rate_book', 'arguments' => ['book_id' => $bookId, 'rating' => 6]]);

$step('Resources: a fixed URI, a template, a read');
$client->request('resources/list');
$client->request('resources/templates/list');
$client->request('resources/read', ['uri' => 'library://shelves/reading']);

$step('Prompts: discover, then get one with its argument');
$client->request('prompts/list');
$client->request('prompts/get', ['name' => 'reading_review', 'arguments' => ['year' => '2025']]);

$step('An unknown method is a JSON-RPC error');
$client->request('books/burn');

$step('Close stdin: the server exits');
echo 'exit code: ', $client->close(), "\n";
