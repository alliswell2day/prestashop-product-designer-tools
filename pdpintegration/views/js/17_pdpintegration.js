
$(document).ready(function() {
        $('.ajax_request_customize_button').each(function (index, element) {
            $(this).off('click').on('click', function (e) {
                e.preventDefault();
                pdpintegration.setLinkToButton($(this));
            });
        });
        $('.add-to-cart').each(function (index, element) {
            $(document).off('click', '.add_to_cart');
            pdpintegration.customize_convertButton($('#product_page_product_id').val(), $(this));
        });

        $('.product-customization').each(function (index, element) {
           pdpintegration.customize_remove($('#product_page_product_id').val(), $(this));
        });
});

var pdpintegration = {
    customize_convertButton : function (id, button) {
        var selected = JSON.parse(pdpintegration_enabled_products.replace(/&quot;/g, '"'));
        if(typeof selected[id] != 'undefined' ) {
                button.empty();
                button.html('<span>'+pdpintegration_button_text+'</span>');
                button.removeClass('add-to-cart');
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
                button.removeClass('product-customization');
                button.addClass('disable_pdp_custom');
        }
    },
        
    setLinkToButton : function(element,pdpIdProduct){
      window.location.href=pdpintegration_url_pdp+'?pid='+pdpIdProduct;
    },
    
}
