<?php

declare(strict_types=1);

use RawClient\HttpMcpClient;

require __DIR__ . '/../vendor/autoload.php';

const ENDPOINT = 'http://nginx/mcp';

$print = static function (string $line): void {
    echo $line, "\n";
};

$step = static function (string $title): void {
    echo "\n# ", $title, "\n";
};

$token = getenv('MCP_TOKEN') ?: null;
$client = HttpMcpClient::over(ENDPOINT, $print, token: $token);

$step('No handshake: discover the server, if we want to');
$client->request('server/discover');

$step('Discover the tools, then call one: each request is its own POST');
$client->request('tools/list');
$found = $client->request('tools/call', ['name' => 'search_books', 'arguments' => ['query' => 'dune']]);

$step('A business refusal is still a result, flagged isError, with a 200');
$bookId = data_get($found, 'message.result.structuredContent.books.0.id');
$client->request('tools/call', ['name' => 'rate_book', 'arguments' => [
    'book_id' => is_string($bookId) ? $bookId : 'no-book-found',
    'rating' => 6,
]]);

$step('Resources and prompts: Mcp-Name carries the URI or the name');
$client->request('resources/read', ['uri' => 'library://shelves/reading']);
$client->request('prompts/get', ['name' => 'reading_review', 'arguments' => ['year' => '2025']]);

$step('An unknown method: a JSON-RPC error, and a 404');
$client->request('books/burn');

$step('A version the server does not speak: a 400 that lists the ones it does');
HttpMcpClient::over(ENDPOINT, $print, version: '1900-01-01', token: $token)->request('tools/list');

$step('Without a token: a 401 that says where to learn how to get one');
HttpMcpClient::over(ENDPOINT, $print)->request('tools/list');

$step('Nothing to close: there was no session');
