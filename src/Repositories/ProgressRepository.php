<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class ProgressRepository
{
    public function __construct(private readonly Database $database) {}

    private function installationProgressSql(): string
    {
        return 'SELECT r.installation_id,
            SUM(r.required_quantity) AS required_quantity,
            SUM(COALESCE(m.delivered, 0)) AS delivered_quantity,
            SUM(LEAST(GREATEST(COALESCE(m.delivered, 0), 0), r.required_quantity)) AS covered_quantity,
            SUM(GREATEST(r.required_quantity - COALESCE(m.delivered, 0), 0)) AS remaining_quantity,
            SUM(GREATEST(COALESCE(m.delivered, 0) - r.required_quantity, 0)) AS surplus_quantity,
            SUM(CASE WHEN COALESCE(m.delivered, 0) < 0 THEN 1 ELSE 0 END) AS negative_balances
            FROM requirements r
            LEFT JOIN (SELECT installation_id, commodity_id, SUM(delta_quantity) AS delivered FROM movements GROUP BY installation_id, commodity_id) m
                ON m.installation_id = r.installation_id AND m.commodity_id = r.commodity_id
            WHERE r.active = 1 GROUP BY r.installation_id';
    }

    public function systems(): array
    {
        $sql = 'SELECT s.id, s.name, s.description, COUNT(i.id) AS installation_count,
            COALESCE(SUM(p.required_quantity), 0) AS required_quantity,
            COALESCE(SUM(p.delivered_quantity), 0) AS delivered_quantity,
            COALESCE(SUM(p.covered_quantity), 0) AS covered_quantity,
            COALESCE(SUM(p.remaining_quantity), 0) AS remaining_quantity,
            COALESCE(SUM(p.surplus_quantity), 0) AS surplus_quantity,
            COALESCE(SUM(p.negative_balances), 0) AS negative_balances
            FROM systems s LEFT JOIN installations i ON i.system_id = s.id AND i.archived_at IS NULL
            LEFT JOIN (' . $this->installationProgressSql() . ') p ON p.installation_id = i.id
            WHERE s.archived_at IS NULL GROUP BY s.id, s.name, s.description ORDER BY s.name LIMIT 100';
        return array_map(self::normalize(...), $this->database->connection()->query($sql)->fetchAll());
    }

    public function system(string $id): array
    {
        $db = $this->database->connection();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        try {
            $result = $this->loadSystem($id);
            $db->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $error;
        }
    }

    private function loadSystem(string $id): array
    {
        $query = $this->database->connection()->prepare('SELECT id, name, description, archived_at FROM systems WHERE id = ?');
        $query->execute([$id]);
        $system = $query->fetch();
        if (!$system) {
            throw new HttpError(404, 'not_found', 'No se encontró el sistema.');
        }
        $system['id'] = (string) $system['id'];
        $query = $this->database->connection()->prepare('SELECT i.id, i.name, i.body_name, i.location_label, i.tier, i.type, i.state,
            COALESCE(p.required_quantity, 0) AS required_quantity,
            COALESCE(p.delivered_quantity, 0) AS delivered_quantity,
            COALESCE(p.covered_quantity, 0) AS covered_quantity,
            COALESCE(p.remaining_quantity, 0) AS remaining_quantity,
            COALESCE(p.surplus_quantity, 0) AS surplus_quantity,
            COALESCE(p.negative_balances, 0) AS negative_balances
            FROM installations i LEFT JOIN (' . $this->installationProgressSql() . ') p ON p.installation_id = i.id
            WHERE i.system_id = ? AND i.archived_at IS NULL ORDER BY i.sort_order, i.id LIMIT 200');
        $query->execute([$id]);
        $system['installations'] = array_map(self::normalize(...), $query->fetchAll());
        $query = $this->database->connection()->prepare('SELECT r.installation_id, c.name_es AS commodity_name,
            r.required_quantity, COALESCE(m.delivered, 0) AS delivered_quantity,
            GREATEST(r.required_quantity - COALESCE(m.delivered, 0), 0) AS remaining_quantity,
            GREATEST(COALESCE(m.delivered, 0) - r.required_quantity, 0) AS surplus_quantity
            FROM requirements r JOIN commodities c ON c.id = r.commodity_id JOIN installations i ON i.id = r.installation_id
            LEFT JOIN (SELECT installation_id, commodity_id, SUM(delta_quantity) AS delivered FROM movements GROUP BY installation_id, commodity_id) m
                ON m.installation_id = r.installation_id AND m.commodity_id = r.commodity_id
            WHERE i.system_id = ? AND i.archived_at IS NULL AND r.active = 1 ORDER BY i.sort_order, i.id, c.name_es');
        $query->execute([$id]);
        $system['requirements'] = array_map(static function(array $row): array {
            foreach (['installation_id', 'required_quantity', 'delivered_quantity', 'remaining_quantity', 'surplus_quantity'] as $key) {
                $row[$key] = (string) $row[$key];
            }
            return $row;
        }, $query->fetchAll());
        return $system;
    }

    private static function normalize(array $row): array
    {
        foreach (['id', 'required_quantity', 'delivered_quantity', 'covered_quantity', 'remaining_quantity', 'surplus_quantity'] as $key) {
            $row[$key] = (string) $row[$key];
        }
        $row['negative_balances'] = (int) $row['negative_balances'];
        $row['percent'] = $row['required_quantity'] === '0' ? null : round(100 * (float) $row['covered_quantity'] / (float) $row['required_quantity'], 2);
        return $row;
    }
}
