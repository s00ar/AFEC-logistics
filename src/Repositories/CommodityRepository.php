<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class CommodityRepository
{
    public function __construct(private readonly Database $database) {}

    public function aliasesReady(): bool
    {
        $query = $this->database->connection()->query("SHOW COLUMNS FROM commodities LIKE 'aliases_json'");
        return (bool)$query->fetch();
    }

    public static function normalize(array $row): array
    {
        $data = ['id' => (string)$row['id'], 'code' => $row['code'], 'name_es' => $row['name_es'],
            'name_en' => $row['name_en'], 'active' => (bool)$row['active'],
            'aliases' => json_decode($row['aliases_json'] ?? '[]', true, 32, JSON_THROW_ON_ERROR)];
        $data['version'] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . $row['updated_at']);
        return $data;
    }

    public function rows(bool $lock = false): array
    {
        return array_map(self::normalize(...), $this->database->connection()->query('SELECT * FROM commodities ORDER BY id' . ($lock ? ' FOR UPDATE' : ''))->fetchAll());
    }

    public function find(string $id): array
    {
        $query = $this->database->connection()->prepare('SELECT * FROM commodities WHERE id = ?');
        $query->execute([$id]);
        return self::normalize($query->fetch() ?: throw new HttpError(404, 'not_found', 'No se encontró la mercancía.'));
    }

    public function listing(int $page, bool $inactive): array
    {
        $query = $this->database->connection()->query('SELECT * FROM commodities ' . ($inactive ? '' : 'WHERE active = 1 ')
            . 'ORDER BY name_es, id LIMIT 26 OFFSET ' . (($page - 1) * 25));
        $rows = $query->fetchAll(); $more = count($rows) > 25;
        if ($more) { array_pop($rows); }
        return ['items' => array_map(self::normalize(...), $rows), 'has_more' => $more];
    }
}
