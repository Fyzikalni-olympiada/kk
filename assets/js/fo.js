/**
 * Klientský JS krajských webů FO — jediný skript, bez závislostí.
 *
 * Ze sesterského projektu ../fo se přenášelo víc (taby, accordion,
 * fotogalerie, osy termínů, filtr studijních textů, přesměrování starých
 * adres s query parametry), ale žádnou z těch značek krajské weby nemají
 * — zbylo jen hamburger menu.
 */
(function () {
	'use strict';

	var tlacitko = document.getElementById('menu-tlacitko');
	var menu = document.getElementById('main-nav');
	if (!tlacitko || !menu) {
		return;
	}

	function zavri() {
		menu.classList.remove('open');
		tlacitko.setAttribute('aria-expanded', 'false');
	}

	tlacitko.addEventListener('click', function () {
		tlacitko.setAttribute('aria-expanded', menu.classList.toggle('open'));
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			zavri();
		}
	});
})();
