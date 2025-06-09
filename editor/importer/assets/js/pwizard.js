var Pwizard = (function($){

    var t;
    var current_step = '';
    var step_pointer = '';
    var callbacks = {
        do_next_step: function(btn) {
            do_next_step(btn);
        },
        import_content: function(btn){
            var content = new ContentManager(btn.text);
            content.init(btn);
        },
        replace_content: function(btn){
            var content = new ContentManager(btn.text);
            content.init(btn);
        },
        import_products: function(btn) {
            var content = new ContentManager(btn.text);
            content.init(btn);
        },
        replace_products: function(btn) {
            var content = new ContentManager(btn.text);
            content.init(btn);
        },
        theme_appearance_update: function() {

            function stopWithError(msg) {
                var settingsUrl = pwizard_params.settingsUrl || '#';
                $('.pwizard-wrap')
                    .removeClass('spinning')
                    .html(`<p>Failed to set option. An error occurred: <span style="color: red;">${msg}</span></p>
                           <p>You can set it in the <a href="${settingsUrl}">Plugin Settings</a></p>`);
            }

            var selectedOption = $('#np_theme_appearance').val();
            var _ajax_nonce = pwizard_params.wpnonceThemeAppearance;

            $.ajax({
                url: pwizard_params.urlContent,
                type: 'POST',
                data: {
                    action: 'np_theme_appearance_update',
                    _ajax_nonce: _ajax_nonce,
                    np_theme_appearance: selectedOption
                },
                success: function(response) {
                    if (response.success) {
                        do_next_step();
                    } else {
                        stopWithError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    stopWithError(error);
                }
            }).fail(function(xhr, status, error) {
                console.error(`AJAX fail: ${status} - ${error}`);
                console.error(xhr.responseText);
                stopWithError(error || 'An unknown error occurred.');
            });
        }
    };

    function window_loaded() {
        var maxHeight = 0;
        $('.pwizard-menu li.step').each(function(index) {
            $(this).attr('data-height', $(this).innerHeight());
            if($(this).innerHeight() > maxHeight) {
                maxHeight = $(this).innerHeight();
            }
        });
        $('.pwizard-menu li .detail').each(function(index) {
            $(this).attr('data-height', $(this).innerHeight());
            $(this).addClass('scale-down');
        });
        $('.pwizard-menu li.step').css('height', maxHeight);
        $('.pwizard-menu li.step:first-child').addClass('active-step');
        $('.pwizard-nav li:first-child').addClass('active-step');
        $('.pwizard-wrap').addClass('loaded');
        // init button clicks:
        $('.p-do-it').on('click', function(e) {
            e.preventDefault();
            step_pointer = $(this).data('step');
            current_step = $('.step-' + $(this).data('step'));
            $('.pwizard-wrap').addClass('spinning');
            if($(this).data('callback') && typeof callbacks[$(this).data('callback')] != 'undefined'){
                // we have to process a callback before continue with form submission
                callbacks[$(this).data('callback')](this);
                return false;
            } else {
                return true;
            }
        });
    }

    function do_next_step(btn) {
        current_step.addClass('done-step');
        $('.nav-step-' + step_pointer).addClass('done-step');
        current_step.fadeOut(500, function() {
            current_step = current_step.next();
            step_pointer = current_step.data('step');
            current_step.fadeIn();
            current_step.addClass('active-step');
            $('.nav-step-' + step_pointer).addClass('active-step');
            $('.pwizard-wrap').removeClass('spinning');
        });
    }

    function ContentManager(btnText){

        function doAjax(action, url, _ajax_nonce, importOptions) {
            return $.ajax({
                url: url,
                type: 'GET',
                data: ({
                    action: action,
                    _ajax_nonce: _ajax_nonce,
                    importOptions: importOptions
                })
            });
        }

        var pAction;
        var name = 'content';
        if (btnText === "Import Products") {
            //products import
            name = 'products';
            pAction = pwizard_params.actionImportProducts
        } else if (btnText === "Replace previously imported Products") {
            //products replace
            name = 'products';
            pAction = pwizard_params.actionReplaceProducts
        } else if (btnText === "Import Content") {
            //content import
            pAction = pwizard_params.actionImportContent;
        } else {
            //content replace
            pAction = pwizard_params.actionReplaceContent
        }

        function stopWithError(msg) {
            $('.pwizard-wrap')
                .removeClass('spinning')
                .html(`<p>Failed to import ${name}. An error occurred: <span style="color: red;">${msg}</span></p>`);
        }

        // set import options
        var importOptions = {};
        if (name === 'content') {
            importOptions.importSidebarsContent = $('#importSidebarsContent').is(":checked");
            importOptions.importProductsContent = $('#importProductsContent').is(":checked");
        }
        if (name === 'products') {
            importOptions.importProductsSource = $('#np_products_source').val();
        }

        doAjax(pAction, pwizard_params.urlContent, pwizard_params.wpnonceContent, importOptions).done(function (response) {
            if (response && response.indexOf('{') === 0) {
                let responseOptions = JSON.parse(response)
                if (responseOptions && responseOptions.error) {
                    stopWithError(responseOptions.error);
                }
            }
            complete();
        }).fail(function (xhr, status, error) {
            if (error) {
                stopWithError(error);
            }
        });

        return {
            init: function(btn){
                complete = function(){
                    do_next_step();
                };
            }
        }
    }

    return {
        init: function(){
			t = this;
			$(window_loaded);
        },
        callback: function(func){
            console.log(func);
            console.log(this);
        }
    }

})(jQuery);

Pwizard.init();