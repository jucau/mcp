<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use App\Library\Infrastructure\Api\ProblemDetails;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as ObjectSchema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ValidationToProblem extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(ValidationException::class);
    }

    #[\Override]
    public function toResponse(Type $type): Response
    {
        $messages = new ArrayType();
        $messages->setItems(new StringType());

        $errors = new ObjectSchema();
        $errors->additionalProperties($messages);

        return Problem::response(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, ProblemDetails::INVALID_REQUEST, ['errors' => $errors]);
    }
}
