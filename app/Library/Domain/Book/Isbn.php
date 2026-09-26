<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

final readonly class Isbn
{
    public string $value;

    private function __construct(string $isbn)
    {
        $this->value = match (true) {
            $this->isIsbn10($isbn) => $this->isbn13From(substr($isbn, 0, 9)),
            $this->isIsbn13($isbn) => $isbn,
            default => throw InvalidIsbn::value($isbn),
        };
    }

    public static function fromString(string $value): self
    {
        try {
            return new self(strtoupper((string) preg_replace('/[\s-]/', '', $value)));
        } catch (InvalidIsbn) {
            throw InvalidIsbn::value($value);
        }
    }

    private function isIsbn10(string $isbn): bool
    {
        return preg_match('/^\d{9}[\dX]$/', $isbn) === 1 && $this->checkDigit10(substr($isbn, 0, 9)) === $isbn[9];
    }

    private function isIsbn13(string $isbn): bool
    {
        return preg_match('/^97[89]\d{10}$/', $isbn) === 1 && $this->checkDigit13(substr($isbn, 0, 12)) === $isbn[12];
    }

    private function checkDigit10(string $body): string
    {
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += (10 - $position) * (int) $digit;
        }

        $check = (11 - $sum % 11) % 11;

        return $check === 10 ? 'X' : (string) $check;
    }

    private function isbn13From(string $isbn10Body): string
    {
        $body = '978' . $isbn10Body;

        return $body . $this->checkDigit13($body);
    }

    private function checkDigit13(string $body): string
    {
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $sum % 10) % 10);
    }
}
