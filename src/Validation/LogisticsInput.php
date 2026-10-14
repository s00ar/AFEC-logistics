<?php
declare(strict_types=1);
namespace Afec\Validation;

use Afec\Http\HttpError;
use Afec\Http\Request;

final class LogisticsInput
{
    public static function choice(array $data, string $name, array $choices): string
    {
        $value = $data[$name] ?? null;
        if (!is_string($value) || !in_array($value,$choices,true)) { throw new HttpError(422,'validation','Seleccioná un valor válido para ' . $name . '.'); }
        return $value;
    }
    public static function id(array $data, string $name, bool $optional = false): ?string
    {
        $value = $data[$name] ?? null;
        if ($optional && ($value === null || $value === '')) { return null; }
        return Request::positiveId(is_int($value) ? (string)$value : $value);
    }
    public static function amount(array $data): string
    {
        $value = PlanningInput::quantity(['required_quantity' => $data['quantity'] ?? null]);
        if ($value === '0' || self::compare($value,'9223372036854775807') > 0) { throw new HttpError(422,'validation','La cantidad debe ser positiva y no superar 9223372036854775807.'); }
        return $value;
    }
    public static function compare(string $a, string $b): int
    {
        return strlen($a) <=> strlen($b) ?: strcmp($a,$b);
    }
    public static function date(array $data): ?string
    {
        $value = $data['occurred_at'] ?? null;
        if ($value === null || $value === '') { return null; }
        if (!is_string($value)) { throw new HttpError(422,'validation','Fecha no válida.'); }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$value,new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $value) { throw new HttpError(422,'validation','La fecha debe ser UTC con formato YYYY-MM-DDTHH:mm:ssZ.'); }
        return $date->format('Y-m-d H:i:s');
    }
}
