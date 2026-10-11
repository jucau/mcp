<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api\OpenApi;

use App\Library\Domain\Refusal;
use App\Library\Infrastructure\Api\ProblemDetails;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Webmozart\Assert\Assert;

final class RefusalToProblem extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(Refusal::class);
    }

    #[\Override]
    public function toResponse(Type $type): Response
    {
        Assert::isInstanceOf($type, ObjectType::class);
        Assert::subclassOf($type->name, Refusal::class);

        return Problem::response(ProblemDetails::statusOf($type->name), ProblemDetails::typeOf($type->name));
    }
}
