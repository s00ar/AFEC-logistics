<?php
declare(strict_types=1);
namespace Afec\Http;

final class Request
{
    public static function json(): array
    {
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            throw new HttpError(415, 'content_type', 'Enviá el formulario como JSON.');
        }
        $stream = fopen('php://input', 'rb');
        $raw = stream_get_contents($stream, 16385);
        fclose($stream);
        if ($raw === false || strlen($raw) > 16384) {
            throw new HttpError(413, 'too_large', 'La solicitud es demasiado grande.');
        }
        try {
            $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpError(400, 'invalid_json', 'El JSON no es válido.');
        }
        if (!$decoded instanceof \stdClass) {
            throw new HttpError(400, 'invalid_json', 'Se esperaba un objeto JSON.');
        }
        return (array) $decoded;
    }

    public static function method(string $expected): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $expected) {
            header('Allow: ' . $expected);
            throw new HttpError(405, 'method_not_allowed', 'Método no permitido.');
        }
    }

    public static function positiveId(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,18}$/D', $value)) {
            throw new HttpError(422, 'invalid_id', 'Identificador no válido.');
        }
        return $value;
    }
}
