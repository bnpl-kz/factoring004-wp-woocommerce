jQuery(function ($) {
    'use strict'

    $(document).ready(function () {

        function block() {
            $('#woocommerce-order-items').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }

        function unblock() {
            $('#woocommerce-order-items').unblock();
        }

        if (factoring004_options.deliveries) {
            let delivery = $('.shipping_method').val();
            let deliveries = factoring004_options.deliveries.split(',')
            if ($.inArray(delivery, deliveries) !== -1) {
                $('.do-api-refund').removeClass('do-api-refund').addClass('do-api-refund-with-sms')
            }
        }

        /**
         * send otp return ajax
         */

        $(document).on('click','.do-api-refund-with-sms',function (e) {

            if (!confirm('Вы действительно хотите запустить процедуру возврата? Это действие не может быть отменено.')) {
                return;
            }

            let wpnonce = $('#factoring004_nonce').val();
            let order_id = $('#post_ID').val();
            let amount = $('#refund_amount').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'factoring004_send_otp_return',
                    data: {
                        order_id,
                        amount
                    },
                    _nonce : wpnonce,
                },
                beforeSend: function () {
                    block()
                },
                complete: function () {
                    //
                },
                success: function (response) {
                    if (response) {
                        $('#wc-backbone-modal-dialog-factoring004-otp').css('display','block')
                    } else {
                        alert('Произошла ошибка при отправке смс!')
                        unblock()
                        $('#wc-backbone-modal-dialog-factoring004-otp').css('display','none')
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText)
                    unblock()
                    $('#wc-backbone-modal-dialog-factoring004-otp').css('display','none')
                }
            })
        })

        /**
         * check otp return ajax
         */
        $(document).on('click','#factoring004-button-check-otp',function (e) {

            let otp_code = $('.factoring004-input-check-otp').val();

            if (!otp_code.match(/^([0-9]{4})$/)) {
                $('.factoring004-error-text').text('Неверно введен смс код!');
            } else {
                $('.factoring004-error-text').text('')

                let wpnonce = $('#factoring004_nonce').val();
                let order_id = $('#post_ID').val();
                let amount = $('#refund_amount').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'factoring004_check_otp_return',
                        data: {
                            order_id,
                            amount,
                            otp_code
                        },
                        _nonce : wpnonce,
                    },
                    beforeSend: function () {
                        $('#wc-backbone-modal-dialog-factoring004-otp').css('display','none')
                    },
                    complete: function () {
                        unblock()
                        $('.factoring004-input-check-otp').val('')
                    },
                    success: function (response) {
                        if (response) {
                            window.location.reload()
                        } else {
                            alert('Что-то пошло не так! Попробуйте позже!')
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText)
                    }
                })

            }

        })

        $(document).on('click','#factoring004-button-check-otp-cancel',function (e) {
            window.location.reload()
        })

    })
})



