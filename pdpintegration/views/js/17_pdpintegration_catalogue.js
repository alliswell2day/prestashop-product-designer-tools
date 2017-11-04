
$(document).ready(function() {
    $(document).on('click', '#grid', function(e){
        $('.ajax_request_customize_button').each( function( index, element ) {
            $(this).off('click').on('click', function (e) {
                e.preventDefault();
                pdpintegration.setLinkToButton($(this));
            });
        });
    });

    $(document).on('click', '#list', function(e){
        $('.ajax_request_customize_button').each( function( index, element ) {
            $(this).off('click').on('click', function (e) {
                e.preventDefault();
                pdpintegration.setLinkToButton($(this));
            });
        });
    });

    var id = $('#buy_block #product_page_product_id').val();
    var selected = JSON.parse(pdpintegration_enabled_products.replace(/&quot;/g, '"'));
    if(typeof selected[id] != 'undefined' ) {
        $('.ajax_request_customize_button').on('click', function (e) {
            e.preventDefault();
            pdpintegration.setLinkToButton($(this));
        });
    }
    $(document).on('click', '.ajax_request_customize_button', function(e){
       e.preventDefault();
       pdpintegration.setLinkToButton($(this));
    });

});


var pdpintegration = {
    setLinkToButton : function(element,pdpIdProduct){
      window.location.href=pdpintegration_url_pdp+'?pid='+pdpIdProduct;
    },
};
