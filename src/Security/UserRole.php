<?php
declare(strict_types=1);
namespace Afec\Security;

/** Two effective roles; historical operator rows remain compatible without a migration. */
final class UserRole
{
    public const ALL = ['admin', 'viewer'];

    public static function canonical(string $stored): ?string
    {
        return match ($stored) {
            'admin' => 'admin',
            'viewer', 'operator' => 'viewer',
            default => null,
        };
    }
}
