var $ = jQuery.noConflict();
$(document).ready(function () {
    
    $('#futurepay-merchant-login').find(':input[name=save]').click(function (e) {
        e.preventDefault();
        
        // @todo: let the user know the page is doing something so they don't
        // get frustrated.

        if ($('#futurepay-merchant-login')
                .find(':input[name=user_name]').val().length < 1) {
            alert("The login and password fields can not be blank!");
            $('#futurepay-merchant-login').find(':input[name=user_name]').focus();
            return false;
        }
        if ($('#futurepay-merchant-login')
                .find(':input[name=password]').val().length < 1) {
            alert("The login and password fields can not be blank!");
            $('#futurepay-merchant-login').find(':input[name=password]').focus();
            return false;
        }
        
        $.post(login_api_endpoint, {
            ajax: '1',
            user_name: $('#futurepay-merchant-login')
                    .find(':input[name=user_name]').val(),
            password: $('#futurepay-merchant-login')
                    .find(':input[name=password]').val()
        }, function (r) {
            if (r.error == 0) {
                $('#futurepay-merchant-login').fadeOut(function () {
                    $('#woocommerce_futurepay_futurepay_gmid')
                            .val(r.key)
                            .focus();
                });
            }
            alert(r.message);
        }, 'json');
        
        return false;
    });

    // @todo: unbind keyup event inherited from the parent form
    
});