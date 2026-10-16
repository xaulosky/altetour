import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {getPages,header,footer,e} from '../frontend/src/lib/render.mjs';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const site=JSON.parse(read('frontend/src/data/site.json'));
const programs=JSON.parse(read('frontend/src/data/programs.json'));
const pages=getPages(site,programs);
const css=read('frontend/src/styles/global.css');
const runtime=read('frontend/src/scripts/site.js').replace(/export function /g,'function ');
const fullHead=page=>`<!doctype html><html lang="es-CL"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#082c40"><meta name="description" content="${e(page.description)}"><title>${e(page.title)}</title><link rel="icon" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/site.css"></head><body>`;
fs.mkdirSync(path.join(root,'preview'),{recursive:true});
fs.cpSync(path.join(root,'frontend/public/assets'),path.join(root,'preview/assets'),{recursive:true});
fs.writeFileSync(path.join(root,'preview/assets/site.css'),css);
fs.writeFileSync(path.join(root,'preview/assets/site.js'),runtime+'\nboot();');
for(const [url,page] of Object.entries(pages)){
 const dir=path.join(root,'preview',url);fs.mkdirSync(dir,{recursive:true});
 fs.writeFileSync(path.join(dir,'index.html'),fullHead(page)+header(site)+`<main id="main" tabindex="-1">${page.html}</main>`+footer(site,programs)+'<script src="/assets/site.js" defer></script></body></html>');
}
fs.writeFileSync(path.join(root,'preview/robots.txt'),'User-agent: *\nDisallow: /\n');
const missing={title:'Página no encontrada · Alte Tour',description:'Esta página no está disponible.'};
fs.writeFileSync(path.join(root,'preview/404.html'),fullHead(missing)+header(site)+'<main id="main"><section class="inner-hero"><div class="wrap"><p class="eyebrow">404 · UNA RUTA DIFERENTE</p><h1>Volvamos a<br><em>encontrar el camino.</em></h1><p>La página que buscas no está disponible.</p><a class="btn btn-primary" href="/">Volver al inicio →</a></div></section></main>'+footer(site,programs)+'<script src="/assets/site.js" defer></script></body></html>');

// Demostración autocontenida: sin red, sin dependencias y sin envío de datos.
let portable=fullHead(pages['/']).replace('<link rel="stylesheet" href="/assets/site.css">',`<style>${css}</style>`)+header(site)+`<main id="main" tabindex="-1">${pages['/'].html}</main>`+footer(site,programs)+`<div class="portable-badge"><strong>ALTE TOUR</strong> · Propuesta v1 · Sin envío de datos <button aria-label="Ocultar aviso de vista previa" onclick="this.parentElement.remove()">×</button></div>`;
const templateData=JSON.stringify(pages).replace(/</g,'\\u003c');
portable+=`<script>window.__ALTE_PORTABLE__=true;window.__ALTE_ROUTE__='/';const ALTE_PAGES=${templateData};\n${runtime}\n
function portableNavigate(destination,initial=false){
 const u=new URL(destination,'https://demo.local');
 let route=u.pathname;if(!route.endsWith('/'))route+='/';
 const page=ALTE_PAGES[route];if(!page)return false;
 window.__ALTE_ROUTE__=route;window.__ALTE_QUERY__=u.search;
 document.querySelector('main').innerHTML=page.html;document.title=page.title;
 if(!initial)history.pushState({},'', '#!'+route+u.search+u.hash);
 window.dispatchEvent(new Event('alte:navigate'));
 if(u.hash){requestAnimationFrame(()=>document.getElementById(decodeURIComponent(u.hash.slice(1)))?.scrollIntoView({behavior:'auto'}));}else window.scrollTo(0,0);
 return true;
}
document.addEventListener('click',ev=>{const a=ev.target.closest('a');if(!a)return;const href=a.getAttribute('href');if(!href)return;
 if(href.startsWith('/')){ev.preventDefault();portableNavigate(href);}
 else if(href.startsWith('#')&&!href.startsWith('#!')){ev.preventDefault();document.getElementById(href.slice(1))?.scrollIntoView({behavior:'smooth'});}
});
window.addEventListener('popstate',()=>portableNavigate(location.hash.startsWith('#!')?location.hash.slice(2):'/',true));
boot();if(location.hash.startsWith('#!'))portableNavigate(location.hash.slice(2),true);
</script></body></html>`;
// Sustituir recursos en HTML directo y en el JSON de las páginas, sin imágenes externas.
for(const filename of fs.readdirSync(path.join(root,'preview/assets'))){
 if(!/\.(jpg|webp|svg)$/.test(filename))continue;
 const data=fs.readFileSync(path.join(root,'preview/assets',filename));
 const mime=filename.endsWith('.svg')?'image/svg+xml':filename.endsWith('.jpg')?'image/jpeg':'image/webp';
 portable=portable.replaceAll('/assets/'+filename,`data:${mime};base64,${data.toString('base64')}`);
}
const dest=path.join(root,'altetour-vista-previa.html');
fs.writeFileSync(dest,portable);
console.log(`Vista previa: ${Object.keys(pages).length} páginas; HTML portable ${Math.round(fs.statSync(dest).size/1024)} KB`);
