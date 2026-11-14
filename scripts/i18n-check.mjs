// Revisa los diccionarios de traducción (frontend/src/i18n/*.mjs).
//   node scripts/i18n-check.mjs                 resumen por idioma
//   node scripts/i18n-check.mjs --missing out   guarda en out/<idioma>.json los textos sin traducir
// Un texto falta si sigue en español en la página traducida. Las noticias no se revisan aquí:
// traen su propio texto por idioma desde el panel.
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {getPages,chrome,staticPrivacyPatch} from '../frontend/src/lib/render.mjs';
import {LANGS,collectStrings,dictionaries,translateHtml} from '../frontend/src/lib/i18n.mjs';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const read=f=>JSON.parse(fs.readFileSync(path.join(root,f),'utf8'));
const site=read('frontend/src/data/site.json'),programs=read('frontend/src/data/programs.json'),news=read('frontend/src/data/news.json');
// Textos que son iguales en todos los idiomas (marcas, siglas, nombres propios).
const SAME=new Set(['Alte Tour','ALTE TOUR','USD','WhatsApp','Instagram','TA','Biobío','Chile','Teresa Alarcón','Contur Biobío','Fedetur','Pamukkale','Capadocia','Estambul']);

function pageSet(lang){
  const pages=getPages(site,programs,news,lang),es=getPages(site,programs,news,'es');
  const html=[translateHtml(staticPrivacyPatch(es['/privacidad/'].html),lang)];
  const texts=[];
  for(const [route,page] of Object.entries(pages)){
    const {header,footer}=chrome(site,programs,lang,route);
    html.push(page.html,header,footer);texts.push(page.title,page.description);
  }
  const set=collectStrings(html.join('\n'));texts.forEach(x=>set.add(x.replace(/\s+/g,' ').trim()));
  return set;
}
const spanish=pageSet('es');
const out=process.argv.includes('--missing')?process.argv[process.argv.indexOf('--missing')+1]:null;
let failed=false;
for(const lang of LANGS.filter(l=>l!=='es')){
  const dict=dictionaries[lang];
  const missing=[...pageSet(lang)].filter(x=>spanish.has(x)&&!SAME.has(x)&&!(x in dict));
  const unused=Object.keys(dict).filter(k=>!spanish.has(k));
  console.log(`${lang}: ${Object.keys(dict).length} traducciones, ${missing.length} textos sin traducir, ${unused.length} sin uso`);
  if(unused.length)console.log('  sin uso:',unused.slice(0,10).join(' | '),unused.length>10?'…':'');
  if(missing.length)failed=true;
  if(out){fs.mkdirSync(out,{recursive:true});fs.writeFileSync(path.join(out,lang+'.json'),JSON.stringify(missing,null,1));}
}
if(failed&&process.argv.includes('--strict'))process.exit(1);
