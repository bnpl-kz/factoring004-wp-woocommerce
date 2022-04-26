jQuery(function ($) {
    'use strict'
    $(document).ready(function () {

        let paymentId = 'factoring004';
        let paymentFactoring004 = $('#payment').find('.payment_methods').find('input:checked');

        $('body').on('updated_checkout', function() {
            agreementShowOrHide(paymentFactoring004.val())
        });

        $(document).on('change','.payment_methods',function (e) {
            agreementShowOrHide(e.target.value)
        })

        function agreementShowOrHide(value)
        {
            if (value === paymentId) {
                $('.factoring004-checkbox-agreement').css('display','block');
            } else {
                $('.factoring004-checkbox-agreement').css('display','none');
            }
        }

    })

})