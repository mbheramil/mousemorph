(function ($) {
    'use strict';

    var promptsOn  = mmorph.enable_prompts || false;
    var resultStep = promptsOn ? 3 : 2;
    var i18n       = mmorph.i18n || {};
    var MAX_DIM    = 1200;
    var JPEG_QUALITY = 0.82;

    var state = {
        uploadId:     '',
        step:         1,
        uploading:    false,
        generating:   false,
        caricatureUrl:'',
        remaining:    null,
        limit:        null
    };

    var $maker, $steps, $panels, $dropzone, $fileInput, $preview, $previewImg,
        $scene, $charCount, $genBtn, $resultImg, $downloadBtn,
        $loading, $loadingText, $progressFill, $error, $errorText;

    function init() {
        $maker       = $('#mmorph-maker');
        if (!$maker.length) return;

        $steps       = $maker.find('.mmorph-step');
        $panels      = $maker.find('.mmorph-panel');
        $dropzone    = $('#mmorph-dropzone');
        $fileInput   = $('#mmorph-file-input');
        $preview     = $('#mmorph-upload-preview');
        $previewImg  = $('#mmorph-preview-img');
        $scene       = $('#mmorph-scene');
        $charCount   = $('#mmorph-char-count');
        $genBtn      = $('#mmorph-generate-btn');
        $resultImg   = $('#mmorph-result-img');
        $downloadBtn = $('#mmorph-download-btn');
        $loading     = $('#mmorph-loading');
        $loadingText = $('#mmorph-loading-text');
        $progressFill= $('#mmorph-progress-fill');
        $error       = $('#mmorph-error');
        $errorText   = $('#mmorph-error-text');

        bind();
        fetchRate();
    }

    /* ── Events ─────────────────────────────────── */

    function bind() {
        $dropzone.on('dragover dragenter', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
        $dropzone.on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('dragover'); });
        $dropzone.on('drop', function (e) {
            var f = e.originalEvent.dataTransfer.files;
            if (f.length) handleFile(f[0]);
        });
        $fileInput.on('change', function () { if (this.files.length) handleFile(this.files[0]); });
        $('#mmorph-change-photo').on('click', resetUpload);
        $('#mmorph-back-to-upload').on('click', resetAll);

        if ($scene.length) {
            $scene.on('input', function () { $charCount.text($(this).val().length); });
        }

        $maker.on('click', '.mmorph-chip', function () {
            $scene.val($(this).data('prompt')).trigger('input');
            $('.mmorph-chip').removeClass('active');
            $(this).addClass('active');
        });

        $genBtn.on('click', generate);
        $('#mmorph-regenerate-btn').on('click', function () {
            promptsOn ? goTo(2) : generate();
        });
        $('#mmorph-new-photo-btn').on('click', resetAll);
        $('#mmorph-error-dismiss').on('click', function () { $error.addClass('hidden'); });
    }

    /* ── Rate Limit ─────────────────────────────── */

    function fetchRate() {
        $.post(mmorph.ajax_url, { action: 'mmorph_rate_info', nonce: mmorph.nonce }, function (r) {
            if (r.success) {
                state.remaining = r.data.remaining;
                state.limit     = r.data.limit;
                showRate(r.data);
            }
        });
    }

    function showRate(d) {
        var $info = $('#mmorph-rate-info'), $text = $('#mmorph-rate-text');
        if (d.remaining <= 0) {
            $text.text(i18n.limit_reached);
            $info.removeClass('hidden').addClass('mmorph-rate-warn');
            $genBtn.prop('disabled', true).addClass('mmorph-btn-disabled');
        } else if (d.remaining <= 2) {
            $text.text(d.remaining + ' generation' + (d.remaining === 1 ? '' : 's') + ' remaining today');
            $info.removeClass('hidden').addClass('mmorph-rate-warn');
        } else {
            $text.text(d.remaining + ' of ' + d.limit + ' generations remaining today');
            $info.removeClass('hidden').removeClass('mmorph-rate-warn');
        }
    }

    /* ── Steps ──────────────────────────────────── */

    function goTo(step) {
        state.step = step;
        $steps.each(function () {
            var s = $(this).data('step');
            $(this).toggleClass('active', s <= step).toggleClass('completed', s < step);
        });
        $panels.removeClass('active');
        $('#mmorph-step-' + step).addClass('active');
    }

    /* ── Client-side Image Resize ──────────────── */

    function resizeImage(file) {
        return new Promise(function (resolve) {
            if (!window.HTMLCanvasElement) {
                resolve(file);
                return;
            }

            var img = new Image();
            var url = URL.createObjectURL(file);

            img.onload = function () {
                URL.revokeObjectURL(url);

                var w = img.naturalWidth;
                var h = img.naturalHeight;

                if (w <= MAX_DIM && h <= MAX_DIM && file.size <= 2 * 1024 * 1024) {
                    resolve(file);
                    return;
                }

                var ratio = Math.min(MAX_DIM / w, MAX_DIM / h, 1);
                var nw = Math.round(w * ratio);
                var nh = Math.round(h * ratio);

                var canvas = document.createElement('canvas');
                canvas.width = nw;
                canvas.height = nh;
                var ctx = canvas.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, nw, nh);

                canvas.toBlob(function (blob) {
                    if (!blob) { resolve(file); return; }
                    var resized = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(resized);
                }, 'image/jpeg', JPEG_QUALITY);
            };

            img.onerror = function () {
                URL.revokeObjectURL(url);
                resolve(file);
            };

            img.src = url;
        });
    }

    /* ── File ───────────────────────────────────── */

    function handleFile(file) {
        if (mmorph.allowed.indexOf(file.type) === -1) { showError(i18n.invalid_type); return; }
        if (file.size > mmorph.max_size) { showError(i18n.file_too_large); return; }

        var reader = new FileReader();
        reader.onload = function (e) {
            $previewImg.attr('src', e.target.result);
            $dropzone.addClass('hidden');
            $preview.removeClass('hidden');
        };
        reader.readAsDataURL(file);

        showLoading(i18n.optimizing || i18n.uploading, 0);
        resizeImage(file).then(function (optimized) {
            upload(optimized);
        });
    }

    function upload(file) {
        if (state.uploading) return;
        state.uploading = true;
        showLoading(i18n.uploading, 5);

        var fd = new FormData();
        fd.append('action', 'mmorph_upload_photo');
        fd.append('nonce', mmorph.nonce);
        fd.append('photo', file);

        $.ajax({
            url: mmorph.ajax_url, type: 'POST', data: fd,
            processData: false, contentType: false,
            xhr: function () {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) setProgress(5 + Math.round(e.loaded / e.total * 30));
                });
                return xhr;
            },
            success: function (r) {
                state.uploading = false;
                if (r.success) {
                    state.uploadId = r.data.upload_id;
                    $('#mmorph-upload-id').val(state.uploadId);
                    if (promptsOn) { hideLoading(); goTo(2); }
                    else { $loadingText.text(i18n.generating); setProgress(40); generate(); }
                } else {
                    hideLoading(); showError(r.data || i18n.error); resetUpload();
                }
            },
            error: function () { state.uploading = false; hideLoading(); showError(i18n.error); resetUpload(); }
        });
    }

    /* ── Generate ───────────────────────────────── */

    function generate() {
        if (state.generating) return;
        if (!state.uploadId) { showError(i18n.no_file); return; }
        state.generating = true;

        if ($loading.hasClass('hidden')) { showLoading(i18n.generating, 0); }
        else { $loadingText.text(i18n.generating); setProgress(40); }
        animateProgress(40);

        $.ajax({
            url: mmorph.ajax_url, type: 'POST', timeout: 60000,
            data: {
                action: 'mmorph_generate', nonce: mmorph.nonce,
                upload_id: state.uploadId,
                scene: $scene.length ? $scene.val() : ''
            },
            success: function (r) {
                if (r.success) {
                    if (r.data.remaining !== undefined) {
                        state.remaining = r.data.remaining;
                        state.limit = r.data.limit;
                        showRate(r.data);
                    }
                    if (r.data.gen_status === 'ready' && r.data.caricature_url) {
                        finish(r.data.caricature_url);
                    } else {
                        poll(r.data);
                    }
                } else {
                    state.generating = false; hideLoading();
                    showError(r.data && r.data.indexOf && r.data.indexOf('not allowed') !== -1 ? i18n.moderation : (r.data || i18n.error));
                }
            },
            error: function () { state.generating = false; hideLoading(); showError(i18n.error); }
        });
    }

    /* ── Polling — Predictions API with server-side polling ── */

    function poll(data) {
        var maxTime = 180000;
        var startTime = Date.now();
        var delay = 3000;
        var pollMode = data.poll_mode || 'prediction';
        var requestId = data.request_id || '';
        var cdnUrl = data.caricature_url || '';

        $loadingText.text(i18n.generating);

        function check() {
            var elapsed = Date.now() - startTime;

            if (elapsed > maxTime) {
                state.generating = false;
                hideLoading();
                showError(i18n.timeout);
                return;
            }

            if (elapsed > 30000) $loadingText.text(i18n.almost);

            var postData = {
                action: 'mmorph_poll_status',
                nonce: mmorph.nonce,
                poll_mode: pollMode,
                request_id: requestId,
                url: cdnUrl,
                upload_id: state.uploadId
            };

            $.post(mmorph.ajax_url, postData, function (r) {
                if (!r.success) {
                    state.generating = false;
                    hideLoading();
                    showError(r.data || i18n.error);
                    return;
                }

                var s = r.data.status;
                if (s === 'ready') {
                    var outputUrl = r.data.url || cdnUrl;
                    finish(outputUrl);
                } else if (s === 'error') {
                    state.generating = false;
                    hideLoading();
                    showError(r.data.error || i18n.error);
                } else {
                    delay = Math.min(delay * 1.25, 8000);
                    setTimeout(check, delay);
                }
            }).fail(function () {
                delay = Math.min(delay * 1.5, 10000);
                setTimeout(check, delay);
            });
        }

        setTimeout(check, delay);
    }

    function finish(imageUrl) {
        state.generating = false;
        setProgress(100);
        $loadingText.text(i18n.done);
        setTimeout(function () { hideLoading(); showResult(imageUrl); }, 500);
    }

    function showResult(imageUrl) {
        state.caricatureUrl = imageUrl;
        $resultImg.attr('src', imageUrl);
        $downloadBtn.attr('href', imageUrl);
        $('#mmorph-caricature-url').val(imageUrl);
        goTo(resultStep);
    }

    /* ── Loading / Progress / Error ─────────────── */

    var progInterval;

    function showLoading(text, pct) { $loading.removeClass('hidden'); $loadingText.text(text); setProgress(pct || 0); }
    function hideLoading() { $loading.addClass('hidden'); clearInterval(progInterval); }
    function setProgress(pct) { $progressFill.css('width', Math.min(pct, 100) + '%'); }

    function animateProgress(start) {
        var pct = start || 5;
        clearInterval(progInterval);
        progInterval = setInterval(function () {
            pct += Math.random() * 2.5 + 0.5;
            if (pct >= 88) { $loadingText.text(i18n.almost); clearInterval(progInterval); }
            setProgress(Math.min(pct, 92));
        }, 1500);
    }

    function showError(msg) {
        $errorText.text(msg);
        $error.removeClass('hidden');
        setTimeout(function () { $error.addClass('hidden'); }, 8000);
    }

    /* ── Reset ──────────────────────────────────── */

    function resetUpload() {
        $fileInput.val('');
        $preview.addClass('hidden');
        $dropzone.removeClass('hidden');
        $previewImg.attr('src', '');
    }

    function resetAll() {
        state.uploadId = '';
        state.caricatureUrl = '';
        if ($scene.length) $scene.val('').trigger('input');
        $('.mmorph-chip').removeClass('active');
        $('#mmorph-upload-id').val('');
        $('#mmorph-caricature-url').val('');
        resetUpload();
        goTo(1);
    }

    $(init);

})(jQuery);
