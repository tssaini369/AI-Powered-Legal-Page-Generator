jQuery(document).ready(function($) {
    // Country autosuggest
    $('#alg_country').on('input', function() {
        // Could integrate with a free API like RestCountries
        console.log('Consider adding autocomplete here');
    });
    
    // Business type examples
    $('#alg_business_type').focus(function() {
        if (!this.value) {
            this.placeholder = "Examples: SaaS, Digital Agency, Online Store";
        }
    });
});
