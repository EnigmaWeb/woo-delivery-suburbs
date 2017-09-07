jQuery(document).ready(function () {
    jQuery('#shipping_suburb').prepend('<option value="" selected="selected" disabled="disabled">Select Suburb</option>');
    jQuery("input[name='delivery_suburb_checkout']").attr('readOnly', true);
    jQuery("input[name='shipping_postcode']").attr('readOnly', true);
    jQuery('.ds_shipping_suburb_select').change(function () {
        var textVal = jQuery('.ds_shipping_suburb_select').val();
        jQuery('#ds_delivery_suburb_hidden_val').val(textVal);
    });
});

