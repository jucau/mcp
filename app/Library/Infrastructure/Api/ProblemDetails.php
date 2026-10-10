<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api;

use App\Library\Domain\Book\BookAlreadyLent;
use App\Library\Domain\Book\BookLent;
use App\Library\Domain\Book\BookNotFinished;
use App\Library\Domain\Book\BookNotLent;
use App\Library\Domain\Book\BookNotOwned;
use App\Library\Domain\Book\DuplicateIsbn;
use App\Library\Domain\Book\FinishedBeforeStarted;
use App\Library\Domain\Book\InvalidBookId;
use App\Library\Domain\Book\InvalidIsbn;
use App\Library\Domain\Book\InvalidRating;
use App\Library\Domain\Book\InvalidReaderId;
use App\Library\Domain\Book\InvalidText;
use App\Library\Domain\Book\ReadingTransitionNotAllowed;
use App\Library\Domain\Book\UnknownBook;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ProblemDetails
{
    /**
     * @param class-string<DomainException> $refusal
     */
    public static function statusOf(string $refusal): int
    {
        return match ($refusal) {
            UnknownBook::class, InvalidBookId::class => Response::HTTP_NOT_FOUND,
            ReadingTransitionNotAllowed::class,
            BookAlreadyLent::class,
            BookNotLent::class,
            BookLent::class,
            BookNotOwned::class,
            BookNotFinished::class,
            DuplicateIsbn::class => Response::HTTP_CONFLICT,
            InvalidIsbn::class, InvalidRating::class, InvalidText::class, FinishedBeforeStarted::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            InvalidReaderId::class => Response::HTTP_INTERNAL_SERVER_ERROR,
            default => throw new LogicException(sprintf('The refusal %s has no HTTP status: give it one.', $refusal)),
        };
    }

    public static function forRefusal(DomainException $refusal): ?JsonResponse
    {
        if (! str_starts_with($refusal::class, 'App\\Library\\Domain\\')) {
            return null;
        }

        $status = self::statusOf($refusal::class);

        return self::problem(
            Str::kebab(class_basename($refusal)),
            $status,
            $status === Response::HTTP_INTERNAL_SERVER_ERROR ? 'The server could not serve this request.' : $refusal->getMessage(),
        );
    }

    public static function forInvalidRequest(ValidationException $invalid): JsonResponse
    {
        return self::problem('invalid-request', Response::HTTP_UNPROCESSABLE_ENTITY, $invalid->getMessage(), ['errors' => $invalid->errors()]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private static function problem(string $type, int $status, string $detail, array $extensions = []): JsonResponse
    {
        return new JsonResponse([
            'type' => url('problems/' . $type),
            'title' => ucfirst(str_replace('-', ' ', $type)),
            'status' => $status,
            'detail' => $detail,
            ...$extensions,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
