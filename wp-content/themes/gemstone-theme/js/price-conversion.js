jQuery(document).ready(function($) {
    $('.price-input').on('change', function() {
        var amount = $(this).val();
        
        $.ajax({
            url: priceConversionData.ajax_url,
            type: 'POST',
            data: {
                action: 'convert_price',
                amount: amount,
                nonce: priceConversionData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.formatted-price').text(response.data.formatted);
                }
            }
        });
    });
}); 