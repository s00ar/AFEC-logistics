"""Covered objectives remain readable without hiding outstanding destinations or leaking private lists."""
from html.parser import HTMLParser
import uuid


class Disclosures(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.installations={}; self.completed=None; self.feed(html)

    def handle_starttag(self, tag, attrs):
        row=dict(attrs)
        if tag!='details': return
        if 'data-materials-installation' in row: self.installations[row['data-materials-installation']]=row
        if 'data-completed-materials' in row: self.completed=row


def run_completed_material_checks(anonymous, admin, viewer, check):
    def write(route, body, method='POST'):
        result=viewer.call(route,method,body,viewer.session()['csrf_token'],key=str(uuid.uuid4()))
        check(result[0] in [200,201], 'Prepare covered-material scenario: '+route.split('&')[0]+' (status '+str(result[0])+')')
        return result[1]['data']

    material=write('commodities',{'code':'covered_'+uuid.uuid4().hex,'name_es':'ZZZ Material cubierto de pruebas'})['id']
    other=write('commodities',{'code':'pending_'+uuid.uuid4().hex,'name_es':'ZZZ Material pendiente de pruebas'})['id']
    system=write('systems',{'name':'Colapso de materiales de pruebas'})['id']
    places=[]
    for name in ['Instalación cubierta','Instalación pendiente']:
        place=write('installations&system_id='+system,{'name':name})['id'];places.append(place)
        for product,quantity in [(material,'100'),(other,'50')]:
            write(f'requirement&installation_id={place}&commodity_id={product}',{'required_quantity':quantity,'version':'0','reason':'Objetivo de prueba de colapso'},'PUT')
        for scope in ['personal','fleet']:write('wishlist/entries&installation_id='+place,{'scope':scope})
    write(f'deliveries&installation_id={places[0]}&commodity_id={material}',{'quantity':'220'})
    write(f'deliveries&installation_id={places[0]}&commodity_id={other}',{'quantity':'50'})

    def listing(client,scope,kind):
        page=1;items=[]
        while True:
            query='completed_page' if kind=='completed' else 'list_page'
            result=client.call(f'wishlist&scope={scope}&{query}={page}')
            check(result[0]==200,'Read paginated '+kind+' for '+scope)
            data=result[1]['data'][kind];items+=data['items']
            if not data['pagination']['has_more']:return items
            page+=1

    check(material not in [r['commodity_id'] for r in listing(viewer,'personal','completed')], 'Surplus at one destination cannot cover another destination')
    before=Disclosures(anonymous.page('/?page=system&id='+system)[1]).installations
    check('open' not in before[places[0]] and 'open' in before[places[1]],'Only an installation with all its materials covered starts collapsed publicly')
    progress=anonymous.call('system&id='+system)[1]['data']['installations']
    covered=next(r for r in progress if r['id']==places[0]);pending=next(r for r in progress if r['id']==places[1])
    check(covered['materials_complete'] and covered['effective_construction_status']=='completed' and covered['construction_status']=='under_construction','Covered materials report completed construction without rewriting the manual status')
    check(not pending['materials_complete'] and pending['effective_construction_status']=='under_construction','An incomplete installation keeps its construction status')
    write(f'deliveries&installation_id={places[1]}&commodity_id={material}',{'use_remaining':True})
    for scope in ['personal','fleet']:
        completed=listing(viewer,scope,'completed');row=next(r for r in completed if r['commodity_id']==material)
        check(row['required_quantity']=='200' and row['delivered_quantity']=='320' and row['surplus_quantity']=='120','Completed totals preserve exact requirements, deliveries and surplus in '+scope)
        check(other not in [r['commodity_id'] for r in completed],'A commodity with one unfilled destination stays outside completed materials')
        page=1
        while True:
            result=viewer.call(f'wishlist&scope={scope}&completed_page={page}')[1]['data']['completed']
            if any(r['commodity_id']==material for r in result['items']): break
            check(result['pagination']['has_more'],'Completed material can be reached through its own pagination')
            page+=1
        html=viewer.page(f'/?page=wishlist&scope={scope}')[1]
        check(Disclosures(html).completed is not None and 'open' not in Disclosures(html).completed,'Completed list materials stay collapsed by default in '+scope)
        explicit=viewer.page(f'/?page=wishlist&scope={scope}&completed_page={page}')[1]
        check('ZZZ Material cubierto de pruebas' in explicit and 'open' in Disclosures(explicit).completed,'Explicit completed pagination reveals the requested material')
    check(material not in [r['commodity_id'] for r in listing(admin,'personal','completed')],'Completed materials do not leak another account personal list')
    check(material in [r['commodity_id'] for r in listing(admin,'fleet','completed')],'Authenticated fleet members share the covered-material view')
    check(viewer.call('wishlist&scope=personal&completed_page=0')[0]==422,'Completed pagination rejects invalid pages')
    check(viewer.call('wishlist&scope=personal&completed_page=1&user_id=1')[0]==422,'Completed pagination cannot select another account')

    saved=write(f'deliveries&installation_id={places[1]}&commodity_id={other}',{'use_remaining':True})
    full=Disclosures(viewer.page('/?page=system&id='+system)[1]).installations
    check(all('open' not in full[place] for place in places),'Authenticated view collapses both installations after the last material')
    commander=saved['commander_id']
    write(f'quick/withdraw&installation_id={places[1]}&commodity_id={other}',{'quantity':'1','commander_id':commander})
    corrected=Disclosures(viewer.page('/?page=system&id='+system)[1]).installations
    check('open' in corrected[places[1]] and 'open' not in corrected[places[0]],'A compensating correction reopens only the installation with a new deficit')
    progress=anonymous.call('system&id='+system)[1]['data']['installations']
    corrected=next(r for r in progress if r['id']==places[1])
    check(not corrected['materials_complete'] and corrected['effective_construction_status']=='under_construction','Correction returns automatic completion to under construction')
    check(other not in [r['commodity_id'] for r in listing(viewer,'personal','completed')],'Corrected material returns to pending instead of staying in completed')
    version=viewer.call('installation&id='+places[1])[1]['data']['version']
    write('installation/status&id='+places[1],{'construction_status':'completed','version':version,'reason':'Cierre manual de prueba con faltante','confirmed':True},'PATCH')
    progress=anonymous.call('system&id='+system)[1]['data']['installations']
    manual=next(r for r in progress if r['id']==places[1])
    check(not manual['materials_complete'] and manual['effective_construction_status']=='completed','Explicit manual completion with deficits remains respected')
