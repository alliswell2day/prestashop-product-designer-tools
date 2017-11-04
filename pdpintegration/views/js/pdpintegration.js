
$(document).ready(function() {
        $('.ajax_request_customize_button').each(function (index, element) {
            $(this).off('click').on('click', function (e) {
                e.preventDefault();
                pdpintegration.setLinkToButton($(this));
            });
        });
        $('#add_to_cart button').each(function (index, element) {
            $(document).off('click', '#add_to_cart button');
            pdpintegration.customize_convertButton($('#buy_block #product_page_product_id').val(), $(this));
        });

        $('.ajax_add_to_cart_button').each(function (index, element) {
            pdpintegration.customize_convertButton($(this).attr('data-id-product'), $(this));
        });
        $('#customizationForm').each(function (index, element) {
           pdpintegration.customize_remove($('#buy_block #product_page_product_id').val(), $(this));
        });
});

var pdpintegration = {
    customize_convertButton : function (id, button) {
        var selected = JSON.parse(pdpintegration_enabled_products.replace(/&quot;/g, '"'));
        if(typeof selected[id] != 'undefined' ) {
                button.empty();
                button.html('<span>'+pdpintegration_button_text+'</span>');
                button.removeClass('ajax_add_to_cart_button');
                button.addClass('ajax_request_customize_button');
                button.off('click', "**");
                button.on('click', function (e) {
                    e.preventDefault();
                    pdpintegration.setLinkToButton($(this),selected[id].pdp_id_product);
                });
        }
    },
    
    customize_remove : function (id, button) {
        var selected = JSON.parse(pdpintegration_enabled_products.replace(/&quot;/g, '"'));
        if(typeof selected[id] != 'undefined' ) {
                button.empty();
                button.removeClass('customizableProductsFile');
                button.addClass('disable_pdp_custom');
        }
    },
        
    setLinkToButton : function(element,pdpIdProduct){
      window.location.href=pdpintegration_url_pdp+'?pid='+pdpIdProduct;
    },
    
}
