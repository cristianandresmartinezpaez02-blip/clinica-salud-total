/* Clínica Salud Total — menú lateral deslizable de los paneles en pantallas pequeñas */
(function () {
  'use strict';
  var body = document.body;
  var sidebar = document.querySelector('nav.sidebar');
  var navbar = document.querySelector('main > .navbar');
  if (!sidebar || !navbar) return;

  sidebar.id = sidebar.id || 'panelSidebar';
  sidebar.setAttribute('aria-label', 'Menú del panel');

  var boton = document.createElement('button');
  boton.type = 'button';
  boton.className = 'panel-toggle';
  boton.setAttribute('aria-label', 'Abrir menú');
  boton.setAttribute('aria-controls', sidebar.id);
  boton.setAttribute('aria-expanded', 'false');
  boton.innerHTML = '<i class="bi bi-list"></i>';
  var contenedor = navbar.querySelector('.container-fluid') || navbar;
  contenedor.insertBefore(boton, contenedor.firstChild);

  var fondo = document.createElement('div');
  fondo.className = 'panel-backdrop';
  body.appendChild(fondo);

  function abrir(estado) {
    body.classList.toggle('menu-abierto', estado);
    boton.setAttribute('aria-expanded', String(estado));
    boton.setAttribute('aria-label', estado ? 'Cerrar menú' : 'Abrir menú');
  }

  boton.addEventListener('click', function () { abrir(!body.classList.contains('menu-abierto')); });
  fondo.addEventListener('click', function () { abrir(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') abrir(false); });
  sidebar.addEventListener('click', function (e) { if (e.target.closest('a')) abrir(false); });

  // El logo del menú vuelve al resumen del panel
  sidebar.querySelectorAll('[data-ir]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (typeof window.showPage === 'function') { e.preventDefault(); window.showPage(a.dataset.ir); }
    });
  });
})();

/* Detalle de las gráficas al pasar el cursor o enfocar con teclado (los valores también están en la tabla) */
(function () {
  'use strict';
  var tip = document.getElementById('vizTip');
  if (!tip) return;
  var valor = tip.querySelector('strong'), etq = tip.querySelector('span');
  function mostrar(el) {
    valor.textContent = el.getAttribute('data-tip-valor');
    etq.textContent = el.getAttribute('data-tip-etq');
    tip.hidden = false;
    var r = el.getBoundingClientRect(), t = tip.getBoundingClientRect();
    var x = Math.min(Math.max(8, r.left + r.width / 2 - t.width / 2), window.innerWidth - t.width - 8);
    var y = r.top - t.height - 8;
    if (y < 8) y = r.bottom + 8;
    tip.style.left = x + 'px';
    tip.style.top = y + 'px';
  }
  function ocultar() { tip.hidden = true; }
  document.querySelectorAll('[data-tip-valor]').forEach(function (el) {
    el.addEventListener('pointerenter', function () { mostrar(el); });
    el.addEventListener('focus', function () { mostrar(el); });
    el.addEventListener('pointerleave', ocultar);
    el.addEventListener('blur', ocultar);
  });
  window.addEventListener('scroll', ocultar, { passive: true });
})();
