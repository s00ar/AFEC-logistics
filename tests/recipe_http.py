"""Editable recipes and atomic installation creation against guarded local fixtures."""
import json
import uuid


def run_recipe_checks(anonymous,admin,viewer,operator,check,command):
    def write(client,route,method,body,key=None):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    def stats():return json.loads(command('tests/recipe-inspect.php','stats'))
    expect(operator.login('operator'),200,'Operator session available for recipe tests')
    expect(anonymous.call('facility/recipes'),401,'Public cannot administer recipes')
    expect(viewer.call('facility/recipes'),403,'Commander cannot change planning recipes')
    check(operator.page('/?page=recipes')[0]==200 and 'Crear receta' in operator.page('/?page=recipes')[1],'Operator has recipe catalog screen')
    check('Eliminar' in operator.page('/')[1] and 'Agregar facility' in operator.page('/')[1] and 'Ver instalaciones' in operator.page('/')[1],'Dashboard exposes deletion and facility shortcuts')
    check('Eliminar instalación' in operator.page('/?page=system&id=1')[1],'Installation exposes clear deletion action')
    body={'name':'Test <script>recipe</script>','type':'Orbital test','tier':'1','source_note':'Synthetic quantities; never game recipes','source_url':'https://example.com/reference','reason':'Local test','materials':[{'commodity_id':'1','required_quantity':'10'},{'commodity_id':'2','required_quantity':'25'}]}
    key=str(uuid.uuid4())
    recipe=expect(write(operator,'facility/recipes','POST',body,key),201,'Operator creates recipe with materials')
    rid=recipe['id']
    again=write(operator,'facility/recipes','POST',body,key)
    check(again[0]==201 and again[1]['replayed'] and again[1]['data']['id']==rid,'Recipe creation retry is idempotent')
    check('&lt;script&gt;recipe&lt;/script&gt;' in operator.page('/?page=recipes')[1],'Recipe names escaped in catalog')
    expect(write(operator,'facility/recipes','POST',body|{'materials':[]}),422,'Empty recipe rejected')
    expect(write(operator,'facility/recipes','POST',body|{'materials':[body['materials'][0],body['materials'][0]]}),422,'Duplicate commodity rejected')
    expect(write(operator,'facility/recipes','POST',body|{'materials':[{'commodity_id':'1','required_quantity':'0'}]}),422,'Zero quantity rejected')
    expect(write(operator,'facility/recipes','POST',body|{'materials':[{'commodity_id':'4','required_quantity':'1'}]}),422,'Inactive commodity rejected')
    expect(write(operator,'facility/recipes','POST',body|{'source_url':'javascript:alert(1)'}),422,'Unsafe source URL rejected')
    expect(write(operator,'facility/recipes','POST',body|{'materials':[{'commodity_id':'1','required_quantity':'18446744073709551616'}]}),422,'Quantity overflow rejected')
    expect(operator.call('facility/recipes','POST',body,'wrong',key=str(uuid.uuid4())),403,'Recipe writes require CSRF')
    expect(write(operator,'facility/recipes','POST',body,key='short'),422,'Recipe writes require idempotency key')
    create={'name':'Atomic facility','facility_recipe_id':rid,'facility_recipe_version':recipe['version'],'type':'Client cannot override recipe','tier':'wrong'}
    before=stats()
    command('tests/recipe-inspect.php','fail-audit')
    try: expect(write(operator,'installations&system_id=1','POST',create),503,'Recipe installation fails when audit cannot be written')
    finally:command('tests/recipe-inspect.php','clear-failure')
    check(stats()==before,'Failed recipe installation rolls back installation, objectives, revisions and receipt')
    key=str(uuid.uuid4())
    inst=expect(write(operator,'installations&system_id=1','POST',create,key),201,'Facility loads complete recipe automatically')
    iid=inst['id']
    check(inst['type']=='Orbital test' and inst['tier']=='1' and inst['facility_recipe_id']==rid and inst['facility_recipe_version']=='1','Server assigns recipe metadata and provenance')
    system=operator.call('system&id=1')[1]['data']
    requirements=[r for r in system['requirements'] if r['installation_id']==iid]
    check({r['commodity_id']:r['required_quantity'] for r in requirements}=={'1':'10','2':'25'},'All copied quantities are exact decimal strings')
    after=stats()
    again=write(operator,'installations&system_id=1','POST',create,key)
    check(again[0]==201 and again[1]['replayed'] and stats()==after,'Facility retry does not duplicate installation or objectives')
    saved=expect(write(operator,'requirement&installation_id='+iid+'&commodity_id=1','PUT',{'version':'1','required_quantity':'12','reason':'Adjust this project'}),200,'Copied objective remains editable per installation')
    check(saved['required_quantity']=='12','Installation adjustment persists')
    changed=body|{'version':recipe['version'],'materials':[{'commodity_id':'1','required_quantity':'30'},{'commodity_id':'2','required_quantity':'60'}],'reason':'Future recipe update'}
    recipe=expect(write(operator,'facility/recipe&id='+rid,'PATCH',changed),200,'Recipe materials can change for future game updates')
    check(recipe['version']=='2','Recipe update increments version')
    expect(write(operator,'facility/recipe&id='+rid,'PATCH',changed),409,'Stale recipe edit rejected')
    expect(write(operator,'installations&system_id=1','POST',create),409,'Changed recipe requires reviewing latest version before creating facility')
    system=operator.call('system&id=1')[1]['data']
    quantities={r['commodity_id']:r['required_quantity'] for r in system['requirements'] if r['installation_id']==iid}
    check(quantities=={'1':'12','2':'25'},'Recipe update never overwrites existing installation objectives')
    second=expect(write(operator,'installations&system_id=1','POST',create|{'name':'Second facility','facility_recipe_version':'2'}),201,'New facility uses edited recipe')
    system=operator.call('system&id=1')[1]['data']
    check({r['commodity_id']:r['required_quantity'] for r in system['requirements'] if r['installation_id']==second['id']}=={'1':'30','2':'60'},'New recipe quantities copied to subsequent facility')
    history=expect(operator.call('facility/recipe/history&id='+rid),200,'Recipe history is accessible to planner')
    check(len(history)>=2 and history[0]['changes']['before']['materials'][0]['required_quantity']=='10','Audit preserves old recipe quantities')
    receipt=expect(operator.call('operation&scope=planning.recipe.create&key='+key),200,'Receipt lookup accepts planning recipe scopes')
    check(receipt is None,'Unrelated idempotency key has no recipe receipt')
    recipe=expect(write(operator,'facility/recipe&id='+rid,'DELETE',{'version':recipe['version'],'reason':'Remove template','confirmed':True}),200,'Recipe deletion archives reversibly')
    check(all(r['id']!=rid for r in operator.call('facility/recipes')[1]['data']),'Archived recipe hidden from active selector')
    expect(write(operator,'installations&system_id=1','POST',create|{'facility_recipe_version':recipe['version']}),409,'Archived recipe cannot seed a facility')
    expect(write(operator,'facility/recipe/restore&id='+rid,'POST',{'version':recipe['version'],'reason':'Restore template'}),200,'Recipe restored with complete materials')
    check(operator.call('facility/recipe&id='+rid)[1]['data']['materials'][1]['required_quantity']=='60','Recipe materials retained across archive/restore')
    check(stats()['movements']==before['movements'],'Recipe operations never modify the movement ledger')
