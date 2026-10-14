/* Clínica Salud Total — comportamiento de cabecera, menú móvil y avisos */
(function () {
  'use strict';

  var header = document.getElementById('st-header');
  var burger = document.getElementById('st-burger');
  var nav = document.getElementById('st-nav');

  // Sombra en la cabecera al hacer scroll
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // Menú móvil
  if (burger && nav) {
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', String(open));
      burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    };
    burger.addEventListener('click', function () { setOpen(!nav.classList.contains('is-open')); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1240) setOpen(false); });
  }

  // Aviso emergente reutilizable: window.stToast('mensaje', 'ok' | 'error')
  window.stToast = function (mensaje, tipo) {
    var t = document.createElement('div');
    t.className = 'st-toast' + (tipo ? ' st-toast--' + tipo : '');
    t.setAttribute('role', 'status');
    t.textContent = mensaje;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('is-visible'); });
    setTimeout(function () {
      t.classList.remove('is-visible');
      setTimeout(function () { t.remove(); }, 400);
    }, 5000);
  };

  // Mensajes de resultado que llegan por la URL (?cita=ok, ?contacto=ok, etc.)
  var params = new URLSearchParams(window.location.search);
  var mensajes = {
    cita: { ok: 'Su solicitud de cita fue enviada. Le contactaremos para confirmarla.', error: 'No pudimos registrar la cita. Revise los datos e intente de nuevo.', limite: 'Ha enviado demasiadas solicitudes. Intente de nuevo en unos minutos.' }
  };
  Object.keys(mensajes).forEach(function (clave) {
    var valor = params.get(clave);
    if (valor && mensajes[clave][valor]) window.stToast(mensajes[clave][valor], valor === 'ok' ? 'ok' : 'error');
  });
})();
