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
