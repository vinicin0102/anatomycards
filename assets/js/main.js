/* AnatomyCards — interações da landing page */
(function () {
  'use strict';

  /* Menu mobile */
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('nav');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    });

    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* CTA fixo: aparece depois do hero e some quando os planos estão na tela */
  var sticky = document.getElementById('stickyCta');
  var planos = document.getElementById('planos');

  if (sticky && planos) {
    var onScroll = function () {
      var passouDobra = window.scrollY > window.innerHeight * 0.6;
      var box = planos.getBoundingClientRect();
      var planosVisiveis = box.top < window.innerHeight && box.bottom > 0;
      sticky.classList.toggle('is-visible', passouDobra && !planosVisiveis);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* FAQ: mantém apenas uma pergunta aberta por vez */
  var perguntas = document.querySelectorAll('.faq__item');
  perguntas.forEach(function (item) {
    item.addEventListener('toggle', function () {
      if (!item.open) return;
      perguntas.forEach(function (outro) {
        if (outro !== item) outro.open = false;
      });
    });
  });

  /* Ano do rodapé */
  var year = document.getElementById('year');
  if (year) year.textContent = String(new Date().getFullYear());
})();
