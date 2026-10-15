"""Two roles: all domain operations for Commander, accounts remain administrator-only."""
import json
import uuid


def run_commander_planning_checks(anonymous, viewer, operator, check, command):
    def write(client, route, method, body, key=None):
        return client.call(route, method, {'reason':'Role policy test'} | body, client.session()['csrf_token'], key=key or str(uuid.uuid4()))
    def expect(response, status, label):
        check(response[0] == status, label + ' (status ' + str(response[0]) + ')')
        return response[1].get('data')
    before = json.loads(command('tests/recipe-inspect.php', 'stats'))
    check(json.loads(command('tests/roles-inspect.php')) == {'canonical': True, 'legacy_preserved': True, 'forged_admin': 403, 'stale_auth': 401}, 'Canonical roles and transaction permission revalidation')
    for client in [viewer, operator]:
        check(client.session()['user']['role'] == 'viewer', 'Both stored roles expose Commander')
        for route in ['admin/users', 'admin/user&id=1', 'admin/roles', 'admin/password-help']:
            expect(client.call(route), 403, 'Commander cannot read account administration: ' + route)
        for route, method in [('admin/users','POST'), ('admin/user&id=1','PATCH'), ('admin/user/password&id=1','POST'), ('admin/user/restore&id=1','POST'), ('admin/password-help/close&id=1','POST')]:
            expect(write(client, route, method, {}), 403, 'Commander cannot mutate account administration: ' + route)
        for page in ['users', 'password-requests']:
            check(client.page('/?page=' + page)[0] == 403, 'Account administration page is restricted')
        for page in ['recipes', 'commodities']:
            check(client.page('/?page=' + page)[0] == 200, 'Commander can manage ' + page)
    expect(viewer.call('systems', 'POST', {'name':'No CSRF'}, key=str(uuid.uuid4())), 403, 'Commander writes require CSRF')
    expect(write(viewer,'systems','POST',{'name':'No key'},'short'), 422, 'Commander writes require idempotency')
    body = {'name':'Commander-managed system'}; key = str(uuid.uuid4())
    system = expect(write(viewer,'systems','POST',body,key), 201, 'Commander creates system')
    check(write(viewer,'systems','POST',body,key)[1]['replayed'], 'System creation retry is idempotent')
    sid = system['id']
    system = expect(write(viewer,'system&id='+sid,'PATCH',{'name':'Commander edited system','version':system['version']}), 200, 'Commander edits system')
    expect(viewer.call('planning/history&type=system&id='+sid), 200, 'Commander reads system audit')
    recipe_body = {'name':'Commander recipe','type':'Orbital','source_note':'Synthetic test','materials':[{'commodity_id':'1','required_quantity':'12'}]}
    recipe = expect(write(viewer,'facility/recipes','POST',recipe_body), 201, 'Commander creates recipe')
    rid = recipe['id']
    recipe = expect(write(viewer,'facility/recipe&id='+rid,'PATCH',recipe_body|{'name':'Commander edited recipe','version':recipe['version']}), 200, 'Commander edits recipe')
    expect(viewer.call('facility/recipe/history&id='+rid), 200, 'Commander reads recipe audit')
    route = 'installations&system_id='+sid
    body = {'name':'Commander facility','facility_recipe_id':rid,'facility_recipe_version':recipe['version']}
    inst = expect(write(viewer,route,'POST',body), 201, 'Commander creates facility from recipe'); iid = inst['id']
    check(viewer.call('system&id='+sid)[1]['data']['requirements'][0]['required_quantity'] == '12', 'Recipe objectives copied')
    inst = expect(write(viewer,'installation&id='+iid,'PATCH',{'name':'Edited facility','version':inst['version']}), 200, 'Commander edits facility')
    objective = 'requirement&installation_id='+iid+'&commodity_id=1'
    req = expect(write(viewer,objective,'PUT',{'required_quantity':'100','version':'1'}), 200, 'Commander edits objective')
    req = expect(write(viewer,objective,'DELETE',{'version':req['version'],'reason':'Retire test','confirmed':True}), 200, 'Commander retires objective')
    expect(write(viewer,objective,'PUT',{'required_quantity':'100','version':req['version']}), 200, 'Commander restores objective')
    inst = expect(write(viewer,'installation/status&id='+iid,'PATCH',{'construction_status':'completed','version':inst['version'],'reason':'Close test','confirmed':True}), 200, 'Commander completes construction')
    inst = expect(write(viewer,'installation/status&id='+iid,'PATCH',{'construction_status':'under_construction','version':inst['version'],'reason':'Reopen test'}), 200, 'Commander reopens construction')
    inst = expect(write(viewer,'installation&id='+iid,'DELETE',{'version':inst['version'],'reason':'Archive test','confirmed':True}), 200, 'Commander archives facility')
    expect(write(viewer,'installation/restore&id='+iid,'POST',{'version':inst['version'],'reason':'Restore test'}), 200, 'Commander restores facility')
    recipe = expect(write(viewer,'facility/recipe&id='+rid,'DELETE',{'version':recipe['version'],'reason':'Archive template','confirmed':True}), 200, 'Commander archives recipe')
    expect(viewer.call('facility/recipes&include_archived=1'), 200, 'Commander lists archived recipes')
    expect(viewer.call('facility/recipe&id='+rid), 200, 'Commander reviews archived recipe')
    expect(write(viewer,route,'POST',body), 409, 'Archived recipe cannot create facility')
    expect(write(viewer,'facility/recipe/restore&id='+rid,'POST',{'version':recipe['version'],'reason':'Restore template'}), 200, 'Commander restores recipe')
    snapshot = json.loads(command('tests/recipe-inspect.php','stats'))
    command('tests/recipe-inspect.php','fail-audit')
    try:
        expect(write(viewer,route,'POST',{'name':'Must roll back'}), 503, 'Audit failure rolls back Commander write')
    finally:
        command('tests/recipe-inspect.php','clear-failure')
    check(json.loads(command('tests/recipe-inspect.php','stats')) == snapshot, 'Failed write leaves no partial rows or receipt')
    html = viewer.page('/')[1]
    check('data-action="system-edit"' in html and 'data-action="system-archive"' in html and '?page=commodities' in html and '?page=users' not in html, 'Commander sees operational controls and no account administration')
    html = viewer.page('/?page=system&id='+sid)[1]
    check('data-action="installation-edit"' in html and 'data-action="requirement-create"' in html, 'Commander sees facility and objective controls')
    check('data-action="system-create"' not in anonymous.page('/')[1], 'Public receives no writing controls')
    system = expect(write(viewer,'system&id='+sid,'DELETE',{'version':system['version'],'reason':'Archive test','confirmed':True}), 200, 'Commander archives system')
    expect(viewer.call('systems&include_archived=1'), 200, 'Commander lists archived systems')
    expect(write(viewer,route,'POST',{'name':'Archived parent'}), 409, 'Archived parent rejects construction')
    expect(write(viewer,'system/restore&id='+sid,'POST',{'version':system['version'],'reason':'Restore test'}), 200, 'Commander restores system')
    check(json.loads(command('tests/recipe-inspect.php','stats'))['movements'] == before['movements'], 'Planning never rewrites deliveries')
