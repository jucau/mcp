<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\Oauth2SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;

final class DescribeTheApi implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->setInfo(
            new InfoObject('Marque-page', '0.1.0')
                ->setDescription('The personal library of the reader behind the token: the same use cases as the MCP server, over REST.'),
        );

        $oauth = new Oauth2SecurityScheme();
        $oauth->flow('authorizationCode', static function (OAuthFlow $flow): void {
            $flow->authorizationUrl(url('oauth/authorize'))->tokenUrl(url('oauth/token'))->refreshUrl(url('oauth/token'));
        });
        $oauth->setDescription('Authorization code with PKCE (S256). Name this API as the resource (RFC 8707): a token issued for another server is refused.');

        $document->secure($oauth);
    }
}
