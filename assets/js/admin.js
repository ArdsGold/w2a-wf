(function ($) {
    'use strict';

    const WTAI = {
        init() {
            this.bindEvents();
            this.loadImportHistory();
        },

        bindEvents() {
            $('#wtai-file-drop-zone').on('click', () => $('#wtai-documents').trigger('click'));
            $('#wtai-documents').on('change', (e) => this.displayFileList(e.target.files));
            $('.wtai-file-drop-zone').on('dragover', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave drop', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                if (e.type === 'drop') {
                    const input = $('#wtai-documents')[0];
                    const dt = new DataTransfer();
                    Array.from(e.originalEvent.dataTransfer.files).forEach(file => dt.items.add(file));
                    input.files = dt.files;
                    WTAI.displayFileList(input.files);
                }
            });

            $('#wtai-import-form').on('submit', (e) => this.handleImport(e));
            $('#wtai-auto-images').on('change', function () {
                $('.wtai-image-matching-options').toggle($(this).is(':checked'));
            });
            $('#wtai-publish-schedule').on('change', function () {
                const scheduled = $(this).val() !== 'immediate';
                $('.wtai-schedule-options').toggle(scheduled);
                $('.wtai-end-date-field').toggle($(this).val() === 'custom_range');
            });
            $('#wtai-load-media').on('click', () => this.loadMediaImages());
            $('#wtai-media-search').on('input', () => this.debounceMediaSearch());
            $('#wtai-clear-history').on('click', () => this.clearImportHistory());
            $('#wtai-reset-settings').on('click', () => this.resetSettings());
            $('#wtai-view-error-stats').on('click', () => this.viewErrorStats());
            $('#wtai-clear-error-logs').on('click', () => this.clearErrorLogs());
        },

        displayFileList(files) {
            const $list = $('#wtai-file-list').empty();
            Array.from(files || []).forEach((file, index) => {
                const $item = $('<div>', { class: 'wtai-file-item' });
                $('<span>', { class: 'dashicons dashicons-media-document' }).appendTo($item);
                $('<span>', { class: 'wtai-file-name', text: file.name }).appendTo($item);
                $('<span>', { class: 'wtai-file-size', text: this.formatFileSize(file.size) }).appendTo($item);
                $('<button>', {
                    type: 'button', class: 'button-link wtai-remove-file', 'aria-label': 'Remove ' + file.name,
                    text: '×', 'data-index': index
                }).appendTo($item);
                $list.append($item);
            });
            $list.off('click', '.wtai-remove-file').on('click', '.wtai-remove-file', function () {
                const input = $('#wtai-documents')[0];
                const dt = new DataTransfer();
                Array.from(input.files).forEach((file, i) => { if (i !== Number($(this).data('index'))) dt.items.add(file); });
                input.files = dt.files;
                WTAI.displayFileList(input.files);
            });
        },

        formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const units = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            return (bytes / Math.pow(1024, i)).toFixed(i ? 2 : 0) + ' ' + units[i];
        },

        handleImport(e) {
            e.preventDefault();
            const input = $('#wtai-documents')[0];
            const files = Array.from(input.files || []);
            if (!files.length) return alert(wtai_ajax.strings.select_file);
            if (files.some(file => !/\.docx$/i.test(file.name))) return alert(wtai_ajax.strings.invalid_file);

            const $button = $('#wtai-import-button').prop('disabled', true);
            $('#wtai-spinner').addClass('is-active');
            $('#wtai-results').hide();

            // Form fields are already named settings[...]. Do not append a second JSON settings field.
            const formData = new FormData($('#wtai-import-form')[0]);
            formData.append('action', 'wtai_upload_documents');
            formData.append('nonce', wtai_ajax.nonce);

            $.ajax({
                url: wtai_ajax.ajax_url, type: 'POST', data: formData,
                processData: false, contentType: false, dataType: 'json'
            }).done((response) => {
                if (response.success) {
                    this.displayResults(response.data);
                    this.loadImportHistory();
                } else {
                    alert(response.data && response.data.message ? response.data.message : wtai_ajax.strings.unknown_error);
                }
            }).fail((xhr) => {
                let message = xhr.responseJSON?.data?.message;
                if (!message && xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        message = parsed?.data?.message;
                    } catch (error) {
                        const text = $('<div>').html(xhr.responseText).text().trim();
                        if (text && text.length < 500) message = text;
                    }
                }
                alert(message || wtai_ajax.strings.unknown_error + (xhr.status ? ' (HTTP ' + xhr.status + ')' : ''));
            }).always(() => {
                $button.prop('disabled', false);
                $('#wtai-spinner').removeClass('is-active');
            });
        },

        displayResults(data) {
            const $content = $('#wtai-results-content').empty();
            $('<div>', {
                class: 'wtai-status-message ' + (data.failed ? 'info' : 'success'),
                html: '<strong>Import Complete!</strong> ' + Number(data.successful) + ' of ' + Number(data.total) + ' documents imported successfully.'
            }).appendTo($content);

            if (data.results?.length) {
                $('<h3>', { text: 'Successful Imports' }).appendTo($content);
                data.results.forEach(result => {
                    const $item = $('<div>', { class: 'wtai-result-item success' });
                    $('<h4>', { text: result.file }).appendTo($item);
                    $('<p>', { text: 'Post ID: ' + result.post_id }).appendTo($item);
                    const $meta = $('<div>', { class: 'wtai-result-meta' });
                    $('<span>', { text: 'Status: ' + result.status }).appendTo($meta);
                    $('<span>', { text: 'Publish: ' + result.publish_date }).appendTo($meta);
                    $('<a>', { href: wtai_ajax.edit_post_url.replace('%d', encodeURIComponent(result.post_id)), target: '_blank', rel: 'noopener', text: 'Edit Post' }).appendTo($meta);
                    $item.append($meta).appendTo($content);
                });
            }

            if (data.errors?.length) {
                $('<h3>', { text: 'Failed Imports' }).appendTo($content);
                data.errors.forEach(error => {
                    const $item = $('<div>', { class: 'wtai-result-item error' });
                    $('<h4>', { text: error.file }).appendTo($item);
                    $('<p>', { text: 'Error: ' + error.error }).appendTo($item);
                    if (error.error_code) $('<p>', { html: '<strong>Error Code:</strong> ' }).append(error.error_code).appendTo($item);
                    $item.appendTo($content);
                });
            }
            $('#wtai-results').show();
        },

        debounceMediaSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.loadMediaImages(), 350);
        },

        loadMediaImages() {
            $.get(wtai_ajax.ajax_url, {
                action: 'wtai_get_media_images', nonce: wtai_ajax.nonce,
                search: $('#wtai-media-search').val() || '', page: 1
            }).done(response => {
                if (response.success) this.displayMediaImages(response.data.images || []);
            });
        },

        displayMediaImages(images) {
            const $preview = $('#wtai-media-preview').empty();
            if (!images.length) return $('<p>', { text: 'No images found in media library.' }).appendTo($preview);
            images.forEach(image => {
                const $item = $('<div>', { class: 'wtai-media-item', title: image.title + (image.description ? '\n' + image.description : '') });
                $('<img>', { src: image.thumbnail, alt: image.title }).appendTo($item);
                $('<div>', { class: 'wtai-media-title', text: image.title }).appendTo($item);
                $preview.append($item);
            });
        },

        loadImportHistory() {
            const $container = $('#wtai-import-history');
            if (!$container.length) return;
            $.post(wtai_ajax.ajax_url, { action: 'wtai_get_import_history', nonce: wtai_ajax.nonce }).done(response => {
                if (!response.success) return;
                $container.empty();
                const history = response.data.history || [];
                if (!history.length) return $('<p>', { text: 'No recent imports.' }).appendTo($container);
                history.forEach(item => {
                    const $row = $('<div>', { class: 'wtai-history-item' });
                    $('<div>', { class: 'wtai-history-date', text: item.import_date }).appendTo($row);
                    $('<div>', { class: 'wtai-history-file', text: item.original_filename }).appendTo($row);
                    $('<a>', { class: 'wtai-history-link', href: wtai_ajax.edit_post_url.replace('%d', encodeURIComponent(item.post_id)), target: '_blank', rel: 'noopener', text: 'View Post' }).appendTo($row);
                    $container.append($row);
                });
            });
        },

        postAction(action, confirmText, onSuccess) {
            if (!confirm(confirmText)) return;
            $.post(wtai_ajax.ajax_url, { action, nonce: wtai_ajax.nonce }).done(response => {
                if (response.success) onSuccess(response);
                else alert(response.data?.message || wtai_ajax.strings.unknown_error);
            }).fail(() => alert(wtai_ajax.strings.unknown_error));
        },

        clearImportHistory() {
            this.postAction('wtai_clear_import_history', 'Are you sure you want to clear all import history?', () => {
                $('#wtai-import-history').html('<p>No recent imports.</p>');
            });
        },
        resetSettings() {
            this.postAction('wtai_reset_settings', 'Are you sure you want to reset all settings to defaults?', () => location.reload());
        },
        viewErrorStats() {
            $.post(wtai_ajax.ajax_url, { action: 'wtai_get_error_stats', nonce: wtai_ajax.nonce }).done(response => {
                if (response.success) this.displayErrorStats(response.data);
            });
        },
        displayErrorStats(stats) {
            const $content = $('#wtai-error-stats-content').empty();
            $('<p>', { text: 'Total Errors: ' + Number(stats.total || 0) + ' | Recent: ' + (stats.recent?.length || 0) }).appendTo($content);
            if (stats.total) {
                Object.entries(stats.by_code || {}).forEach(([code, count]) => $('<p>', { text: code + ': ' + count }).appendTo($content));
            }
            $('#wtai-error-stats-section').show();
        },
        clearErrorLogs() {
            this.postAction('wtai_clear_error_logs', 'Are you sure you want to clear all error logs?', () => $('#wtai-error-stats-section').hide());
        }
    };

    $(WTAI.init.bind(WTAI));
})(jQuery);
