"""Pruebas locales, con registros ficticios. No realiza conexiones externas."""
import requests,subprocess,secrets,uuid,re,json,csv,io,time
from pathlib import Path
root=Path(__file__).resolve().parents[1]
(root/'qa').mkdir(exist_ok=True)
base='http://127.0.0.1:8766'
password=secrets.token_urlsafe(24)
subprocess.run(['php',str(root/'backend/bin/create-admin.php'),'qa@example.test'],input=password+'\n',text=True,check=True,capture_output=True)
checks=[]
def check(name,condition):
 assert condition, name
 checks.append({'test':name,'result':'PASS'})
s=requests.Session()
r=s.get(base+'/api/index.php?route=csrf');check('CSRF token disponible',r.status_code==200 and len(r.json()['token'])==64)
token=r.json()['token'];headers={'X-CSRF-Token':token}
lead={'name':'Consulta de prueba <script>NO_EJECUTAR</script>','email':'qa@example.test','phone':'+56 9 0000 0000','interest':'turquia','travelers':'2','room':'double','program':'turquia-2027','message':'Consulta ficticia utilizada únicamente para pruebas locales.','website':'','source':'/viajes/turquia-2027/','utm':{'utm_campaign':'qa-local'},'consent':True,'idempotency_key':str(uuid.uuid4())}
r=s.post(base+'/api/index.php?route=leads',json=lead);check('POST sin CSRF rechazado',r.status_code==419)
r=s.post(base+'/api/index.php?route=leads',json={**lead,'email':'no-valido'},headers=headers);check('Validación del correo en servidor',r.status_code==422)
r=s.post(base+'/api/index.php?route=leads',json={**lead,'consent':False},headers=headers);check('Consentimiento obligatorio en servidor',r.status_code==422)
r=s.post(base+'/api/index.php?route=leads',json=lead,headers=headers);check('Consulta persistida',r.status_code==201 and r.json()['reference'].startswith('AT-'))
reference=r.json()['reference']
r=s.post(base+'/api/index.php?route=leads',json=lead,headers=headers);check('Reintento idempotente sin duplicar',r.status_code==201 and r.json()['reference']==reference)
r=s.post(base+'/api/index.php?route=leads',json={**lead,'name':'Nombre distinto'},headers=headers);check('Misma clave con distinto contenido rechazada',r.status_code==409)
r=s.post(base+'/api/index.php?route=leads',json={**lead,'idempotency_key':str(uuid.uuid4()),'program':'no-existe'},headers=headers);check('Programa inválido rechazado',r.status_code==422)
r=s.post(base+'/api/index.php?route=leads',json={**lead,'message':'x'*15000},headers=headers);check('Límite de tamaño',r.status_code==413)
r=s.get(base+'/api/index.php?route=programs');check('La API pública no filtra notas internas',r.status_code==200 and 'editorialWarnings' not in r.text)
anon=requests.Session();r=anon.get(base+'/admin/?view=export',allow_redirects=False);check('CSV protegido por autenticación',r.status_code==302)
r=s.get(base+'/admin/?view=login');check('Pantalla de acceso',r.status_code==200 and 'Entrar al panel' in r.text)
csrf=re.search(r'name="_csrf" value="([a-f0-9]+)"',r.text).group(1)
r=s.post(base+'/admin/?view=login',data={'_csrf':csrf,'action':'login','email':'qa@example.test','password':'equivocada'});check('Credenciales inválidas rechazadas',r.status_code==200 and 'Correo o contraseña incorrectos' in r.text)
r=s.post(base+'/admin/?view=login',data={'_csrf':csrf,'action':'login','email':'qa@example.test','password':password},allow_redirects=False);check('Acceso administrador válido',r.status_code==302)
r=s.get(base+'/admin/');check('Escape de HTML en datos de consultas',r.status_code==200 and '&lt;script&gt;NO_EJECUTAR' in r.text and '<script>NO_EJECUTAR' not in r.text)
(root/'qa/admin-page.html').write_text(r.text)
csrf=re.search(r'name="_csrf" value="([a-f0-9]+)"',r.text).group(1)
id=re.search(r'view=lead&id=([a-f0-9]+)',r.text).group(1)
r=s.post(base+'/admin/?view=lead&id='+id,data={'_csrf':csrf,'action':'status','id':id,'status':'cotizado'},allow_redirects=False);check('Cambio de estado autenticado',r.status_code==302)
r=s.get(base+'/admin/?view=export');check('CSV autenticado exportado',r.status_code==200 and 'text/csv' in r.headers.get('Content-Type','') and reference in r.text)
check('CSV neutraliza prefijos de fórmula en teléfono',"'+56 9 0000 0000" in r.text)
r=s.get(base+'/admin/?view=programs');check('Panel del programa disponible',r.status_code==200 and 'Exportar programs.json' in r.text)
(root/'qa/admin-program.html').write_text(r.text)
original=s.get(base+'/admin/?view=export-programs').json()[0]
r=s.post(base+'/admin/?view=programs',data={'_csrf':csrf,'action':'program','slug':original['slug'],'title':original['title'],'subtitle':original['subtitle'],'status':'published','price_double':'3760','price_single':'4015','price_triple':'3750'},allow_redirects=False);check('Edición del precio persistida',r.status_code==302)
updated=s.get(base+'/admin/?view=export-programs').json()[0];check('Exportación refleja precio actualizado',updated['prices']['double']==3760)
s.post(base+'/admin/?view=programs',data={'_csrf':csrf,'action':'program','slug':original['slug'],'title':original['title'],'subtitle':original['subtitle'],'status':original['status'],'price_double':str(original['prices']['double']),'price_single':str(original['prices']['single']),'price_triple':str(original['prices']['triple'])})
r=s.post(base+'/admin/?view=lead&id='+id,data={'_csrf':csrf,'action':'delete','id':id,'confirmation':'ELIMINAR'},allow_redirects=False);check('Eliminación exige autenticación y confirmación',r.status_code==302)
r=s.get(base+'/admin/?view=lead&id='+id);check('Consulta eliminada del almacenamiento activo',r.status_code==404)
r=s.post(base+'/admin/',data={'_csrf':csrf,'action':'logout'},allow_redirects=False);check('Cierre de sesión',r.status_code==302)
r=s.get(base+'/admin/?view=export',allow_redirects=False);check('Sesión cerrada sin acceso a exportación',r.status_code==302)
(root/'qa/backend-results.json').write_text(json.dumps({'adapter':'JSON local, no MySQL','checks':checks},ensure_ascii=False,indent=2))
print(f'{len(checks)} pruebas del backend aprobadas (adaptador JSON local).')
