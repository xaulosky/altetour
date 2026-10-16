import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {getPages,header,footer,e} from '../frontend/src/lib/render.mjs';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const output=path.join(root,'pages-dist');
const base=(process.env.PAGES_BASE_PATH||'/altetour').replace(/\/$/,'');
if(base&&!/^\/[a-zA-Z0-9_-]+$/.test(base))throw new Error('Invalid Pages base path');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const site=JSON.parse(read('frontend/src/data/site.json'));
const programs=JSON.parse(read('frontend/src/data/programs.json'));
const pages=getPages(site,programs);
pages['/privacidad/'].html=pages['/privacidad/'].html.replace(
  /<h2>Dónde se registra<\/h2>[\s\S]*?(?=<h2>Enlaces externos<\/h2>)/,
  '<h2>Cómo se prepara tu consulta</h2><p>Esta versión estática no registra consultas en un servidor ni utiliza un panel administrativo. El formulario prepara un mensaje que puedes revisar y enviar mediante WhatsApp. Los datos escritos no se guardan en esta web ni se envían automáticamente. Al continuar por WhatsApp, se aplican las condiciones y políticas de ese servicio.</p>',
);
fs.mkdirSync(path.join(output,'assets'),{recursive:true});
fs.cpSync(path.join(root,'frontend/public/assets'),path.join(output,'assets'),{recursive:true});
fs.copyFileSync(path.join(root,'frontend/src/styles/global.css'),path.join(output,'assets/site.css'));
fs.writeFileSync(path.join(output,'assets/site.js'),read('frontend/src/scripts/site.js').replace(/export function /g,'function ')+'\nboot();');

function render(route,page){
  const hero=/class="(?:hero|trip-hero|about-hero)"/.test(page.html);
  const prelude=`window.__ALTE_STATIC__=true;window.__ALTE_BASE__=${JSON.stringify(base)};window.__ALTE_ROUTE__=${JSON.stringify(route)};`;
  let html=`<!doctype html><html lang="es-CL"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${e(page.title)}</title><meta name="description" content="${e(page.description)}"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#082c40"><meta property="og:title" content="${e(page.title)}"><meta property="og:description" content="${e(page.description)}"><link rel="icon" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/site.css"></head><body${hero?' class="has-hero-nav"':''}>${header(site)}<main id="main" tabindex="-1">${page.html}</main>${footer(site,programs)}<script>${prelude}</script><script src="/assets/site.js" defer></script></body></html>`;
  html=html.replace(/(href|src)="\/(?!\/)/g,`$1="${base}/`);
  html=html.replace(/srcset="([^"]*)"/g,(_,value)=>`srcset="${value.replaceAll('/assets/',`${base}/assets/`)}"`);
  return html;
}
for(const [route,page] of Object.entries(pages)){
  const directory=path.join(output,route);
  fs.mkdirSync(directory,{recursive:true});
  fs.writeFileSync(path.join(directory,'index.html'),render(route,page));
}
fs.writeFileSync(path.join(output,'404.html'),render('/404/',{
  title:'Página no encontrada · Alte Tour',description:'La página que buscas no está disponible.',
  html:'<section class="inner-hero"><div class="wrap"><p class="eyebrow">404</p><h1>Volvamos a encontrar el camino.</h1><p>La página que buscas no está disponible.</p><a class="btn btn-primary" href="/">Volver al inicio</a></div></section>',
}));
fs.writeFileSync(path.join(output,'.nojekyll'),'');
fs.writeFileSync(path.join(output,'robots.txt'),'User-agent: *\nDisallow: /\n');
console.log(`GitHub Pages: ${Object.keys(pages).length} paginas en ${output}, base ${base||'/'}`);
