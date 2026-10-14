"""Catalogue lifecycle, aliases, authorization and preservation of existing balances."""
import uuid
import json
import os
import subprocess
import time


def run_commodity_checks(anonymous, admin, viewer, operator, check, command):
    def write(client, route, method, body, key=None):
        return client.call(route, method, body, client.session()['csrf_token'], key=key or str(uuid.uuid4()))
    def expect(response, code, label):
        check(response[0] == code, label + ' (status ' + str(response[0]) + ')')
        return response[1].get('data')
    def item(identity):
        return admin.call('admin/commodity&id=' + identity)[1]['data']
    for client, code in [(anonymous,401),(viewer,403),(operator,403)]:
        expect(client.call('admin/commodities'),code,'Catalogue administration requires admin')
        expect(write(client,'admin/commodity&id=1','DELETE',{}),code,'Catalogue mutation requires admin')
        check(client.page('/?page=commodities')[0] == code,'Catalogue page respects admin permission')
    listing=expect(admin.call('admin/commodities&include_inactive=1'),200,'Administrator can list inactive commodities')
    check(admin.call('admin/commodities')[1]['aliases_ready'],'Alias migration detected')
    html=admin.page('/?page=commodities&include_inactive=1')[1]
    check('Desactivar' in html and 'Reactivar' in html and 'Alias' in html,'Catalogue exposes lifecycle and aliases')
    body={'code':'afec_test_new','name_es':'Nueva mercancía <script>','name_en':'New test commodity','aliases':['Alternate Name'],'reason':'Alta de prueba'}
    key=str(uuid.uuid4())
    row=expect(write(admin,'commodities','POST',body,key),201,'Administrator creates commodity and alias')
    cid=row['id']; route='admin/commodity&id='+cid
    check(write(admin,'commodities','POST',body,key)[1]['replayed'],'Commodity creation retry is idempotent')
    check('&lt;script&gt;' in admin.page('/?page=commodities')[1],'Commodity names escaped')
    expect(write(admin,'commodities','POST',body|{'code':'another_test','name_es':'Otro','name_en':None,'aliases':[' alternate-name ']}),409,'Normalized alias cannot belong to another commodity')
    expect(write(admin,'commodities','POST',body|{'code':'bad_test','name_es':'Otro','name_en':None,'aliases':['Same Alias','same-alias']}),422,'Repeated normalized aliases rejected')
    expect(write(admin,'commodities','POST',body|{'aliases':['x'*256]}),422,'Oversized aliases rejected')
    expect(admin.call(route,'PATCH',body|{'version':row['version']},'invalid',key=str(uuid.uuid4())),403,'Commodity edits require CSRF')
    patch={'code':'afec_changed','name_es':'Mercancía editada','name_en':'Edited test','aliases':row['aliases'],'version':row['version'],'reason':'Editar catálogo'}
    updated=expect(write(admin,route,'PATCH',patch),200,'Administrator edits code and names')
    check(updated['id']==cid and 'afec_test_new' in updated['aliases'] and 'Nueva mercancía <script>' in updated['aliases'],'Edited commodity preserves identity and previous names as aliases')
    expect(write(admin,route,'PATCH',patch),409,'Stale commodity edits rejected')
    retired=expect(write(admin,route,'DELETE',{'version':updated['version'],'reason':'Retirar material','confirmed':True}),200,'Administrator deactivates commodity')
    check(not any(x['id']==cid for x in anonymous.call('commodities')[1]['data']),'Inactive commodity excluded from selection')
    expect(write(admin,'commodities','POST',body|{'code':'reserved_inactive','name_es':'Otro nombre','name_en':None,'aliases':['Alternate Name']}),409,'Inactive commodity retains alias reservation')
    restored=expect(write(admin,'admin/commodity/restore&id='+cid,'POST',{'version':retired['version'],'reason':'Volver a utilizar'}),200,'Administrator restores commodity')
    check(restored['active'] and restored['id']==cid,'Restoration reuses original ID')
    history=expect(admin.call('admin/commodity/history&id='+cid),200,'Administrator reads commodity audit history')
    check([x['action'] for x in history[:4]]==['restore','deactivate','update','create'],'Each catalogue mutation is auditable')
    discarded=expect(write(admin,route,'PATCH',{'code':'afec_corrected_identity','name_es':'Nombre correcto','name_en':None,'aliases':['Alternate Name'],'preserve_previous':False,'version':restored['version'],'reason':'Corregir identidad equivocada'}),200,'Administrator explicitly discards wrong previous names')
    check('afec_changed' not in discarded['aliases'] and discarded['aliases']==['Alternate Name'],'Wrong prior identity is not retained when explicitly unchecked')
    # Existing water has goals, deliveries, supply locations and catalogue references.
    water=item('1')
    before=anonymous.call('system&id=1')[1]['data']
    totals=[(x['id'],x['required_quantity'],x['delivered_quantity'],x['remaining_quantity']) for x in before['installations']]
    original_sources=viewer.call('sources&commodity_id=1')[1]['data']
    renamed=expect(write(admin,'admin/commodity&id=1','PATCH',{'code':'afec_water_renamed','name_es':'Agua AFEC ajustada','name_en':'AFEC water adjusted','aliases':water['aliases'],'version':water['version'],'reason':'Conservar equivalencias'}),200,'Edit commodity with existing requirements and deliveries')
    check('water' in renamed['aliases'] and 'Agua' in renamed['aliases'],'Original canonical identities remain usable by catalogue import')
    retired=expect(write(admin,'admin/commodity&id=1','DELETE',{'version':renamed['version'],'reason':'Desactivar con historia','confirmed':True}),200,'Deactivate commodity with historical balances')
    after=anonymous.call('system&id=1')[1]['data']
    check(totals==[(x['id'],x['required_quantity'],x['delivered_quantity'],x['remaining_quantity']) for x in after['installations']],'Commodity edits/deactivation preserve all progress totals')
    check(viewer.call('sources&commodity_id=1')[1]['data']==original_sources,'Commodity edit preserves supply locations')
    expect(write(admin,'admin/commodity/restore&id=1','POST',{'version':retired['version'],'reason':'Restaurar agua'}),200,'Restore commodity before subsequent catalogue tests')
    stable=item(cid)
    command('tests/recipe-inspect.php','fail-audit')
    try:
        expect(write(admin,route,'PATCH',{'code':stable['code'],'name_es':'Must rollback','name_en':stable['name_en'],'aliases':stable['aliases'],'version':stable['version'],'reason':'Atomicity'}),503,'Audit failure rejects commodity edit')
    finally:
        command('tests/recipe-inspect.php','clear-failure')
    check(item(cid)==stable,'Failed catalogue audit rolls back names, aliases and version')


def run_commodity_race(admin,check,env,php,root):
    second=next(row for row in admin.call('admin/users')[1]['data'] if row['username']=='seed_admin')
    actors=[admin.session()['user']['id'],second['id']]
    directory=root/'.local/test-artifacts'/('commodity-race-'+uuid.uuid4().hex)
    directory.mkdir();barrier=directory/'go';processes=[]
    try:
        for index,actor in enumerate(actors):
            job={'action':'commodity_create','actor_id':actor,'key':str(uuid.uuid4()),'barrier':str(barrier),
                 'body':{'code':'race_code_'+str(index),'name_es':'Mercancía concurrente '+str(index),'aliases':['Alias compartido de concurrencia']}}
            file=directory/(str(index)+'.json');file.write_text(json.dumps(job),encoding='utf-8')
            processes.append(subprocess.Popen([php,'tests/planning-worker.php',str(file)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
        deadline=time.monotonic()+10
        while len(list(directory.glob('*.ready')))<2:
            if time.monotonic()>deadline:raise AssertionError('Commodity race barrier timed out')
            time.sleep(.02)
        barrier.write_text('go')
        results=[]
        for process in processes:
            stdout,stderr=process.communicate(timeout=20)
            if process.returncode:raise AssertionError('Commodity worker failed: '+stderr.decode())
            results.append(json.loads(stdout))
        check(sorted(row['status'] for row in results)==[201,409],'Independent administrators cannot assign the same alias to different commodities')
    finally:
        for process in processes:
            if process.poll() is None:process.terminate();process.wait(timeout=10)
