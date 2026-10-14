<?php
declare(strict_types=1);
namespace Afec\Http;

final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}
