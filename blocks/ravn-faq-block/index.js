(function(blocks, element, blockEditor, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;

    blocks.registerBlockType('ravn-affiliate/faq', {
        edit: function(props) {
            return el('div', {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('FAQ instellingen', 'ravn-affiliate') },
                        el(ToggleControl, {
                            label: __('Meerdere items tegelijk openen', 'ravn-affiliate'),
                            checked: props.attributes.multiOpen,
                            onChange: function(v) { props.setAttributes({ multiOpen: v }); }
                        })
                    )
                ),
                el('div', { className: 'ravn-block-preview', style: { padding:'12px', background:'#f6f7f7', borderRadius:'4px', border:'1px solid #ddd' } },
                    el('strong', {}, '📋 ' + __('FAQ accordion', 'ravn-affiliate')),
                    el('p', { style: { margin:'4px 0 0', fontSize:'13px', color:'#555' } },
                        __('De vragen uit het meta box van dit bericht worden hier getoond.', 'ravn-affiliate')
                    )
                )
            );
        },
        save: function() { return null; }
    });

})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
