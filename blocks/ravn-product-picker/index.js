/**
 * Ravn Affiliate – Product Picker Block (editor)
 * Uses wp.element, wp.blocks, wp.blockEditor, wp.components
 */

if (typeof ravnResolveServerSideRender !== 'function') {
    /**
     * Haalt de ServerSideRender-component op ongeacht WordPress-versie: in
     * sommige versies is window.wp.serverSideRender de component zelf, in
     * nieuwere versies (na de named-export migratie) is het een object met
     * een .ServerSideRender-property. Voorkomt een gebroken blok-preview
     * bij een WordPress-update. Gedeeld tussen alle blok-editorscripts;
     * de guard voorkomt dubbele declaratie als meerdere blok-scripts laden.
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

(function(blocks, element, blockEditor, components, i18n, apif) {
    var el          = element.createElement;
    var __          = i18n.__;
    var useState    = element.useState;
    var useEffect   = element.useEffect;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody   = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var Button      = components.Button;
    var Spinner     = components.Spinner;
    var Placeholder = components.Placeholder;
    var ServerSideRender = apif; // ravnResolveServerSideRender() geeft de component direct terug, of null

    blocks.registerBlockType('ravn-affiliate/product-picker', {
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

            // Show placeholder when no product selected
            if (!attrs.productId) {
                return el('div', { className: 'ravn-block-picker' },
                    el(Placeholder, {
                        icon: 'cart',
                        label: __('Affiliate Product', 'ravn-affiliate'),
                        instructions: __('Zoek een product om in te voegen.', 'ravn-affiliate'),
                    },
                        el('div', { style: { display:'flex', gap:'8px', width:'100%' } },
                            el('input', {
                                type: 'text',
                                className: 'components-text-control__input',
                                placeholder: __('Productnaam zoeken…', 'ravn-affiliate'),
                                value: query,
                                onChange: function(e) { setQuery(e.target.value); },
                                onKeyDown: function(e) { if (e.key === 'Enter') doSearch(); },
                                style: { flex: 1 }
                            }),
                            el(Button, {
                                variant: 'primary',
                                onClick: doSearch,
                                disabled: loading || !query,
                            }, loading ? el(Spinner) : __('Zoeken', 'ravn-affiliate'))
                        ),
                        loading && el(Spinner),
                        results.length > 0 && el('ul', { className: 'ravn-search-results', style: { listStyle:'none', margin:'8px 0 0', padding:0 } },
                            results.map(function(p) {
                                return el('li', { key: p.id, style: { borderBottom:'1px solid #eee', padding:'6px 0' } },
                                    el(Button, {
                                        variant: 'link',
                                        onClick: function() {
                                            setAttr({ productId: p.id, productTitle: p.title });
                                            setResults([]);
                                        }
                                    }, p.title + (p.ean ? ' [' + p.ean + ']' : ''))
                                );
                            })
                        ),
                        results.length === 0 && !loading && query && el('p', {}, __('Geen producten gevonden.', 'ravn-affiliate'))
                    )
                );
            }

            // Product selected: show preview via ServerSideRender
            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Product', 'ravn-affiliate'), initialOpen: true },
                        el('p', {}, el('strong', {}, attrs.productTitle || '#' + attrs.productId)),
                        el(Button, {
                            variant: 'secondary',
                            isDestructive: true,
                            onClick: function() { setAttr({ productId: 0, productTitle: '' }); }
                        }, __('Product wijzigen', 'ravn-affiliate'))
                    ),
                    el(PanelBody, { title: __('Weergave', 'ravn-affiliate'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Afbeelding verbergen', 'ravn-affiliate'),
                            checked: attrs.hideImage,
                            onChange: function(v) { setAttr({ hideImage: v }); }
                        }),
                        el(ToggleControl, {
                            label: __('Prijs verbergen', 'ravn-affiliate'),
                            checked: attrs.hidePrice,
                            onChange: function(v) { setAttr({ hidePrice: v }); }
                        }),
                        el(ToggleControl, {
                            label: __('Beschrijving verbergen', 'ravn-affiliate'),
                            checked: attrs.hideDescription,
                            onChange: function(v) { setAttr({ hideDescription: v }); }
                        }),
                        el(ToggleControl, {
                            label: __('Verkopers verbergen', 'ravn-affiliate'),
                            checked: attrs.hideSellers,
                            onChange: function(v) { setAttr({ hideSellers: v }); }
                        }),
                        el(ToggleControl, {
                            label: __('Beoordeling verbergen', 'ravn-affiliate'),
                            checked: attrs.hideRating,
                            onChange: function(v) { setAttr({ hideRating: v }); }
                        })
                    ),
                    el(PanelBody, { title: __('Tracking', 'ravn-affiliate'), initialOpen: false },
                        el(TextControl, {
                            label: __('Sub ID', 'ravn-affiliate'),
                            value: attrs.subId,
                            onChange: function(v) { setAttr({ subId: v }); },
                            help: __('Overschrijft de globale sub_id instelling voor dit product.', 'ravn-affiliate')
                        })
                    )
                ),
                ServerSideRender
                    ? el(ServerSideRender, {
                        block: 'ravn-affiliate/product-picker',
                        attributes: attrs
                    })
                    : el('div', { className: 'ravn-block-preview' },
                        el('p', {}, '📦 ' + (attrs.productTitle || 'Product #' + attrs.productId))
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
