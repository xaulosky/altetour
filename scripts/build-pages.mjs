import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {getPages,chrome,staticPrivacyPatch,e} from '../frontend/src/lib/render.mjs';
import {LANGS,LANG_META,localizePath,translateHtml} from '../frontend/src/lib/i18n.mjs';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const output=path.join(root,'pages-dist');
const base=(process.env.PAGES_BASE_PATH||'/altetour').replace(/\/$/,'');
if(base&&!/^\/[a-zA-Z0-9_-]+$/.test(base))throw new Error('Invalid Pages base path');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const site=JSON.parse(read('frontend/src/data/site.json'));
const programs=JSON.parse(read('frontend/src/data/programs.json'));
const news=JSON.parse(read('frontend/src/data/news.json'));
// pages-dist es una salida generada (ignorada por Git): se limpia para no publicar páginas antiguas.
fs.rmSync(output,{recursive:true,force:true});
fs.mkdirSync(path.join(output,'assets'),{recursive:true});
fs.cpSync(path.join(root,'frontend/public/assets'),path.join(output,'assets'),{recursive:true});
fs.copyFileSync(path.join(root,'frontend/src/styles/global.css'),path.join(output,'assets/site.css'));
fs.writeFileSync(path.join(output,'assets/site.js'),read('frontend/src/scripts/site.js').replace(/export function /g,'function ')+'\nboot();');

function render(route,page,lang){
  const url=localizePath(route,lang);
  const hero=/class="(?:hero|trip-hero|about-hero)"/.test(page.html);
  const {header,footer}=chrome(site,programs,lang,route);
  const prelude=`window.__ALTE_STATIC__=true;window.__ALTE_BASE__=${JSON.stringify(base)};window.__ALTE_ROUTE__=${JSON.stringify(url)};`;
  const alternates=LANGS.map(l=>`<link rel="alternate" hreflang="${LANG_META[l].html}" href="${localizePath(route,l)}">`).join('');
  let html=`<!doctype html><html lang="${LANG_META[lang].html}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${e(page.title)}</title><meta name="description" content="${e(page.description)}"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#082c40"><meta property="og:title" content="${e(page.title)}"><meta property="og:description" content="${e(page.description)}"><meta property="og:locale" content="${LANG_META[lang].og}">${alternates}<link rel="icon" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/site.css"></head><body${hero?' class="has-hero-nav"':''}>${header}<main id="main" tabindex="-1">${page.html}</main>${footer}<script>${prelude}</script><script src="/assets/site.js" defer></script></body></html>`;
  html=html.replace(/(href|src)="\/(?!\/)/g,`$1="${base}/`);
  html=html.replace(/srcset="([^"]*)"/g,(_,value)=>`srcset="${value.replaceAll('/assets/',`${base}/assets/`)}"`);
  return html;
}
let count=0;
const es=getPages(site,programs,news,'es');
for(const lang of LANGS){
  const pages=lang==='es'?es:getPages(site,programs,news,lang);
  // La privacidad se ajusta en español y luego se traduce, para que el texto estático exista en cada idioma.
  pages['/privacidad/']={...pages['/privacidad/'],html:translateHtml(staticPrivacyPatch(es['/privacidad/'].html),lang)};
  for(const [route,page] of Object.entries(pages)){
    const directory=path.join(output,localizePath(route,lang));
    fs.mkdirSync(directory,{recursive:true});
    fs.writeFileSync(path.join(directory,'index.html'),render(route,page,lang));
    count++;
  }
}
fs.writeFileSync(path.join(output,'404.html'),render('/404/',{
  title:'Página no encontrada · Alte Tour',description:'La página que buscas no está disponible.',
  html:'<section class="inner-hero"><div class="wrap"><p class="eyebrow">404</p><h1>Volvamos a encontrar el camino.</h1><p>La página que buscas no está disponible.</p><a class="btn btn-primary" href="/">Volver al inicio</a></div></section>',
},'es'));
fs.writeFileSync(path.join(output,'.nojekyll'),'');
fs.writeFileSync(path.join(output,'robots.txt'),'User-agent: *\nDisallow: /\n');
console.log(`GitHub Pages: ${count} paginas (${LANGS.join(', ')}) en ${output}, base ${base||'/'}`);
