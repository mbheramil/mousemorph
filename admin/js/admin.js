(function ($) {
    'use strict';

    var $tabs   = $('.mmorph-admin-tab');
    var $panels = $('.mmorph-tab-panel');

    var hash = window.location.hash.replace('#', '');
    if (hash) activateTab(hash);

    $tabs.on('click', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        activateTab(tab);
        window.location.hash = tab;
    });

    function activateTab(tab) {
        $tabs.removeClass('active');
        $panels.removeClass('active');
        $tabs.filter('[data-tab="' + tab + '"]').addClass('active');
        $('#tab-' + tab).addClass('active');
    }

    /* Test Connection */
    $('#mmorph-test-btn').on('click', function () {
        var $status = $('#mmorph-test-status');
        $status.removeClass('hidden success error').addClass('loading')
            .html('<span class="mmorph-spinner-sm"></span> Testing connection…');

        $.post(mmorph_admin.ajax_url, {
            action: 'mmorph_test_connection',
            nonce:  mmorph_admin.nonce
        }, function (res) {
            $status.removeClass('loading');
            if (res.success) {
                $status.addClass('success').html('<span class="dashicons dashicons-yes-alt"></span> ' + res.data);
            } else {
                $status.addClass('error').html('<span class="dashicons dashicons-warning"></span> ' + res.data);
            }
        }).fail(function () {
            $status.removeClass('loading').addClass('error')
                .html('<span class="dashicons dashicons-warning"></span> Connection test failed.');
        });
    });

})(jQuery);
