<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Http\HttpError;
use Afec\Validation\PlanningInput as Input;

/** Strict bounded CSV parsing. Source text is data, never executable instructions. */
final class MarketCsv
{
    public const STOCK = ['sistema','estacion','cuerpo','categoria','mercaderia','oferta_t','disponible_para_compra','fuente','fecha_fuente','verificacion'];
    public const SUMMARY = ['estacion','cuerpo','mercaderias_confirmadas','fuente','observacion'];

    public static function error(string $message): never { throw new HttpError(422, 'market_csv', $message); }
    public static function key(string $value): string { return hash('sha256', RecipeCatalogue::normalize($value)); }
    public static function stationKey(string $system, string $station): string { return hash('sha256', self::key($system) . self::key($station)); }

    public static function date(?string $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value || $value > gmdate('Y-m-d', time() + 86400)) { self::error('Fecha inválida o futura: usar AAAA-MM-DD.'); }
        return $value;
    }

    public static function rows(string $raw, array $columns): array
    {
        if ($raw === '' || strlen($raw) > 1048576 || !mb_check_encoding($raw, 'UTF-8') || str_contains($raw, "\0")) { self::error('El CSV debe ser UTF-8, no vacío y de hasta 1 MiB.'); }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        // fgetcsv tolerates unfinished enclosures, so validate CSV quoting first.
        $state='start';
        for($i=0,$length=strlen($raw);$i<$length;$i++) {
            $char=$raw[$i];
            if($state==='quoted') { if($char==='"') { $state='closed'; } continue; }
            if($state==='closed') {
                if($char==='"') { $state='quoted'; continue; }
                if(!in_array($char,[',',"\r","\n"],true)) { self::error('Comillas CSV incorrectas después de un campo.'); }
            }
            if($char===',') { $state='start'; }
            elseif($char==="\r" || $char==="\n") { $state='start'; }
            elseif($char==='"') { if($state!=='start') { self::error('Comillas CSV dentro de un campo sin encerrar.'); } $state='quoted'; }
            else { $state='text'; }
        }
        if($state==='quoted') { self::error('El CSV contiene un campo con comillas sin cerrar.'); }
        $stream = fopen('php://temp', 'r+'); fwrite($stream, $raw); rewind($stream);
        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if ($header !== $columns) { self::error('Encabezados CSV incorrectos. Se esperaba: ' . implode(',', $columns)); }
            $rows = []; $number = 1;
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $number++;
                if ($row === [null]) { continue; }
                if (count($row) !== count($columns) || count($rows) >= 5000) { self::error("Fila $number: columnas incorrectas o más de 5000 registros."); }
                $record = array_combine($columns, $row);
                foreach ($record as $key => $value) { $record[$key] = Input::text([$key => $value], $key, in_array($key, ['fuente','observacion'], true) ? 1000 : ($key==='categoria'?120:255)) ?? ''; }
                $rows[] = $record;
            }
            if ($rows === []) { self::error('El CSV no contiene registros.'); }
            return $rows;
        } finally { fclose($stream); }
    }

    public static function parse(?string $stock, ?string $summary, ?string $context): array
    {
        if ($stock === null && $summary === null) { self::error('Seleccioná al menos un CSV.'); }
        $stocks = $stock === null ? [] : self::rows($stock, self::STOCK);
        $summaries = $summary === null ? [] : self::rows($summary, self::SUMMARY);
        $stations = []; $seen = []; $systems = [];
        foreach ($stocks as &$row) {
            foreach (['sistema','estacion','mercaderia','fuente','verificacion'] as $field) { if ($row[$field] === '' || RecipeCatalogue::normalize($row[$field]) === '') { self::error("Falta $field en el CSV de stock."); } }
            $row['quantity'] = $row['oferta_t'] === '' ? null : Input::quantity(['required_quantity' => $row['oferta_t']]);
            $row['available'] = match (mb_strtoupper($row['disponible_para_compra'])) { 'SI','SÍ' => 1, 'NO' => 0, '', 'DESCONOCIDO' => null, default => self::error('disponible_para_compra debe ser SI, NO o DESCONOCIDO.') };
            $row['observed_on'] = self::date($row['fecha_fuente']);
            $verification = RecipeCatalogue::normalize($row['verificacion']);
            $row['verified'] = match ($verification) { 'confirmadoencaptura','confirmado' => 1, 'desconocido','noconfirmado','sinverificar' => 0, default => self::error('Verificación no reconocida: usar Confirmado, Confirmado en captura, Desconocido o Sin verificar.') };
            if ($row['available'] === 0 && $row['quantity'] !== null && $row['quantity'] !== '0') { self::error('Stock positivo con disponibilidad NO: corregí la contradicción.'); }
            $row['station_key'] = self::stationKey($row['sistema'], $row['estacion']);
            $row['product_key'] = self::key($row['mercaderia']);
            $pair = $row['station_key'] . $row['product_key'];
            if (isset($seen[$pair])) { self::error('Mercancía repetida en la misma estación.'); }
            $seen[$pair] = true;
            if (isset($stations[$row['station_key']]) && $stations[$row['station_key']]['body'] !== $row['cuerpo']) { self::error('Cuerpos contradictorios para una misma estación.'); }
            $stations[$row['station_key']] = ['system' => $row['sistema'], 'name' => $row['estacion'], 'body' => $row['cuerpo'], 'count' => ($stations[$row['station_key']]['count'] ?? 0) + 1];
            $systems[self::key($row['sistema'])] = $row['sistema'];
        } unset($row);
        if ($context !== null && !isset($systems[self::key($context)]) && $stocks !== []) { self::error('El sistema del resumen no aparece en el CSV de stock.'); }
        $context ??= count($systems) === 1 ? reset($systems) : null;
        $summarySeen = [];
        foreach ($summaries as &$row) {
            if ($context === null || $context === '' || $row['estacion'] === '' || $row['fuente'] === '') { self::error('El resumen requiere una estación, fuente y sistema inequívocos.'); }
            $count = Input::quantity(['required_quantity' => $row['mercaderias_confirmadas']]);
            if (strlen($count) > 5 || (int)$count > 50000) { self::error('Recuento de mercancías fuera de rango.'); }
            $row['confirmed_count'] = (int)$count; $row['sistema'] = $context;
            $row['station_key'] = self::stationKey($context, $row['estacion']);
            if (isset($summarySeen[$row['station_key']])) { self::error('Estación repetida en el resumen.'); }
            $summarySeen[$row['station_key']] = true;
            preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $row['fuente'], $dates);
            if (count(array_unique($dates[0])) !== 1) { self::error('La fuente del resumen debe incluir una fecha AAAA-MM-DD inequívoca.'); }
            $row['observed_on'] = self::date($dates[0][0]);
            if ($stocks !== [] && (!isset($stations[$row['station_key']]) || $stations[$row['station_key']]['count'] !== $row['confirmed_count'] || $stations[$row['station_key']]['body'] !== $row['cuerpo'])) { self::error('El resumen no coincide con la estación, cuerpo o recuento del stock adjunto.'); }
        } unset($row);
        return ['stock' => $stocks, 'summary' => $summaries, 'system_context' => $context,
            'hash' => hash('sha256', json_encode([$stock, $summary, $context], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
    }
}
