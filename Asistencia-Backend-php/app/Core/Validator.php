<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $errors[$field] = "El campo $field es obligatorio.";
            }
        }
        return $errors;
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen(trim($value)) >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen(trim($value)) <= $max;
    }

    public static function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    public static function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    public static function date(string $value, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    public static function isSecurePassword(string $password): bool
    {
        return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
    }
}
