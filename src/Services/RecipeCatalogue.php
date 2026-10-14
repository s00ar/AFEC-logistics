<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Http\HttpError;
use Afec\Validation\PlanningInput as Input;

/** Bundled, attributed data snapshot. No runtime requests to Sheets or third parties. */
final class RecipeCatalogue
{
    public readonly array $data;
    public readonly string $hash;

    public function __construct(string $directory)
    {
        $path = $directory . '/initial-recipes.json';
        if (!is_file($path) || filesize($path) > 1048576) { throw new \RuntimeException('Catalogue snapshot unavailable.'); }
        $raw = file_get_contents($path);
        $this->hash = hash('sha256', $raw);
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (($data['schema_version'] ?? null) !== 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data['retrieved_on'] ?? '')) { throw new \RuntimeException('Invalid catalogue version.'); }
        foreach ($data['sources'] ?? [] as $source) {
            if (!preg_match('/^[a-z0-9_-]+\.(json|csv)$/D', $source['file'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $source['sha256'] ?? '') ||
                !is_file($directory . '/sources/' . $source['file']) || !hash_equals($source['sha256'], hash_file('sha256', $directory . '/sources/' . $source['file']))) {
                throw new \RuntimeException('Catalogue source checksum mismatch.');
            }
        }
        $codes = []; $keys = [];
        if (count($data['commodities'] ?? []) < 1 || count($data['commodities']) > 250 || count($data['recipes'] ?? []) < 1 || count($data['recipes']) > 250) { throw new \RuntimeException('Invalid catalogue size.'); }
        foreach ($data['commodities'] as $commodity) {
            $code = Input::text($commodity, 'code', 120, true);
            if (isset($codes[$code])) { throw new \RuntimeException('Duplicate catalogue code.'); }
            $codes[$code] = true;
            Input::text($commodity, 'name_es', 255, true); Input::text($commodity, 'name_en', 255, true);
            if (!is_array($commodity['aliases'] ?? null) || count($commodity['aliases']) > 30) { throw new \RuntimeException('Invalid commodity aliases.'); }
            foreach ($commodity['aliases'] as $alias) { Input::text(['alias' => $alias], 'alias', 255, true); }
        }
        foreach ($data['recipes'] as $recipe) {
            foreach (['name' => 160, 'type' => 80, 'source_note' => 1000] as $field => $max) { Input::text($recipe, $field, $max, true, $field === 'source_note'); }
            Input::text($recipe, 'tier', 20);
            $url = Input::text($recipe, 'source_url', 1000, true);
            if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($recipe['source_kind'] ?? null, ['afec','community'], true)) { throw new \RuntimeException('Invalid catalogue attribution.'); }
            $key = $recipe['source_key'] ?? '';
            if (!is_string($key) || !preg_match('/^[a-z0-9:]{1,190}$/D', $key) || isset($keys[$key])) { throw new \RuntimeException('Duplicate or invalid source key.'); }
            $keys[$key] = true;
            $materials = $recipe['materials'] ?? null;
            if (!is_array($materials) || !array_is_list($materials) || count($materials) < 1 || count($materials) > 100) { throw new \RuntimeException('Invalid catalogue recipe.'); }
            $seen = [];
            foreach ($materials as $material) {
                $code = $material['code'] ?? '';
                if (!isset($codes[$code]) || isset($seen[$code]) || Input::quantity($material) === '0') { throw new \RuntimeException('Invalid recipe material.'); }
                $seen[$code] = true;
            }
        }
        $this->data = $data;
    }

    public function summary(): array
    {
        return ['snapshot_sha256' => $this->hash, 'retrieved_on' => $this->data['retrieved_on'],
            'recipes' => count($this->data['recipes']), 'commodities' => count($this->data['commodities']),
            'afec' => count(array_filter($this->data['recipes'], static fn(array $r): bool => $r['source_kind'] === 'afec')),
            'community' => count(array_filter($this->data['recipes'], static fn(array $r): bool => $r['source_kind'] === 'community')),
            'unclassified_material_rows' => $this->data['review']['unclassified_material_rows'],
            'types_without_quantities' => $this->data['review']['types_without_quantities'], 'warnings' => $this->data['review']['warnings']];
    }

    public static function normalize(string $value): string
    {
        $value = strtr(mb_strtolower($value), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }
}
