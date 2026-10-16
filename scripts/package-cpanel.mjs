import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const demo=process.argv.includes('--preview');
const publicSource=path.join(root,demo?'preview':'frontend/dist');
if(!fs.existsSync(path.join(publicSource,'index.html'))){
  console.error('Falta frontend/dist. Ejecuta primero npm install && npm run build en frontend. Para una instalación de ensayo del render portable: --preview.');process.exit(1);
}
const target=path.join(root,'deploy');
// Este directorio es una salida de compilación LOCAL, nunca una ruta remota.
fs.rmSync(target,{recursive:true,force:true});
fs.mkdirSync(target,{recursive:true});
fs.cpSync(publicSource,path.join(target,'public_html'),{recursive:true});
fs.cpSync(path.join(root,'backend/public'),path.join(target,'public_html'),{recursive:true});
for(const name of ['src','bin','config']){
 fs.cpSync(path.join(root,'backend',name),path.join(target,'altetour-app',name),{recursive:true,filter:src=>!src.endsWith('config.local.php')});
}
fs.mkdirSync(path.join(target,'altetour-app/storage'),{recursive:true});
fs.writeFileSync(path.join(target,'altetour-app/storage/.gitkeep'),'');
function walk(dir,prefix=''){return fs.readdirSync(dir,{withFileTypes:true}).flatMap(d=>d.isDirectory()?walk(path.join(dir,d.name),prefix+d.name+'/'):[prefix+d.name]);}
fs.writeFileSync(path.join(target,'manifest-publico.json'),JSON.stringify(walk(path.join(target,'public_html')),null,2));
fs.writeFileSync(path.join(target,'LEEME.txt'),`ALTE TOUR · ${demo?'PAQUETE DE ENSAYO / RENDER COMPARTIDO, NO COMPILADO POR ASTRO':'SALIDA DE ASTRO + PHP'}\n\npublic_html/: solamente archivos públicos.\naltetour-app/: fuera de public_html, junto a él en /home/USUARIO/.\nConfigurar MySQL, clave, entorno y administrador según docs/CPANEL.md.\nNo contiene credenciales, sesiones, ni datos de pruebas.\nNo reemplazar el config.local.php ni el storage existentes en una actualización.\nNo activar indexación ni campañas antes de aprobar contenido y probar el hosting.\n`);
console.log('Paquete generado en deploy/. Modo:',demo?'ensayo':'build de Astro');
