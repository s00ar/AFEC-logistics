"""Markets: real CSVs, idempotency, timestamps, permissions and ledger isolation."""
import csv
import io
import json
import os
import subprocess
import time
import urllib.parse
import uuid


def run_market_checks(anonymous, admin, viewer, operator, check, command, root, env, php):
    stock=(root/'resources/markets/AFEC_mercados_stock_confirmado_2026-10-15.csv').read_bytes()
    summary=(root/'resources/markets/AFEC_mercados_resumen_2026-10-15.csv').read_bytes()
    def expect(response,status,label):
        check(response[0]==status,label+' (status '+str(response[0])+')')
        return response[1].get('data')
    def stats(): return json.loads(command('tests/market-inspect.php','stats'))
    def upload(client,action='preview',stock_data=stock,summary_data=summary,review=None,key=None,token=None,extra=None):
        boundary='afecmarket'+uuid.uuid4().hex
        parts=[]
        fields={'action':action}
        if action=='apply':fields.update(review_hash=review or '',confirmed='1')
        fields.update(extra or {})
        for name,value in fields.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        for name,value in [('stock_file',stock_data),('summary_file',summary_data)]:
            if value is not None:
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{name}.csv"\r\nContent-Type: text/csv\r\n\r\n'.encode()+value+b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        return client.call('markets/import','POST',token=token if token is not None else client.session()['csrf_token'],raw=b''.join(parts),content_type='multipart/form-data; boundary='+boundary,key=key or str(uuid.uuid4()))
    def one(changes=None):
        rows=list(csv.DictReader(io.StringIO(stock.decode('utf-8-sig'))))
        row=rows[0]|(changes or {})
        output=io.StringIO(newline='');writer=csv.DictWriter(output,fieldnames=list(rows[0]));writer.writeheader();writer.writerow(row)
        return output.getvalue().encode()
    def write(kind,identity,body,key=None,client=admin):
        return client.call('markets/'+kind+'&id='+identity,'PATCH',body,client.session()['csrf_token'],key=key or str(uuid.uuid4()))
    def listing(query=''):
        rows=[];page=1
        while True:
            response=viewer.call('markets&list_page='+str(page)+query)
            expect(response,200,'Authenticated market page '+str(page))
            rows.extend(response[1]['data'])
            if not response[1]['pagination']['has_more']:return rows
            page+=1
    expect(anonymous.call('markets'),401,'Market data requires a session')
    expect(viewer.call('markets/imports'),403,'Commander cannot read import administration')
    expect(upload(viewer),403,'Commander cannot import')
    expect(upload(operator),403,'Operator cannot import commercial inventory')
    expect(upload(admin,token='invalid'),403,'Upload requires CSRF')
    before=stats()
    preview=expect(upload(admin),200,'Actual attached CSVs validate via multipart API')
    check((preview['stock_rows'],preview['summary_rows'],preview['stations'],preview['products'])==(197,4,4,93),'Real source counts reconcile')
    check(preview['mapped']==45 and len(preview['unmatched'])==48,'Existing aliases map construction goods without inventing catalogue entries')
    check(stats()==before,'Preview does not write data')
    expect(upload(admin,stock_data=stock.replace(b'oferta_t',b'wrong_header')),422,'Unknown CSV schema rejected')
    expect(upload(admin,stock_data=one({'oferta_t':'-1'}),summary_data=None),422,'Negative stock rejected')
    expect(upload(admin,stock_data=one({'oferta_t':'18446744073709551616'}),summary_data=None),422,'Quantity overflow rejected')
    expect(upload(admin,stock_data=one({'oferta_t':'1.5'}),summary_data=None),422,'Fractional stock rejected')
    expect(upload(admin,stock_data=one({'fecha_fuente':'2026-02-30'}),summary_data=None),422,'Invalid calendar date rejected')
    expect(upload(admin,stock_data=one({'fecha_fuente':'2999-01-01'}),summary_data=None),422,'Future source rejected')
    expect(upload(admin,stock_data=one({'disponible_para_compra':'MAYBE'}),summary_data=None),422,'Unknown availability rejected')
    expect(upload(admin,stock_data=one({'disponible_para_compra':'NO'}),summary_data=None),422,'Contradictory quantity and purchase flag rejected')
    expect(upload(admin,stock_data=one({'verificacion':'Inventado'}),summary_data=None),422,'Unknown verification state rejected')
    expect(upload(admin,stock_data=stock+one().split(b'\r\n',1)[1]),422,'Duplicate station/product rows rejected')
    expect(upload(admin,summary_data=summary.replace(b',48,',b',47,')),422,'Summary counts must reconcile with supplied stock')
    expect(upload(admin,stock_data=None,extra={'system':'Col 359 Sector TN-A c1-20'}),200,'Summary-only import has explicit system context')
    expect(upload(admin,stock_data=None),422,'Summary without a known system is rejected')
    expect(upload(admin,stock_data=b'\xffinvalid'),422,'Non UTF-8 CSV rejected')
    malformed=one().rstrip(b'\r\n').removesuffix(b'Confirmado en captura')+b'"Confirmado en captura'
    expect(upload(admin,stock_data=malformed,summary_data=None),422,'Unclosed CSV quotes rejected before import')
    expect(upload(admin,action='apply',review='0'*64),409,'Apply requires the reviewed content fingerprint')
    command('tests/recipe-inspect.php','fail-audit')
    try:expect(upload(admin,'apply',review=preview['review_hash']),503,'Audit failure rolls back entire import')
    finally:command('tests/recipe-inspect.php','clear-failure')
    check(stats()==before,'Import rollback leaves all commercial and operational tables unchanged')
    key=str(uuid.uuid4())
    created=expect(upload(admin,'apply',review=preview['review_hash'],key=key),200,'Real market CSVs imported')
    check(created['applied']==197 and created['summary_rows']==4,'Every supplied observation and summary stored')
    seeded=stats()
    check((seeded['market_stations'],seeded['market_products'],seeded['market_stock'],seeded['market_observations'],seeded['market_imports'])==(4,93,197,197,1),'Unique keys preserve the expected source cardinality')
    check(all(seeded[k]==before[k] for k in before if k.endswith('_hash')),'Import preserves all construction, identity and catalogue records')
    replay=upload(admin,'apply',review=preview['review_hash'],key=key)
    check(replay[0]==200 and replay[1]['replayed'] and stats()==seeded,'Same-key replay returns the original receipt')
    duplicate=expect(upload(admin,'apply',review=preview['review_hash']),200,'Same CSV with a new request key is safe')
    check(duplicate['duplicate_import'] and stats()==seeded,'Content uniqueness prevents duplicate import records')
    rows=listing()
    check(len(rows)==197 and len({(r['station_id'],r['product_id']) for r in rows})==197,'Pagination returns every unique station/product observation')
    source_rows=list(csv.DictReader(io.StringIO(stock.decode('utf-8-sig'))))
    actual={(r['system_name'],r['station_name'],r['original_name']):r for r in rows}
    check(all(actual[(r['sistema'],r['estacion'],r['mercaderia'])]['quantity']==r['oferta_t'] and actual[(r['sistema'],r['estacion'],r['mercaderia'])]['observed_on']==r['fecha_fuente'] for r in source_rows),'Every stored quantity and date matches the original CSV exactly')
    check(all(r['economy'] is None and r['max_pad'] is None for r in rows),'Economies and pads absent from CSV remain unknown')
    check(len(listing('&q=Steel'))==1 and len(listing('&q=Acero'))==1,'Search supports original names and linked Spanish material names')
    check(not listing('&ship_size=L'),'Unknown pads are not falsely advertised as Large')
    options=expect(viewer.call('markets/options'),200,'Filter metadata available to commanders')
    check(sorted(int(s['summary_count']) for s in options['stations'])==[38,48,50,61],'All four station summaries persist')
    first=next(r for r in rows if r['original_name']=='Food Cartridges' and r['station_name']=='Ciudadela Orbital AFEC Prime')
    station=next(s for s in options['stations'] if s['id']==first['station_id'])
    metadata={'version':station['version'],'economy':'High Tech','max_pad':'L','observed_on':'2026-10-15','source':'Synthetic metadata verification','reason':'Test large ship filter'}
    updated_station=expect(write('station',station['id'],metadata),200,'Administrator can declare verified station metadata')
    check(len(listing('&ship_size=L'))==48 and len(listing('&economy=High%20Tech'))==48,'Large and economy filters reflect declared metadata')
    expect(write('station',station['id'],metadata),409,'Concurrent metadata edit rejected')
    expect(write('station',station['id'],metadata|{'version':updated_station['version'],'observed_on':'2026-10-14'}),409,'Older station metadata rejected')
    expect(write('station',station['id'],metadata|{'version':updated_station['version'],'max_pad':'X'}),422,'Invalid pad rejected')
    expect(write('station',station['id'],metadata,client=operator),403,'Operator cannot edit station metadata')
    older=one({'fecha_fuente':'2026-10-14','oferta_t':'999'})
    prior=expect(upload(admin,stock_data=older,summary_data=None),200,'Older CSV validates independently')
    old=expect(upload(admin,'apply',stock_data=older,summary_data=None,review=prior['review_hash']),200,'Older CSV retained as history')
    check(old['older_ignored']==1 and old['applied']==0,'Older observations never overwrite current stock')
    same=one({'oferta_t':'999'})
    prior=expect(upload(admin,stock_data=same,summary_data=None),200,'Same-day conflicting CSV validates')
    conflict=expect(upload(admin,'apply',stock_data=same,summary_data=None,review=prior['review_hash']),200,'Same-day conflict is traced')
    check(conflict['same_date_conflicts']==1 and conflict['applied']==0,'Same-day conflicting values never silently replace current data')
    check(len(listing())==197,'Partial files do not erase omitted products')
    body={'version':first['version'],'station_id':first['station_id'],'quantity':'18446744073709551615','available':True,'verified':True,'observed_on':'2026-10-15','source':'<script>alert(1)</script>','reason':'Synthetic manual stock update'}
    key=str(uuid.uuid4())
    manual=expect(write('stock',first['product_id'],body,key),200,'Manual stock supports exact unsigned BIGINT values')
    check(manual['quantity']=='18446744073709551615','Stock quantity remains an exact decimal string')
    response=write('stock',first['product_id'],body,key)
    check(response[0]==200 and response[1]['replayed'],'Manual retry cannot double apply a stock update')
    expect(write('stock',first['product_id'],body),409,'Stale stock version rejected')
    expect(write('stock',first['product_id'],body|{'version':manual['version'],'observed_on':'2026-10-14'}),409,'Old manual stock rejected')
    expect(write('stock',first['product_id'],body|{'version':manual['version'],'quantity':'-1'}),422,'Negative manual stock rejected')
    expect(write('stock',first['product_id'],body|{'version':manual['version']},client=viewer),403,'Commander cannot edit commercial stock')
    history=expect(admin.call('markets/history&station_id='+first['station_id']+'&product_id='+first['product_id']),200,'Observation history visible to administrator')
    check(len(history)==4 and sum(not int(r['applied']) for r in history)==2,'History retains old, conflicting and manual observations')
    # Two independent administrator actors import the same new content concurrently.
    actors=[admin.session()['user']['id'],next(u['id'] for u in admin.call('admin/users')[1]['data'] if u['username']=='seed_admin')]
    directory=root/'.local/test-artifacts'/('market-race-'+uuid.uuid4().hex);directory.mkdir()
    barrier=directory/'start';workers=[]
    for actor in actors:
        path=directory/(actor+'.json')
        path.write_text(json.dumps({'actor_id':actor,'key':str(uuid.uuid4()),'stock':one({'estacion':'Race test'}).decode(),'barrier':str(barrier)}),encoding='utf-8')
        workers.append(subprocess.Popen([php,'tests/market-worker.php',str(path)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8',creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
    deadline=time.monotonic()+8
    while len(list(directory.glob('*.ready')))<2:
        if time.monotonic()>deadline:raise AssertionError('Market workers did not reach the barrier')
        time.sleep(.01)
    barrier.write_text('start')
    results=[]
    for process in workers:
        output,error=process.communicate(timeout=25)
        check(process.returncode==0,'Independent administrator market worker completed')
        results.append(json.loads(output))
    check(len({r['data']['import_id'] for r in results})==1 and sum(bool(r['data'].get('duplicate_import')) for r in results)==1,'Concurrent imports serialize to one dataset across actors')
    sample=next(r for r in rows if r['commodity_id'] is None)
    target=next(r['commodity_id'] for r in rows if r['commodity_id'] is not None)
    mapped=expect(write('product',sample['product_id'],{'version':sample['product_version'],'commodity_id':target,'reason':'Synthetic equivalence'}),200,'Administrator maps an unmatched original name')
    check(mapped['original_name']==sample['product_name'] and mapped['commodity_id']==target,'Equivalence preserves original material name')
    for changes,status in [({'estacion':'Unknown test','oferta_t':'','fecha_fuente':'','verificacion':'Sin verificar'},'unknown'),({'estacion':'Stale test','fecha_fuente':'2000-01-01'},'stale'),({'estacion':'Zero test','oferta_t':'0','disponible_para_compra':'NO'},'confirmed')]:
        data=one(changes);prior=expect(upload(admin,stock_data=data,summary_data=None),200,'Valid boundary observation preview')
        expect(upload(admin,'apply',stock_data=data,summary_data=None,review=prior['review_hash']),200,'Boundary observation stored')
        found=listing('&station_id='+next(s['id'] for s in viewer.call('markets/options')[1]['data']['stations'] if s['name']==changes['estacion']))
        check(found[0]['stock_status']==status,'Freshness distinguishes '+status+' from other states')
        if status=='unknown':check(found[0]['quantity'] is None,'Unknown stock is not invented as zero')
        if changes['estacion']=='Zero test':check(found[0]['quantity']=='0','Explicit zero remains known zero')
    expect(viewer.call('markets&missing=1'),422,'Missing-provider search requires a system')
    expect(viewer.call('markets&missing=1&system='+urllib.parse.quote('Sistema <script>alert(1)</script>')),200,'Required materials without system suppliers can be queried')
    expect(viewer.call('markets&ship_size=INVALID'),422,'Invalid ship filter rejected')
    html=admin.page('/?page=markets')[1]
    check('Mercados del Sistema' in html and 'market-import-form' in html and '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html,'Markets admin HTML escapes source text')
    check('market-import-form' not in viewer.page('/?page=markets')[1],'Import UI is restricted to administrators')
    check('Dónde conseguir' in viewer.page('/?page=system&id=1')[1],'Required material table links to system market providers')
    after=stats()
    check(all(after[k]==before[k] for k in before if k.endswith('_hash')),'All market imports and edits leave operational tables byte-for-byte intact')
    command('tests/market-inspect.php','schema-off')
    try:
        check('pendiente de habilitar' in admin.page('/?page=markets')[1],'Missing migration produces an actionable markets page')
        expect(admin.call('markets'),503,'Market API refuses an incomplete schema')
        expect(anonymous.call('systems'),200,'Other modules remain available without markets migration')
    finally:command('tests/market-inspect.php','schema-on')
