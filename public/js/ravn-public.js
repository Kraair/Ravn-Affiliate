/* Ravn Affiliate – Public JS */
(function() {
    'use strict';

    /* ────────────────────────────────────────
       Click tracking
    ──────────────────────────────────────── */
    function initClickTracking() {
        // ravnVars is de naam waaronder PHP de instellingen localiseert.
        if (typeof ravnVars === 'undefined') return;
        // Niets versturen als link-tracking is uitgeschakeld in de instellingen.
        if (!ravnVars.trackingEnabled) return;

        document.addEventListener('click', function(e) {
            // De PHP genereert data-offer-id / data-product-id op de links.
            var link = e.target.closest('[data-offer-id]');
            if (!link) return;

            var offerId   = link.dataset.offerId;
            var productId = link.dataset.productId;

            if (!offerId) return;

            var fd = new FormData();
            fd.append('action',     'ravn_track_click');
            fd.append('nonce',      ravnVars.nonce);
            fd.append('offer_id',   offerId);
            fd.append('product_id', productId || '');
            fd.append('page_url',   window.location.href);

            if (navigator.sendBeacon) {
                navigator.sendBeacon(ravnVars.ajaxUrl, fd);
            } else {
                // Fallback XHR async
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ravnVars.ajaxUrl, true);
                xhr.send(fd);
            }
        });
    }

    /* ────────────────────────────────────────
       Carousel
    ──────────────────────────────────────── */
    function initCarousels() {
        document.querySelectorAll('.ravn-carousel-wrap').forEach(function(wrap) {
            var track     = wrap.querySelector('.ravn-carousel-track');
            var btnPrev   = wrap.querySelector('.ravn-carousel-arrow.prev');
            var btnNext   = wrap.querySelector('.ravn-carousel-arrow.next');
            var dotsWrap  = wrap.querySelector('.ravn-carousel-dots');
            var slides    = track ? Array.from(track.children).filter(function(el) {
                return !el.classList.contains('ravn-popup-overlay');
            }) : [];

            if (!track || slides.length === 0) return;

            var opts = {
                infinite: wrap.dataset.infinite === '1',
                autoplay: wrap.dataset.autoplay === '1',
                autoplaySpeed: parseInt(wrap.dataset.autoplaySpeed, 10) || 4000,
                slideSpeed:    parseInt(wrap.dataset.slideSpeed, 10) || 350,
                perView: parseInt(wrap.dataset.perView, 10) || 3,
            };

            var current = 0;
            var total   = slides.length;
            var autoplayTimer;

            // Responsive perView
            function getPerView() {
                var w = wrap.offsetWidth;
                if (w < 480) return 1;
                if (w < 768) return Math.min(2, opts.perView);
                return opts.perView;
            }

            // Build dots
            if (dotsWrap) {
                dotsWrap.innerHTML = '';
                slides.forEach(function(_, i) {
                    var dot = document.createElement('button');
                    dot.className = 'ravn-carousel-dot' + (i === 0 ? ' active' : '');
                    dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                    dot.addEventListener('click', function() { goTo(i); });
                    dotsWrap.appendChild(dot);
                });
            }

            function updateDots() {
                if (!dotsWrap) return;
                dotsWrap.querySelectorAll('.ravn-carousel-dot').forEach(function(d, i) {
                    d.classList.toggle('active', i === current);
                });
            }

            function updateArrows() {
                if (!btnPrev || !btnNext) return;
                if (!opts.infinite) {
                    btnPrev.disabled = current === 0;
                    btnNext.disabled = current >= total - getPerView();
                }
            }

            function goTo(idx) {
                var pv = getPerView();
                if (opts.infinite) {
                    current = ((idx % total) + total) % total;
                } else {
                    current = Math.max(0, Math.min(idx, total - pv));
                }
                var slideWidth = slides[0].offsetWidth;
                track.style.transition = 'transform ' + opts.slideSpeed + 'ms cubic-bezier(0.25,0.1,0.25,1)';
                track.style.transform  = 'translateX(-' + (current * slideWidth) + 'px)';
                updateDots();
                updateArrows();
            }

            function next() { goTo(current + 1); }
            function prev() { goTo(current - 1); }

            if (btnNext) btnNext.addEventListener('click', function() { resetAutoplay(); next(); });
            if (btnPrev) btnPrev.addEventListener('click', function() { resetAutoplay(); prev(); });

            // Touch / swipe
            var touchStart = null;
            track.addEventListener('touchstart', function(e) {
                touchStart = e.touches[0].clientX;
            }, { passive: true });
            track.addEventListener('touchend', function(e) {
                if (touchStart === null) return;
                var diff = touchStart - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) {
                    resetAutoplay();
                    diff > 0 ? next() : prev();
                }
                touchStart = null;
            });

            // Autoplay
            function startAutoplay() {
                if (!opts.autoplay) return;
                autoplayTimer = setInterval(next, opts.autoplaySpeed);
            }

            function resetAutoplay() {
                if (!opts.autoplay) return;
                clearInterval(autoplayTimer);
                startAutoplay();
            }

            // Keyboard nav when focused
            wrap.setAttribute('tabindex', '0');
            wrap.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft')  { resetAutoplay(); prev(); }
                if (e.key === 'ArrowRight') { resetAutoplay(); next(); }
            });

            // Init
            updateArrows();
            startAutoplay();

            // Recalculate on resize
            var resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() { goTo(current); }, 150);
            });
        });
    }

    /* ────────────────────────────────────────
       FAQ accordion
    ──────────────────────────────────────── */
    function initFaqAccordion() {
        document.querySelectorAll('.ravn-faq').forEach(function(faq) {
            var allowMulti = faq.dataset.multiOpen === '1';

            faq.querySelectorAll('.ravn-faq-question').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var answer = this.nextElementSibling;
                    var isOpen = this.getAttribute('aria-expanded') === 'true';

                    // Andere items sluiten, tenzij meerdere tegelijk open mogen.
                    if (!allowMulti) {
                        faq.querySelectorAll('.ravn-faq-question').forEach(function(b) {
                            b.setAttribute('aria-expanded', 'false');
                            // De PHP verbergt antwoorden met het hidden-attribuut;
                            // dat schakelen we hier, niet een CSS-klasse (hidden
                            // wint namelijk altijd van een klasse).
                            if (b.nextElementSibling) {
                                b.nextElementSibling.setAttribute('hidden', '');
                            }
                        });
                    }

                    if (isOpen) {
                        this.setAttribute('aria-expanded', 'false');
                        if (answer) answer.setAttribute('hidden', '');
                    } else {
                        this.setAttribute('aria-expanded', 'true');
                        if (answer) answer.removeAttribute('hidden');
                    }
                });
            });
        });
    }

    /* ────────────────────────────────────────
       TOC toggle
    ──────────────────────────────────────── */
    function initToc() {
        document.querySelectorAll('.ravn-toc').forEach(function(toc) {
            var toggleBtn = toc.querySelector('.ravn-toc-toggle');
            var content   = toc.querySelector('.ravn-toc-content');
            if (!toggleBtn || !content) return;

            toggleBtn.addEventListener('click', function() {
                // De PHP verbergt de inhoud via het hidden-attribuut; we
                // schakelen datzelfde attribuut zodat er één bron is.
                var isHidden = content.hasAttribute('hidden');
                if (isHidden) {
                    content.removeAttribute('hidden');
                    toggleBtn.textContent = 'verbergen';
                    toggleBtn.dataset.collapsed = '0';
                } else {
                    content.setAttribute('hidden', '');
                    toggleBtn.textContent = 'tonen';
                    toggleBtn.dataset.collapsed = '1';
                }
            });

            // Smooth anchor scroll
            toc.querySelectorAll('a[href^="#"]').forEach(function(a) {
                a.addEventListener('click', function(e) {
                    var target = document.getElementById(this.getAttribute('href').slice(1));
                    if (!target) return;
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        });
    }

    /* ────────────────────────────────────────
       Popup overlay (product link popup + sellers popup)
    ──────────────────────────────────────── */
    function initPopups() {
        // Openen: alle triggers (afbeelding, titel en de "toon alle
        // verkopers"-knop) hebben de klasse ravn-popup-trigger en een
        // data-product-id; de bijbehorende overlay heeft id="ravn-popup-{id}".
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('.ravn-popup-trigger');
            if (!trigger) return;

            var productId = trigger.dataset.productId;
            if (!productId) return;

            var overlay = document.getElementById('ravn-popup-' + productId);
            if (!overlay) return;

            e.preventDefault();
            openPopup(overlay);
        });

        // Toetsenbordbediening voor de niet-knop triggers (div's met
        // role="button"), zodat de popup ook zonder muis bereikbaar is.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var trigger = e.target.closest && e.target.closest('.ravn-popup-trigger');
            if (!trigger) return;

            var productId = trigger.dataset.productId;
            if (!productId) return;

            var overlay = document.getElementById('ravn-popup-' + productId);
            if (!overlay) return;

            e.preventDefault();
            openPopup(overlay);
        });

        // Sluiten: sluitknop of klik op de achtergrond van de overlay.
        document.addEventListener('click', function(e) {
            if (e.target.closest('.ravn-popup-close')) {
                closePopup(e.target.closest('.ravn-popup-overlay'));
                return;
            }
            if (e.target.classList.contains('ravn-popup-overlay')) {
                closePopup(e.target);
            }
        });

        // Sluiten met Escape.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.ravn-popup-overlay:not([hidden])').forEach(closePopup);
        });

        function openPopup(overlay) {
            // De zichtbaarheid wordt bepaald door het hidden-attribuut, want
            // de gegenereerde CSS gebruikt .ravn-popup-overlay[hidden] om te
            // verbergen (niet een .active-klasse).
            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            var closeBtn = overlay.querySelector('.ravn-popup-close');
            if (closeBtn) closeBtn.focus();
        }

        function closePopup(overlay) {
            if (!overlay) return;
            overlay.setAttribute('hidden', '');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    }

    /* ────────────────────────────────────────
       Init on DOM ready
    ──────────────────────────────────────── */
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function() {
        initClickTracking();
        initCarousels();
        initFaqAccordion();
        initToc();
        initPopups();
    });

})();
