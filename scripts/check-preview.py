import shutil
from playwright.sync_api import sync_playwright
from pathlib import Path
root=Path(__file__).resolve().parents[1]
out=root/'qa';out.mkdir(exist_ok=True)
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=shutil.which('chromium') or shutil.which('chromium-browser'),headless=True,args=['--no-sandbox'])
 page=b.new_page(viewport={'width':1440,'height':1000},device_scale_factor=1)
 errs=[];page.on('pageerror',lambda ex:errs.append(str(ex)))
 page.set_content((root/'altetour-vista-previa.html').read_text(), wait_until='load');page.wait_for_timeout(400)
 page.screenshot(path=str(out/'home-desktop.png'),full_page=True)
 page.get_by_role('link',name='Explorar el viaje',exact=True).click();page.wait_for_timeout(200)
 page.screenshot(path=str(out/'turquia-desktop.png'),full_page=True)
 print('Desktop overflow',page.evaluate('document.documentElement.scrollWidth > innerWidth'))
 page.set_viewport_size({'width':390,'height':844})
 page.evaluate("portableNavigate('/')");page.wait_for_timeout(200)
 page.screenshot(path=str(out/'home-mobile.png'),full_page=True)
 page.evaluate("portableNavigate('/viajes/turquia-2027/')");page.wait_for_timeout(200)
 page.screenshot(path=str(out/'turquia-mobile.png'),full_page=True)
 print('Mobile overflow',page.evaluate('document.documentElement.scrollWidth > innerWidth'))
 print('JS errors',errs)
 b.close()
