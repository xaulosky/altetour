// Idiomas del sitio. El español es el original (sin prefijo); inglés y portugués
// viven en /en/ y /pt/. El HTML se genera en español y se traduce con los
// diccionarios de src/i18n (texto plano: español -> traducción). Las noticias
// traen su propio texto por idioma y no pasan por los diccionarios.
import en from '../i18n/en.mjs';
import pt from '../i18n/pt.mjs';

export const LANGS = ['es', 'en', 'pt'];
export const LANG_META = {
  es: { html: 'es-CL', og: 'es_CL', intl: 'es-CL', label: 'Español', short: 'ES' },
  en: { html: 'en', og: 'en_US', intl: 'en-US', label: 'English', short: 'EN' },
  pt: { html: 'pt-BR', og: 'pt_BR', intl: 'pt-BR', label: 'Português', short: 'PT' },
};
const DICTS = { en, pt };

const norm = s => String(s).replace(/\s+/g, ' ').trim();
const decode = s => String(s).replace(/&(amp|lt|gt|quot|#39);/g, (_, x) => ({ amp: '&', lt: '<', gt: '>', quot: '"', '#39': "'" }[x]));
const encode = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
// Rutas que no son páginas del sitio y no llevan prefijo de idioma.
const NOT_A_PAGE = /^\/(assets|api|admin|uploads)\//;

export const isLang = l => LANGS.includes(l);
export function localizePath(path, lang) {
  if (lang === 'es' || !path.startsWith('/') || path.startsWith('//') || NOT_A_PAGE.test(path)) return path;
  return `/${lang}${path}`;
}
export function stripLang(path) {
  const m = String(path).match(/^\/(en|pt)(\/.*)?$/);
  return m ? (m[2] || '/') : path;
}
export function langOf(path) {
  const m = String(path).match(/^\/(en|pt)(\/|$)/);
  return m ? m[1] : 'es';
}

/** Traduce un texto plano (títulos, descripciones). */
export function t(text, lang) {
  if (lang === 'es') return text;
  return DICTS[lang][norm(text)] ?? text;
}
const tEncoded = (raw, dict) => {
  const key = norm(decode(raw));
  return key && dict[key] != null ? encode(dict[key]) : null;
};
// Mensaje prellenado de WhatsApp (?text=...) en el idioma de la página.
const translateWa = (url, dict) => url.replace(/([?&]text=)([^&"]*)/, (m, k, v) => {
  let text; try { text = decodeURIComponent(v); } catch { return m; }
  const out = dict[norm(text)];
  return out != null ? k + encodeURIComponent(out) : m;
});

/** Recorre el HTML y llama a fn con cada texto traducible. */
function walk(html, onText, onTag) {
  let skip = false;
  return html.split(/(<[^>]+>)/).map(part => {
    if (part.startsWith('<')) {
      const tag = part.match(/^<\/?([a-zA-Z0-9-]+)/)?.[1]?.toLowerCase();
      if (tag === 'script' || tag === 'style') { skip = !part.startsWith('</'); return part; }
      return onTag(part);
    }
    if (skip || !/\p{L}/u.test(part)) return part;
    const m = part.match(/^(\s*)([\s\S]*?)(\s*)$/);
    const out = onText(m[2]);
    return out == null ? part : m[1] + out + m[3];
  }).join('');
}

/** Traduce textos, atributos visibles y enlaces internos del HTML. */
export function translateHtml(html, lang) {
  if (lang === 'es') return html;
  const dict = DICTS[lang];
  const out = walk(html, raw => tEncoded(raw, dict), tag => {
    let out = tag.replace(/\b(alt|aria-label|placeholder|title)="([^"]*)"/g, (m, a, v) => {
      const tr = tEncoded(v, dict); return tr != null ? `${a}="${tr}"` : m;
    });
    out = out.replace(/\bhref="(https:\/\/wa\.me\/[^"]*)"/g, (m, url) => `href="${translateWa(url, dict)}"`);
    // El selector de idioma (hreflang) ya trae sus rutas; el resto se prefija.
    if (!/\bhreflang=/.test(out)) out = out.replace(/\b(href|action)="(\/(?!\/)[^"]*)"/g, (m, a, v) => `${a}="${localizePath(v, lang)}"`);
    return out;
  });
  // En inglés los miles van con coma (USD 3,750; 20,000+). Se aplica después del diccionario,
  // cuyas claves usan el formato español.
  return lang === 'en' ? out.split(/(<[^>]+>)/).map(p => p.startsWith('<') ? p : p.replace(/\b(\d{1,3})((?:\.\d{3})+)\b/g, (_, a, b) => a + b.replaceAll('.', ','))).join('') : out;
}

/** Textos traducibles de un HTML en español (para revisar los diccionarios). */
export function collectStrings(html) {
  const found = new Set();
  const add = raw => { const k = norm(decode(raw)); if (k && /\p{L}/u.test(k)) found.add(k); };
  walk(html, raw => { add(raw); return null; }, tag => {
    for (const [, , v] of tag.matchAll(/\b(alt|aria-label|placeholder|title)="([^"]*)"/g)) add(v);
    for (const [, v] of tag.matchAll(/href="https:\/\/wa\.me\/[^"]*?[?&]text=([^&"]*)/g)) { try { add(encode(decodeURIComponent(v))); } catch {} }
    return tag;
  });
  return found;
}
export const dictionaries = DICTS;
