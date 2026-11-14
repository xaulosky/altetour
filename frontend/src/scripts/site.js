// Interacciones ligeras: navegación móvil, itinerario, búsqueda y consultas.
// La vista portable no envía datos; el sitio desplegado usa la API PHP del mismo origen.
let booted = false;
// Textos generados por el navegador, en el idioma de la página (<html lang>).
const STRINGS={
  es:{collapse:'Contraer todos ',expand:'Expandir todos ',one:'programa publicado',many:'programas publicados',prepare:'Preparar mi consulta ',continueWa:'Continuar con mi consulta por WhatsApp ',
    waText:(v,interest)=>`Hola Alte Tour, soy ${v.name}. Me interesa ${interest}. Viajamos ${v.travelers} personas. ${v.message||''}`,
    staticReady:'Tu consulta está preparada. Continúa con el enlace de WhatsApp para enviarla a Alte Tour. Tus datos no se guardan en este sitio.',
    previewReady:'Tu consulta está preparada. Esta vista previa no guarda ni envía tus datos. Usa el enlace de WhatsApp para enviarla directamente a Alte Tour.',
    noConnect:'No se pudo conectar con el formulario. Tus datos no se han borrado. Puedes reintentar o escribir por WhatsApp.',
    notSaved:'No pudimos registrar la consulta. Revisa los campos o escribe por WhatsApp.',
    saved:ref=>`Tu consulta quedó registrada con el código ${ref}. Alte Tour podrá revisarla en su panel. Esto no confirma una reserva ni disponibilidad.`,
    timeout:'La conexión tardó demasiado. Tu solicitud podría haberse registrado: reintenta sin cambiar los datos para evitar duplicados, o consulta por WhatsApp.',
    openMenu:'Abrir menú',closeMenu:'Cerrar menú'},
  en:{collapse:'Collapse all ',expand:'Expand all ',one:'published program',many:'published programs',prepare:'Prepare my inquiry ',continueWa:'Continue my inquiry on WhatsApp ',
    waText:(v,interest)=>`Hello Alte Tour, I am ${v.name}. I am interested in ${interest}. We are ${v.travelers} travelers. ${v.message||''}`,
    staticReady:'Your inquiry is ready. Use the WhatsApp link to send it to Alte Tour. Your details are not stored on this site.',
    previewReady:'Your inquiry is ready. This preview does not store or send your details. Use the WhatsApp link to send it directly to Alte Tour.',
    noConnect:'We could not connect to the form. Your details have not been deleted. You can try again or write to us on WhatsApp.',
    notSaved:'We could not register your inquiry. Check the fields or write to us on WhatsApp.',
    saved:ref=>`Your inquiry was registered with code ${ref}. Alte Tour will review it. This does not confirm a booking or availability.`,
    timeout:'The connection took too long. Your request may have been registered: try again without changing the details to avoid duplicates, or contact us on WhatsApp.',
    openMenu:'Open menu',closeMenu:'Close menu'},
  pt:{collapse:'Recolher todos ',expand:'Expandir todos ',one:'programa publicado',many:'programas publicados',prepare:'Preparar minha consulta ',continueWa:'Continuar minha consulta pelo WhatsApp ',
    waText:(v,interest)=>`Olá, Alte Tour, sou ${v.name}. Tenho interesse em ${interest}. Somos ${v.travelers} viajantes. ${v.message||''}`,
    staticReady:'Sua consulta está pronta. Use o link do WhatsApp para enviá-la à Alte Tour. Seus dados não são armazenados neste site.',
    previewReady:'Sua consulta está pronta. Esta prévia não armazena nem envia seus dados. Use o link do WhatsApp para enviá-la diretamente à Alte Tour.',
    noConnect:'Não foi possível conectar ao formulário. Seus dados não foram apagados. Você pode tentar novamente ou escrever pelo WhatsApp.',
    notSaved:'Não conseguimos registrar a consulta. Revise os campos ou escreva pelo WhatsApp.',
    saved:ref=>`Sua consulta foi registrada com o código ${ref}. A Alte Tour vai analisá-la. Isso não confirma uma reserva nem disponibilidade.`,
    timeout:'A conexão demorou demais. Sua solicitação pode ter sido registrada: tente novamente sem alterar os dados para evitar duplicidade, ou consulte pelo WhatsApp.',
    openMenu:'Abrir menu',closeMenu:'Fechar menu'},
};
const L=()=>STRINGS[(document.documentElement.lang||'es').slice(0,2)]||STRINGS.es;
// Ruta sin prefijo de GitHub Pages ni de idioma (/en/, /pt/).
function pagePath(p){const base=window.__ALTE_BASE__;if(base&&p.startsWith(base+'/'))p=p.slice(base.length);return p.replace(/^\/(en|pt)(?=\/)/,'');}
function requestKey(){return crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('');}
function norm(s){ return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }
export function initPage(){
  const main=document.querySelector('main');
  if(!main)return;
  const path=pagePath(window.__ALTE_ROUTE__ || window.location.pathname);
  document.body.classList.toggle('has-hero-nav',Boolean(main.querySelector('.hero,.trip-hero,.about-hero')));
  document.querySelector('.header')?.classList.toggle('is-scrolled',window.scrollY>40);
  const booking=main.querySelector('.mobile-booking');
  const updateBookingHeight=()=>document.body.style.setProperty('--mobile-booking-height',`${booking?.getBoundingClientRect().height||0}px`);
  updateBookingHeight();
  if(booking){
    window.__alteBookingObserver?.disconnect();
    window.__alteBookingObserver=new ResizeObserver(updateBookingHeight);
    window.__alteBookingObserver.observe(booking);
  }else window.__alteBookingObserver?.disconnect();
  document.body.classList.toggle('has-trip',path.startsWith('/viajes/')&&path!='/viajes/');
  document.querySelectorAll('#main-nav a').forEach(a=>{
    a.removeAttribute('aria-current');
    const navPath=pagePath(a.getAttribute('href'));
    if(path===navPath || ((navPath==='/viajes/'||navPath==='/noticias/') && path.startsWith(navPath)))a.setAttribute('aria-current','page');
  });
  main.querySelectorAll('[data-poster]').forEach(el=>el.addEventListener('click',()=>document.querySelector('.poster-dialog')?.showModal()));
  main.querySelector('[data-expand-days]')?.addEventListener('click',ev=>{
    const days=[...main.querySelectorAll('.day')];const open=!days.every(d=>d.open);
    days.forEach(d=>d.open=open);ev.currentTarget.firstChild.textContent=open?L().collapse:L().expand;
  });
  const search=main.querySelector('[data-trip-search]');
  let filter='all';
  function filterTrips(){
    const q=norm(search?.value||'');let visible=0;
    main.querySelectorAll('[data-trip-card]').forEach(card=>{
      card.hidden=!(norm(card.dataset.search).includes(q)&&(filter==='all'||card.dataset.category===filter));
      if(!card.hidden)visible++;
    });
    const count=main.querySelector('[data-results]');if(count)count.textContent=`${visible} ${visible===1?L().one:L().many}`;
    const empty=main.querySelector('.empty-state');if(empty)empty.hidden=visible>0;
  }
  search?.addEventListener('input',filterTrips);
  main.querySelectorAll('[data-filter]').forEach(button=>button.addEventListener('click',()=>{
    filter=button.dataset.filter;main.querySelectorAll('[data-filter]').forEach(b=>{const active=b===button;b.classList.toggle('active',active);b.setAttribute('aria-pressed',String(active));});filterTrips();
  }));
  // Noticias: filtro por categoría, también desde ?categoria=viajes.
  const newsButtons=[...main.querySelectorAll('[data-news-filter]')];
  if(newsButtons.length){
    const applyNews=value=>{
      let visible=0;
      main.querySelectorAll('[data-news-card]').forEach(card=>{card.hidden=!(value==='all'||card.dataset.category===value);if(!card.hidden)visible++;});
      newsButtons.forEach(b=>{const active=b.dataset.newsFilter===value;b.classList.toggle('active',active);b.setAttribute('aria-pressed',String(active));});
      const empty=main.querySelector('.news-empty');if(empty)empty.hidden=visible>0;
    };
    newsButtons.forEach(b=>b.addEventListener('click',()=>{
      applyNews(b.dataset.newsFilter);
      const url=new URL(window.location.href);if(b.dataset.newsFilter==='all')url.searchParams.delete('categoria');else url.searchParams.set('categoria',b.dataset.newsFilter);
      history.replaceState(null,'',url);
    }));
    const initial=new URLSearchParams(window.location.search).get('categoria');
    if(initial&&newsButtons.some(b=>b.dataset.newsFilter===initial))applyNews(initial);
  }
  main.querySelectorAll('[data-lead-form]').forEach(form=>{
    const interest = new URLSearchParams(window.__ALTE_QUERY__ || window.location.search).get('interes');
    const sel=form.querySelector('[name="interest"]');
    if(interest&&[...sel.options].some(o=>o.value===interest))sel.value=interest;
    if(window.__ALTE_PORTABLE__||window.__ALTE_STATIC__){form.querySelector('[type=submit]').firstChild.textContent=L().prepare;}
    let key=requestKey();
    form.addEventListener('submit',async ev=>{
      ev.preventDefault();
      if(!form.reportValidity())return;
      const fd=new FormData(form),values=Object.fromEntries(fd.entries());
      const result=form.querySelector('.form-status'),submit=form.querySelector('[type=submit]');
      result.className='form-status';result.textContent='';
      if(window.__ALTE_PORTABLE__||window.__ALTE_STATIC__){
        const text=L().waText(values,sel.options[sel.selectedIndex].text);
        const wa=form.querySelector('.form-wa-fallback');wa.href='https://wa.me/56992195711?text='+encodeURIComponent(text);
        wa.firstChild.textContent=L().continueWa;
        result.textContent=window.__ALTE_STATIC__?L().staticReady:L().previewReady;
        result.focus();return;
      }
      submit.disabled=true;
      const ctrl=new AbortController();const timeout=setTimeout(()=>ctrl.abort(),15000);
      try{
        const csrfRes=await fetch('/api/index.php?route=csrf',{credentials:'same-origin',headers:{Accept:'application/json'},signal:ctrl.signal});
        if(!csrfRes.ok)throw new Error(L().noConnect);
        const csrf=await csrfRes.json();
        const utm={};const qs=new URLSearchParams(window.location.search);
        ['utm_source','utm_medium','utm_campaign'].forEach(k=>{if(qs.has(k))utm[k]=qs.get(k).slice(0,150);});
        const response=await fetch('/api/index.php?route=leads',{method:'POST',credentials:'same-origin',signal:ctrl.signal,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf.token},body:JSON.stringify({...values,consent:values.consent==='1',idempotency_key:key,source:window.location.pathname,utm})});
        const payload=await response.json();
        if(!response.ok)throw new Error(payload.message||L().notSaved);
        result.classList.add('success');result.textContent=L().saved(payload.reference);
        form.reset();key=requestKey();
      }catch(error){result.classList.add('error');result.textContent=error.name==='AbortError'?L().timeout:error.message;}
      finally{clearTimeout(timeout);submit.disabled=false;result.focus();}
    });
  });
}
export function boot(){
  if(booted){initPage();return;}booted=true;
  const header=document.querySelector('.header');
  let scrollFrame=0;
  window.addEventListener('scroll',()=>{
    if(scrollFrame)return;
    scrollFrame=requestAnimationFrame(()=>{header?.classList.toggle('is-scrolled',window.scrollY>40);scrollFrame=0;});
  },{passive:true});
  const toggle=document.querySelector('.menu-toggle'),nav=document.querySelector('#main-nav');
  const closeMenu=()=>{nav?.classList.remove('open');toggle?.setAttribute('aria-expanded','false');toggle?.setAttribute('aria-label',L().openMenu);document.body.classList.remove('menu-open');};
  toggle?.addEventListener('click',()=>{const open=!nav.classList.contains('open');nav.classList.toggle('open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?L().closeMenu:L().openMenu);document.body.classList.toggle('menu-open',open);});
  nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
  window.addEventListener('keydown',ev=>{if(ev.key==='Escape')closeMenu();});
  const dialog=document.querySelector('.poster-dialog');
  dialog?.querySelector('.close-dialog')?.addEventListener('click',()=>dialog.close());
  dialog?.addEventListener('click',ev=>{if(ev.target===dialog)dialog.close();});
  window.addEventListener('alte:navigate',()=>{closeMenu();initPage();});
  initPage();
}
