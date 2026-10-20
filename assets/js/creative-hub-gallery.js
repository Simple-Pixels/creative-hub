/**
 * Creative Hub coverflow gallery.
 * Vanilla JS, no dependencies. Handles arrows, thumbnails, swipe, keyboard.
 */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function init(root) {
		if (!root || root.getAttribute('data-ch-ready')) {
			return;
		}
		root.setAttribute('data-ch-ready', '1');

		var stage = root.querySelector('.ch-gallery__stage');
		var slides = Array.prototype.slice.call(root.querySelectorAll('.ch-gallery__slide'));
		var thumbs = Array.prototype.slice.call(root.querySelectorAll('.ch-gallery__thumb'));
		var thumbBar = root.querySelector('.ch-gallery__thumbs');
		var prev = root.querySelector('.ch-gallery__nav--prev');
		var next = root.querySelector('.ch-gallery__nav--next');
		var status = root.querySelector('.ch-gallery__status');
		var count = slides.length;
		var current = 0;

		if (count < 2 || !stage) {
			return;
		}

		function render() {
			slides.forEach(function (slide, i) {
				var offset = i - current;
				var abs = Math.abs(offset);
				slide.style.setProperty('--o', Math.max(-3, Math.min(3, offset)));
				slide.style.setProperty('--abs', Math.min(abs, 3));
				slide.style.zIndex = String(100 - abs);
				slide.classList.toggle('is-active', offset === 0);
				slide.classList.toggle('is-far', abs > 2);
				slide.setAttribute('aria-hidden', offset === 0 ? 'false' : 'true');
			});

			thumbs.forEach(function (thumb, i) {
				var active = i === current;
				thumb.classList.toggle('is-active', active);
				if (active) {
					thumb.setAttribute('aria-current', 'true');
				} else {
					thumb.removeAttribute('aria-current');
				}
			});

			if (prev) {
				prev.disabled = current === 0;
			}
			if (next) {
				next.disabled = current === count - 1;
			}
			if (status) {
				status.textContent = (current + 1) + ' / ' + count;
			}

			// Keep the active thumbnail in view without scrolling the page.
			var active = thumbs[current];
			if (active && thumbBar && thumbBar.scrollWidth > thumbBar.clientWidth) {
				var left = active.offsetLeft - (thumbBar.clientWidth - active.offsetWidth) / 2;
				if (thumbBar.scrollTo) {
					thumbBar.scrollTo({ left: left, behavior: reduceMotion ? 'auto' : 'smooth' });
				} else {
					thumbBar.scrollLeft = left;
				}
			}
		}

		function go(index) {
			var target = Math.max(0, Math.min(count - 1, index));
			if (target === current) {
				return;
			}
			current = target;
			render();
		}

		if (prev) {
			prev.addEventListener('click', function () { go(current - 1); });
		}
		if (next) {
			next.addEventListener('click', function () { go(current + 1); });
		}

		thumbs.forEach(function (thumb, i) {
			thumb.addEventListener('click', function () { go(i); });
		});

		// Click a neighbouring slide to bring it to the centre.
		var suppressClick = false;
		slides.forEach(function (slide, i) {
			slide.addEventListener('click', function () {
				if (!suppressClick) {
					go(i);
				}
			});
		});

		// Swipe / drag.
		var startX = null;
		stage.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) {
				return;
			}
			startX = e.clientX;
		});
		stage.addEventListener('pointerup', function (e) {
			if (startX === null) {
				return;
			}
			var delta = e.clientX - startX;
			startX = null;
			if (Math.abs(delta) > 40) {
				go(current + (delta < 0 ? 1 : -1));
				suppressClick = true;
				window.setTimeout(function () { suppressClick = false; }, 0);
			}
		});
		stage.addEventListener('pointercancel', function () { startX = null; });
		stage.addEventListener('pointerleave', function () { startX = null; });

		// Keyboard.
		stage.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				go(current - 1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				go(current + 1);
			} else if (e.key === 'Home') {
				e.preventDefault();
				go(0);
			} else if (e.key === 'End') {
				e.preventDefault();
				go(count - 1);
			}
		});

		root.classList.add('is-ready');
		render();
	}

	function initAll(scope) {
		var nodes = (scope || document).querySelectorAll('[data-ch-gallery]');
		Array.prototype.forEach.call(nodes, init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initAll(); });
	} else {
		initAll();
	}

	// Elementor re-renders widgets inside its editor; initialise those too.
	window.addEventListener('elementor/frontend/init', function () {
		try {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($scope) {
				initAll($scope && $scope[0] ? $scope[0] : document);
			});
		} catch (err) { /* not in Elementor */ }
	});
})();
