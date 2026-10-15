"""Account lifecycle, privacy, logistics, partial correction and independent-worker races."""
import json
import os
from pathlib import Path
import subprocess
import time
import uuid


def run_expansion_checks(anonymous,admin,viewer,operator,Client,check,command,env,php,root):
    def write(client,route,method,body,key=None):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    def stats():return json.loads(command('tests/expansion-inspect.php','stats'))
    def user(identity):return admin.call('admin/user&id='+identity)[1]['data']
    def wish(client,scope):return client.call('wishlist&scope='+scope)[1]['data']
    def race(jobs):
        directory=root/'.local/test-artifacts'/('expansion-race-'+str(uuid.uuid4()));directory.mkdir()
        barrier=directory/'go';processes=[]
        try:
            for index,job in enumerate(jobs):
                path=directory/(str(index)+'.json');path.write_text(json.dumps(job|{'barrier':str(barrier)}),encoding='utf-8')
                processes.append(subprocess.Popen([php,'tests/planning-worker.php',str(path)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8',creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
            deadline=time.monotonic()+8
            while not all((directory/(str(i)+'.json.ready')).exists() for i in range(len(jobs))):
                if time.monotonic()>deadline:raise AssertionError('Independent workers did not reach barrier')
                time.sleep(.02)
            barrier.write_text('go')
            results=[]
            for process in processes:
                out,err=process.communicate(timeout=15)
                if process.returncode:raise AssertionError('Worker failed: '+err)
                results.append(json.loads(out))
            return results
        finally:
            for process in processes:
                if process.poll() is None:process.terminate();process.wait(timeout=10)
    initial=stats()
    for client,code in [(anonymous,401),(viewer,403),(operator,403)]:
        expect(write(client,'admin/users','POST',{}),code,'Account creation authorization')
        expect(write(client,'admin/user&id=1','DELETE',{'version':'1','reason':'no','confirmed':True}),code,'Account deletion authorization')
    expect(write(admin,'admin/user&id=1','DELETE',{'version':user('1')['version'],'reason':'last','confirmed':True}),409,'Last administrator cannot be deleted')
    expect(write(admin,'admin/user&id=1','PATCH',{'username':'admin','display_name':'Admin','role':'viewer','active':True,'version':user('1')['version'],'reason':'last'}),409,'Last administrator cannot be demoted')
    create={'username':'crew_test','display_name':'Comandante de prueba','role':'viewer','password':env['AFEC_TEST_PASSWORD'],'reason':'Alta de prueba'}
    key=str(uuid.uuid4());account=expect(write(admin,'admin/users','POST',create,key),201,'Administrator creates commander account');uid=account['id']
    check(account['commander_id'] is not None and 'password' not in account and 'password_hash' not in account,'New account links commander without exposing credentials')
    replay=write(admin,'admin/users','POST',create,key);check(replay[1]['replayed'] and replay[1]['data']==account,'Account retry returns same account')
    expect(write(admin,'admin/users','POST',create|{'password':env['AFEC_TEST_PASSWORD']+'different'},key),409,'Reusing account operation with different password rejected')
    protected=json.loads(command('tests/expansion-inspect.php','password_operation',key))
    check(protected=={'slow_verifier_valid':True,'uses_plaintext_digest':False,'receipt_contains_password':False},'Password operation uses slow verifier without plaintext digest or receipt disclosure')
    expect(write(admin,'admin/users','POST',create),409,'Username remains unique')
    expect(write(admin,'admin/users','POST',create|{'username':'bad_role','role':'invented'}),422,'Unknown role rejected')
    expect(write(admin,'admin/users','POST',create|{'username':'short_password','password':'tiny'}),422,'Short password rejected')
    expect(write(admin,'admin/users','POST',create|{'username':'forged_actor','created_by_user_id':'2'}),422,'Forged account fields rejected')
    expect(admin.call('admin/roles'),200,'Role definitions available to administrator')
    crew=Client();expect(crew.login('crew_test', remember_me=True),200,'New commander can log in with a remembered session')
    check('Añadir a mi lista' in crew.page('/?page=system&id=1')[1] and '+ Crear sistema' in crew.page('/')[1],'Commander sees logistics and creation controls')
    html=admin.page('/?page=users')[1]
    check('data-action="user-role"' in html and 'id="form-user-role"' in html and 'data-rename-username' in html,'Account UI separates role changes from explicit username edits')
    expect(write(admin,'admin/user&id='+uid,'PATCH',{'username':'admin','display_name':account['display_name'],'role':'operator','active':True,'version':account['version'],'reason':'Autofill regression'}),409,'Renaming another account to admin is rejected')
    unchanged=user(uid)
    check(unchanged['username']=='crew_test' and unchanged['role']=='viewer' and unchanged['version']==account['version'],'Username collision preserves identity, permissions and version')
    account=expect(write(admin,'admin/user&id='+uid,'PATCH',{'username':'crew_test','display_name':'Crew renamed','role':'operator','active':True,'version':account['version']}),200,'Administrator changes name and role without a reason')
    check(account['username']=='crew_test' and account['commander_id']==unchanged['commander_id'],'Role change preserves login and commander identity')
    check(crew.session()['user'] is None,'Role change revokes existing session');expect(crew.login('crew_test'),200,'Account can log in after role change')
    check('+ Crear sistema' in crew.page('/')[1],'Promoted role receives planning UI')
    account=expect(write(admin,'admin/user&id='+uid,'PATCH',{'username':'crew_test','display_name':'Crew renamed','role':'viewer','active':True,'version':account['version'],'reason':'Rol comandante'}),200,'Administrator restores commander role')
    expect(write(admin,'admin/user&id='+uid,'PATCH',{'username':'crew_test','display_name':'Stale','role':'admin','active':True,'version':'1','reason':'old'}),409,'Stale account edit rejected')
    expect(crew.login('crew_test'),200,'Commander role login restored')
    expect(anonymous.call('wishlist&scope=fleet'),401,'Fleet list requires authentication')
    expect(viewer.call('wishlist&scope=personal&owner_user_id='+uid),422,'Personal list cannot be selected by another user ID')
    expect(write(viewer,'wishlist/entries&installation_id=2','POST',{'scope':'personal','owner_user_id':uid}),422,'List write cannot impersonate owner')
    expect(write(viewer,'wishlist/entries&installation_id=2','POST',{'scope':'personal'}),200,'All authenticated users add own personal list')
    check(wish(crew,'personal')['selected']['items']==[],'Another commander cannot see personal selections')
    check(wish(admin,'personal')['selected']['items']==[],'Administrator does not inherit another personal list')
    expect(write(viewer,'wishlist/entries&installation_id=2','POST',{'scope':'fleet'}),200,'Commander contributes to fleet list')
    check(wish(crew,'fleet')['commodities']['items'][0]['remaining_quantity']=='75','Fleet shows shared current deficit')
    again=write(crew,'wishlist/entries&installation_id=2','POST',{'scope':'fleet'});check(again[1]['data']['already_added'],'Repeated fleet selection does not duplicate quantities')
    selection=wish(crew,'fleet')['selected']['items'][0]
    expect(write(crew,'wishlist/entry&installation_id=2','DELETE',{'scope':'fleet','version':selection['version'],'reason':'Quitar selección'}),200,'Another authenticated user can remove fleet selection')
    check(wish(viewer,'personal')['selected']['items'] and not wish(viewer,'fleet')['selected']['items'],'Removing fleet selection preserves personal list')
    expect(write(crew,'wishlist/entries&installation_id=2','POST',{'scope':'fleet'}),200,'Fleet selection can be added again')
    expect(write(viewer,'wishlist/entries&installation_id=7','POST',{'scope':'fleet'}),409,'Archived installations cannot be added')
    status=operator.call('installation&id=2')[1]['data']
    expect(write(viewer,'installation/status&id=2','PATCH',{'construction_status':'completed','version':status['version'],'reason':'No','confirmed':True}),403,'Commander cannot change planning status')
    expect(write(operator,'installation/status&id=2','PATCH',{'construction_status':'completed','version':status['version'],'reason':'Desfase','confirmed':True}),200,'Operator closes construction with deficits')
    check(not wish(crew,'fleet')['commodities']['items'] and not wish(viewer,'personal')['commodities']['items'],'Completed installations excluded from both lists')
    check(anonymous.call('system&id=1')[1]['data']['installations'][1]['delivered_quantity']=='25','Manual completion preserves actual delivery count')
    status=operator.call('installation&id=2')[1]['data']
    expect(write(operator,'installation/status&id=2','PATCH',{'construction_status':'under_construction','version':status['version'],'reason':'Reapertura'}),200,'Operator reopens construction')
    check(wish(crew,'fleet')['commodities']['items'][0]['remaining_quantity']=='75','Reopening restores deficit without duplicated selection')
    source_body={'commodity_id':'1','system_name':'Sol <script>','facility_name':'Estación de prueba','facility_type':'station','notes':'Información compartida','reason':'Alta de origen'}
    source=expect(write(viewer,'sources','POST',source_body),201,'Any authenticated commander records supply location');sid=source['id']
    check(crew.call('sources&commodity_id=1')[1]['data']['items'][0]['id']==sid,'Supply location shared with other commanders')
    check(wish(crew,'fleet')['commodities']['items'][0]['source_count']==1,'Shared supply location activates globe indicator')
    expect(write(crew,'source&id='+sid,'PATCH',{'system_name':'Sol','facility_name':'Estación editada','facility_type':'settlement','notes':None,'version':source['version'],'reason':'Corregir lugar'}),200,'Another commander edits supply location')
    expect(write(viewer,'source&id='+sid,'PATCH',{'system_name':'Old','facility_name':'Old','facility_type':'station','notes':None,'version':'1','reason':'old'}),409,'Stale source edit rejected')
    source=crew.call('sources&commodity_id=1')[1]['data']['items'][0]
    source=expect(write(viewer,'source&id='+sid,'DELETE',{'version':source['version'],'reason':'Ya no vende','confirmed':True}),200,'Any authenticated user removes obsolete supply location')
    check(crew.call('sources&commodity_id=1')[1]['data']['items']==[],'Retired source excluded from active popup')
    check(crew.call('sources&commodity_id=1&include_inactive=1')[1]['data']['items'][0]['id']==sid,'Retired source remains available for review and restoration')
    expect(write(crew,'source/restore&id='+sid,'POST',{'version':source['version'],'reason':'Vuelve a vender'}),200,'Supply location restoration works')
    expect(anonymous.call('sources&commodity_id=1'),401,'Public cannot access supply editing/read API')
    system=expect(write(operator,'systems','POST',{'name':'Logistics workflow'}),201,'Create isolated logistics system')
    place=expect(write(operator,'installations&system_id='+system['id'],'POST',{'name':'Destino de pruebas'}),201,'Create active construction destination');iid=place['id']
    check(place['construction_status']=='under_construction','New installations start under construction')
    expect(write(operator,'requirement&installation_id='+iid+'&commodity_id=1','PUT',{'required_quantity':'100','version':'0','reason':'Objetivo'}),201,'Create logistics objective')
    expect(write(crew,'wishlist/entries&installation_id='+iid,'POST',{'scope':'personal'}),200,'Commander personal destination added')
    delivery_route='deliveries&installation_id='+iid+'&commodity_id=1';delivery_key=str(uuid.uuid4())
    delivery=expect(write(crew,delivery_route,'POST',{'quantity':'150','reason':'Sobreentrega'},delivery_key),201,'Commander logs own delivery, including overdelivery');mid=delivery['id']
    check(delivery['commander_id']==account['commander_id'] and delivery['created_by_user_id']==uid,'Delivery binds own commander and recorder separately')
    check(write(crew,delivery_route,'POST',{'quantity':'150','reason':'Sobreentrega'},delivery_key)[1]['data']['id']==mid,'Delivery retry does not duplicate ledger')
    check(not wish(crew,'personal')['commodities']['items'],'Covered objective disappears from missing-material list')
    expect(write(viewer,delivery_route,'POST',{'quantity':'1','commander_id':account['commander_id']}),403,'Commander cannot impersonate another delivering commander')
    expect(write(viewer,'movement/withdraw&id='+mid,'POST',{'quantity':'1','reason':'No','confirmed':True}),403,'Commander cannot correct another commander delivery')
    expect(write(crew,'movement/withdraw&id='+mid,'POST',{'quantity':'70','reason':'Cargué de más','confirmed':True}),201,'Commander partially corrects own delivery')
    check(wish(crew,'personal')['commodities']['items'][0]['remaining_quantity']=='20','Partial correction restores the missing quantity')
    expect(write(operator,'movement/withdraw&id='+mid,'POST',{'quantity':'30','reason':'Segundo retiro','confirmed':True}),201,'Operator can partially correct third-party delivery')
    check(wish(crew,'personal')['commodities']['items'][0]['remaining_quantity']=='50','Multiple partial withdrawals remain correctly aggregated')
    expect(write(crew,'movement/withdraw&id='+mid,'POST',{'quantity':'51','reason':'Exceso','confirmed':True}),409,'Cannot withdraw beyond original remaining attribution')
    ledger=crew.call('movements&installation_id='+iid)[1]['data']['items']
    check(next(x for x in ledger if x['id']==mid)['delta_quantity']=='150' and len([x for x in ledger if x['reverses_movement_id']==mid])==2,'Original movement immutable; two linked compensations retained')
    expect(write(crew,delivery_route,'POST',{'quantity':'1','created_by_user_id':'1'}),422,'Delivery recorder cannot be forged')
    expect(write(crew,delivery_route,'POST',{'quantity':'9223372036854775808'}),422,'Movement quantity bounded to signed bigint without float conversion')
    own=expect(write(viewer,delivery_route,'POST',{'quantity':'5'}),201,'Legacy account without commander gets stable own attribution')
    check(own['created_by_user_id']=='2' and own['commander_id'] is not None,'Missing legacy commander linked transactionally')
    before=stats();command('tests/expansion-inspect.php','fail_audit')
    try:
        expect(write(crew,delivery_route,'POST',{'quantity':'2'}),503,'Forced audit failure rejects delivery')
        check(stats()==before,'Delivery audit failure rolls back movement and operation atomically')
        expect(write(admin,'admin/users','POST',create|{'username':'rollback_user'}),503,'Forced audit failure rejects account creation')
        check(stats()==before,'Account audit failure rolls back commander, account and operation')
    finally:command('tests/expansion-inspect.php','clear_failure')
    raced=expect(write(crew,delivery_route,'POST',{'quantity':'100'}),201,'Prepare concurrent withdrawal delivery')
    jobs=[{'action':'withdraw','actor_id':actor,'key':str(uuid.uuid4()),'id':raced['id'],'body':{'quantity':'80','reason':'Concurrent partial','confirmed':True}} for actor in ['1','3']]
    results=race(jobs);check(sorted(r['status'] for r in results)==[201,409],'Independent concurrent partial withdrawals cannot overdraw delivery')
    row=next(x for x in crew.call('movements&installation_id='+iid)[1]['data']['items'] if x['id']==raced['id'])
    check(row['available_quantity']=='20','Concurrent withdrawal leaves exact remaining attribution')
    results=race([{'action':'list_add','actor_id':actor,'key':str(uuid.uuid4()),'id':iid,'body':{'scope':'fleet'}} for actor in ['1','3']])
    check(all(r['status']==200 for r in results) and sum(bool(r['data'].get('already_added')) for r in results)==1,'Concurrent fleet adds share a single selection')
    alt=expect(write(admin,'admin/users','POST',create|{'username':'second_admin','role':'admin'}),201,'Create administrator for concurrent access test')
    second=Client();expect(second.login('second_admin'),200,'Second administrator authenticates')
    original=user('1')
    results=race([{'action':'user_update','actor_id':'1','key':str(uuid.uuid4()),'id':alt['id'],'body':{'username':alt['username'],'display_name':alt['display_name'],'role':'viewer','active':True,'version':alt['version'],'reason':'Concurrent role removal'}},
        {'action':'user_update','actor_id':alt['id'],'key':str(uuid.uuid4()),'id':'1','body':{'username':original['username'],'display_name':original['display_name'],'role':'viewer','active':True,'version':original['version'],'reason':'Concurrent role removal'}}])
    check(sorted(r['status'] for r in results)==[200,401],'Concurrent opposing role changes retain one administrator and reject revoked actor')
    if admin.session()['user'] is None:
        current=second.call('admin/user&id=1')[1]['data']
        expect(write(second,'admin/user&id=1','PATCH',{'username':current['username'],'display_name':current['display_name'],'role':'admin','active':True,'version':current['version'],'reason':'Restore test admin'}),200,'Remaining administrator restores original administrator')
        expect(admin.login('admin'),200,'Original administrator can authenticate after restoration')
    else:
        check(admin.session()['user']['role']=='admin','Original administrator retains access when its concurrent change wins')
        check(second.session()['user'] is None,'Losing administrator session is revoked when original administrator wins')
    account=user(uid)
    expect(write(admin,'admin/user&id='+uid,'DELETE',{'version':account['version'],'reason':'Eliminar prueba','confirmed':True}),200,'Administrator deletes commander account reversibly')
    check(crew.session()['user'] is None,'Account deletion revokes logged-in sessions')
    expect(crew.login('crew_test'),401,'Deleted account cannot log in')
    check(admin.call('movements&installation_id='+iid)[1]['data']['items'],'Deleted user contributions remain auditable')
    account=user(uid)
    account=expect(write(admin,'admin/user/restore&id='+uid,'POST',{'version':account['version'],'reason':'Restaurar prueba'}),200,'Administrator restores account')
    expect(crew.login('crew_test', remember_me=True),200,'Restored account can log in with a remembered session')
    check(wish(crew,'personal')['selected']['items'],'Restored account retains its own private list')
    expect(write(admin,'admin/user/password&id='+uid,'POST',{'password':env['AFEC_TEST_PASSWORD']+'x','version':account['version'],'reason':'Reset de prueba'}),200,'Password reset functional')
    check(crew.session()['user'] is None,'Password reset revokes existing session')
    expect(crew.login('crew_test'),401,'Previous password no longer works')
    token=crew.session()['csrf_token'];expect(crew.call('login','POST',{'username':'crew_test','password':env['AFEC_TEST_PASSWORD']+'x'},token),200,'New password authenticates')
    receipt=admin.call('operation&scope=admin.user.create&key='+key)[1]['data']
    check(receipt['data']['id']==uid and 'password' not in json.dumps(receipt),'Account receipt confirms creation without containing password')
    check(viewer.call('operation&scope=admin.user.create&key='+key)[1]['data'] is None,'Receipts cannot be read by another user')
    check(stats()['original_movements_hash']==initial['original_movements_hash'],'All original fixture ledger rows remain unchanged')
    check(crew.page('/?page=wishlist&scope=personal')[0]==200 and 'Quitar de esta lista' in crew.page('/?page=wishlist&scope=personal')[1],'Personal wishlist HTML renders persisted selections')
    check('Crear usuario' in admin.page('/?page=users')[1] and 'Eliminar usuario' in admin.page('/?page=users')[1],'Administrator HTML exposes full account CRUD')
