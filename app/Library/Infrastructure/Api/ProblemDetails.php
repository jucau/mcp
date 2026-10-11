<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Api;

use App\Library\Application\Book\Catalog\CatalogUnavailable;
use App\Library\Application\Book\Catalog\NotInCatalog;
use App\Library\Domain\Book\BookAlreadyLent;
use App\Library\Domain\Book\BookChangedMeanwhile;
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
use App\Library\Domain\Refusal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ProblemDetails
{
    public const string INVALID_REQUEST = 'invalid-request';

    /**
     * @param class-string<Refusal> $refusal
     */
    public static function statusOf(string $refusal): int
    {
        return match ($refusal) {
            UnknownBook::class, InvalidBookId::class, NotInCatalog::class => Response::HTTP_NOT_FOUND,
            ReadingTransitionNotAllowed::class,
            BookAlreadyLent::class,
            BookNotLent::class,
            BookLent::class,
            BookNotOwned::class,
            BookNotFinished::class,
            BookChangedMeanwhile::class,
            DuplicateIsbn::class => Response::HTTP_CONFLICT,
            InvalidIsbn::class, InvalidRating::class, InvalidText::class, FinishedBeforeStarted::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            InvalidReaderId::class => Response::HTTP_INTERNAL_SERVER_ERROR,
            CatalogUnavailable::class => Response::HTTP_SERVICE_UNAVAILABLE,
            default => throw new LogicException(sprintf('The refusal %s has no HTTP status: give it one.', $refusal)),
        };
    }

    public static function forRefusal(Refusal $refusal): JsonResponse
    {
        $status = self::statusOf($refusal::class);

        return self::problem(
            self::typeOf($refusal::class),
            $status,
            $status === Response::HTTP_INTERNAL_SERVER_ERROR ? 'The server could not serve this request.' : $refusal->getMessage(),
        );
    }

    public static function forInvalidRequest(ValidationException $invalid): JsonResponse
    {
        return self::problem(self::INVALID_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY, $invalid->getMessage(), ['errors' => $invalid->errors()]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private static function problem(string $type, int $status, string $detail, array $extensions = []): JsonResponse
    {
        return new JsonResponse([
            'type' => self::typeUri($type),
            'title' => self::title($type),
            'status' => $status,
            'detail' => $detail,
            ...$extensions,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }

    public static function typeOf(string $refusal): string
    {
        return Str::kebab(class_basename($refusal));
    }

    public static function typeUri(string $type): string
    {
        return url('problems/' . $type);
    }

    public static function title(string $type): string
    {
        return ucfirst(str_replace('-', ' ', $type));
    }
}
