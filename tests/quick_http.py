"""Standard loads, bounded row corrections, attribution and immutable historical entries."""
import json
import os
import subprocess
import time
import uuid


def run_quick_checks(anonymous,admin,viewer,operator,check,command,env,php,root):
    def write(client,route,method,body,key=None):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    def options(client):return client.call('quick/options')[1]['data']
    own=viewer.session()['user']['commander_id']
    other=operator.session()['user']['commander_id']
    check(own is not None and own!=other,'Quick tests use distinct commander identities')
    expect(anonymous.call('quick/options'),401,'Quick options require login')
    expect(write(anonymous,'commander/load&id='+own,'PATCH',{}),401,'Public cannot configure loads')
    check({own,other}.issubset({row['id'] for row in options(viewer)['commanders']}),'Commander can select other identities')
    check(options(viewer)['presets_ready'],'Migration 008 detected')
    expect(viewer.call('commander/load&id='+other),200,'Commander can fetch a third party preset directly')
    check(operator.call('commander/load&id='+own)[0]==200,'Operator can restore saved commander outside first options page')
    expect(write(viewer,'commander/load&id='+own,'PATCH',{'version':'1'}),422,'Missing quantity cannot accidentally clear an existing preset')
    def profile(identity):return next(row for row in options(admin)['commanders'] if row['id']==identity)
    def save(client,identity,quantity,version=None,key=None):
        return write(client,'commander/load&id='+identity,'PATCH',{'quantity':quantity,'version':version or profile(identity)['load_version']},key)
    expect(save(viewer,other,'784'),200,'Commander can configure a third party load')
    token=viewer.session()['csrf_token']
    expect(viewer.call('commander/load&id='+own,'PATCH',{'quantity':'784','version':'1'},'bad',key=str(uuid.uuid4())),403,'Preset save requires CSRF')
    for value in ['0','-2','1.5','9223372036854775808']:
        expect(save(viewer,own,value),422,'Invalid standard load rejected')
    key=str(uuid.uuid4());version=profile(own)['load_version']
    saved=expect(save(viewer,own,'784',version,key),200,'Commander configures own standard load')
    check(saved['standard_load_quantity']=='784','Individual load saved as exact integer string')
    check(save(viewer,own,'784',version,key)[1]['replayed'],'Preset retry is idempotent')
    expect(save(operator,own,'700',version),409,'Stale preset edit cannot overwrite latest value')
    expect(save(operator,other,'100'),200,'Operator sets another individual load')
    check(profile(own)['standard_load_quantity']=='784','Other commander preset is independent')
    system=expect(write(operator,'systems','POST',{'name':'Quick load isolated project'}),201,'Create isolated quick-load project')['id']
    place=expect(write(operator,'installations&system_id='+system,'POST',{'name':'Quick load facility'}),201,'Create isolated quick-load facility')['id']
    expect(write(operator,f'requirement&installation_id={place}&commodity_id=1','PUT',{'required_quantity':'1500','version':'0','reason':'Quick-load test'}),201,'Create isolated quick-load goal')
    route=f'deliveries&installation_id={place}&commodity_id=1'
    subtract=f'quick/withdraw&installation_id={place}&commodity_id=1'
    def amount():return anonymous.call('system&id='+system)[1]['data']['requirements'][0]['delivered_quantity']
    def history():return viewer.call('movements&installation_id='+place)[1]['data']['items']
    preset={'commander_id':own,'use_standard_load':True}
    key=str(uuid.uuid4())
    first=expect(write(viewer,route,'POST',preset,key),201,'Register a full standard load')
    check(first['delta_quantity']=='784' and first['commander_id']==own and first['created_by_user_id']==viewer.session()['user']['id'],'Standard load preserves commander and registering user attribution')
    expect(save(viewer,own,'700'),200,'Modify standard load')
    replay=write(viewer,route,'POST',preset,key)
    check(replay[1]['replayed'] and replay[1]['data']['delta_quantity']=='784' and amount()=='784','Retry after preset change replays original quantity')
    expect(save(viewer,own,'784'),200,'Restore test load')
    second=expect(write(viewer,route,'POST',{'commander_id':own,'quantity':'200'}),201,'Register custom quantity without modal')
    third=expect(write(viewer,route,'POST',preset),201,'Register consecutive load with same commander')
    check(amount()=='1768' and third['exceeds_pending'],'Oversupply is explicit and exact amount remains intact')
    expect(write(viewer,route,'POST',preset|{'quantity':'1'}),422,'Manual and standard amount cannot be mixed')
    expect(write(viewer,route,'POST',{'commander_id':other,'quantity':'1'}),201,'Commander can deliver on behalf of another')
    expect(write(viewer,subtract,'POST',{'commander_id':other,'quantity':'1','reason':'Third party correction'}),201,'Commander can subtract another commander deliveries')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'quantity':'1769','reason':''}),409,'Optional reason still checks available stock')
    key=str(uuid.uuid4());body={'commander_id':own,'quantity':'800','reason':'Correct an oversized delivery'}
    correction=expect(write(viewer,subtract,'POST',body,key),201,'Subtract across multiple original deliveries')
    check(len(correction['movement_ids'])==2 and amount()=='968','Subtraction is split into linked compensations')
    check(write(viewer,subtract,'POST',body,key)[1]['replayed'] and amount()=='968','Repeated correction does not subtract twice')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'use_standard_load':True,'reason':'One standard load correction'}),201,'Subtract a full standard load')
    check(amount()=='184','Standard withdrawal immediately reflected in totals')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'quantity':'185','reason':'Too much'}),409,'Cannot subtract more than delivered')
    expect(write(operator,subtract,'POST',{'commander_id':other,'quantity':'1','reason':'Wrong commander'}),409,'Even operator cannot consume a different commander balance')
    expect(write(operator,subtract,'POST',{'commander_id':own,'quantity':'184','reason':'Authorized third-party correction'}),201,'Operator can correct selected third party')
    check(amount()=='0','Balance reaches zero without becoming negative')
    items=history();originals={row['id']:row for row in items if row['id'] in [first['id'],second['id'],third['id']]}
    check([originals[row['id']]['delta_quantity'] for row in [first,second,third]]==['784','200','784'],'Original delivery amounts are immutable')
    reversals=[row for row in items if row['movement_type']=='reversal' and row['commander_id']==own]
    check(all(row['reverses_movement_id'] in originals and row['commander_id']==own and row['reason'] for row in reversals),'Every subtraction has original link, commander, actor, timestamp and reason')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'use_standard_load':True,'reason':'Below zero'}),409,'Cannot subtract a load from zero')
    expect(save(viewer,own,None),200,'Commander can clear standard load')
    expect(write(viewer,route,'POST',preset),409,'Missing preset never assumes a global quantity')
    manual={'commander_id':own,'quantity':'10'}
    key=str(uuid.uuid4());expect(write(viewer,route,'POST',manual,key),201,'Manual delivery remains available without standard load')
    check(write(viewer,route,'POST',manual,key)[1]['replayed'],'Manual delivery retry remains compatible')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'quantity':'1'}),201,'Stock subtraction needs no reason field')
    check(history()[0]['reason']=='Resta de stock desde panel de material','Subtraction preserves an automatic audit description')
    expect(write(viewer,route,'POST',{'commander_id':own,'quantity':'1'}),201,'Restore exact quantity after optional-reason test')
    command('tests/recipe-inspect.php','fail-audit')
    try:expect(write(operator,subtract,'POST',{'commander_id':own,'quantity':'5','reason':'Atomic rollback'}),503,'Audit failure rejects entire correction')
    finally:command('tests/recipe-inspect.php','clear-failure')
    check(amount()=='10','Failed audit leaves every original balance intact')
    expect(save(viewer,own,'784'),200,'Preset restored for concurrency checks')
    expect(write(viewer,route,'POST',preset),201,'New load available for concurrent corrections')
    directory=root/'.local/test-artifacts'/('quick-race-'+uuid.uuid4().hex);directory.mkdir();barrier=directory/'go';processes=[]
    try:
        for index,client in enumerate([admin,operator]):
            job={'action':'quick_withdraw','actor_id':client.session()['user']['id'],'key':str(uuid.uuid4()),'installation_id':place,'commodity_id':'1','body':{'commander_id':own,'quantity':'700','reason':'Concurrent correction'},'barrier':str(barrier)}
            file=directory/(str(index)+'.json');file.write_text(json.dumps(job),encoding='utf-8')
            processes.append(subprocess.Popen([php,'tests/planning-worker.php',str(file)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
        deadline=time.monotonic()+10
        while len(list(directory.glob('*.ready')))<2:
            if time.monotonic()>deadline:raise AssertionError('Quick withdrawal concurrency barrier timed out')
            time.sleep(.02)
        barrier.write_text('go');results=[]
        for process in processes:
            out,err=process.communicate(timeout=20)
            if process.returncode:raise AssertionError('Quick worker failed: '+err.decode())
            results.append(json.loads(out))
        check(sorted(row['status'] for row in results)==[201,409] and amount()=='94','Concurrent corrections cannot overdraw shared ledger balance')
    finally:
        for process in processes:
            if process.poll() is None:process.terminate();process.wait(timeout=10)
    expect(save(viewer,own,'9223372036854775807'),200,'Standard load supports exact signed BIGINT maximum')
    expect(write(viewer,route,'POST',preset),201,'Maximum load preserves precision beyond JavaScript Number')
    check(amount()=='9223372036854775901','Aggregate balances can exceed one movement maximum without rounding')
    expect(write(viewer,subtract,'POST',{'commander_id':own,'use_standard_load':True,'reason':'Precision correction'}),201,'Maximum standard withdrawal spans originals exactly')
    check(amount()=='94','Maximum load round trip preserves original balance')
    expect(save(viewer,own,'784'),200,'Restore normal individual load after precision test')
    html=viewer.page('/?page=system&id='+system)[1]
    check('data-quick-row' in html and 'quick-material-panel' in html and 'data-quick="add-load"' in html and 'data-quick-commander' in html,'Authenticated table exposes a shared stock panel and commander selector')
    check('data-quick="add-remaining">Cargar faltante' in html,'Settlement stock panel offers completion below manual quantity')
    check('Entrega detallada / carrier' not in html and 'data-quick-reason' not in html,'Stock rows omit carrier delivery and reason controls')
    check('data-quick-row' not in anonymous.page('/?page=system&id='+system)[1],'Public table has no quick mutation controls')
    check(all(value in html for value in ['data-balance="delivered_quantity"','data-quick-progress','quick-load.js?v=']),'Rows and percentages expose live refresh hooks and versioned JavaScript')
