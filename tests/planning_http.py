"""CRUD, audit, atomic rollback and real two-connection concurrency checks."""
import json
import os
from pathlib import Path
import subprocess
import time
import uuid


def run_planning_checks(anonymous, admin, viewer, operator, check, command, env, php, root):
    def write(client, route, method, body, key=None):
        return client.call(route, method, body, client.session()['csrf_token'], key=key or str(uuid.uuid4()))
    def stats():
        return json.loads(command('tests/planning-inspect.php', 'stats'))
    def history(client, kind, identity, commodity=None):
        route = f'planning/history&type={kind}&id={identity}'
        if commodity: route += '&commodity_id=' + commodity
        return client.call(route)
    start = stats()
    for client, expected in [(anonymous, 401), (viewer, 403)]:
        for route, method, body in [('systems','POST',{'name':'Forbidden'}), ('system&id=1','PATCH',{'name':'Forbidden','version':'1','reason':'No'}), ('system&id=1','DELETE',{'version':'1','reason':'No','confirmed':True}), ('installations&system_id=1','POST',{'name':'Forbidden'}), ('requirement&installation_id=1&commodity_id=1','PUT',{'required_quantity':'99','version':'1','reason':'No'})]:
            if client is viewer and route in ['systems','installations&system_id=1']: continue
            check(write(client,route,method,body)[0] == expected, 'Unauthorized domain writes rejected')
    check(stats() == start, 'Rejected writes leave no operation, audit or domain row')
    check(operator.call('systems','POST',{'name':'No CSRF'},key=str(uuid.uuid4()))[0] == 403, 'Operator writes require CSRF')
    check(operator.call('systems','POST',{'name':'No key'},operator.session()['csrf_token'])[0] == 422, 'Domain write requires idempotency key')
    check(write(operator,'systems','POST',{'name':'Impersonation','created_by_user_id':'1'})[0] == 422, 'Client cannot impose an audit actor')
    check(write(operator,'systems','POST',{'name':'\n'})[0] == 422, 'Blank system name rejected')
    check(write(operator,'systems','POST',{'name':'Bad\x00name'})[0] == 422, 'Control character rejected')
    check(write(operator,'commodities','POST',{'code':'op-code','name_es':'Unauthorized catalog'})[0] == 403, 'Catalog creation remains administrator only')

    key = str(uuid.uuid4())
    body = {'name':'CRUD system <img src=x onerror=alert(1)>','description':'Created by operator'}
    status, created, _ = write(operator,'systems','POST',body,key)
    system_id = created['data']['id']
    check(status == 201 and created['data']['version'] == '1', 'Operator creates a system')
    again = write(operator,'systems','POST',dict(reversed(list(body.items()))),key)
    check(again[0] == 201 and again[1]['data'] == created['data'] and again[1]['replayed'], 'Same request with reordered fields replays exact result')
    check(write(operator,'systems','POST',{'name':'Different'},key)[0] == 409, 'Same key with different content rejected')
    check(operator.call('system&id='+system_id)[0] == 200, 'New system immediately readable')
    check('onerror=' in operator.page('/?page=system&id='+system_id)[1] and '<img src=x' not in operator.page('/?page=system&id='+system_id)[1], 'New malicious-looking name escaped as text')
    audit = history(operator,'system',system_id)
    check(audit[0] == 200 and len(audit[1]['data']) == 1 and audit[1]['data'][0]['author'] == 'Operador', 'Creation audit attributes operator and replay adds no audit')
    update = {'name':'Updated operator system','description':'Updated','version':'1','reason':'Rename for test'}
    check(write(operator,'system&id='+system_id,'PATCH',update)[1]['data']['version'] == '2', 'Operator edits system with fresh version')
    check(write(admin,'system&id='+system_id,'PATCH',update)[0] == 409, 'Stale administrator edit does not overwrite operator')
    check(operator.call('system&id='+system_id)[1]['data']['name'] == 'Updated operator system', 'Stale write leaves latest name intact')

    installation_body = {'name':'New installation','body_name':'A 3','tier':'2','type':'Custom type','state':'En planificación','sort_order':'7'}
    install_key = str(uuid.uuid4())
    status, added, _ = write(operator,'installations&system_id='+system_id,'POST',installation_body,install_key)
    installation_id = added['data']['id']
    check(status == 201 and added['data']['system_id'] == system_id, 'Operator creates installation under system')
    check(write(operator,'installations&system_id='+system_id,'POST',installation_body,install_key)[1]['data']['id'] == installation_id, 'Installation creation retry preserves ID')
    edited_installation = installation_body | {'name':'Renamed installation','version':'1','reason':'Update location','location_label':'Surface'}
    status, result, _ = write(operator,'installation&id='+installation_id,'PATCH',edited_installation)
    check(status == 200 and result['data']['version'] == '2' and result['data']['location_label'] == 'Surface', 'Operator edits installation metadata')
    check(write(operator,'installation&id='+installation_id,'PATCH',edited_installation)[0] == 409, 'Stale installation edit rejected')
    check(write(operator,'installation&id='+installation_id,'PATCH',edited_installation | {'version':'2','system_id':'1'})[0] == 422, 'CRUD does not silently move a historical installation')
    check(anonymous.call('installation&id='+installation_id)[0] == 200, 'Public can read installation metadata')

    material_key = str(uuid.uuid4())
    material = {'code':'new-test-code','name_es':'Nuevo material','name_en':'New material'}
    status, result, _ = write(admin,'commodities','POST',material,material_key)
    material_id = result['data']['id']
    check(status == 201 and write(admin,'commodities','POST',material,material_key)[1]['data']['id'] == material_id, 'Administrator material creation is idempotent')
    check(write(admin,'commodities','POST',material)[0] == 409, 'Duplicate material code conflicts without partial operation')
    target_route = f'requirement&installation_id={installation_id}&commodity_id={material_id}'
    target_key = str(uuid.uuid4())
    target = {'required_quantity':'00100','version':'0','reason':'Initial goal'}
    status, target_created, _ = write(operator,target_route,'PUT',target,target_key)
    check(status == 201 and target_created['data']['required_quantity'] == '100', 'New installation accepts a material goal immediately')
    check(write(operator,target_route,'PUT',target | {'required_quantity':'100'},target_key)[1]['replayed'], 'Equivalent integer normalization replays one target')
    check(write(operator,target_route,'PUT',target)[0] == 409, 'Existing target cannot be recreated with version zero')
    for quantity in ['-1','1.5','1e3',0.5,'18446744073709551616']:
        check(write(operator,target_route,'PUT',{'required_quantity':quantity,'version':'1','reason':'Invalid'})[0] == 422, 'Invalid quantity rejected')
    before_failed = stats()
    command('tests/planning-inspect.php','failure-on')
    try:
        check(write(operator,target_route,'PUT',{'required_quantity':'200','version':'1','reason':'Forced failure'})[0] == 503, 'Revision storage failure returns no success')
    finally:
        command('tests/planning-inspect.php','failure-off')
    check(stats() == before_failed, 'Failed revision rolls back target, operation and audit together')
    check(write(operator,target_route,'PUT',{'required_quantity':'9007199254740993','version':'1','reason':'Precision'})[1]['data']['required_quantity'] == '9007199254740993', 'Operator goal edit preserves large integer precision')
    check(write(operator,target_route,'PUT',{'required_quantity':'150','version':'2','reason':'Working goal'})[0] == 200, 'Operator updates objective after large value')
    check(write(operator,target_route,'DELETE',{'version':'3','reason':'Retire','confirmed':False})[0] == 422, 'Retirement needs explicit confirmation')
    check(write(operator,target_route,'DELETE',{'version':'3','reason':'Retire','confirmed':True})[0] == 200, 'Operator retires objective logically')
    check(not any(x['commodity_id']==material_id for x in anonymous.call('system&id='+system_id)[1]['data']['requirements']), 'Retired objective excluded from public active progress')
    archived = operator.call('system&id='+system_id+'&include_archived=1')[1]['data']['requirements']
    check(any(x['commodity_id']==material_id and not x['active'] for x in archived), 'Operator can find retired objective for restoration')
    check(write(operator,target_route,'PUT',{'required_quantity':'150','version':'4','reason':'Restore goal'})[0] == 200, 'Operator restores objective and keeps identity')
    revisions = history(operator,'requirement',installation_id,material_id)
    check(len(revisions[1]['data']) == 5 and revisions[1]['data'][0]['old_active'] == 0 and revisions[1]['data'][0]['new_active'] == 1, 'All quantity and active-state transitions have revisions')
    check(history(viewer,'requirement',installation_id,material_id)[0] == 403 and history(anonymous,'system',system_id)[0] == 401, 'Planning author history is restricted to writers')

    existing_target = 'requirement&installation_id=1&commodity_id=1'
    check(write(operator,existing_target,'PUT',{'required_quantity':'125','version':'1','reason':'Goal correction'})[0] == 200, 'Existing objective can change without rewriting past delivery')
    edited_requirement = next(x for x in anonymous.call('system&id=1')[1]['data']['requirements'] if x['installation_id']=='1' and x['commodity_id']=='1')
    check(edited_requirement['surplus_quantity'] == '75', 'Progress recalculates surplus after objective change')
    check(write(operator,existing_target,'DELETE',{'version':'2','reason':'Retire with deliveries','confirmed':True})[0] == 200, 'Objective with deliveries can be retired without erasing references')
    check(stats()['movement_hash'] == start['movement_hash'], 'Ledger bytes unchanged by metadata/goal CRUD')
    check(write(operator,existing_target,'PUT',{'required_quantity':'100','version':'3','reason':'Restore historical goal'})[0] == 200, 'Goal with historical deliveries restores same pair')

    check(write(operator,'installation&id='+installation_id,'DELETE',{'version':'2','reason':'Archive station','confirmed':True})[0] == 200, 'Operator archives installation')
    check(write(operator,target_route,'PUT',{'required_quantity':'160','version':'5','reason':'Archived parent'})[0] == 409, 'Archived installation rejects goal changes')
    check(write(operator,'installation/restore&id='+installation_id,'POST',{'version':'3','reason':'Restore station'})[0] == 200, 'Operator restores installation')
    check(write(operator,'system&id='+system_id,'DELETE',{'version':'2','reason':'Archive system','confirmed':True})[0] == 200, 'Operator archives whole system')
    check(write(operator,'installations&system_id='+system_id,'POST',{'name':'Blocked'})[0] == 409, 'Archived system rejects new installations')
    check(write(operator,target_route,'PUT',{'required_quantity':'160','version':'5','reason':'Archived system'})[0] == 409, 'Archived system rejects goal changes')
    check(anonymous.call('systems&include_archived=1')[0] == 401, 'Archive management filter is not public')
    check(operator.call('systems&include_archived=1')[0] == 200, 'Operator can discover archived systems')
    check(write(operator,'system/restore&id='+system_id,'POST',{'version':'3','reason':'Restore system'})[0] == 200, 'Operator restores system')
    check(stats()['movement_hash'] == start['movement_hash'], 'System/installation archive never mutates movements')

    def race(jobs):
        work = root / '.local' / 'test-artifacts' / uuid.uuid4().hex
        work.mkdir()
        barrier = work / 'start'
        processes = []
        flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
        for index, job in enumerate(jobs):
            job_file = work / f'job{index}.json'
            job_file.write_text(json.dumps(job | {'barrier':str(barrier)}),encoding='utf-8')
            processes.append(subprocess.Popen([php,'tests/planning-worker.php',str(job_file)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8',creationflags=flags))
        deadline = time.monotonic()+6
        while len(list(work.glob('*.ready'))) < 2:
            if time.monotonic()>deadline: raise AssertionError('Workers did not reach barrier')
            time.sleep(.01)
        barrier.write_text('start')
        results=[]
        for process in processes:
            output, errors = process.communicate(timeout=15)
            if process.returncode: raise AssertionError(errors)
            results.append(json.loads(output))
        return results

    key = str(uuid.uuid4())
    job = {'action':'create','actor_id':'3','key':key,'body':{'name':'Concurrent same operation'}}
    results = race([job,job])
    check(all(x['status']==201 for x in results) and results[0]['data']['id']==results[1]['data']['id'] and sorted(x['replayed'] for x in results)==[False,True], 'Two real connections commit one idempotent creation')
    race_id = results[0]['data']['id']
    check(len(history(operator,'system',race_id)[1]['data']) == 1, 'Concurrent duplicate adds one audit row')
    jobs = [{'action':'update','actor_id':actor,'id':race_id,'key':str(uuid.uuid4()),'body':{'name':name,'version':'1','reason':'Concurrent edit'}} for actor,name in [('1','Admin version'),('3','Operator version')]]
    results = race(jobs)
    check(sorted(x['status'] for x in results)==[200,409], 'Concurrent edits keep one winner and report stale version to other')
    check(operator.call('system&id='+race_id)[1]['data']['version']=='2', 'Concurrent update increments once')
    jobs = [{'action':'requirement','actor_id':actor,'installation_id':installation_id,'commodity_id':'2','key':str(uuid.uuid4()),'body':{'required_quantity':q,'version':'0','reason':'Concurrent initial goal'}} for actor,q in [('1','45'),('3','55')]]
    results = race(jobs)
    check(sorted(x['status'] for x in results)==[201,409], 'Concurrent new target does not overwrite or duplicate pair')
    command('tests/planning-inspect.php','pages',system_id)
    first_page = anonymous.call('systems')[1]
    second_page = anonymous.call('systems&list_page=2')[1]
    check(len(first_page['data'])==25 and first_page['pagination']['has_more'] and second_page['data'], 'Systems have explicit pagination')
    check(not ({x['id'] for x in first_page['data']} & {x['id'] for x in second_page['data']}), 'System pages do not duplicate rows')
    station_page = anonymous.call('system&id='+system_id)[1]['data']
    station_second = anonymous.call('system&id='+system_id+'&list_page=2')[1]['data']
    check(len(station_page['installations'])==25 and station_page['pagination']['has_more'] and len(station_second['installations'])==6, 'Installation pagination includes remaining stations')
    check(anonymous.call('systems&list_page=-1')[0]==422, 'Invalid pagination rejected')
    check(stats()['movement_hash']==start['movement_hash'], 'All CRUD and concurrency checks preserve the original ledger')
