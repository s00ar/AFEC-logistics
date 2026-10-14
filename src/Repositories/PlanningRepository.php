<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class PlanningRepository
{
    public function __construct(private readonly Database $database) {}

    private function one(string $sql, array $parameters): ?array
    {
        $query = $this->database->connection()->prepare($sql);
        $query->execute($parameters);
        $row = $query->fetch();
        if (!$row) { return null; }
        foreach (['id', 'system_id', 'installation_id', 'commodity_id', 'version', 'required_quantity'] as $field) {
            if (isset($row[$field])) { $row[$field] = (string)$row[$field]; }
        }
        return $row;
    }

    public function system(string $id, bool $lock = false): array
    {
        return $this->one('SELECT id, name, description, archived_at, version FROM systems WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])
            ?? throw new HttpError(404, 'not_found', 'No se encontró el sistema.');
    }

    public function installation(string $id, bool $lock = false): array
    {
        return $this->one('SELECT id, system_id, name, body_name, location_label, tier, type, state, construction_status, sort_order, archived_at, version FROM installations WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id])
            ?? throw new HttpError(404, 'not_found', 'No se encontró la instalación.');
    }

    /** Always acquire parent before child. Parent relationships are not reassigned by this CRUD. */
    public function lockInstallation(string $id): array
    {
        $child = $this->installation($id);
        $parent = $this->system($child['system_id'], true);
        $child = $this->installation($id, true);
        if ($child['system_id'] !== $parent['id']) { throw new HttpError(409, 'parent_changed', 'Cambió el sistema de esta instalación.'); }
        return [$parent, $child];
    }

    public function requirement(string $installationId, string $commodityId, bool $lock = false): ?array
    {
        return $this->one('SELECT installation_id, commodity_id, required_quantity, active, version FROM requirements WHERE installation_id = ? AND commodity_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$installationId, $commodityId]);
    }

    public function activeCommodity(string $id): void
    {
        $row = $this->one('SELECT id, active FROM commodities WHERE id = ? FOR UPDATE', [$id]);
        if (!$row || !(bool)$row['active']) { throw new HttpError(422, 'commodity_inactive', 'Seleccioná un material activo del catálogo.'); }
    }

    public function insertSystem(array $data): string
    {
        $query = $this->database->connection()->prepare('INSERT INTO systems (name, description) VALUES (?, ?)');
        $query->execute([$data['name'], $data['description']]);
        return $this->database->connection()->lastInsertId();
    }

    public function updateSystem(string $id, array $data): void
    {
        $query = $this->database->connection()->prepare('UPDATE systems SET name = ?, description = ?, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = ?');
        $query->execute([$data['name'], $data['description'], $id]);
    }

    public function insertInstallation(string $systemId, array $data): string
    {
        $query = $this->database->connection()->prepare('INSERT INTO installations (system_id, name, body_name, location_label, tier, type, state, sort_order, construction_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $query->execute([$systemId, $data['name'], $data['body_name'], $data['location_label'], $data['tier'], $data['type'], $data['state'], $data['sort_order'], 'under_construction']);
        return $this->database->connection()->lastInsertId();
    }

    public function updateInstallation(string $id, array $data): void
    {
        $query = $this->database->connection()->prepare('UPDATE installations SET name = ?, body_name = ?, location_label = ?, tier = ?, type = ?, state = ?, sort_order = ?, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = ?');
        $query->execute([$data['name'], $data['body_name'], $data['location_label'], $data['tier'], $data['type'], $data['state'], $data['sort_order'], $id]);
    }

    public function archive(string $type, string $id, bool $restore): void
    {
        $table = match ($type) { 'system' => 'systems', 'installation' => 'installations' };
        $query = $this->database->connection()->prepare('UPDATE ' . $table . ' SET archived_at = ' . ($restore ? 'NULL' : 'UTC_TIMESTAMP(6)') . ', version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = ?');
        $query->execute([$id]);
    }

    public function saveRequirement(string $installationId, string $commodityId, string $quantity, bool $insert): void
    {
        $sql = $insert
            ? 'INSERT INTO requirements (required_quantity, installation_id, commodity_id) VALUES (?, ?, ?)'
            : 'UPDATE requirements SET required_quantity = ?, active = 1, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE installation_id = ? AND commodity_id = ?';
        $query = $this->database->connection()->prepare($sql);
        $query->execute([$quantity, $installationId, $commodityId]);
    }

    public function removeRequirement(string $installationId, string $commodityId): void
    {
        $query = $this->database->connection()->prepare('UPDATE requirements SET active = 0, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE installation_id = ? AND commodity_id = ?');
        $query->execute([$installationId, $commodityId]);
    }

    public function revision(?array $before, array $after, string $actorId, string $reason, string $operationId): void
    {
        $query = $this->database->connection()->prepare('INSERT INTO requirement_revisions (installation_id, commodity_id, revision_number, old_quantity, new_quantity, old_active, new_active, actor_user_id, reason, operation_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $query->execute([$after['installation_id'], $after['commodity_id'], $after['version'], $before['required_quantity'] ?? null, $after['required_quantity'], $before['active'] ?? null, $after['active'], $actorId, $reason, $operationId]);
    }

    public function audit(string $type, string $id, string $action, ?array $before, array $after, string $actorId, string $reason, string $operationId): void
    {
        $query = $this->database->connection()->prepare('INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, changes_json, reason, operation_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $query->execute([$actorId, $action, $type, $id, json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $reason, $operationId]);
    }

    public function insertCommodity(array $data): array
    {
        $query = $this->database->connection()->prepare('INSERT INTO commodities (code, name_es, name_en) VALUES (?, ?, ?)');
        $query->execute([$data['code'], $data['name_es'], $data['name_en']]);
        return ['id' => $this->database->connection()->lastInsertId()] + $data;
    }

    public function history(string $type, string $id, ?string $commodityId, int $page): array
    {
        $offset = ($page - 1) * 25;
        if ($type === 'requirement') {
            $sql = 'SELECT r.id, r.old_quantity, r.new_quantity, r.old_active, r.new_active, r.revision_number, r.reason, r.recorded_at, u.display_name AS author FROM requirement_revisions r JOIN users u ON u.id = r.actor_user_id WHERE r.installation_id = ? AND r.commodity_id = ? ORDER BY r.id DESC LIMIT 26 OFFSET ' . $offset;
            $parameters = [$id, $commodityId];
        } else {
            $sql = 'SELECT a.id, a.action, a.changes_json, a.reason, a.recorded_at, u.display_name AS author FROM audit_log a JOIN users u ON u.id = a.actor_user_id WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.id DESC LIMIT 26 OFFSET ' . $offset;
            $parameters = [$type, $id];
        }
        $query = $this->database->connection()->prepare($sql);
        $query->execute($parameters);
        $rows = $query->fetchAll();
        $more = count($rows) > 25;
        if ($more) { array_pop($rows); }
        foreach ($rows as &$row) {
            $row['id'] = (string)$row['id'];
            if ($type === 'requirement') {
                foreach (['old_quantity', 'new_quantity', 'revision_number'] as $field) {
                    if ($row[$field] !== null) { $row[$field] = (string)$row[$field]; }
                }
            } else {
                $row['changes'] = json_decode($row['changes_json'], true, 32, JSON_THROW_ON_ERROR);
                unset($row['changes_json']);
            }
        }
        return ['items' => $rows, 'page' => $page, 'has_more' => $more];
    }
}
