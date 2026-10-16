import shutil
"""Pruebas de la vista portable. Instalar playwright y disponer de Chromium."""
from playwright.sync_api import sync_playwright
from pathlib import Path
import json,re
root=Path(__file__).resolve().parents[1];qa=root/'qa';qa.mkdir(exist_ok=True)
results=[]
def check(name,ok):
 assert ok,name
 results.append({'test':name,'result':'PASS'})
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=shutil.which('chromium') or shutil.which('chromium-browser'),headless=True,args=['--no-sandbox'])
 page=b.new_page(viewport={'width':1440,'height':1000},device_scale_factor=1)
 errors=[];page.on('pageerror',lambda x:errors.append(str(x)))
 page.set_content((root/'altetour-vista-previa.html').read_text(),wait_until='load')
 routes=page.evaluate('Object.keys(ALTE_PAGES)')
 check('Nueve páginas navegables',len(routes)==9)
 for width in [1440,1024,768,390,360]:
  page.set_viewport_size({'width':width,'height':900})
  for route in routes:
   page.evaluate('(r)=>portableNavigate(r)',route);page.wait_for_timeout(30)
   check(f'Sin desborde horizontal {width}px {route}',not page.evaluate('document.documentElement.scrollWidth>innerWidth'))
   check(f'Una cabecera h1 {width}px {route}',page.locator('main h1').count()==1)
  # Comprobar recurso de portada y logo; todo el HTML portable contiene data URIs.
 check('Todas las imágenes usan datos locales',page.evaluate("[...document.images].every(i=>i.src.startsWith('data:'))"))
 page.set_viewport_size({'width':1440,'height':1000});page.evaluate("portableNavigate('/viajes/')")
 page.locator('[data-trip-search]').fill('Turquía');check('Búsqueda encuentra Turquía',page.locator('[data-trip-card]:visible').count()==1)
 page.locator('[data-trip-search]').fill('Noruega');check('Búsqueda sin programa muestra estado vacío',page.locator('.empty-state').is_visible())
 page.locator('[data-trip-search]').fill('');page.locator('[data-filter=nacional]').click();check('Filtro Chile no inventa programas',page.locator('.empty-state').is_visible())
 page.locator('[data-filter=all]').click();page.get_by_role('link',name='Explorar el viaje',exact=True).click()
 check('Navegación al programa',page.evaluate('window.__ALTE_ROUTE__')=='/viajes/turquia-2027/')
 check('Diez días de itinerario',page.locator('.day').count()==10)
 page.locator('[data-expand-days]').click();check('Expandir todo',page.locator('.day[open]').count()==10)
 page.locator('[data-expand-days]').click();check('Contraer todo',page.locator('.day[open]').count()==0)
 page.locator('[data-poster]').click();check('Afiche abre como diálogo',page.locator('dialog[open]').count()==1)
 page.locator('.close-dialog').click();check('Afiche se cierra',page.locator('dialog[open]').count()==0)
 page.evaluate("portableNavigate('/contacto/?interes=misiones')")
 check('Preselección de interés corporativo',page.locator('select[name=interest]').input_value()=='misiones')
 form=page.locator('[data-lead-form]')
 form.locator('[name=name]').fill('Prueba de interfaz')
 form.locator('[name=email]').fill('interfaz@example.test')
 form.locator('[name=phone]').fill('+56 9 0000 0000')
 form.locator('[name=message]').fill('Consulta ficticia para revisión local.')
 form.locator('[name=consent]').check()
 form.locator('[type=submit]').click()
 check('Vista portable no finge guardar consultas','no guarda ni envía' in form.locator('.form-status').inner_text())
 check('Consulta preparada en enlace WhatsApp','Prueba%20de%20interfaz' in form.locator('.form-wa-fallback').get_attribute('href'))
 page.set_viewport_size({'width':390,'height':844});page.evaluate("portableNavigate('/')")
 page.locator('.menu-toggle').click();check('Menú móvil abre',page.locator('#main-nav').is_visible() and page.locator('.menu-toggle').get_attribute('aria-expanded')=='true')
 page.locator('#main-nav a[href="/nosotros/"]').click();check('Menú móvil navega y cierra',page.evaluate('window.__ALTE_ROUTE__')=='/nosotros/' and page.locator('.menu-toggle').get_attribute('aria-expanded')=='false')
 check('Sin errores JavaScript',not errors)
 # Capturas limpias sin etiqueta superpuesta sobre el contenido.
 page.locator('.portable-badge').evaluate('(e)=>e.remove()')
 page.set_viewport_size({'width':1440,'height':1000});page.evaluate("portableNavigate('/')");page.wait_for_timeout(150)
 page.screenshot(path=str(qa/'home-desktop-final.png'),full_page=True)
 page.screenshot(path=str(root.parent/'altetour-diseno-inicio.png'))
 page.evaluate("portableNavigate('/viajes/turquia-2027/')");page.wait_for_timeout(100)
 page.screenshot(path=str(qa/'turquia-desktop-final.png'),full_page=True)
 page.set_viewport_size({'width':390,'height':844});page.evaluate("portableNavigate('/')");page.wait_for_timeout(100)
 page.screenshot(path=str(qa/'home-mobile-final.png'),full_page=True)
 page.evaluate("portableNavigate('/viajes/turquia-2027/')");page.wait_for_timeout(100)
 page.screenshot(path=str(qa/'turquia-mobile-final.png'),full_page=True)
 # El panel se probó por HTTP; renderizar su HTML recuperado solo para inspección visual.
 admin=qa/'admin-program.html'
 if admin.exists():
  html=admin.read_text().replace('<link rel="stylesheet" href="/admin/admin.css">','<style>'+ (root/'backend/public/admin/admin.css').read_text()+'</style>')
  page.set_viewport_size({'width':1440,'height':1000});page.set_content(html)
  page.screenshot(path=str(qa/'admin-program.png'),full_page=True)
 b.close()
(qa/'frontend-results.json').write_text(json.dumps({'checks':results,'javascript_errors':errors},ensure_ascii=False,indent=2))
print(f'{len(results)} pruebas de interfaz aprobadas.')
