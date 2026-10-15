"""Cargo stays separate from deliveries and follows the current wishlist destination order."""
import uuid


def run_carrier_checks(anonymous,admin,viewer,operator,check,command):
    def write(client,route,method,body,key=None):
        return client.call(route,method,body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    def cargo(client=viewer,scope='personal'):
        return expect(client.call('carrier/loads&scope='+scope),200,'Read own cargo allocation')
    def amounts(load): return [row['allocated_quantity'] for row in load['allocations']]
    expect(anonymous.call('carrier/loads&scope=personal'),401,'Cargo requires login')
    expect(write(anonymous,'carrier/load','POST',{}),401,'Anonymous cannot record cargo')
    expect(viewer.call('carrier/loads&scope=personal&user_id=1'),422,'Cargo read cannot target another account')
    expect(viewer.call('carrier/loads&scope=unknown'),422,'Invalid list scope rejected')
    material=expect(write(admin,'commodities','POST',{'code':'cargo_'+uuid.uuid4().hex,'name_es':'Acero de prueba de carrier'}),201,'Create isolated cargo material')['id']
    system=expect(write(operator,'systems','POST',{'name':'Carrier allocation test'}),201,'Create isolated cargo project')['id']
    places=[]
    for name,quantity in [('A','1000'),('B','500'),('C','1500')]:
        place=expect(write(operator,'installations&system_id='+system,'POST',{'name':name}),201,'Create carrier destination '+name)
        places.append(place)
        expect(write(operator,f"requirement&installation_id={place['id']}&commodity_id={material}",'PUT',{'required_quantity':quantity,'version':'0','reason':'Fixture'}),201,'Create destination objective')
        for scope in ['personal','fleet']:
            expect(write(viewer,'wishlist/entries&installation_id='+place['id'],'POST',{'scope':scope}),200,'Select destination '+scope)
    body={'scope':'personal','carrier_name':'Carrier A '+uuid.uuid4().hex,'carrier_code':'T-'+uuid.uuid4().hex[:8],'commodity_id':material,'quantity':'3000','version':'0'}
    expect(viewer.call('carrier/load','POST',body,'wrong',key=str(uuid.uuid4())),403,'Cargo write requires CSRF')
    key=str(uuid.uuid4())
    saved=expect(write(viewer,'carrier/load','POST',body,key),200,'Record cargo without recording delivery')
    check(write(viewer,'carrier/load','POST',body,key)[1]['replayed'],'Cargo creation retry is idempotent')
    carrier=saved['carrier_id']
    def load(): return next(row for row in cargo() if row['carrier_id']==carrier and row['commodity_id']==material)
    check(amounts(load())==['1000','500','1500'],'3000 t automatically split as 1000, 500 and 1500')
    check(not any(row['carrier_id']==carrier for row in cargo(operator)),'Own cargo is private to its account')
    check(not any(row['carrier_id']==carrier for row in cargo(scope='fleet')),'Cargo belongs to one selected list at a time')
    data=anonymous.call('system&id='+system)[1]['data']
    check(all(row['delivered_quantity']=='0' for row in data['requirements']),'Cargo is never counted as delivered stock')
    def update(quantity,**changes):
        current=load()
        return write(viewer,'carrier/load','POST',{'scope':'personal','carrier_id':carrier,'commodity_id':material,'quantity':quantity,'version':current['version']}|changes)
    expect(update('1200'),200,'Replace on-board total with a partial load')
    check(amounts(load())==['1000','200'],'Insufficient cargo fills each destination before the next')
    stale={'scope':'personal','carrier_id':carrier,'commodity_id':material,'quantity':'1','version':'1'}
    expect(write(viewer,'carrier/load','POST',stale),409,'Stale cargo update rejected')
    first=places[0]['id']
    selections=viewer.call('wishlist&scope=personal')[1]['data']['selected']['items']
    selection=next(row for row in selections if row['installation_id']==first)
    expect(write(viewer,'wishlist/entry&installation_id='+first,'DELETE',{'scope':'personal','version':selection['version'],'reason':'Fixture removal'}),200,'Remove first destination')
    check(amounts(load())==['500','700'],'Removing a list entry immediately recalculates allocation')
    expect(write(viewer,'wishlist/entries&installation_id='+first,'POST',{'scope':'personal'}),200,'Re-add first destination')
    check(amounts(load())==['1000','200'],'Adding a list entry automatically restores destination order')
    expect(update('4000'),200,'Cargo may exceed the list needs')
    check(amounts(load())==['1000','500','1500'] and load()['unallocated_quantity']=='1000','Surplus stays aboard without invented destination')
    expect(write(viewer,f'deliveries&installation_id={first}&commodity_id={material}','POST',{'quantity':'100'}),201,'Register separate actual delivery')
    check(amounts(load())==['900','500','1500'] and load()['unallocated_quantity']=='1100','Actual delivery updates the current destination deficits')
    expect(update('600'),200,'Prepare split across two carriers')
    second=expect(write(viewer,'carrier/load','POST',{'scope':'personal','carrier_name':'Carrier B '+uuid.uuid4().hex,'commodity_id':material,'quantity':'1200','version':'0'}),200,'Record second carrier')['carrier_id']
    second_load=next(row for row in cargo() if row['carrier_id']==second)
    check(amounts(second_load)==['300','500','400'],'Two own carriers do not allocate the same destination deficit twice')
    command('tests/recipe-inspect.php','fail-audit')
    try: expect(update('601'),503,'Audit failure rejects the entire cargo update')
    finally: command('tests/recipe-inspect.php','clear-failure')
    check(load()['quantity']=='600','Audit rollback preserves previous cargo total')
    for quantity in ['-1','1.2','1e3','9223372036854775808']:
        expect(update(quantity),422,'Invalid cargo quantity rejected')
    expect(update('9007199254740993'),200,'Cargo retains precision above JavaScript integer limit')
    check(load()['quantity']=='9007199254740993','Cargo quantity survives exact string roundtrip')
    requirement=next(row for row in anonymous.call('system&id='+system)[1]['data']['requirements'] if row['installation_id']==first)
    expect(write(operator,f'requirement&installation_id={first}&commodity_id={material}','PUT',{'required_quantity':'18446744073709551615','version':requirement['version'],'reason':'Unsigned precision fixture'}),200,'Goal can exceed a signed integer')
    expect(update('9223372036854775807'),200,'First carrier contributes maximum exact cargo')
    second_load=next(row for row in cargo() if row['carrier_id']==second)
    expect(write(viewer,'carrier/load','POST',{'scope':'personal','carrier_id':second,'commodity_id':material,'quantity':'9223372036854775807','version':second_load['version']}),200,'Second carrier fills a wider aggregated deficit')
    second_load=next(row for row in cargo() if row['carrier_id']==second)
    check(amounts(second_load)==['9223372036854775708','99'],'Allocation retains unsigned objective precision across carriers')
    expect(update('0'),200,'Zero clears the current on-board total')
    check(load()['quantity']=='0' and load()['allocations']==[],'Empty cargo has no allocations')
    expect(write(viewer,'carrier/load','POST',body|{'carrier_id':carrier}),422,'Cannot mix existing and new carrier identities')
    expect(write(viewer,'carrier/load','POST',body|{'actor_user_id':'1'}),422,'Cannot forge cargo owner')
    html=viewer.page('/?page=wishlist&scope=personal')[1]
    check('data-carrier-form' in html and 'carrier-load.js?v=' in html,'Carrier total and allocation live on the wishlist page')
    check('Entrega detallada / carrier' not in viewer.page('/?page=system&id='+system)[1],'No detailed carrier control on stock rows')
