/* Ravn Affiliate – FAQ Meta Box JS */
(function($) {
    'use strict';

    var faqIndex = 0;

    function initFaqMeta() {
        faqIndex = $('#ravn-faq-container .ravn-faq-question').length;

        // Add question
        $('#ravn-add-faq-question').on('click', function(e) {
            e.preventDefault();
            var tpl = '<div class="ravn-faq-question">'
                + '<button type="button" class="ravn-remove-faq" title="' + ravnFaqMeta.remove + '">&times;</button>'
                + '<label>' + ravnFaqMeta.question_label + ' <input type="text" name="ravn_faq[' + faqIndex + '][question]" style="width:100%;" placeholder="' + ravnFaqMeta.question_placeholder + '"></label>'
                + '<label style="margin-top:8px; display:block;">' + ravnFaqMeta.answer_label + ' <textarea name="ravn_faq[' + faqIndex + '][answer]" rows="3" style="width:100%;" placeholder="' + ravnFaqMeta.answer_placeholder + '"></textarea></label>'
                + '</div>';
            $('#ravn-faq-container').append(tpl);
            faqIndex++;
        });

        // Remove question
        $(document).on('click', '.ravn-remove-faq', function() {
            $(this).closest('.ravn-faq-question').remove();
        });

        // Sortable
        if ($.fn.sortable) {
            $('#ravn-faq-container').sortable({
                handle: '.ravn-faq-question label:first',
                placeholder: 'ravn-offer-placeholder',
                forcePlaceholderSize: true,
                update: function() {
                    // re-index names
                    $('#ravn-faq-container .ravn-faq-question').each(function(i) {
                        $(this).find('input').attr('name', 'ravn_faq[' + i + '][question]');
                        $(this).find('textarea').attr('name', 'ravn_faq[' + i + '][answer]');
                    });
                }
            });
        }
    }

    $(function() {
        initFaqMeta();
    });

})(jQuery);
