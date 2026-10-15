"""Delivery actions target the selected destination and update both wishlist scopes."""
from html.parser import HTMLParser
import json
import uuid

class DeliveryButtons(HTMLParser):
    def __init__(self, text):
        super().__init__(); self.records=[]; self.feed(text)
    def handle_starttag(self, tag, attrs):
        data=dict(attrs)
        if tag=='button' and data.get('data-action')=='delivery-create':
            self.records.append(json.loads(data['data-record']))

def run_wishlist_delivery_checks(anonymous,admin,viewer,operator,check,command):
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
        check({row['installation_id'] for row in found}==set(places),'Delivery buttons identify both actual '+scope+' destinations')
        check(all(row['installation_name'] in row['name'] and row['commodity_name'] in row['name'] for row in found),'Delivery dialog identifies destination and commodity')
        check('data-cargo-adjust' not in html,'On-board increments do not replace actual delivery controls')
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
