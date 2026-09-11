/**
 * Ravn Affiliate – Product List/Carousel Block (editor)
 */

if (typeof ravnResolveServerSideRender !== 'function') {
    /**
     * Haalt de ServerSideRender-component op ongeacht WordPress-versie (zie
     * uitgebreide toelichting in ravn-product-picker/index.js). Lokaal
     * gedupliceerd omdat blok-editorscripts onafhankelijk van elkaar laden
     * en er geen gedeeld modulesysteem is zonder build-proces; de guard
     * voorkomt een dubbele declaratie als beide scripts op dezelfde
     * pagina laden.
     */
    var ravnResolveServerSideRender = function() {
        var ssr = window.wp && window.wp.serverSideRender;
        if (!ssr) return null;
        if (typeof ssr === 'function') return ssr;
        if (ssr.ServerSideRender) return ssr.ServerSideRender;
        if (ssr.default) return ssr.default;
        return null;
    };
}

(function(blocks, element, blockEditor, components, i18n, serverSideRender) {
    var el          = element.createElement;
    var __          = i18n.__;
    var useState    = element.useState;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody   = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var RangeControl  = components.RangeControl;
    var SelectControl = components.SelectControl;
    var TextControl   = components.TextControl;
    var Button      = components.Button;
    var Spinner     = components.Spinner;
    var Placeholder = components.Placeholder;
    var ServerSideRender = serverSideRender; // al de opgeloste component, zie ravnResolveServerSideRender()

    blocks.registerBlockType('ravn-affiliate/product-list', {
        // Maakt het mogelijk om een oud "Affiliate Product"-blok met één klik
        // om te zetten naar dit blok (via de blok-toolbar → Transformeren naar).
        // Het oude blok blijft werken op bestaande pagina's, maar is uit de
        // blok-inserter verborgen.
        transforms: {
            from: [
                {
                    type: 'block',
                    blocks: ['ravn-affiliate/product-picker'],
                    transform: function(attributes) {
                        var titles = {};
                        if (attributes.productId && attributes.productTitle) {
                            titles[attributes.productId] = attributes.productTitle;
                        }
                        return blocks.createBlock('ravn-affiliate/product-list', {
                            productIds:      attributes.productId ? [attributes.productId] : [],
                            productTitles:   titles,
                            displayMode:     'single',
                            hideImage:       !!attributes.hideImage,
                            hidePrice:       !!attributes.hidePrice,
                            hideDescription: !!attributes.hideDescription,
                            hideSellers:     !!attributes.hideSellers,
                            hideRating:      !!attributes.hideRating,
                            subId:           attributes.subId || ''
                        });
                    }
                }
            ]
        },

        edit: function(props) {
            var attrs   = props.attributes;
            var setAttr = props.setAttributes;

            var searchState = useState('');
            var query       = searchState[0];
            var setQuery    = searchState[1];

            var resultsState = useState([]);
            var results      = resultsState[0];
            var setResults   = resultsState[1];

            var loadingState = useState(false);
            var loading      = loadingState[0];
            var setLoading   = loadingState[1];

            function doSearch() {
                if (!query) return;
                setLoading(true);
                window.fetch(
                    (window.ravnBlock && window.ravnBlock.rest_url) + 'ravn-affiliate/v1/search?q=' + encodeURIComponent(query),
                    { headers: { 'X-WP-Nonce': window.ravnBlock && window.ravnBlock.nonce } }
                )
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    setResults(Array.isArray(data) ? data : []);
                })
                .catch(function() { setResults([]); })
                .finally(function() { setLoading(false); });
            }

            // Praktische bovengrens: ServerSideRender stuurt de attributen als
            // GET-query (limiet ~2048 tekens). Met ruim 40 product-ID's komt
            // dat in de buurt, dus we begrenzen hier expliciet in plaats van
            // de preview stil te laten falen.
            var MAX_PRODUCTS = 40;

            function addProduct(p) {
                if (attrs.productIds.indexOf(p.id) !== -1) return; // al toegevoegd
                if (attrs.productIds.length >= MAX_PRODUCTS) return;
                var newIds    = attrs.productIds.concat([p.id]);
                var newTitles = Object.assign({}, attrs.productTitles);
                newTitles[p.id] = p.title;
                setAttr({ productIds: newIds, productTitles: newTitles });
            }

            function removeProduct(id) {
                setAttr({ productIds: attrs.productIds.filter(function(pid) { return pid !== id; }) });
            }

            function moveProduct(index, direction) {
                var ids = attrs.productIds.slice();
                var newIndex = index + direction;
                if (newIndex < 0 || newIndex >= ids.length) return;
                var tmp = ids[index];
                ids[index] = ids[newIndex];
                ids[newIndex] = tmp;
                setAttr({ productIds: ids });
            }

            var isCarousel = attrs.displayMode === 'carousel';
            var isSingle   = attrs.displayMode === 'single';

            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Producten', 'ravn-affiliate'), initialOpen: true },
                        el('div', { style: { display: 'flex', gap: '8px', marginBottom: '10px' } },
                            el('input', {
                                type: 'text',
                                className: 'components-text-control__input',
                                placeholder: __('Productnaam zoeken…', 'ravn-affiliate'),
                                value: query,
                                onChange: function(e) { setQuery(e.target.value); },
                                onKeyDown: function(e) { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } },
                                style: { flex: 1 }
                            }),
                            el(Button, {
                                variant: 'secondary',
                                onClick: doSearch,
                                disabled: loading || !query,
                            }, loading ? el(Spinner) : __('Zoeken', 'ravn-affiliate'))
                        ),
                        results.length > 0 && el('ul', { style: { listStyle: 'none', margin: '0 0 12px', padding: 0, maxHeight: '200px', overflowY: 'auto' } },
                            results.map(function(p) {
                                var alreadyAdded = attrs.productIds.indexOf(p.id) !== -1;
                                var limitReached = attrs.productIds.length >= MAX_PRODUCTS;
                                return el('li', { key: p.id, style: { borderBottom: '1px solid #eee', padding: '6px 0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                                    el('span', { style: { fontSize: '13px' } }, p.title + (p.ean ? ' [' + p.ean + ']' : '')),
                                    el(Button, {
                                        variant: 'link',
                                        disabled: alreadyAdded || limitReached,
                                        onClick: function() { addProduct(p); }
                                    }, alreadyAdded ? __('Toegevoegd', 'ravn-affiliate') : __('+ Toevoegen', 'ravn-affiliate'))
                                );
                            })
                        ),

                        attrs.productIds.length >= MAX_PRODUCTS && el('p', { style: { fontSize: '12px', color: '#b32d2e', margin: '0 0 12px' } },
                            __('Maximum van 40 producten per blok bereikt. Gebruik een tweede blok als je er meer wilt tonen.', 'ravn-affiliate')
                        ),

                        el('h4', { style: { margin: '12px 0 6px' } }, __('Gekozen producten', 'ravn-affiliate') + ' (' + attrs.productIds.length + ')'),
                        attrs.productIds.length === 0 && el('p', { style: { fontSize: '13px', color: '#757575' } }, __('Nog geen producten gekozen. Zoek hierboven en klik op Toevoegen.', 'ravn-affiliate')),
                        el('ul', { style: { listStyle: 'none', margin: 0, padding: 0 } },
                            attrs.productIds.map(function(id, index) {
                                return el('li', { key: id, style: { display: 'flex', alignItems: 'center', gap: '4px', padding: '4px 0', borderBottom: '1px solid #f0f0f0' } },
                                    el('span', { style: { flex: 1, fontSize: '13px' } }, attrs.productTitles[id] || ('#' + id)),
                                    el(Button, { icon: 'arrow-up-alt2', label: __('Omhoog', 'ravn-affiliate'), onClick: function() { moveProduct(index, -1); }, disabled: index === 0 }),
                                    el(Button, { icon: 'arrow-down-alt2', label: __('Omlaag', 'ravn-affiliate'), onClick: function() { moveProduct(index, 1); }, disabled: index === attrs.productIds.length - 1 }),
                                    el(Button, { icon: 'trash', label: __('Verwijderen', 'ravn-affiliate'), isDestructive: true, onClick: function() { removeProduct(id); } })
                                );
                            })
                        )
                    ),

                    el(PanelBody, { title: __('Weergave', 'ravn-affiliate'), initialOpen: false },
                        el(SelectControl, {
                            label: __('Weergavemodus', 'ravn-affiliate'),
                            value: attrs.displayMode,
                            options: [
                                { label: __('Eén product', 'ravn-affiliate'), value: 'single' },
                                { label: __('Lijst (grid)', 'ravn-affiliate'), value: 'list' },
                                { label: __('Carrousel / slider', 'ravn-affiliate'), value: 'carousel' }
                            ],
                            onChange: function(v) { setAttr({ displayMode: v }); },
                            help: isSingle ? __('Alleen het eerste gekozen product wordt getoond.', 'ravn-affiliate') : undefined
                        }),
                        ! isSingle && el(RangeControl, {
                            label: __('Aantal kolommen / zichtbaar tegelijk', 'ravn-affiliate'),
                            value: attrs.columns,
                            onChange: function(v) { setAttr({ columns: v }); },
                            min: 1, max: 6
                        }),
                        isCarousel && el(ToggleControl, {
                            label: __('Pijlen tonen', 'ravn-affiliate'),
                            checked: attrs.arrows,
                            onChange: function(v) { setAttr({ arrows: v }); }
                        }),
                        isCarousel && el(ToggleControl, {
                            label: __('Navigatiepunten (dots) tonen', 'ravn-affiliate'),
                            checked: attrs.dots,
                            onChange: function(v) { setAttr({ dots: v }); }
                        }),
                        isCarousel && el(ToggleControl, {
                            label: __('Oneindig doorlopen', 'ravn-affiliate'),
                            checked: attrs.infinite,
                            onChange: function(v) { setAttr({ infinite: v }); }
                        }),
                        isCarousel && el(ToggleControl, {
                            label: __('Automatisch afspelen', 'ravn-affiliate'),
                            checked: attrs.autoplay,
                            onChange: function(v) { setAttr({ autoplay: v }); }
                        })
                    ),

                    el(PanelBody, { title: __('Elementen tonen/verbergen', 'ravn-affiliate'), initialOpen: false },
                        el(ToggleControl, { label: __('Afbeelding verbergen', 'ravn-affiliate'), checked: attrs.hideImage, onChange: function(v) { setAttr({ hideImage: v }); } }),
                        el(ToggleControl, { label: __('Prijs verbergen', 'ravn-affiliate'), checked: attrs.hidePrice, onChange: function(v) { setAttr({ hidePrice: v }); } }),
                        el(ToggleControl, { label: __('Beschrijving verbergen', 'ravn-affiliate'), checked: attrs.hideDescription, onChange: function(v) { setAttr({ hideDescription: v }); } }),
                        el(ToggleControl, { label: __('Verkopers verbergen', 'ravn-affiliate'), checked: attrs.hideSellers, onChange: function(v) { setAttr({ hideSellers: v }); } }),
                        el(ToggleControl, { label: __('Beoordeling verbergen', 'ravn-affiliate'), checked: attrs.hideRating, onChange: function(v) { setAttr({ hideRating: v }); } })
                    ),

                    el(PanelBody, { title: __('Tracking', 'ravn-affiliate'), initialOpen: false },
                        el(TextControl, {
                            label: __('Sub ID', 'ravn-affiliate'),
                            value: attrs.subId,
                            onChange: function(v) { setAttr({ subId: v }); },
                            help: __('Overschrijft de globale sub_id instelling.', 'ravn-affiliate')
                        })
                    )
                ),

                attrs.productIds.length === 0
                    ? el(Placeholder, {
                        icon: 'grid-view',
                        label: __('Ravn Affiliate', 'ravn-affiliate'),
                        instructions: __('Open het instellingenpaneel rechts om producten te zoeken en toe te voegen.', 'ravn-affiliate')
                    })
                    : (ServerSideRender
                        ? el(ServerSideRender, {
                            block: 'ravn-affiliate/product-list',
                            // productTitles is puur voor de editor-UI en wordt
                            // door render_callback niet gebruikt; weglaten
                            // voorkomt dat de GET-request tegen de URL-
                            // lengtelimiet aanloopt bij veel/lange titels.
                            attributes: (function() {
                                var copy = Object.assign({}, attrs);
                                delete copy.productTitles;
                                return copy;
                            })()
                        })
                        : el('div', { className: 'ravn-block-preview' },
                            el('p', {}, '📦 ' + attrs.productIds.length + ' ' + __('producten gekozen', 'ravn-affiliate') + ' — ' + (isSingle ? __('één product', 'ravn-affiliate') : (isCarousel ? __('carrousel', 'ravn-affiliate') : __('lijst', 'ravn-affiliate'))))
                        )
                    )
            );
        },

        save: function() {
            return null; // Server-side rendered
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    ravnResolveServerSideRender()
);
