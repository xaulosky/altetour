import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import {spawn, spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const frontend=path.join(root,'frontend');
const windows=process.platform==='win32';
const flags=new Set(process.argv.slice(2));
const children=new Set();
let stopping=false;

function command(line, options={}){
  // Comandos constantes: cwd se pasa separado para admitir carpetas con espacios.
  return spawn(windows?'cmd.exe':'sh',windows?['/d','/s','/c',line]:['-c',line],{
    cwd:root,stdio:'inherit',windowsHide:true,...options,
  });
}

function run(line,options={}){
  return new Promise((resolve,reject)=>{
    const child=command(line,options);
    let output='';
    child.stdout?.on('data',chunk=>{output+=chunk.toString();process.stdout.write(chunk);});
    children.add(child);
    child.once('error',reject);
    child.once('exit',code=>{
      children.delete(child);
      code===0?resolve(output):reject(new Error(`Fallo el comando: ${line}`));
    });
  });
}

async function available(port){
  // Windows puede permitir otro bind junto a una escucha en 0.0.0.0.
  // Primero comprobar conexiones evita confundir un servidor existente con el nuevo.
  const occupied=await new Promise(resolve=>{
    const socket=net.createConnection({port,host:'127.0.0.1'});
    const finish=value=>{socket.destroy();resolve(value);};
    socket.once('connect',()=>finish(true));
    socket.once('error',error=>finish(error.code!=='ECONNREFUSED'));
    socket.setTimeout(1000,()=>finish(true));
  });
  if(occupied)return false;
  return new Promise((resolve,reject)=>{
    const server=net.createServer();
    server.once('error',error=>{
      if(error.code==='EADDRINUSE'||error.code==='EACCES')resolve(false);
      else reject(error);
    });
    server.listen({port,host:'127.0.0.1',exclusive:true},()=>server.close(()=>resolve(true)));
  });
}

function stop(code=0){
  if(stopping)return;
  stopping=true;
  console.log('\nCerrando los servidores iniciados por este archivo...');
  for(const child of children){
    if(!child.pid)continue;
    if(windows){
      // Solo termina el arbol de procesos creado por este lanzador.
      spawnSync('taskkill',['/PID',String(child.pid),'/T','/F'],{stdio:'ignore',windowsHide:true});
    }else child.kill('SIGTERM');
  }
  children.clear();
  process.exit(code);
}

function watch(child,name){
  children.add(child);
  child.once('error',error=>{console.error(`${name}: ${error.message}`);stop(1);});
  child.once('exit',code=>{
    children.delete(child);
    if(!stopping){console.error(`${name} se detuvo (codigo ${code}).`);stop(code||1);}
  });
}

async function ready(url,validate){
  const deadline=Date.now()+120_000;
  while(Date.now()<deadline){
    try{
      const response=await fetch(url,{signal:AbortSignal.timeout(3000)});
      if(response.ok&&await validate(response))return;
    }catch{}
    await new Promise(resolve=>setTimeout(resolve,500));
  }
  throw new Error(`El servidor no respondio a tiempo: ${url}`);
}

process.on('SIGINT',()=>stop());
process.on('SIGTERM',()=>stop());

try{
  console.log('\nALTE TOUR - Desarrollo local\n');
  const [major,minor]=process.versions.node.split('.').map(Number);
  if(major<22||(major===22&&minor<12))throw new Error('Se requiere Node.js 22.12 o superior.');
  console.log(`Node.js ${process.versions.node}`);
  const phpVersion=await run('php --version',{stdio:['inherit','pipe','inherit']});
  const phpMatch=phpVersion.match(/PHP (\d+)\.(\d+)/);
  if(!phpMatch||Number(phpMatch[1])<8||(Number(phpMatch[1])===8&&Number(phpMatch[2])<3))throw new Error('Se requiere PHP 8.3 o superior.');
  await run(windows?'npm.cmd --version':'npm --version');
  if(flags.has('--check')){
    console.log('\nRequisitos disponibles. Puedes ejecutar iniciar-sitio.bat.');
  }else{
    if(!await available(8766))throw new Error('El puerto PHP 8766 esta ocupado. Cierra el servidor que lo usa y vuelve a ejecutar este archivo.');
    let port=4321;
    while(port<=4340&&!await available(port))port++;
    if(port>4340)throw new Error('No hay puertos disponibles entre 4321 y 4340.');

    const astro=path.join(frontend,'node_modules','astro','bin','astro.mjs');
    if(!fs.existsSync(astro)){
      console.log('\nInstalando las dependencias del frontend...');
      const install=fs.existsSync(path.join(frontend,'package-lock.json'))?'ci':'install';
      await run(`${windows?'npm.cmd':'npm'} ${install}`,{cwd:frontend});
    }
    const env=path.join(frontend,'.env');
    if(!fs.existsSync(env))fs.copyFileSync(path.join(frontend,'.env.example'),env);

    console.log('\nPreparando el almacenamiento local...');
    await run('php backend/bin/install.php --demo');
    console.log('\nIniciando PHP y Astro. Espera a que ambos esten disponibles...');
    watch(command('php -S 127.0.0.1:8766 -t backend/public'),'PHP');
    watch(spawn(process.execPath,[astro,'dev','--host','127.0.0.1','--port',String(port),'--strictPort'],{
      cwd:frontend,stdio:'inherit',windowsHide:true,
    }),'Astro');
    const url=`http://127.0.0.1:${port}/`;
    await Promise.all([
      ready(`${url}api/index.php?route=csrf`,async response=>typeof (await response.json()).token==='string'),
      ready(url,async response=>(await response.text()).includes('Alte Tour')),
    ]);
    console.log(`\nSitio listo: ${url}\nPanel: ${url}admin/\nBackend: http://127.0.0.1:8766/\n\nDeja esta ventana abierta. Usa Ctrl+C para detener ambos servidores.\n`);
    if(flags.has('--smoke-test'))stop();
    if(!flags.has('--no-open')){
      const opener=windows?command(`start "" "${url}"`):spawn('xdg-open',[url],{stdio:'ignore'});
      opener.on('error',()=>console.log(`Abre el navegador en ${url}`));
    }
  }
}catch(error){
  console.error(`\nERROR: ${error.message}`);
  console.error('Comprueba que Node.js, npm y PHP 8.3 o superior esten instalados y disponibles en PATH.');
  stop(1);
}
