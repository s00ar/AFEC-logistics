"""Source attribution, atomic seed, cross-actor concurrency and preservation checks."""
import json
import os
import subprocess
import time
import uuid


def run_catalogue_checks(anonymous, admin, viewer, operator, Client, check, command, env, php, root):
    def write(client, body, key=None):
        return client.call('facility/catalogue/import', 'POST', body, client.session()['csrf_token'], key=key or str(uuid.uuid4()))
    def expect(response, status, label):
        check(response[0] == status, label + ' (status ' + str(response[0]) + ')')
        return response[1].get('data')
    def stats(): return json.loads(command('tests/catalogue-inspect.php', 'stats'))
    expect(anonymous.call('facility/catalogue'), 401, 'Public cannot inspect administrative seed')
    expect(viewer.call('facility/catalogue'), 403, 'Commander cannot seed catalogue')
    expect(operator.call('facility/catalogue'), 403, 'Only administrator can seed global commodities')
    before = stats()
    preview = expect(admin.call('facility/catalogue'), 200, 'Administrator reviews bundled sources before seed')
    check((preview['recipes'], preview['community'], preview['afec'], preview['commodities']) == (71, 66, 5, 55), 'Catalogue includes both approved sources')
    check(preview['unclassified_material_rows'] == 33 and preview['types_without_quantities'] == 51, 'Partial research is documented without inventing quantities')
    check(stats() == before, 'Preview is read-only')
    body = {'snapshot_sha256': preview['snapshot_sha256'], 'confirmed': True}
    expect(write(operator, body), 403, 'Operator cannot trigger seed writes')
    expect(write(admin, body | {'confirmed': False}), 422, 'Seed requires explicit review confirmation')
    expect(write(admin, body | {'snapshot_sha256': '0' * 64}), 409, 'Changed snapshot requires new review')
    expect(write(admin, body | {'path': 'untrusted.json'}), 422, 'Seed cannot read arbitrary user paths')
    expect(admin.call('facility/catalogue/import', 'POST', body, 'bad', key=str(uuid.uuid4())), 403, 'Seed requires CSRF')
    command('tests/catalogue-inspect.php', 'ambiguous-add')
    try: expect(write(admin, body), 409, 'Ambiguous commodity aliases stop complete seed')
    finally: command('tests/catalogue-inspect.php', 'ambiguous-clear')
    check(stats() == before, 'Ambiguous mapping leaves no partial seed data')
    command('tests/catalogue-inspect.php', 'inactive-water')
    try: expect(write(admin, body), 409, 'Inactive commodity is never silently reactivated')
    finally: command('tests/catalogue-inspect.php', 'active-water')
    check(stats() == before, 'Inactive commodity failure leaves no partial seed data')
    command('tests/recipe-inspect.php', 'fail-audit')
    try: expect(write(admin, body), 503, 'Seed cannot partially commit when audit fails')
    finally: command('tests/recipe-inspect.php', 'clear-failure')
    check(stats() == before, 'Seed failure rolls back commodities, recipes, materials and receipts')
    created = admin.call('admin/users', 'POST', {'username':'seed_admin','display_name':'Synthetic catalogue admin','role':'admin','password':env['AFEC_TEST_PASSWORD'],'reason':'Independent concurrency test'}, admin.session()['csrf_token'], key=str(uuid.uuid4()))
    second_id = expect(created, 201, 'Second administrator fixture created')['id']
    second = Client(); expect(second.login('seed_admin'), 200, 'Second seed actor authenticated')
    directory = root / '.local/test-artifacts' / ('catalogue-' + uuid.uuid4().hex)
    directory.mkdir(); barrier = directory / 'start'; workers = []
    for actor_id in [admin.session()['user']['id'], second_id]:
        job = directory / (actor_id + '.json')
        job.write_text(json.dumps({'action':'catalogue_import','actor_id':actor_id,'key':str(uuid.uuid4()),'barrier':str(barrier)}), encoding='utf-8')
        workers.append(subprocess.Popen([php, 'tests/planning-worker.php', str(job)], cwd=root, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding='utf-8', creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0))
    deadline = time.monotonic() + 8
    while len(list(directory.glob('*.ready'))) < 2:
        for process in workers:
            if process.poll() is not None:
                raise AssertionError(process.communicate()[1])
        if time.monotonic() > deadline: raise AssertionError('Catalogue workers did not reach barrier')
        time.sleep(.01)
    barrier.write_text('start')
    results = []
    for process in workers:
        output, error = process.communicate(timeout=25)
        if process.returncode: raise AssertionError(error)
        results.append(json.loads(output))
    check(all(r['status'] == 200 for r in results) and sorted(r['data']['created_recipes'] for r in results) == [0, 71], 'Different actors serialize catalogue load without duplicate sources')
    seeded = stats()
    check(seeded['facility_recipes'] == before['facility_recipes'] + 71 and seeded['facility_recipe_materials'] == before['facility_recipe_materials'] + 1418, 'Complete source recipes and all material rows inserted')
    check(seeded['original_commodity_hash'] == before['original_commodity_hash'], 'Seed reuses existing commodity IDs and preserves labels')
    check(seeded['movements_hash'] == before['movements_hash'] and seeded['requirements_hash'] == before['requirements_hash'], 'Seed never changes installations, objectives or ledger')
    loaded = []; page = 1
    while True:
        response = operator.call('facility/recipes&list_page=' + str(page))[1]
        loaded.extend(response['data'])
        if not response['pagination']['has_more']: break
        page += 1
    imported = [r for r in loaded if r['source_key']]
    check(len(imported) == 71 and sum(r['source_kind'] == 'afec' for r in imported) == 5, 'Both origins visible through paginated operator API')
    primary = next(r for r in imported if r['source_key'] == 'daftmav:orbitalstarportcoriolisprimary')
    normal = next(r for r in imported if r['source_key'] == 'daftmav:orbitalstarportcoriolis')
    def recipe(identity): return operator.call('facility/recipe&id=' + identity)[1]['data']
    check(sum(int(m['required_quantity']) for m in recipe(primary['id'])['materials']) == 70533 and sum(int(m['required_quantity']) for m in recipe(normal['id'])['materials']) == 53723, 'Primary and subsequent construction recipes remain distinct')
    bio_afec = next(r for r in imported if r['source_kind'] == 'afec' and r['type'] == 'Research Bio Settlement — Large')
    bio_community = next(r for r in imported if r['type'] == 'Surface - Settlement - Research Bio T2 L')
    check(sum(int(m['required_quantity']) for m in recipe(bio_afec['id'])['materials']) == 8524 and sum(int(m['required_quantity']) for m in recipe(bio_community['id'])['materials']) == 8523, 'AFEC observation and community estimate are not merged')
    chosen = recipe(normal['id'])
    patch = {field:chosen[field] for field in ['name','type','tier','source_note','source_url','version']}
    patch['materials'] = [{'commodity_id':m['commodity_id'],'required_quantity':m['required_quantity']} for m in chosen['materials']]
    patch['materials'][0]['required_quantity'] = '12345'; patch['reason'] = 'Local override must survive future seeds'
    saved = expect(operator.call('facility/recipe&id=' + chosen['id'], 'PATCH', patch, operator.session()['csrf_token'], key=str(uuid.uuid4())), 200, 'Operator adjusts imported recipe')
    patch['version'] = saved['version']
    expect(operator.call('facility/recipe&id=' + chosen['id'], 'PATCH', patch | {'source_kind':'manual'}, operator.session()['csrf_token'], key=str(uuid.uuid4())), 422, 'Source identity cannot be forged through recipe editor')
    key = str(uuid.uuid4())
    result = expect(write(admin, body, key), 200, 'Reapplying catalogue preserves loaded recipes')
    check(result['created_recipes'] == 0 and result['created_commodities'] == 0 and result['preserved_recipes'] == 71, 'New seed request skips all existing source identities')
    current = recipe(chosen['id'])
    check(current == saved, 'Seed does not overwrite quantity overrides, source notes or versions')
    replay = write(admin, body, key)
    check(replay[0] == 200 and replay[1]['replayed'], 'Seed HTTP retry confirms original receipt')
    check(admin.call('operation&scope=planning.catalogue.import&key=' + key)[1]['data']['data']['created_recipes'] == 0, 'Actor can recover catalogue seed receipt')
    history = operator.call('facility/recipe/history&id=' + chosen['id'])[1]['data']
    check(history[-1]['changes']['after']['source_snapshot_sha256'] == preview['snapshot_sha256'], 'Source snapshot preserved in audit')
    check('Referencia comunitaria' in operator.page('/?page=recipes')[1] and 'Catálogo AFEC + comunidad' in admin.page('/?page=recipes')[1], 'Catalogue HTML identifies attribution and administrator controls')
