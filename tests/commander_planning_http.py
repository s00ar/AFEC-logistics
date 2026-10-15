"""Creation permission is independent of editing and catalogue administration."""
import json
import uuid

def run_commander_planning_checks(anonymous,viewer,operator,check,command):
    def write(client,route,method,body,key=None):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    before=json.loads(command('tests/recipe-inspect.php','stats'))
    expect(viewer.call('systems','POST',{'name':'No CSRF'},key=str(uuid.uuid4())),403,'Commander creation requires CSRF')
    expect(write(viewer,'systems','POST',{'name':'No key'},'short'),422,'Commander creation requires idempotency')
    key=str(uuid.uuid4());body={'name':'Commander-created system','reason':'Commander creation test'}
    system=expect(write(viewer,'systems','POST',body,key),201,'Commander creates a system')
    check(write(viewer,'systems','POST',body,key)[1]['replayed'],'Commander system retry is idempotent')
    recipe=expect(write(operator,'facility/recipes','POST',{'name':'Commander selection template','type':'Orbital','source_note':'Synthetic local test template','materials':[{'commodity_id':'1','required_quantity':'12'}],'reason':'Selection test'}),201,'Operator prepares a recipe')
    rid=recipe['id'];sid=system['id']
    expect(viewer.call('facility/recipe&id='+rid),200,'Commander can preview selected recipe')
    expect(viewer.call('facility/recipes&include_archived=1'),403,'Commander cannot list archived recipes')
    expect(viewer.call('facility/recipe/history&id='+rid),403,'Commander does not gain recipe administration history')
    expect(write(viewer,'facility/recipes','POST',{'name':'Forbidden'}),403,'Commander cannot create recipes')
    body={'name':'Commander orbital base','facility_recipe_id':rid,'facility_recipe_version':recipe['version']}
    key=str(uuid.uuid4());route='installations&system_id='+sid
    inst=expect(write(viewer,route,'POST',body,key),201,'Commander creates construction inside system using recipe')
    check(write(viewer,route,'POST',body,key)[1]['replayed'],'Commander construction retry is idempotent')
    project=viewer.call('system&id='+sid)[1]['data']
    check(len(project['installations'])==1 and project['requirements'][0]['required_quantity']=='12','Commander construction copies recipe objectives exactly once')
    expect(write(viewer,'system&id='+sid,'PATCH',{'name':'Forbidden','version':system['version'],'reason':'No'}),403,'Creation does not grant system edit')
    expect(write(viewer,'installation&id='+inst['id'],'DELETE',{'version':inst['version'],'reason':'No','confirmed':True}),403,'Creation does not grant installation deletion')
    expect(write(viewer,'requirement&installation_id='+inst['id']+'&commodity_id=1','PUT',{'version':'1','required_quantity':'100','reason':'No'}),403,'Creation does not grant objective edits')
    expect(write(operator,'facility/recipe&id='+rid,'DELETE',{'version':recipe['version'],'reason':'Retire test template','confirmed':True}),200,'Operator archives test recipe')
    expect(viewer.call('facility/recipe&id='+rid),404,'Commander cannot preview archived template directly')
    expect(write(viewer,route,'POST',body),409,'Commander cannot use a stale or archived recipe')
    plain=expect(write(viewer,route,'POST',{'name':'Manual commander construction'}),201,'Commander can create without template')
    snapshot=json.loads(command('tests/recipe-inspect.php','stats'))
    command('tests/recipe-inspect.php','fail-audit')
    try: expect(write(viewer,route,'POST',{'name':'Must roll back'}),503,'Commander creation fails atomically when audit fails')
    finally: command('tests/recipe-inspect.php','clear-failure')
    check(json.loads(command('tests/recipe-inspect.php','stats'))==snapshot,'Failed commander creation leaves no installation or receipt')
    html=viewer.page('/')[1]
    check('data-action="system-create"' in html and 'data-action="installation-create"' in html,'Commander dashboard exposes create shortcuts')
    check('data-action="system-edit"' not in html and 'data-action="system-archive"' not in html,'Commander dashboard retains edit restrictions')
    html=viewer.page('/?page=system&id='+sid)[1]
    check('data-action="installation-create"' in html and 'data-action="installation-edit"' not in html,'Commander sees construction creation inside a system')
    check('data-action="system-create"' not in anonymous.page('/')[1],'Public receives no creation controls')
    expect(write(operator,'system&id='+sid,'DELETE',{'version':system['version'],'reason':'Archive test project','confirmed':True}),200,'Operator can still archive commander-created project')
    expect(write(viewer,route,'POST',{'name':'Forbidden archived parent'}),409,'Commander cannot build inside an archived system')
    check(json.loads(command('tests/recipe-inspect.php','stats'))['movements']==before['movements'],'Commander planning never modifies existing deliveries')
