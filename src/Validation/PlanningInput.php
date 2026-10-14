<?php
declare(strict_types=1);
namespace Afec\Validation;

use Afec\Http\HttpError;

final class PlanningInput
{
    public static function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw new HttpError(422, 'validation', 'La solicitud contiene campos no permitidos.');
        }
    }

    public static function text(array $input, string $field, int $maximum, bool $required = false, bool $multiline = false): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null && !$required) { return null; }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) { self::invalid($field, 'Ingresá texto válido.'); }
        $value = trim($value);
        $controls = $multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/';
        if (mb_strlen($value) > $maximum || preg_match($controls, $value) || ($required && $value === '')) {
            self::invalid($field, "El campo debe contener texto válido de hasta $maximum caracteres.");
        }
        return $value === '' ? null : $value;
    }

    public static function version(array $input, bool $allowZero = false): string
    {
        $value = $input['version'] ?? null;
        if (is_int($value)) { $value = (string)$value; }
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', $value) ||
            (!$allowZero && $value === '0') || (strlen($value) === 10 && strcmp($value, '4294967295') > 0)) {
            self::invalid('version', 'Recargá los datos antes de editar.');
        }
        return $value;
    }

    public static function quantity(array $input): string
    {
        $value = $input['required_quantity'] ?? null;
        if (is_int($value)) { $value = (string)$value; }
        if (!is_string($value) || !preg_match('/^[0-9]{1,20}$/D', $value)) {
            self::invalid('required_quantity', 'Ingresá una cantidad entera no negativa, sin separadores.');
        }
        $value = ltrim($value, '0');
        $value = $value === '' ? '0' : $value;
        if (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0) {
            self::invalid('required_quantity', 'La cantidad excede el rango admitido por la base de datos.');
        }
        return $value;
    }

    public static function order(array $input): int
    {
        $value = $input['sort_order'] ?? 0;
        if (is_int($value)) { $value = (string)$value; }
        if (!is_string($value) || !preg_match('/^-?[0-9]{1,10}$/D', $value) ||
            (float)$value < -2147483648 || (float)$value > 2147483647) {
            self::invalid('sort_order', 'Ingresá un orden entero dentro del rango admitido.');
        }
        return (int)$value;
    }

    public static function confirmation(array $input): void
    {
        if (($input['confirmed'] ?? null) !== true) { self::invalid('confirmed', 'Confirmá el archivo para continuar.'); }
    }

    private static function invalid(string $field, string $message): never
    {
        throw new HttpError(422, 'validation', $message, [$field => $message]);
    }
}
