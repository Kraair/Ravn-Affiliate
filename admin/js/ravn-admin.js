/* Ravn Affiliate – Admin JS */
(function($) {
    'use strict';

    /* ── Color pickers ── */
    function initColorPickers() {
        $('.ravn-color-picker').each(function() {
            if ($(this).hasClass('wp-color-picker')) return;
            $(this).wpColorPicker({
                change: function() {
                    // trigger CSS preview refresh if needed
                }
            });
        });
    }

    /* ── Shortcode copy ── */
    function initShortcodeCopy() {
        $(document).on('click', '.ravn-shortcode-copy', function(e) {
            e.preventDefault();
            var code = $(this).data('shortcode') || $(this).text().trim();
            navigator.clipboard.writeText(code).then(function() {}).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = code;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
            var $copied = $(this).find('.ravn-shortcode-copied');
            $copied.stop(true, true).show().delay(2000).fadeOut();
        });
    }

    /* ── EAN search ── */
    function initEanSearch() {
        var $btn     = $('#ravn-ean-search-btn');
        var $input   = $('#ravn-ean-search');
        var $results = $('#ravn-ean-results');

        if (!$btn.length) return;

        function applyProduct(p) {
            if (p.title)       $('#ravn-title').val(p.title);
            if (p.description) $('#ravn-desc').val(p.description);
            if (p.image)       $('#ravn-image').val(p.image);
            if (p.ean)         $('#ravn-ean').val(p.ean);

            // Maak direct een aanbieder-rij aan als het zoekresultaat een
            // (basis-)URL en netwerk bevat, zodat de gebruiker niet zelf
            // handmatig een aanbieder hoeft aan te maken. De uiteindelijke
            // affiliate/tracking-link (met Site ID) wordt pas bij weergave
            // opgebouwd door Ravn_Product::build_url() — deze URL is de basis.
            if (p.url && p.network && typeof addOfferRow === 'function') {
                var domain = '';
                try { domain = new URL(p.url).hostname.replace(/^www\./, ''); } catch (e) {}
                addOfferRow({
                    seller_name:   p.network === 'bol' ? 'Bol.com' : p.network,
                    seller_domain: domain,
                    affiliate_url: p.url,
                    network:       p.network,
                    price:         (p.price !== null && p.price !== undefined) ? p.price : '',
                    currency:      'EUR'
                });
            }
        }

        $btn.on('click', function() {
            var query = $.trim($input.val());
            if (!query) return;

            $btn.prop('disabled', true).text(ravnAdmin.searching);
            $results.empty();

            $.ajax({
                url:  ravnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ravn_search_ean',
                    nonce:  ravnAdmin.nonce,
                    query:  query
                },
                success: function(res) {
                    if (res.success && res.data && res.data.length) {
                        var $list = $('<div class="ravn-ean-result-list"></div>');
                        res.data.forEach(function(p) {
                            var $row = $(
                                '<div class="ravn-ean-result-item" style="display:flex;align-items:center;gap:10px;padding:8px;border:1px solid #ddd;border-radius:4px;margin-bottom:6px;">' +
                                    (p.image ? '<img src="' + escAttr(p.image) + '" style="width:40px;height:40px;object-fit:contain;">' : '') +
                                    '<span style="flex:1;">' + escHtml(p.title || '') +
                                        (p.ean ? ' <code>' + escHtml(p.ean) + '</code>' : '') +
                                        (p.network ? ' <em style="color:#888;">(' + escHtml(p.network) + ')</em>' : '') +
                                        ((p.price !== null && p.price !== undefined) ? ' <strong style="color:var(--ravn-admin-primary, #2271b1);">&euro; ' + escHtml(String(p.price).replace('.', ',')) + '</strong>' : '') +
                                        (p.debug ? '<br><small style="color:#b32d2e;">' + escHtml(p.debug) + '</small>' : '') +
                                    '</span>' +
                                    '<button type="button" class="button button-small ravn-ean-use">Gebruik</button>' +
                                '</div>'
                            );
                            $row.find('.ravn-ean-use').on('click', function() {
                                applyProduct(p);
                                var msg = ravnAdmin.found + ': ' + escHtml(p.title || '');
                                if (p.url && p.network) {
                                    msg += ' — ' + ravnAdmin.offer_prefilled;
                                }
                                $results.html('<p style="color:var(--ravn-admin-primary, #2271b1);">' + msg + '</p>');
                            });
                            $list.append($row);
                        });
                        $results.append($list);
                    } else {
                        $results.html('<p class="ravn-ean-empty">' + ravnAdmin.not_found + '</p>');
                    }
                },
                error: function() {
                    $results.html('<p class="ravn-ean-empty" style="color:#b32d2e;">' + ravnAdmin.error + '</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(ravnAdmin.search_btn);
                }
            });
        });

        $input.on('keypress', function(e) {
            if (e.which === 13) { e.preventDefault(); $btn.trigger('click'); }
        });
    }

    /* ── Bol.com EAN-diagnose ── */
    function initBolDiagTool() {
        var $btn    = $('#ravn-bol-diag-btn');
        var $input  = $('#ravn-bol-diag-ean');
        var $result = $('#ravn-bol-diag-result');

        if (!$btn.length) {
            if (window.console && window.console.warn) {
                console.warn('Ravn: #ravn-bol-diag-btn niet gevonden op deze pagina — diagnosetool niet geïnitialiseerd.');
            }
            return;
        }

        $btn.on('click', function() {
            var ean = $.trim($input.val());
            if (!ean) return;

            $btn.prop('disabled', true).text(ravnAdmin.searching);
            $result.hide().text('');

            $.ajax({
                url:  ravnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ravn_bol_diag_ean',
                    nonce:  ravnAdmin.nonce,
                    ean:    ean
                },
                success: function(res) {
                    if (res.success) {
                        var lines = [];
                        lines.push('EAN: ' + res.data.ean);
                        lines.push('');
                        lines.push('— offers/best-endpoint —');
                        lines.push('Prijs: ' + (res.data.offer_price !== null ? '€ ' + res.data.offer_price : '(geen)'));
                        if (res.data.offer_reason) {
                            lines.push('Reden: ' + res.data.offer_reason);
                        }
                        lines.push('');
                        lines.push('— product-endpoint (ruwe data) —');
                        lines.push(JSON.stringify(res.data.product_data, null, 2));
                        $result.show().text(lines.join('\n'));
                    } else {
                        $result.show().text('Fout: ' + (res.data && res.data.message ? res.data.message : ravnAdmin.error));
                    }
                },
                error: function() {
                    $result.show().text(ravnAdmin.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Testen');
                }
            });
        });

        $input.on('keypress', function(e) {
            if (e.which === 13) { e.preventDefault(); $btn.trigger('click'); }
        });
    }

    /* ── Offer rows ── */
    var offerIndex = 0;

    /**
     * Voegt een nieuwe aanbieder-rij toe aan het formulier, optioneel
     * vooraf ingevuld (bijv. vanuit een EAN-zoekresultaat). Retourneert
     * de toegevoegde rij als jQuery-object zodat de aanroeper eventueel
     * verder kan aanpassen.
     */
    function addOfferRow(prefill) {
        var tpl = $('#ravn-offer-template').html();
        if (!tpl) return null;
        tpl = tpl.replace(/#IDX#/g, offerIndex);
        var $row = $(tpl).appendTo('#ravn-offers-container');
        offerIndex++;
        initColorPickers();

        if (prefill) {
            if (prefill.seller_name)   $row.find('[name$="[seller_name]"]').val(prefill.seller_name);
            if (prefill.seller_domain) $row.find('[name$="[seller_domain]"]').val(prefill.seller_domain);
            if (prefill.affiliate_url) $row.find('[name$="[affiliate_url]"]').val(prefill.affiliate_url);
            if (prefill.network)       $row.find('[name$="[network]"]').val(prefill.network);
            if (prefill.price !== undefined && prefill.price !== '' && prefill.price !== null) {
                $row.find('[name$="[price]"]').val(prefill.price);
                // Een prijs die bol.com teruggeeft impliceert dat het product
                // op dat moment besteld kon worden.
                $row.find('[name$="[stock_status]"]').val('in_stock');
            }
            if (prefill.currency) $row.find('[name$="[currency]"]').val(prefill.currency);
        }
        return $row;
    }

    function initOffers() {
        // Count existing rows
        offerIndex = $('#ravn-offers-container .ravn-offer-row').length;

        // Add offer
        $('#ravn-add-offer-btn').on('click', function(e) {
            e.preventDefault();
            addOfferRow();
        });

        // Remove offer
        $(document).on('click', '.ravn-remove-offer', function(e) {
            e.preventDefault();
            if (confirm(ravnAdmin.remove_offer_confirm)) {
                $(this).closest('.ravn-offer-row').remove();
            }
        });

        // Sortable offers
        if ($.fn.sortable) {
            $('#ravn-offers-container').sortable({
                handle: '.ravn-offer-handle',
                placeholder: 'ravn-offer-placeholder',
                forcePlaceholderSize: true
            });
        }
    }

    /* ── Media library (image picker) ── */
    var mediaFrame;

    function initMediaLibrary() {
        $(document).on('click', '.ravn-media-upload', function(e) {
            e.preventDefault();
            var $btn    = $(this);
            var $target = $($btn.data('target'));

            if (mediaFrame) { mediaFrame.open(); return; }

            mediaFrame = wp.media({
                title:    ravnAdmin.media_title,
                button:   { text: ravnAdmin.media_use },
                multiple: false,
                library:  { type: 'image' }
            });

            mediaFrame.on('select', function() {
                var att = mediaFrame.state().get('selection').first().toJSON();
                $target.val(att.url);
                // Show preview if exists
                var $preview = $btn.siblings('.ravn-image-preview');
                if ($preview.length) {
                    $preview.attr('src', att.url).show();
                }
            });

            mediaFrame.open();
        });
    }

    /* ── Logo rows ── */
    function initLogoRows() {
        // Add new logo row (button id matches settings view)
        $('#ravn-add-logo-btn').on('click', function(e) {
            e.preventDefault();
            var tpl = $('#ravn-logo-row-template').html();
            if (tpl) {
                $('#ravn-custom-logos').append(tpl);
            }
        });

        // Remove logo row
        $(document).on('click', '.ravn-remove-logo-row', function(e) {
            e.preventDefault();
            $(this).closest('.ravn-logo-row').remove();
        });
    }

    /* ── Bol.com verbindingstest ── */
    function initBolConnectionTest() {
        initGenericConnectionTest({
            buttonId: 'ravn-test-bol-connection',
            resultId: 'ravn-bol-test-result',
            action:   'ravn_test_bol_connection',
            fields:   { client_id: '#bol_client_id', client_secret: '#bol_client_secret' },
            required: ['#bol_client_id', '#bol_client_secret']
        });
    }

    function initAwinConnectionTest() {
        initGenericConnectionTest({
            buttonId: 'ravn-test-awin-connection',
            resultId: 'ravn-awin-test-result',
            action:   'ravn_test_awin_connection',
            fields:   { publisher_id: '#awin_publisher_id', api_token: '#awin_api_token', advertiser_id: '#awin_advertiser_id' },
            required: ['#awin_publisher_id', '#awin_api_token', '#awin_advertiser_id']
        });
    }

    function initTradeTrackerConnectionTest() {
        initGenericConnectionTest({
            buttonId: 'ravn-test-tradetracker-connection',
            resultId: 'ravn-tradetracker-test-result',
            action:   'ravn_test_tradetracker_connection',
            fields:   { customer_id: '#tradetracker_customer_id', passphrase: '#tradetracker_api_key', site_id: '#tradetracker_site_id' },
            required: ['#tradetracker_customer_id', '#tradetracker_api_key', '#tradetracker_site_id']
        });
    }

    /**
     * Generieke verbindingstest-knop: leest de opgegeven veld-selectors,
     * stuurt ze naar de opgegeven AJAX-actie, en toont het resultaat.
     * Gedeeld door bol.com, Awin en TradeTracker om duplicatie te voorkomen.
     */
    function initGenericConnectionTest(cfg) {
        var $btn = $('#' + cfg.buttonId);
        if (!$btn.length) return;

        $btn.on('click', function(e) {
            e.preventDefault();
            var $result = $('#' + cfg.resultId);
            var data = { action: cfg.action, nonce: ravnAdmin.nonce };
            var missing = false;

            cfg.required.forEach(function(sel) {
                if (!$.trim($(sel).val())) missing = true;
            });
            if (missing) {
                $result.show().css({ background: '#fde8e8', color: '#b32d2e' }).text('Vul eerst alle velden hierboven in.');
                return;
            }

            Object.keys(cfg.fields).forEach(function(key) {
                data[key] = $.trim($(cfg.fields[key]).val());
            });

            $btn.prop('disabled', true).text(ravnAdmin.testing);
            $result.show().css({ background: '#f0f0f1', color: '#50575e' }).text(ravnAdmin.testing);

            $.ajax({
                url:  ravnAdmin.ajax_url,
                type: 'POST',
                data: data,
                success: function(res) {
                    if (res.success) {
                        $result.css({ background: '#edfaef', color: '#00a32a' }).text('✓ ' + (res.data.message || ravnAdmin.api_ok));
                    } else {
                        $result.css({ background: '#fde8e8', color: '#b32d2e' }).text('✗ ' + (res.data.message || ravnAdmin.api_error));
                    }
                },
                error: function() {
                    $result.css({ background: '#fde8e8', color: '#b32d2e' }).text('✗ ' + ravnAdmin.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Verbinding testen');
                }
            });
        });
    }

    /* ── Helpers ── */
    function escHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    function escAttr(str) {
        return String(str == null ? '' : str).replace(/"/g, '&quot;');
    }

    function showNotice(msg, type) {
        var cls  = type === 'success' ? 'notice-success' : 'notice-error';
        var $n   = $('<div class="notice ' + cls + ' is-dismissible ravn-notice-inline"><p>' + escHtml(msg) + '</p></div>');
        $('.wrap h1').after($n);
        if (wp && wp.a11y) wp.a11y.speak(msg);
        setTimeout(function() { $n.fadeOut(400, function() { $n.remove(); }); }, 5000);
    }

    /* ── Init ── */
    $(function() {
        function safeInit(name, fn) {
            try {
                fn();
            } catch (err) {
                if (window.console && window.console.error) {
                    console.error('Ravn: fout bij initialiseren van ' + name + ':', err);
                }
            }
        }

        safeInit('initColorPickers',       initColorPickers);
        safeInit('initShortcodeCopy',      initShortcodeCopy);
        safeInit('initOffers',             initOffers);
        safeInit('initEanSearch',          initEanSearch);
        safeInit('initBolDiagTool',        initBolDiagTool);
        safeInit('initMediaLibrary',       initMediaLibrary);
        safeInit('initLogoRows',           initLogoRows);
        safeInit('initBolConnectionTest',  initBolConnectionTest);
        safeInit('initAwinConnectionTest', initAwinConnectionTest);
        safeInit('initTradeTrackerConnectionTest', initTradeTrackerConnectionTest);
    });

})(jQuery);
