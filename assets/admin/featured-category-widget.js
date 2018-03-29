/*
 * Featured Category Widget JS.
 */
(function ($) {
    $(document).on('click', 'input.fc-remove-img', function (e) {
        var removeButton = $(this);
        removeButton.siblings('input.fc-image-id').val('');
        removeButton.siblings('input.fc-image-uri').val('');
        removeButton.siblings('img').removeAttr('src').addClass('u-hidden');
        removeButton.siblings('.fc-placeholder').removeClass('u-hidden');
        removeButton.siblings('.fc-select-img').removeClass('u-hidden');
        removeButton.parent().parent().find('.fc-title-input').trigger('change');
        removeButton.addClass('u-hidden');
    });

    $(document).on('click', 'input.fc-select-img, span.fc-placeholder', function (e) {
        var addButton = $('input.fc-select-img');
        var mediaLibrary = wp.media.frames.mediaLibrary = wp.media({
            title:    'Select or upload image',
            library:  {
                type: 'image'
            },
            button:   {
                text: 'Select'
            },
            multiple: false
        });

        mediaLibrary.on('select', function () {
            var attachment = mediaLibrary.state().get('selection').first().toJSON();

            addButton.siblings('input.fc-image-id').val(attachment.id);
            addButton.siblings('input.fc-image-uri').val(attachment.url);
            addButton.siblings('img').attr('src', attachment.url).removeClass('u-hidden');
            addButton.siblings('.fc-placeholder').addClass('u-hidden');
            addButton.siblings('.fc-remove-img').removeClass('u-hidden');
            addButton.parent().parent().find('.fc-title-input').trigger('change');
            addButton.addClass('u-hidden');
        });

        mediaLibrary.open();
    });

    $(document).on('change', '.fc-taxonomy-selection', function () {
        get_terms($(this));
    });

    get_terms = function (element) {
        var selected_taxonomy = $(element).val();
        var term_select = $(element).parent().parent().find('.fc-term-selection');

        var data = {
            'action': 'get_terms',
            'taxonomy': selected_taxonomy
        };

        $.post(ajaxurl, data, function (response) {
            if ($(term_select).html() !== response) {
                $(term_select).html('');
                $(term_select).append(response);
                $(term_select).click();
            }
            return true;
        });

        return false;
    };

})(jQuery);