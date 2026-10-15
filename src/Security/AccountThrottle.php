<?php
declare(strict_types=1);
namespace Afec\Security;

use Afec\Database;
use Afec\Http\HttpError;

/** Scoped counters share the existing attempt store without consuming login's budget. */
final class AccountThrottle
{
    public function __construct(private readonly Database $database) {}

    public function run(string $scope, string $identity, string $address, int $identityLimit, int $networkLimit, callable $work): mixed
    {
        $identity = hash('sha256', $scope . ':identity:' . strtolower($identity), true);
        $network = hash('sha256', $scope . ':network:' . $address, true);
        $locks = ['afec-account-' . substr(bin2hex($identity), 0, 48), 'afec-account-' . substr(bin2hex($network), 0, 48)];
        sort($locks);
        $db = $this->database->connection(); $held = [];
        try {
            foreach ($locks as $lock) {
                $q = $db->prepare('SELECT GET_LOCK(?,2)'); $q->execute([$lock]);
                if ((int)$q->fetchColumn() !== 1) { throw new HttpError(429, 'account_throttled', 'Esperá unos minutos antes de volver a intentar.'); }
                $held[] = $lock;
            }
            foreach ([['identity_hash',$identity,$identityLimit], ['network_hash',$network,$networkLimit]] as [$column,$value,$limit]) {
                $q = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE $column=? AND succeeded=0 AND recorded_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE");
                $q->execute([$value]);
                if ((int)$q->fetchColumn() >= $limit) { throw new HttpError(429, 'account_throttled', 'Esperá unos minutos antes de volver a intentar.'); }
            }
            $q = $db->prepare('INSERT INTO login_attempts (identity_hash,network_hash,succeeded) VALUES (?,?,0)');
            $q->execute([$identity,$network]);
            return $work();
        } finally {
            foreach (array_reverse($held) as $lock) { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
        }
    }
}
