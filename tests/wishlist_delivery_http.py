"""Delivery actions target the selected destination and update both wishlist scopes."""
from html.parser import HTMLParser
import json
import os
import subprocess
import time
import uuid

class DeliveryButtons(HTMLParser):
    def __init__(self, text):
        super().__init__(); self.records=[]; self.material_rows=[]; self.feed(text)
    def handle_starttag(self, tag, attrs):
        data=dict(attrs)
        if tag=='li' and 'data-quick-row' in data:
            self.records.append(json.loads(data['data-record']))
        if tag=='tr' and 'data-material' in data:self.material_rows.append(data)

def run_wishlist_delivery_checks(anonymous,admin,viewer,operator,check,command,env,php,root):
    def write(client,route,body,key=None,method='POST'):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    material=expect(write(admin,'commodities',{'code':'wish_delivery_'+uuid.uuid4().hex,'name_es':'Material de entrega desde listas'}),201,'Create isolated wishlist delivery material')['id']
    system=expect(write(viewer,'systems',{'name':'Destinos de entrega desde listas'}),201,'Create wishlist delivery system')['id']
    places=[]
    for name,quantity in [('Settlement primero','100'),('Settlement segundo','200')]:
        place=expect(write(viewer,'installations&system_id='+system,{'name':name}),201,'Create named settlement for delivery');places.append(place['id'])
        expect(write(viewer,f"requirement&installation_id={place['id']}&commodity_id={material}",{'required_quantity':quantity,'version':'0','reason':'Fixture de entrega desde listas'},method='PUT'),201,'Set settlement delivery objective')
        for scope in ['personal','fleet']:
            expect(write(viewer,'wishlist/entries&installation_id='+place['id'],{'scope':scope}),200,'Select delivery destination in '+scope)
    records=[]
    def material_page(client,scope):
        page=1
        while True:
            data=client.call('wishlist&scope='+scope+'&list_page='+str(page))[1]['data']['commodities']
            row=next((row for row in data['items'] if row['commodity_id']==material),None)
            if row:return page,row
            assert data['pagination']['has_more'],'Expected delivery material in wishlist'
            page+=1
    for scope in ['personal','fleet']:
        page,_=material_page(viewer,scope)
        html=viewer.page('/?page=wishlist&scope='+scope+'&list_page='+str(page))[1]
        found=[row for row in DeliveryButtons(html).records if row['commodity_id']==material]
        check({row['installation_id'] for row in found}==set(places),'Quick rows identify both actual '+scope+' destinations')
        check(all(row['installation_name'] and row['commodity_name'] and row['system_name'] for row in found),'Shared panel receives destination and commodity context')
        check('data-cargo-adjust' not in html,'On-board increments do not replace actual delivery controls')
        check('data-quick-list' in html and html.count('id="quick-material-panel"')==1 and 'js/quick-load.js?v=' in html,'Wishlist reuses one installation stock panel and its module')
        check(all('data-quick="'+action+'"' in html for action in ['add-load','subtract-load','add','subtract','add-remaining']),'Shared wishlist panel exposes standard, manual and full remaining operations')
        check('data-action="delivery-create"' not in html,'Destination rows replace separate delivery buttons')
        records+=found
    route=f'deliveries&installation_id={records[0]["installation_id"]}&commodity_id={records[0]["commodity_id"]}'
    expect(write(anonymous,route,{'quantity':'40'}),401,'Anonymous cannot submit list delivery')
    expect(viewer.call(route,'POST',{'quantity':'40'},'invalid',key=str(uuid.uuid4())),403,'Wishlist delivery requires CSRF')
    before=viewer.call('carrier/loads&scope=personal')[1]['data']
    key=str(uuid.uuid4()); payload={'quantity':'40','reason':'Registrar entrega recibida'}
    receipt=write(viewer,route,payload,key)
    saved=expect(receipt,201,'Record actual unloaded material from list destination')
    check(saved['installation_id']==places[0] and saved['commodity_id']==material and saved['delta_quantity']=='40','Movement belongs to exactly the chosen settlement and material')
    replay=write(viewer,route,payload,key)
    check(replay[0]==201 and replay[1]['replayed'] and replay[1]['data']['id']==saved['id'],'Uncertain list delivery retry returns the same movement')
    def remaining(client,scope):
        return material_page(client,scope)[1]['remaining_quantity']
    check(remaining(viewer,'personal')=='260' and remaining(viewer,'fleet')=='260','One real delivery updates personal and shared fleet deficits')
    needs=viewer.call('wishlist/destinations&scope=personal&commodity_id='+material)[1]['data']['items']
    check({row['installation_id']:row['remaining_quantity'] for row in needs}=={places[0]:'60',places[1]:'200'},'No automatic distribution into other settlements')
    check(viewer.call('carrier/loads&scope=personal')[1]['data']==before,'Actual delivery does not create or alter on-board cargo')
    second=f'deliveries&installation_id={places[1]}&commodity_id={material}'
    commander=operator.call('logistics/options')[1]['data']['commanders'][0]['id']
    third=expect(write(operator,second,{'quantity':'20','commander_id':commander}),201,'Commander records fleet delivery for a named commander')
    check(third['commander_id']==commander and third['created_by_user_id']==operator.session()['user']['id'],'Delivered-by identity stays separate from actor')
    check(remaining(viewer,'personal')=='240' and remaining(operator,'fleet')=='240','Shared actual delivery updates both views regardless of registering account')
    command('tests/recipe-inspect.php','fail-audit')
    try:expect(write(viewer,second,{'quantity':'5'}),503,'Failed delivery audit rolls back movement from list flow')
    finally:command('tests/recipe-inspect.php','clear-failure')
    check(remaining(viewer,'personal')=='240','Failed delivery leaves list progress intact')
    expect(write(viewer,route,{'quantity':'0'}),422,'Zero received quantity rejected')
    expect(write(viewer,route,{'quantity':'-1'}),422,'Negative delivery does not rewrite previous stock')

    fill={'use_remaining':True}
    expect(write(anonymous,route,fill),401,'Anonymous cannot complete a requirement')
    expect(viewer.call(route,'POST',fill,'invalid',key=str(uuid.uuid4())),403,'Complete remaining requires CSRF')
    for value in ['true',1,None]:
        expect(write(viewer,route,{'use_remaining':value}),422,'Completion flag requires a boolean')
    expect(write(viewer,route,fill|{'quantity':'1'}),422,'Completion cannot combine with a manual amount')
    expect(write(viewer,route,fill|{'use_standard_load':True}),422,'Completion cannot combine with a standard load')
    command('tests/recipe-inspect.php','fail-audit')
    try:expect(write(viewer,route,fill),503,'Completion audit failure rolls back the full delivery')
    finally:command('tests/recipe-inspect.php','clear-failure')
    check(remaining(viewer,'personal')=='240','Failed completion preserves actual progress')
    expect(write(operator,route,{'quantity':'10'}),201,'Another delivery changes previously displayed pending')
    key=str(uuid.uuid4())
    completed=expect(write(viewer,route,fill,key),201,'Complete actual current pending after another delivery')
    check(completed['delta_quantity']=='50' and not completed['exceeds_pending'],'Completion calculates the current missing amount under lock')
    check(remaining(viewer,'personal')=='180' and remaining(viewer,'fleet')=='180','Completion updates both lists without distributing to another installation')
    progress=anonymous.call('system&id='+system)[1]['data']['requirements']
    row=next(row for row in progress if row['installation_id']==places[0] and row['commodity_id']==material)
    check(row['delivered_quantity']=='100' and row['remaining_quantity']=='0' and row['surplus_quantity']=='0','Same completion is reflected in the individual installation table')
    for client in [anonymous,viewer]:
        rows=[row for row in DeliveryButtons(client.page('/?page=system&id='+system)[1]).material_rows if row['data-material']==material]
        check([('requirement-complete' in row.get('class','').split()) for row in rows]==[True,False],'Complete row is highlighted after reload; another pending destination stays normal')
    repeated=write(viewer,route,fill,key)
    check(repeated[1]['replayed'] and repeated[1]['data']['id']==completed['id'],'Completion replay returns the original receipt after fulfillment')
    count=len(viewer.call('movements&installation_id='+places[0])[1]['data']['items'])
    expect(write(viewer,route,fill),409,'Already fulfilled material creates no new delivery')
    check(len(viewer.call('movements&installation_id='+places[0])[1]['data']['items'])==count,'No zero or surplus movement on a new completion attempt')

    directory=root/'.local/test-artifacts'/('complete-race-'+uuid.uuid4().hex);directory.mkdir();barrier=directory/'go';processes=[]
    try:
        for index,client in enumerate([admin,operator]):
            job={'action':'deliver','actor_id':client.session()['user']['id'],'key':str(uuid.uuid4()),'installation_id':places[1],'commodity_id':material,'body':fill,'barrier':str(barrier)}
            file=directory/(str(index)+'.json');file.write_text(json.dumps(job),encoding='utf-8')
            processes.append(subprocess.Popen([php,'tests/planning-worker.php',str(file)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
        deadline=time.monotonic()+10
        while len(list(directory.glob('*.ready')))<2:
            if time.monotonic()>deadline:raise AssertionError('Completion concurrency barrier timed out')
            time.sleep(.02)
        barrier.write_text('go');results=[]
        for process in processes:
            out,err=process.communicate(timeout=20)
            if process.returncode:raise AssertionError('Completion worker failed: '+err.decode())
            results.append(json.loads(out))
        check(sorted(result['status'] for result in results)==[201,409],'Concurrent completions serialize without oversupply')
        winner=next(result for result in results if result['status']==201)
        check(winner['data']['delta_quantity']=='180','Concurrent completion records only the actual missing amount')
    finally:
        for process in processes:
            if process.poll() is None:process.terminate();process.wait(timeout=5)
    row=next(row for row in anonymous.call('system&id='+system)[1]['data']['requirements'] if row['installation_id']==places[1] and row['commodity_id']==material)
    check(row['delivered_quantity']=='200' and row['remaining_quantity']=='0' and row['surplus_quantity']=='0','Concurrent completion leaves the individual goal exact')

    goalroute=f'requirement&installation_id={places[0]}&commodity_id={material}'
    expect(write(viewer,goalroute,{'required_quantity':'9007199254741993','version':'1','reason':'Precision completion'},method='PUT'),200,'Set a completion goal beyond JavaScript Number precision')
    precise=expect(write(viewer,route,fill),201,'Complete a large exact pending quantity')
    check(precise['delta_quantity']=='9007199254741893','Completion preserves integer precision above Number maximum')
    expect(write(viewer,goalroute,{'required_quantity':'18446744073709551615','version':'2','reason':'Completion movement limit'},method='PUT'),200,'Set a goal above the movement maximum')
    expect(write(viewer,route,fill),422,'Oversized completion cannot overflow one signed movement')
