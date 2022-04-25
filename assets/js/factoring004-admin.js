jQuery(function ($) {
    'use strict'

    $(document).on('click','#factoring004-button-delete',function (e) {
        let filename = $(e.target).attr('data-filename');
        let wpnonce = $('#_wpnonce').val();
        let button = $('#factoring004-button-delete');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'factoring004_agreement_destroy',
                filename: filename,
                _nonce : wpnonce,
            },
            beforeSend: function () {
                button.prop('disabled',true);
                button.prev('a').attr('onclick','return false')
            },
            complete: function () {
                button.prop('disabled',false);
                button.prev('a').removeAttr('onclick')
                location.reload();
            },
            success: function (data) {
                alert(data.message)
            },
            error: function(xhr, ajaxOptions, thrownError) {
                alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText)
            }
        })
    })

})