<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use App\Library\Infrastructure\Api\ProblemDetails;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

final class Problem
{
    /**
     * @param array<string, Type> $extensions
     */
    public static function response(int $status, string $type, array $extensions = []): Response
    {
        $title = ProblemDetails::title($type);

        $typeUri = new StringType();
        $typeUri->format('uri');
        $typeUri->enum([ProblemDetails::typeUri($type)]);

        $titleText = new StringType();
        $titleText->enum([$title]);

        $statusCode = new IntegerType();
        $statusCode->enum([$status]);

        $detail = new StringType();
        $detail->setDescription('The message of the refusal, as the domain wrote it.');

        $body = new ObjectType();
        $body->addProperty('type', $typeUri);
        $body->addProperty('title', $titleText);
        $body->addProperty('status', $statusCode);
        $body->addProperty('detail', $detail);

        foreach ($extensions as $name => $schema) {
            $body->addProperty($name, $schema);
        }

        $body->setRequired(['type', 'title', 'status', 'detail', ...array_keys($extensions)]);

        $schema = new Schema();
        $schema->type = $body;

        return new Response($status)
            ->setDescription($title)
            ->setContent('application/problem+json', $schema);
    }
}
