(function(blocks, element, blockEditor, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var RangeControl = components.RangeControl;
    var ToggleControl = components.ToggleControl;

    blocks.registerBlockType('ravn-affiliate/toc', {
        edit: function(props) {
            var attrs   = props.attributes;
            var setAttr = props.setAttributes;
            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Inhoudsopgave instellingen', 'ravn-affiliate') },
                        el(RangeControl, {
                            label: __('Begin niveau (H?)', 'ravn-affiliate'),
                            value: attrs.startLevel,
                            onChange: function(v) { setAttr({ startLevel: v }); },
                            min: 1, max: 6
                        }),
                        el(RangeControl, {
                            label: __('Eind niveau (H?)', 'ravn-affiliate'),
                            value: attrs.endLevel,
                            onChange: function(v) { setAttr({ endLevel: v }); },
                            min: 1, max: 6
                        }),
                        el(ToggleControl, {
                            label: __('Standaard ingeklapt', 'ravn-affiliate'),
                            checked: attrs.collapsed,
                            onChange: function(v) { setAttr({ collapsed: v }); }
                        })
                    )
                ),
                el('div', { className: 'ravn-block-preview', style: { padding:'12px', background:'#f6f7f7', borderRadius:'4px', border:'1px solid #ddd' } },
                    el('strong', {}, '📑 ' + __('Inhoudsopgave', 'ravn-affiliate')),
                    el('p', { style: { margin:'4px 0 0', fontSize:'13px', color:'#555' } },
                        __('H', 'ravn-affiliate') + attrs.startLevel + ' – H' + attrs.endLevel + '. ' +
                        __('Automatisch gegenereerd vanuit de koppenstructuur.', 'ravn-affiliate')
                    )
                )
            );
        },
        save: function() { return null; }
    });

})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
