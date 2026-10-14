"""Build a reviewable catalogue from bounded source snapshots, without network/DB writes.

Python is a development/data-preparation tool only, never a production dependency.
"""
import argparse
import csv
import hashlib
import json
from pathlib import Path
import re
import unicodedata


def normalize(value):
    return ''.join(c for c in unicodedata.normalize('NFKD', str(value)).lower() if c.isalnum())


def integer(value):
    if isinstance(value, bool):
        raise ValueError('Boolean cannot be a quantity')
    if isinstance(value, str):
        if not re.fullmatch(r'[0-9]+(?:,[0-9]{3})*', value):
            raise ValueError('Invalid decimal quantity: ' + value)
        value = int(value.replace(',', ''))
    if not isinstance(value, int) or not 0 <= value <= 18446744073709551615:
        raise ValueError('Quantity outside unsigned BIGINT')
    return str(value)


def build(source_dir, retrieved_on):
    def values(name):
        return json.loads((source_dir / (name + '.json')).read_text(encoding='utf-8'))['values']
    community = values('community-commodities')
    afec_meta = values('afec-facilities')[1:]
    bio = values('afec-bio-requirements')[1:25]
    afec_url = 'https://docs.google.com/spreadsheets/d/14RxFS9FSc12ybmMuO95vFh8S9An0D4tdgDdEqT6ydEg/edit'
    community_url = 'https://docs.google.com/spreadsheets/d/1jsZOzNJSnPIWlU88puOc9gQXvsw-MGp8meoQ6k-Yj-Y/edit'
    commodities = {}
    aliases = {}
    for row in community[8:]:
        if len(row) < 5 or not row[2] or not row[4]:
            continue
        code, name = row[2], row[4]
        if code in commodities:
            raise ValueError('Duplicate internal commodity code')
        commodities[code] = {'code': code, 'name_en': name, 'name_es': name, 'aliases': [name, code]}
        for alias in [code, name]:
            normalized = normalize(alias)
            if normalized in aliases and aliases[normalized] != code:
                raise ValueError('Ambiguous source commodity name')
            aliases[normalized] = code

    # Spanish names read from AFEC, not used to infer quantities or facility types.
    afec_spanish = {'Aluminio': 'aluminium', 'Autofabricantes': 'autofabricators', 'Compuestos cerámicos': 'ceramiccomposites',
        'Compuestos CMM': 'cmmcomposite', 'Compuestos informáticos': 'computercomponents', 'Cobre': 'copper',
        'Cartuchos de comida': 'foodcartridges', 'Frutas y verduras': 'fruitandvegetables', 'Membrana aislante': 'insulatingmembrane',
        'Oxígeno líquido': 'liquidoxygen', 'Equipo de diagnóstico médico': 'medicaldiagnosticequipment',
        'Armas no letales': 'nonlethalweapons', 'Polímeros': 'polymers', 'Generadores de energía': 'powergenerators',
        'Semiconductores': 'semiconductors', 'Acero': 'steel', 'Superconductores': 'superconductors', 'Titanio': 'titanium',
        'Agua': 'water', 'Purificadores de agua': 'waterpurifiers'}
    for name, code in afec_spanish.items():
        aliases[normalize(name)] = code
        commodities[code]['name_es'] = name
        commodities[code]['aliases'].append(name)
    for source in [bio, values('afec-unclassified')[1:]]:
        for row in source:
            if len(row) < 3 or not row[0] or normalize(row[0]) not in aliases or row[2] in ['', None]:
                continue
            code = aliases[normalize(row[0])]
            if row[1]:
                commodities[code]['name_es'] = row[1]
                commodities[code]['aliases'].append(row[1])
                aliases[normalize(row[1])] = code

    tiers = {normalize(row[0]): row[2] for row in afec_meta if len(row) >= 3 and row[0]}
    afec_type_aliases = {
        'Orbital - Starport - Coriolis': 'Coriolis Starport', 'Orbital - Starport - Asteroid Base': 'Asteroid Base',
        'Orbital - Starport - Orbis': 'Orbis Starport', 'Orbital - Starport - Ocellus': 'Ocellus Starport',
        'Orbital - Outpost - Commercial': 'Commercial Outpost', 'Orbital - Outpost - Civilian': 'Civilian Outpost',
        'Orbital - Outpost - Industrial': 'Industrial Outpost', 'Orbital - Outpost - Scientific': 'Scientific Outpost',
        'Orbital - Installation - Satellite': 'Satellite Installation', 'Orbital - Installation - Comm Station': 'Comms Installation',
        'Orbital - Installation - Research Station': 'Research Station', 'Surface - Planetary Port - Port': 'Planetary Port T3',
    }
    recipes = []
    for col, structure in enumerate(community[0]):
        if col < 15 or not structure:
            continue
        materials = []
        for row_number, row in enumerate(community[8:], 9):
            if len(row) <= col or len(row) < 5 or row[2] not in commodities or row[col] in ['', None, 0]:
                continue
            quantity = integer(row[col])
            if quantity != '0':
                materials.append({'code': row[2], 'required_quantity': quantity, 'source_row': row_number})
        if not materials or sum(int(row['required_quantity']) for row in materials) != int(integer(community[6][col])):
            raise ValueError('Incomplete recipe or inconsistent community total: ' + structure)
        base_type = structure.removesuffix(' (Primary)')
        tier = tiers.get(normalize(afec_type_aliases.get(base_type, base_type)))
        explicit_tier = re.search(r'\bT([123])\b', structure)
        if explicit_tier:
            tier = 'T' + explicit_tier.group(1)
        if 'Surface - Hub - ' in base_type:
            tier = tiers.get(normalize(base_type.split(' - ')[-1] + ' Hub'))
        recipes.append({'source_key': 'daftmav:' + normalize(structure), 'source_kind': 'community',
            'name': structure + ' · comunidad', 'type': base_type, 'tier': tier,
            'source_note': 'DaftMav v3.4; snapshot ' + retrieved_on + '. Referencia comunitaria ' + ('de puerto primario' if '(Primary)' in structure else 'de construcción posterior') + '; ajustar con los requeridos del proyecto en el juego.',
            'source_url': community_url, 'source_locator': 'Commodities, columna ' + str(col + 1), 'materials': materials})

    research_path = source_dir / 'afec-research.csv'
    with research_path.open(encoding='utf-8-sig', newline='') as stream:
        rows = list(csv.DictReader(stream))
    csv_row_count = len(rows)
    identified = {}; unresolved = []
    for row_number, row in enumerate(rows, 2):
        if row['estado_dato'] == 'ejemplo_AFEC':
            key = (row['estructura'], row['ejemplo_origen'])
            identified.setdefault(key, []).append(row | {'source_row': row_number})
        else:
            unresolved.append(row | {'source_row': row_number})
    for (structure, project), rows in identified.items():
        materials = []; seen = set()
        for row in rows:
            code = aliases.get(normalize(row['mercaderia']))
            if not code or code in seen:
                raise ValueError('Unmapped/duplicate AFEC commodity: ' + row['mercaderia'])
            seen.add(code)
            materials.append({'code': code, 'required_quantity': integer(row['cantidad_requerida']), 'source_row': row['source_row']})
        if structure == 'Research Bio Settlement — Large':
            live = {aliases[normalize(row[0])]: integer(row[2]) for row in bio}
            if live != {row['code']: row['required_quantity'] for row in materials}:
                raise ValueError('AFEC research CSV differs from live Bio snapshot; review before loading')
        recipes.append({'source_key': 'afec:' + normalize(structure) + ':' + normalize(project), 'source_kind': 'afec',
            'name': structure + ' · AFEC (' + project + ')', 'type': structure, 'tier': tiers.get(normalize(structure)),
            'source_note': 'Observación AFEC del proyecto ' + project + '; CSV de investigación recibido ' + retrieved_on + '. Cantidades de una instalación específica, no receta universal.' + (' Coincide con la lectura de Sheets.' if structure == 'Research Bio Settlement — Large' else ''),
            'source_url': afec_url, 'source_locator': 'afec-research.csv / ' + row['fuente'], 'materials': materials})

    # Keep the literal type shown by AFEC; do not infer whether this project was primary.
    dodec_materials = []
    for row_number, row in enumerate(values('afec-dodec')[1:], 4):
        code = aliases.get(normalize(row[0]))
        if not code:
            raise ValueError('Unknown AFEC Dodec material: ' + row[0])
        dodec_materials.append({'code': code, 'required_quantity': integer(row[2]), 'source_row': row_number})
    recipes.append({'source_key': 'afec:dodec:col359sectorviac16', 'source_kind': 'afec',
        'name': 'Dodec · AFEC (Col 359 Sector VI-A c1-6)', 'type': 'Dodec', 'tier': None,
        'source_note': 'Observación AFEC; tipo literal Dodec leído en E1. Proyecto Col 359 Sector VI-A c1-6, snapshot ' + retrieved_on + '. Variante primaria/posterior y tier sin confirmar; no receta universal.',
        'source_url': afec_url, 'source_locator': "'Col 359 Sector VI-A c1-6'!A3:C27, tipo E1", 'materials': dodec_materials})

    for commodity in commodities.values():
        commodity['aliases'] = sorted(set(commodity['aliases']))
    for recipe in recipes:
        assert len(recipe['name']) <= 160 and len(recipe['materials']) <= 100
        assert len({row['code'] for row in recipe['materials']}) == len(recipe['materials'])
    assert len({r['source_key'] for r in recipes}) == len(recipes)
    sources = [{'file': p.name, 'sha256': hashlib.sha256(p.read_bytes()).hexdigest()} for p in sorted(source_dir.iterdir()) if p.suffix in ['.json', '.csv']]
    data = {'schema_version': 1, 'retrieved_on': retrieved_on, 'sources': sources,
        'commodities': sorted(commodities.values(), key=lambda c: c['code']), 'recipes': recipes,
        'review': {'csv_rows': csv_row_count, 'unclassified_material_rows': sum(r['estado_dato'] == 'ejemplo_AFEC_sin_tipo' for r in unresolved),
            'types_without_quantities': sum(r['estado_dato'] == 'pendiente_verificacion' for r in unresolved),
            'warnings': ['AFEC Bio: sumatoria individual 8524; celda TOTALES 6375. Se usan las filas individuales.',
                'No se asignan tipos a las estructuras sin identificar del CSV ni a DAVIDENKO EXTRACTION EXCHANGE.',
                'Metadata Tier de Commodities contiene #REF; se ignora. Tiers solo de nombres explícitos o catálogo AFEC; los demás quedan sin confirmar.',
                'Observaciones AFEC y referencias comunitarias son variantes separadas, sin sobrescribir cantidades entre fuentes.'],
            'unresolved_rows': unresolved}}
    return data


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--sources', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--retrieved-on', required=True)
    args = parser.parse_args()
    data = build(args.sources, args.retrieved_on)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
    print(json.dumps({'recipes': len(data['recipes']), 'community': sum(r['source_kind'] == 'community' for r in data['recipes']), 'afec': sum(r['source_kind'] == 'afec' for r in data['recipes']), 'commodities': len(data['commodities']), 'material_lines': sum(len(r['materials']) for r in data['recipes'])}))
