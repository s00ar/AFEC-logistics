<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\UserRepository;
use Afec\Security\Session;

final class AuthService
{
    // Valid cost-12 synthetic hash for constant-cost verification of unknown accounts.
    private const DUMMY_HASH = '$2y$12$bKTmPujMDwq.2ehMXtLdo.b.QrrF7SLuSNmqBVObNj1KRu1Fn9qza';

    public function __construct(private readonly Database $database, private readonly UserRepository $users, private readonly Session $session) {}

    public function current(): ?array
    {
        $id = $this->session->userId();
        if ($id === null) {
            return null;
        }
        $user = $this->users->findById($id);
        if ($user === null || !(bool) $user['active'] || (int) $user['auth_version'] !== $this->session->authVersion() || !in_array($user['role'], ['admin', 'operator', 'viewer'], true)) {
            $this->session->logout();
            return null;
        }
        return UserRepository::publicUser($user);
    }

    public function requireUser(): array
    {
        return $this->current() ?? throw new HttpError(401, 'unauthenticated', 'Iniciá sesión para continuar.');
    }

    public function requireAdmin(): array
    {
        $user = $this->requireUser();
        if ($user['role'] !== 'admin') {
            throw new HttpError(403, 'forbidden', 'No tenés permiso para administrar usuarios.');
        }
        return $user;
    }

    public function requirePlanner(): array
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], ['admin', 'operator'], true)) {
            throw new HttpError(403, 'forbidden', 'No tenés permiso para modificar la planificación.');
        }
        return $user;
    }

    public function login(array $input, string $address): array
    {
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;
        $rememberMe = array_key_exists('remember_me', $input) ? $input['remember_me'] : false;
        if (!is_bool($rememberMe)) {
            throw new HttpError(422, 'validation', 'La opción de mantener la sesión debe ser verdadera o falsa.');
        }
        if (!is_string($username) || !is_string($password) || !preg_match('/^[a-zA-Z0-9_.@+-]{3,120}$/D', $username) || strlen($password) < 1 || strlen($password) > 1024) {
            throw new HttpError(422, 'validation', 'Ingresá un usuario y una contraseña válidos.');
        }
        $identity = hash('sha256', strtolower($username), true);
        $network = hash('sha256', $address, true);
        $db = $this->database->connection();
        // Serialize throttle checks for both identity and REMOTE_ADDR, including parallel attempts.
        // Forwarded IP headers are deliberately not trusted.
        $locks = ['afec-login-' . substr(bin2hex($identity), 0, 48), 'afec-login-' . substr(bin2hex($network), 0, 48)];
        $locks = array_unique($locks);
        sort($locks);
        $held = [];
        try {
            foreach ($locks as $lock) {
                $query = $db->prepare('SELECT GET_LOCK(?, 2)');
                $query->execute([$lock]);
                if ((int) $query->fetchColumn() !== 1) {
                    throw new HttpError(429, 'login_throttled', 'Esperá unos minutos antes de volver a intentar.');
                }
                $held[] = $lock;
            }
            $query = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE identity_hash = ? AND succeeded = 0 AND recorded_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
            $query->execute([$identity]);
            $identityFailures = (int) $query->fetchColumn();
            $query = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE network_hash = ? AND succeeded = 0 AND recorded_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE');
            $query->execute([$network]);
            if ($identityFailures >= 8 || (int) $query->fetchColumn() >= 30) {
                throw new HttpError(429, 'login_throttled', 'Esperá unos minutos antes de volver a intentar.');
            }
            $user = $this->users->findByUsername($username);
            $verified = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
            $accepted = $verified && $user !== null && (bool) $user['active'];
            $query = $db->prepare('INSERT INTO login_attempts (identity_hash, network_hash, succeeded) VALUES (?, ?, ?)');
            $query->execute([$identity, $network, (int) $accepted]);
            if (!$accepted) {
                throw new HttpError(401, 'invalid_credentials', 'Usuario o contraseña incorrectos.');
            }
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $query = $db->prepare('UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP(6) WHERE id = ? AND password_hash = ?');
                $query->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $user['password_hash']]);
            }
            $this->session->login($user, $rememberMe);
            return UserRepository::publicUser($user);
        } finally {
            foreach (array_reverse($held) as $lock) {
                $query = $db->prepare('SELECT RELEASE_LOCK(?)');
                $query->execute([$lock]);
            }
        }
    }
}
