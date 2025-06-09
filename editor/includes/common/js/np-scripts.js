jQuery(document).ready(function($) {
    jQuery('.u-blog .u-select-categories').change(function() {
        let selectedOption = jQuery(this).children("option:selected").val();
        let url = new URL(window.location.href);
        let params = new URLSearchParams(url.search);
        params.delete('postsCategoryId');
        params.delete('paged');
        params.append('postsCategoryId', selectedOption);
        url.search = params.toString();
        let newUrl = url.toString();
        if (newUrl) {
            window.location.href = newUrl;
        }
    });
});