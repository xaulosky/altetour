// Interacciones ligeras: navegación móvil, itinerario, búsqueda y consultas.
// La vista portable no envía datos; el sitio desplegado usa la API PHP del mismo origen.
let booted = false;
function requestKey(){return crypto.randomUUID?.() || Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('');}
function norm(s){ return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }
export function initPage(){
  const main=document.querySelector('main');
  if(!main)return;
  const path=window.__ALTE_ROUTE__ || window.location.pathname;
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
    const href=a.getAttribute('href');
    const navPath=window.__ALTE_BASE__&&href.startsWith(window.__ALTE_BASE__+'/')?href.slice(window.__ALTE_BASE__.length):href;
    if(path===navPath || (navPath==='/viajes/' && path.startsWith('/viajes/')))a.setAttribute('aria-current','page');
  });
  main.querySelectorAll('[data-poster]').forEach(el=>el.addEventListener('click',()=>document.querySelector('.poster-dialog')?.showModal()));
  main.querySelector('[data-expand-days]')?.addEventListener('click',ev=>{
    const days=[...main.querySelectorAll('.day')];const open=!days.every(d=>d.open);
    days.forEach(d=>d.open=open);ev.currentTarget.firstChild.textContent=open?'Contraer todos ':'Expandir todos ';
  });
  const search=main.querySelector('[data-trip-search]');
  let filter='all';
  function filterTrips(){
    const q=norm(search?.value||'');let visible=0;
    main.querySelectorAll('[data-trip-card]').forEach(card=>{
      card.hidden=!(norm(card.dataset.search).includes(q)&&(filter==='all'||card.dataset.category===filter));
      if(!card.hidden)visible++;
    });
    const count=main.querySelector('[data-results]');if(count)count.textContent=`${visible} ${visible===1?'programa publicado':'programas publicados'}`;
    const empty=main.querySelector('.empty-state');if(empty)empty.hidden=visible>0;
  }
  search?.addEventListener('input',filterTrips);
  main.querySelectorAll('[data-filter]').forEach(button=>button.addEventListener('click',()=>{
    filter=button.dataset.filter;main.querySelectorAll('[data-filter]').forEach(b=>{const active=b===button;b.classList.toggle('active',active);b.setAttribute('aria-pressed',String(active));});filterTrips();
  }));
  main.querySelectorAll('[data-lead-form]').forEach(form=>{
    const interest = new URLSearchParams(window.__ALTE_QUERY__ || window.location.search).get('interes');
    const sel=form.querySelector('[name="interest"]');
    if(interest&&[...sel.options].some(o=>o.value===interest))sel.value=interest;
    if(window.__ALTE_PORTABLE__||window.__ALTE_STATIC__){form.querySelector('[type=submit]').firstChild.textContent='Preparar mi consulta ';}
    let key=requestKey();
    form.addEventListener('submit',async ev=>{
      ev.preventDefault();
      if(!form.reportValidity())return;
      const fd=new FormData(form),values=Object.fromEntries(fd.entries());
      const result=form.querySelector('.form-status'),submit=form.querySelector('[type=submit]');
      result.className='form-status';result.textContent='';
      if(window.__ALTE_PORTABLE__||window.__ALTE_STATIC__){
        const text=`Hola Alte Tour, soy ${values.name}. Me interesa ${values.program==='turquia-2027'?'Turquía Imbatible 2027':sel.options[sel.selectedIndex].text}. Viajamos ${values.travelers} personas. ${values.message||''}`;
        const wa=form.querySelector('.form-wa-fallback');wa.href='https://wa.me/56992195711?text='+encodeURIComponent(text);
        wa.firstChild.textContent='Continuar con mi consulta por WhatsApp ';
        result.textContent=window.__ALTE_STATIC__?'Tu consulta está preparada. Continúa con el enlace de WhatsApp para enviarla a Alte Tour. Tus datos no se guardan en este sitio.':'Tu consulta está preparada. Esta vista previa no guarda ni envía tus datos. Usa el enlace de WhatsApp para enviarla directamente a Alte Tour.';
        result.focus();return;
      }
      submit.disabled=true;
      const ctrl=new AbortController();const timeout=setTimeout(()=>ctrl.abort(),15000);
      try{
        const csrfRes=await fetch('/api/index.php?route=csrf',{credentials:'same-origin',headers:{Accept:'application/json'},signal:ctrl.signal});
        if(!csrfRes.ok)throw new Error('No se pudo conectar con el formulario. Tus datos no se han borrado. Puedes reintentar o escribir por WhatsApp.');
        const csrf=await csrfRes.json();
        const utm={};const qs=new URLSearchParams(window.location.search);
        ['utm_source','utm_medium','utm_campaign'].forEach(k=>{if(qs.has(k))utm[k]=qs.get(k).slice(0,150);});
        const response=await fetch('/api/index.php?route=leads',{method:'POST',credentials:'same-origin',signal:ctrl.signal,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf.token},body:JSON.stringify({...values,consent:values.consent==='1',idempotency_key:key,source:window.location.pathname,utm})});
        const payload=await response.json();
        if(!response.ok)throw new Error(payload.message||'No pudimos registrar la consulta. Revisa los campos o escribe por WhatsApp.');
        result.classList.add('success');result.textContent=`Tu consulta quedó registrada con el código ${payload.reference}. Alte Tour podrá revisarla en su panel. Esto no confirma una reserva ni disponibilidad.`;
        form.reset();key=requestKey();
      }catch(error){result.classList.add('error');result.textContent=error.name==='AbortError'?'La conexión tardó demasiado. Tu solicitud podría haberse registrado: reintenta sin cambiar los datos para evitar duplicados, o consulta por WhatsApp.':error.message;}
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
  const closeMenu=()=>{nav?.classList.remove('open');toggle?.setAttribute('aria-expanded','false');toggle?.setAttribute('aria-label','Abrir menú');document.body.classList.remove('menu-open');};
  toggle?.addEventListener('click',()=>{const open=!nav.classList.contains('open');nav.classList.toggle('open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Cerrar menú':'Abrir menú');document.body.classList.toggle('menu-open',open);});
  nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
  window.addEventListener('keydown',ev=>{if(ev.key==='Escape')closeMenu();});
  const dialog=document.querySelector('.poster-dialog');
  dialog?.querySelector('.close-dialog')?.addEventListener('click',()=>dialog.close());
  dialog?.addEventListener('click',ev=>{if(ev.target===dialog)dialog.close();});
  window.addEventListener('alte:navigate',()=>{closeMenu();initPage();});
  initPage();
}
