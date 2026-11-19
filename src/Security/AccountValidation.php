<?php

namespace App\Security;

/** Shared account validation; callers retain their existing optional-field policies. */
final class AccountValidation
{
    public static function required(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === "" || mb_strlen($value) > $max) {
            throw new \DomainException(
                "Veuillez compléter les champs avec des valeurs valides.",
            );
        }
        return $value;
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function password(
        string $password,
        string $confirmation,
        bool $minimumInBytes = false,
    ): bool {
        $length = $minimumInBytes ? strlen($password) : mb_strlen($password);
        return $length >= 12 &&
            strlen($password) <= 72 &&
            $password === $confirmation;
    }
}
