(function ($) {
    'use strict';

    const WTAI = {
        init() {
            $('.wtai-duplicate-trash-form').on('submit', event => {
                if (!confirm('Move this duplicate to Trash? The original will remain unchanged.')) event.preventDefault();
            });
            if (!$('#wtai-import-form, #wtai-image-settings-form, #wtai-settings').length) return;
            this.syncOptions();
            this.savedSettings = JSON.stringify(this.settingsFields());
            this.bindEvents();
            this.loadImportHistory();
            if ($('#wtai-drive-status').length) this.driveCredentials('status');
            this.showTab(new URL(window.location.href).searchParams.get('tab') || 'generate');
        },

        bindEvents() {
            $('.wtai-tabs').on('click', 'a', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (event.button && event.button !== 0)) return;
                event.preventDefault();
                this.showTab(new URL(event.currentTarget.href).searchParams.get('tab') || 'generate', event.currentTarget.href);
            });
            $(window).on('popstate.wtai', () => this.showTab(new URL(window.location.href).searchParams.get('tab') || 'generate'));
            $('#wtai-file-drop-zone').on('click keydown', (event) => {
                if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                if (!this.importing) $('#wtai-documents').trigger('click');
            });
            $('#wtai-documents').on('change', (e) => this.displayFileList(e.target.files));
            $('#wtai-document-source').on('change', () => { this.syncOptions(); this.queueSchedulePreview(); });
            $('#wtai-drive-folder').on('input', () => {
                this.driveListVersion = (this.driveListVersion || 0) + 1;
                this.driveFiles = [];
                this.driveNextPage = '';
                $('#wtai-drive-files').empty();
                $('#wtai-drive-more').prop('hidden', true);
                this.queueSchedulePreview();
            });
            $('#wtai-drive-load').on('click', () => this.loadDriveFiles());
            $('#wtai-drive-more').on('click', () => this.loadDriveFiles(true));
            $('#wtai-drive-select-all').on('change', event => {
                (this.driveFiles || []).forEach(file => { file.selected = event.target.checked; });
                $('#wtai-drive-files input').prop('checked', event.target.checked);
                this.queueSchedulePreview();
            });
            $('#wtai-drive-files').on('change', 'input', event => {
                const file = (this.driveFiles || []).find(item => item.id === event.target.dataset.fileId);
                if (file) file.selected = event.target.checked;
                $('#wtai-drive-select-all').prop('checked', this.driveFiles.length > 0 && this.driveFiles.every(item => item.selected));
                this.queueSchedulePreview();
            });
            $('#wtai-drive-save-key').on('click', () => this.driveCredentials('save_key'));
            $('#wtai-pixabay-save-key').on('click', () => this.pixabayCredentials('save_key'));
            $('#wtai-pixabay-remove-key').on('click', () => this.pixabayCredentials('remove_key'));
            $('#wtai-drive-remove-key').on('click', () => this.driveCredentials('remove_key'));
            $('#wtai-drive-options').on('click', 'a[href*="tab=settings"]', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                this.showTab('settings', event.currentTarget.href);
            });
            $('#wtai-month-number, #wtai-month-year').on('change', () => {
                this.updateMonthValue();
                this.syncOptions();
                this.queueSettingsSave();
            });
            $('[data-wtai-date-action]').on('click', event => this.chooseDateShortcut(event.currentTarget.dataset.wtaiDateAction));
            $('.wtai-file-drop-zone').on('dragover', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            }).on('dragleave drop', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                if (e.type === 'drop' && !WTAI.importing) {
                    const input = $('#wtai-documents')[0];
                    const dt = new DataTransfer();
                    Array.from(e.originalEvent.dataTransfer.files).forEach(file => dt.items.add(file));
                    input.files = dt.files;
                    WTAI.displayFileList(input.files);
                }
            });

            $('#wtai-import-form').on('submit', (e) => this.handleImport(e));
            $('#wtai-cancel-import').on('click', () => this.cancelImport());
            $('#wtai-import-form, #wtai-image-settings-form').on('input change', '[name^="settings["]', event => {
                this.syncSetting(event.target);
                this.syncOptions();
                this.queueSettingsSave();
            });
            $('#wtai-image-settings-form').on('submit', event => { event.preventDefault(); this.saveSettings().catch(() => {}); });
            $(window).on('pageshow.wtai', () => {
                this.syncOptions();
                if (JSON.stringify(this.settingsFields()) !== this.savedSettings) this.queueSettingsSave();
            }).on('beforeunload.wtai', event => {
                if (this.importing || JSON.stringify(this.settingsFields()) !== this.savedSettings) {
                    event.preventDefault();
                    event.originalEvent.returnValue = '';
                }
            });
            $('#wtai-load-media').on('click', () => this.loadMediaImages());
            $('#wtai-media-prev').on('click', () => this.loadMediaImages(Math.max(1, (this.mediaPage || 1) - 1)));
            $('#wtai-media-next').on('click', () => this.loadMediaImages((this.mediaPage || 1) + 1));
            $('#wtai-refresh-index').on('click', () => this.refreshMediaIndex());
            $('#wtai-save-google-key').on('click', () => this.googlePhotos('save_key'));
            $('#wtai-remove-google-key').on('click', () => this.googlePhotos('remove_key'));
            $('#wtai-check-google-profile').on('click', () => this.googlePhotos('check'));
            $('#wtai-preview-images').on('click', () => this.previewImages());
            $('#wtai-media-search').on('input', () => this.debounceMediaSearch());
            $('#wtai-clear-history').on('click', () => this.clearImportHistory());
            $('[data-wtai-copy-imports]').on('click', () => this.copyImports());
            $('#wtai-import-history').on('click', '.wtai-trash-import', event => this.trashImport(event));
            $('#wtai-reset-settings').on('click', () => this.resetSettings());
            $('#wtai-view-error-stats').on('click', () => this.viewErrorStats());
            $('#wtai-clear-error-logs').on('click', () => this.clearErrorLogs());
        },

        syncOptions() {
            this.syncMonthControls();
            $('#wtai-cancel-import').prop('disabled', !this.importing || Boolean(this.resetting || this.importAbort?.signal.aborted));
            const drive = $('#wtai-document-source').val() === 'drive';
            $('#wtai-drive-options').prop('hidden', !drive);
            $('#wtai-local-upload, #wtai-file-list').prop('hidden', drive);
            $('#wtai-documents').prop('disabled', drive || Boolean(this.importing));
            $('#wtai-drive-folder').prop('disabled', !drive || Boolean(this.importing));
            $('.wtai-gbp-options').toggle(['gbp', 'both'].includes($('#wtai-image-source').val()));
            const mode = $('#wtai-publish-schedule').val();
            const scheduled = mode !== 'immediate';
            const range = mode === 'custom_range';
            const month = mode === 'specific_month';
            $('.wtai-schedule-options').toggle(scheduled);
            $('.wtai-month-field').toggle(month);
            $('.wtai-start-date-field, .wtai-interval-field').toggle(scheduled && !month);
            $('#wtai-month-number, #wtai-month-year').prop('required', month).prop('disabled', !month || Boolean(this.importing));
            $('.wtai-end-date-field').toggle(range);
            $('#wtai-schedule-start-date').prop('required', scheduled && !month).prop('disabled', !scheduled || month || Boolean(this.importing));
            $('#wtai-schedule-interval').prop('disabled', !scheduled || month || Boolean(this.importing));
            const start = $('#wtai-schedule-start-date').val();
            const end = $('#wtai-schedule-end-date').prop('required', range).prop('disabled', !range || Boolean(this.importing))[0];
            const error = range && start && end?.value && end.value < start ? 'Choose an end date on or after the start date.' : '';
            if (end) { end.min = range ? (start || '') : ''; end.setCustomValidity(error); end.setAttribute('aria-invalid', error ? 'true' : 'false'); }
            $('#wtai-range-error').text(error);
            $('#wtai-start-date-label').text(this.readableDate(start));
            $('#wtai-end-date-label').text(this.readableDate(end?.value));
            this.queueSchedulePreview();
        },

        syncMonthControls(force = false) {
            const hidden = document.getElementById('wtai-schedule-month');
            if (!hidden || (!force && this.monthControlValue === hidden.value)) return;
            this.monthControlValue = hidden.value;
            const today = document.querySelector('.wtai-schedule-options').dataset.today;
            document.getElementById('wtai-month-number').value = hidden.value.slice(5, 7);
            const year = document.getElementById('wtai-month-year');
            year.value = hidden.value.slice(0, 4) || today.slice(0, 4);
            year.setCustomValidity('');
        },

        updateMonthValue() {
            const month = document.getElementById('wtai-month-number').value;
            const year = document.getElementById('wtai-month-year');
            year.setCustomValidity(year.value && !/^\d{4}$/.test(year.value) ? 'Enter a four-digit year, such as 2027.' : '');
            const value = month && year.value && year.validity.valid ? year.value.padStart(4, '0') + '-' + month : '';
            document.getElementById('wtai-schedule-month').value = value;
            this.monthControlValue = value;
        },

        shiftedDate(value, days = 0, months = 0) {
            const date = new Date(value + 'T12:00:00Z');
            if (!Number.isFinite(date.getTime())) return '';
            if (months) { date.setUTCDate(1); date.setUTCMonth(date.getUTCMonth() + months); }
            date.setUTCDate(date.getUTCDate() + days);
            return date.toISOString().slice(0, 10);
        },

        readableDate(value) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return '';
            const date = new Date(value + 'T12:00:00Z');
            return Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat(document.documentElement?.lang || undefined, { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC', calendar: 'gregory' }).format(date) : '';
        },

        chooseDateShortcut(action) {
            if (this.importing) return;
            const today = document.querySelector('.wtai-schedule-options').dataset.today;
            if (action === 'this-month' || action === 'next-month') {
                $('#wtai-schedule-month').val(this.shiftedDate(today, 0, action === 'next-month' ? 1 : 0).slice(0, 7));
                this.syncMonthControls(true);
            } else if (action === 'today' || action === 'tomorrow') {
                $('#wtai-schedule-start-date').val(this.shiftedDate(today, action === 'tomorrow' ? 1 : 0));
            } else if (action === 'range-week' || action === 'range-month') {
                const start = $('#wtai-schedule-start-date').val() || today;
                $('#wtai-schedule-start-date').val(start);
                $('#wtai-schedule-end-date').val(this.shiftedDate(start, action === 'range-week' ? 6 : 29));
            }
            this.syncOptions();
            this.queueSettingsSave();
        },

        queueSchedulePreview() {
            if (!document.getElementById('wtai-schedule-preview') || this.importing) return;
            const fields = this.settingsFields().filter(([name]) => /^settings\[(publish_schedule|schedule_.+)\]$/.test(name));
            const files = this.selectedDocuments();
            const count = files.length;
            const signature = JSON.stringify([fields, files.map(file => file.name)]);
            if (signature === this.schedulePreviewSignature) return;
            this.schedulePreviewSignature = signature;
            const version = this.schedulePreviewVersion = (this.schedulePreviewVersion || 0) + 1;
            clearTimeout(this.schedulePreviewTimer);
            const $preview = $('#wtai-schedule-preview').empty();
            if ($('#wtai-publish-schedule').val() === 'immediate') { $preview.text('Articles will publish immediately when you submit.'); return; }
            $preview.text('Updating schedule preview...');
            this.schedulePreviewTimer = setTimeout(async () => {
                try {
                    const data = this.importData(fields, 'wtai_validate_import', count || 3);
                    data.append('preview', '1');
                    const response = await this.request(data);
                    if (version !== this.schedulePreviewVersion) return;
                    $preview.empty();
                    if (!response.success) { $('<p>', { class: 'wtai-date-error', text: response.data?.message || 'Choose valid dates to preview the schedule.' }).appendTo($preview); return; }
                    const preview = response.data.preview;
                    $('<strong>', { text: count ? 'Schedule for ' + count + ' selected articles' : 'Example schedule for 3 articles' }).appendTo($preview);
                    $('<p>', { text: '9:00 AM in ' + preview.timezone + (count ? '. Files use the order shown above.' : '. Select documents to preview your actual batch.') }).appendTo($preview);
                    const $dates = $('<ol>');
                    preview.dates.forEach(item => $('<li>', { value: item.article, text: (files[item.article - 1] ? files[item.article - 1].name + ' - ' : '') + item.label }).appendTo($dates));
                    $dates.appendTo($preview);
                    if (preview.total > preview.dates.length) $('<p>', { class: 'description', text: 'Showing the first 5 dates and the last article.' }).appendTo($preview);
                    if (preview.repeated) $('<p>', { class: 'wtai-date-notice', text: preview.repeated + ' articles reuse a date. Extra articles loop back to the first available date.' }).appendTo($preview);
                    if (preview.publish_now) $('<p>', { class: 'wtai-date-notice', text: preview.publish_now + ' articles have a time that has already passed and will publish immediately.' }).appendTo($preview);
                } catch (error) {
                    if (version === this.schedulePreviewVersion) {
                        $preview.empty();
                        $('<p>', { class: 'wtai-date-error', text: error.network === false ? error.message : 'Schedule preview is unavailable. Submit will validate your dates again.' }).appendTo($preview);
                    }
                }
            }, 400);
        },

        showTab(tab, url) {
            const panels = Array.from(document.querySelectorAll('[data-wtai-panel]'));
            if (!panels.some(panel => panel.dataset.wtaiPanel === tab)) tab = 'generate';
            panels.forEach(panel => { panel.hidden = panel.dataset.wtaiPanel !== tab; });
            document.querySelectorAll('.wtai-tabs a').forEach(link => {
                const active = (new URL(link.href).searchParams.get('tab') || 'generate') === tab;
                link.classList.toggle('nav-tab-active', active);
                if (active) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            });
            if (url && window.location.href !== url) window.history.pushState(null, '', url);
            if (tab === 'images' && !this.mediaLoaded) {
                this.mediaLoaded = true;
                this.loadMediaImages();
            }
        },

        settingsForms() {
            return ['wtai-import-form', 'wtai-image-settings-form'].map(id => document.getElementById(id)).filter(Boolean);
        },

        settingsFields() {
            return Array.from(new Map(this.settingsForms().flatMap(form => Array.from(form.elements))
                .filter(field => field.name.startsWith('settings['))
                .map(field => [field.name, field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value])));
        },

        syncSetting(source) {
            this.settingsForms().forEach(form => {
                const field = form.elements.namedItem(source.name);
                if (!field || field === source) return;
                if (source.type === 'checkbox') field.checked = source.checked;
                else field.value = source.value;
            });
        },

        applySettings(settings) {
            Object.entries(settings).forEach(([key, value]) => {
                this.settingsForms().forEach(form => {
                    const field = form.elements.namedItem('settings[' + key + ']');
                    if (!field) return;
                    if (field.type === 'checkbox') field.checked = Boolean(value);
                    else field.value = String(value);
                });
            });
            this.syncOptions();
        },

        queueSettingsSave() {
            if (this.resetting) return;
            clearTimeout(this.settingsSaveTimer);
            $('[data-wtai-options-status]').text('Unsaved changes.').removeClass('wtai-save-error');
            this.settingsSaveTimer = setTimeout(() => this.saveSettings().catch(() => {}), 350);
        },

        async saveSettings() {
            clearTimeout(this.settingsSaveTimer);
            if (this.resetting) return;
            if (this.settingsSavePromise) {
                await this.settingsSavePromise;
                return this.saveSettings();
            }
            const fields = this.settingsFields();
            const signature = JSON.stringify(fields);
            if (signature === this.savedSettings) {
                $('[data-wtai-options-status]').text('Options saved.').removeClass('wtai-save-error');
                return;
            }
            $('[data-wtai-options-status]').text('Saving options...').removeClass('wtai-save-error');
            // Serialize saves so a slow older request cannot overwrite newer edits.
            this.settingsSavePromise = (async () => {
                const previous = new Map(JSON.parse(this.savedSettings || '[]'));
                const changed = fields.filter(([name, value]) => previous.get(name) !== value);
                const response = await this.request(this.importData(changed, 'wtai_save_settings', 0));
                if (!response.success) throw new Error(response.data?.message || 'Options could not be saved.');
                this.savedSettings = signature;
                if (JSON.stringify(this.settingsFields()) === signature) {
                    this.applySettings(response.data.settings);
                    this.savedSettings = JSON.stringify(this.settingsFields());
                }
            })();
            try {
                await this.settingsSavePromise;
            } catch (error) {
                $('[data-wtai-options-status]').text('Options not saved: ' + error.message + ' Change a setting to retry.').addClass('wtai-save-error');
                throw error;
            } finally {
                this.settingsSavePromise = null;
            }
            if (this.resetting) return;
            if (JSON.stringify(this.settingsFields()) !== this.savedSettings) return this.saveSettings();
            $('[data-wtai-options-status]').text('Options saved.');
        },

        displayFileList(files) {
            if (this.importing) return;
            this.queueSchedulePreview();
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

        selectedDocuments() {
            return $('#wtai-document-source').val() === 'drive' ? (this.driveFiles || []).filter(file => file.selected) : Array.from($('#wtai-documents')[0]?.files || []);
        },

        showDriveStatus(status, prefix = '') {
            if (status) {
                this.driveStatus = status;
                this.driveStatusRevision = (this.driveStatusRevision || 0) + 1;
            }
            const current = this.driveStatus || {};
            $('#wtai-drive-api-status').text(current.connection_label || (current.source === 'missing' ? 'Not configured' : 'Configured (not checked)')).removeClass('wtai-status-good').addClass(current.connection === 'connected' ? 'wtai-status-good' : '');
            const source = current.source === 'override' ? 'Using your saved Drive API key override.' : current.source === 'default' ? 'Using the server default Drive API key.' : 'No Drive API key configured. Add one in Settings & Logs.';
            $('#wtai-drive-key-status').text(source);
            $('#wtai-drive-status').text([prefix, source, current.notice].filter(Boolean).join(' '));
        },

        async driveCredentials(operation) {
            const revision = this.driveStatusRevision = (this.driveStatusRevision || 0) + 1;
            if (operation !== 'status') this.driveListVersion = (this.driveListVersion || 0) + 1;
            $('#wtai-drive-save-key, #wtai-drive-remove-key').prop('disabled', true);
            try {
                const data = this.importData([], 'wtai_google_drive', 0);
                data.append('operation', operation);
                if (operation === 'save_key') data.append('key', $('#wtai-drive-api-key').val());
                const response = await this.request(data);
                if (revision !== this.driveStatusRevision) return;
                if (!response.success) throw new Error(response.data?.message || 'Drive credentials could not be updated.');
                this.showDriveStatus(response.data);
                $('#wtai-drive-api-key').val('');
            } catch (error) {
                if (revision === this.driveStatusRevision) $('#wtai-drive-key-status').text(error.message);
            } finally {
                $('#wtai-drive-save-key, #wtai-drive-remove-key').prop('disabled', Boolean(this.importing));
            }
        },

        async loadDriveFiles(more = false) {
            if (this.importing) return;
            this.driveLoading = true;
            const version = this.driveListVersion = (this.driveListVersion || 0) + 1;
            $('#wtai-drive-load, #wtai-drive-more').prop('disabled', true);
            this.showDriveStatus(null, 'Loading public Word and Google Docs documents...');
            try {
                const data = this.importData([], 'wtai_google_drive', 0);
                data.append('operation', 'list');
                data.append('folder_url', $('#wtai-drive-folder').val());
                if (more) data.append('page_token', this.driveNextPage || '');
                const response = await this.request(data);
                if (version !== this.driveListVersion) return;
                if (!response.success) throw new Error(response.data?.message || 'Drive documents could not be loaded.');
                const files = more ? (this.driveFiles || []).slice() : [];
                const ids = new Set(files.map(file => file.id));
                response.data.files.forEach(file => { if (!ids.has(file.id) && files.length < 1000) { files.push({ ...file, selected: true }); ids.add(file.id); } });
                this.driveFiles = files;
                this.driveNextPage = response.data.next_page || '';
                const $list = $('#wtai-drive-files').empty();
                files.forEach(file => {
                    const $label = $('<label>', { class: 'wtai-file-item' });
                    $('<input>', { type: 'checkbox', checked: file.selected, 'data-file-id': file.id }).appendTo($label);
                    $('<span>', { class: 'wtai-file-name', text: file.name + (file.kind === 'google_doc' ? ' (Google Doc; exports to Word)' : ' (' + this.formatFileSize(file.size) + ')') }).appendTo($label);
                    $label.appendTo($list);
                });
                $('#wtai-drive-select-all').prop('checked', files.length > 0 && files.every(file => file.selected));
                $('#wtai-drive-more').prop('hidden', !this.driveNextPage || files.length >= 1000);
                this.showDriveStatus(response.data.status, files.length + ' documents loaded.' + (!files.length && !this.driveNextPage ? ' No supported files were found directly in this folder. Check public access, file types, and whether the documents are inside subfolders.' : '') + (this.driveNextPage ? (files.length >= 1000 ? ' Batch limit reached. Use smaller folders for remaining documents.' : ' More documents are available; choose Load More.') : ''));
                this.queueSchedulePreview();
            } catch (error) {
                if (version === this.driveListVersion) this.showDriveStatus(error.driveStatus, error.message);
            } finally {
                this.driveLoading = false;
                $('#wtai-drive-load, #wtai-drive-more').prop('disabled', Boolean(this.importing));
            }
        },

        async handleImport(e) {
            e.preventDefault();
            if (this.importing) return;
            const drive = $('#wtai-document-source').val() === 'drive';
            if (drive && this.driveLoading) return this.showDriveStatus(null, 'Wait for the document list to finish loading before submitting.');
            const files = this.selectedDocuments();
            if (!files.length) return alert(wtai_ajax.strings.select_file);
            if (files.length > 1000) return alert('Select at most 1,000 documents.');
            if (files.some(file => !/\.docx$/i.test(file.name))) return alert(wtai_ajax.strings.invalid_file);
            if (files.some(file => file.size > wtai_ajax.max_upload_size)) return alert('A selected document exceeds the server upload limit.');

            // Snapshot options once so every document uses the same schedule/settings.
            let fields = this.settingsFields();
            const totals = { results: [], errors: [], total: files.length, successful: 0, failed: 0, processed: 0 };
            const controller = this.importAbort = new AbortController();
            this.setImportBusy(true);
            try {
                await this.saveSettings();
                if (controller.signal.aborted) return;
                const preflight = this.importData(fields, 'wtai_validate_import', files.length);
                const validation = await this.request(preflight, controller.signal);
                if (!validation.success) throw new Error(validation.data?.message || wtai_ajax.strings.unknown_error);
                // Freeze all server-side preferences, including the separate image tab, for this batch.
                if (validation.data?.settings) fields = Object.entries(validation.data.settings).map(([key, value]) => ['settings[' + key + ']', typeof value === 'boolean' ? (value ? '1' : '0') : String(value)]);
                for (let index = 0; index < files.length; index++) {
                    if (controller.signal.aborted) break;
                    $('#wtai-progress-text').text('Importing ' + (index + 1) + ' of ' + files.length + ': ' + files[index].name);
                    const data = this.importData(fields, drive ? 'wtai_google_drive' : 'wtai_upload_documents', files.length, index);
                    if (drive) {
                        data.append('operation', 'import');
                        data.append('file_id', files[index].id);
                        data.append('resource_key', files[index].resource_key || '');
                    } else data.append('documents[]', files[index], files[index].name);
                    try {
                        const response = await this.request(data, controller.signal);
                        if (!response.success) {
                            const error = new Error(response.data?.message || wtai_ajax.strings.unknown_error);
                            error.code = response.data?.code;
                            error.driveQuota = response.data?.code === 'wtai_drive_quota';
                            error.driveStatus = response.data?.drive_status;
                            throw error;
                        }
                        totals.results.push(response.data);
                        if (drive && response.data.drive_status) this.showDriveStatus(response.data.drive_status);
                        totals.successful++;
                    } catch (error) {
                        if (error.cancelled || controller.signal.aborted) {
                            totals.unknown_file = files[index].name;
                            break;
                        }
                        totals.errors.push({ file: files[index].name, error: error.message });
                        totals.failed++;
                        // A lost connection leaves the current outcome unknown. Stop,
                        // retain the original selection, and let a safe retry reconcile it.
                        if (drive) this.showDriveStatus(error.driveStatus, error.message);
                        if (error.network || error.driveQuota || (drive && error.code === 'wtai_drive_key')) {
                            totals.processed = index + 1;
                            break;
                        }
                    }
                    totals.processed = index + 1;
                    $('#wtai-import-progress').prop('max', files.length).val(totals.processed);
                    this.displayResults(totals);
                }
                if (!controller.signal.aborted) {
                    this.displayResults(totals);
                    this.loadImportHistory();
                }
            } catch (error) {
                if (!controller.signal.aborted) alert(error.message);
            } finally {
                totals.cancelled = controller.signal.aborted;
                if (totals.cancelled) {
                    this.displayResults(totals);
                    this.loadImportHistory();
                }
                this.importAbort = null;
                this.setImportBusy(false);
                $('#wtai-progress-text').text(totals.cancelled ? 'Import cancelled. No further documents will be sent. Completed articles are kept. Keep the same selection and settings to retry safely.' : 'Processed ' + totals.processed + ' of ' + files.length + '. ' +
                    (totals.failed || totals.processed < files.length ? 'Keep the same selection and settings to retry safely.' : 'Import complete.'));
            }
        },

        cancelImport() {
            if (!this.importing || !this.importAbort || this.importAbort.signal.aborted) return;
            $('#wtai-cancel-import').prop('disabled', true);
            $('#wtai-progress-text').text('Cancelling import...');
            this.importAbort.abort();
        },

        importData(fields, action, count, index = 0) {
            const data = new FormData();
            fields.forEach(([name, value]) => data.append(name, value));
            data.append('action', action);
            data.append('nonce', wtai_ajax.nonce);
            data.append('document_count', count);
            data.append('document_index', index);
            return data;
        },

        request(data, signal) {
            return new Promise((resolve, reject) => {
                if (signal?.aborted) {
                    const error = new Error('Import cancelled.');
                    error.cancelled = true;
                    reject(error);
                    return;
                }
                const request = $.ajax({ url: wtai_ajax.ajax_url, type: 'POST', data, processData: false, contentType: false, dataType: 'json' });
                const abort = () => request.abort();
                signal?.addEventListener('abort', abort, { once: true });
                request
                    .done(resolve)
                    .fail((xhr, status) => {
                        const message = xhr.responseJSON?.data?.message;
                        const error = new Error(message || 'The request did not complete (HTTP ' + xhr.status + '). Retry with the same files and settings.');
                        error.code = xhr.responseJSON?.data?.code;
                        error.driveQuota = error.code === 'wtai_drive_quota';
                        error.driveStatus = xhr.responseJSON?.data?.drive_status;
                        error.cancelled = status === 'abort';
                        error.network = !xhr.status || xhr.status >= 502 || xhr.status === 408 || xhr.status === 413 || !xhr.responseJSON;
                        reject(error);
                    })
                    .always(() => signal?.removeEventListener('abort', abort));
            });
        },

        setImportBusy(busy) {
            this.importing = busy;
            $('#wtai-import-form, #wtai-image-settings-form').find('input, select, button').prop('disabled', busy);
            this.syncOptions();
            $('[data-wtai-copy-imports]').prop('disabled', busy || !(this.importHistory || []).length);
            $('.wtai-settings-tools button').prop('disabled', busy);
            $('#wtai-spinner').toggleClass('is-active', busy);
            $('#wtai-progress').show();
            if (busy) {
                $('#wtai-import-progress').val(0);
                $('#wtai-results').hide();
            }
        },

        displayResults(data) {
            const $content = $('#wtai-results-content').empty();
            if (data.cancelled) $('<p>', { class: 'wtai-status-message info', text: 'Import cancelled. Completed articles are kept.' + (data.unknown_file ? ' The request for "' + data.unknown_file + '" was stopped in your browser; it may still finish on the server. Check Import History before retrying with the same selection and settings.' : '') }).appendTo($content);
            $('<div>', {
                class: 'wtai-status-message ' + (data.failed ? 'info' : 'success'),
                text: Number(data.successful) + ' of ' + Number(data.total) + ' documents ready; ' + Number(data.failed) + ' failed; ' + (Number(data.total) - Number(data.processed)) + ' remaining.'
            }).appendTo($content);

            if (data.results?.length) {
                $('<h3>', { text: 'Articles' }).appendTo($content);
                data.results.forEach(result => {
                    const incomplete = [result.images, result.links].some(report => report && Number(report.requested) > 0 && Number(report.inserted) < Number(report.requested));
                    const $item = $('<div>', { class: 'wtai-result-item ' + (incomplete ? 'warning' : 'success') });
                    $('<h4>', { text: result.file }).appendTo($item);
                    $('<p>', { text: 'Post ID: ' + result.post_id + (result.existing ? ' (existing article reused)' : '') }).appendTo($item);
                    if (result.images) $('<p>', { text: 'Images inserted: ' + result.images.inserted + ' of ' + result.images.requested + (result.images.message ? '. ' + result.images.message : '') }).appendTo($item);
                    if (result.links) $('<p>', { text: 'Internal links inserted: ' + result.links.inserted + ' of ' + result.links.requested + (result.links.message ? '. ' + result.links.message : '') }).appendTo($item);
                    if (result.warning) $('<p>', { text: result.warning }).appendTo($item);
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

        loadMediaImages(page = 1) {
            const requestId = this.mediaRequestId = (this.mediaRequestId || 0) + 1;
            $('#wtai-media-status').text('Loading images...');
            $.get(wtai_ajax.ajax_url, {
                action: 'wtai_get_media_images', nonce: wtai_ajax.nonce,
                search: $('#wtai-media-search').val() || '', page
            }).done(response => {
                if (requestId !== this.mediaRequestId) return;
                if (response.success) this.displayMediaImages(response.data);
                else $('#wtai-media-status').text(response.data?.message || 'Images could not be loaded.');
            }).fail(() => { if (requestId === this.mediaRequestId) $('#wtai-media-status').text('Images could not be loaded. Use Reload Library to retry.'); });
        },

        displayMediaImages(data) {
            const $preview = $('#wtai-media-preview').empty();
            this.mediaPage = Number(data.page || 1);
            $('#wtai-media-status').text(Number(data.total) + ' images; page ' + this.mediaPage + ' of ' + Math.max(1, Number(data.pages)) + '.');
            $('#wtai-media-prev').prop('disabled', this.mediaPage <= 1);
            $('#wtai-media-next').prop('disabled', this.mediaPage >= Number(data.pages));
            if (!data.images.length) return $('<p>', { text: 'No matching images found.' }).appendTo($preview);
            data.images.forEach(image => {
                const $item = $('<article>', { class: 'wtai-metadata-card' });
                if (image.thumbnail || image.url) $('<img>', { src: image.thumbnail || image.url, alt: image.alt || image.title, loading: 'lazy' }).appendTo($item);
                $('<h4>', { text: image.title || 'Untitled image' }).appendTo($item);
                [['Alt text', image.alt], ['Caption', image.caption], ['Description', image.description]].forEach(([label, text]) => {
                    const $line = $('<p>');
                    $('<strong>', { text: label + ': ' }).appendTo($line);
                    $line.append(document.createTextNode(text || '(empty)')).appendTo($item);
                });
                if (image.edit_url) $('<a>', { href: image.edit_url, target: '_blank', rel: 'noopener', text: 'Edit Media' }).appendTo($item);
                $preview.append($item);
            });
        },

        async refreshMediaIndex() {
            $('#wtai-refresh-index').prop('disabled', true);
            $('#wtai-media-status').text('Refreshing keyword index...');
            try {
                const response = await this.request(this.importData([], 'wtai_refresh_media_index', 0));
                if (!response.success) throw new Error(response.data?.message || 'The index could not be refreshed.');
                this.loadMediaImages();
            } catch (error) {
                $('#wtai-media-status').text(error.message);
            } finally {
                $('#wtai-refresh-index').prop('disabled', false);
            }
        },

        async googlePhotos(operation) {
            $('#wtai-save-google-key, #wtai-remove-google-key, #wtai-check-google-profile').prop('disabled', true);
            $('#wtai-google-status').text(operation === 'check' ? 'Loading Google photos...' : 'Updating Google photo credentials...');
            $('#wtai-google-preview').empty();
            try {
                if (operation === 'check') await this.saveSettings();
                const data = this.importData([], 'wtai_google_photos', 0);
                data.append('operation', operation);
                if (operation === 'save_key') data.append('key', $('#wtai-google-api-key').val());
                const response = await this.request(data);
                if (!response.success) throw new Error(response.data?.message || 'Google photo request failed.');
                $('#wtai-google-status').text(response.data.message);
                if (response.data.content) $('#wtai-google-preview').html(response.data.content);
                if (operation !== 'check') $('#wtai-google-api-key').val('');
            } catch (error) {
                $('#wtai-google-status').text(error.message);
            } finally {
                $('#wtai-save-google-key, #wtai-remove-google-key, #wtai-check-google-profile').prop('disabled', false);
            }
        },

        async previewImages() {
            $('#wtai-preview-images').prop('disabled', true);
            $('#wtai-preview-status').text('Matching images...');
            $('#wtai-image-report, #wtai-article-preview').empty();
            try {
                await this.saveSettings();
                const data = this.importData(this.settingsFields(), 'wtai_preview_images', 0);
                data.append('title', $('#wtai-preview-title').val() || '');
                data.append('content', $('#wtai-preview-content').val() || '');
                const response = await this.request(data);
                if (!response.success) throw new Error(response.data?.message || 'Preview failed.');
                const report = response.data.report;
                $('#wtai-preview-status').text(report.inserted + ' of ' + report.requested + ' images inserted. ' + (report.message || ''));
                report.placements.forEach(placement => {
                    $('<p>', { text: placement.title + ' (#' + placement.id + ') - target ' + placement.target_percent + '%, actual ' + placement.actual_percent + '%. Matching terms: ' + placement.terms.join(', ') }).appendTo('#wtai-image-report');
                });
                $('#wtai-article-preview').html(response.data.content);
            } catch (error) {
                $('#wtai-preview-status').text(error.message);
            } finally {
                $('#wtai-preview-images').prop('disabled', false);
            }
        },

        loadImportHistory() {
            const $container = $('#wtai-import-history');
            if (!$container.length) return;
            this.importHistory = [];
            $('[data-wtai-copy-imports]').prop('disabled', true);
            $.post(wtai_ajax.ajax_url, { action: 'wtai_get_import_history', nonce: wtai_ajax.nonce }).done(response => {
                if (!response.success) return $('#wtai-history-status').text(response.data?.message || 'Import history could not be loaded.');
                $container.empty();
                const history = response.data.history || [];
                this.importHistory = history.filter(item => item.status === 'completed' && item.view_url);
                $('[data-wtai-copy-imports]').prop('disabled', Boolean(this.importing) || !this.importHistory.length);
                if (!history.length) return $('<p>', { text: 'No recent imports.' }).appendTo($container);
                history.forEach(item => {
                    const $row = $('<div>', { class: 'wtai-history-item' });
                    $('<div>', { class: 'wtai-history-date', text: item.import_date }).appendTo($row);
                    if (item.post_title) $('<div>', { text: item.post_title }).appendTo($row);
                    $('<div>', { class: 'wtai-history-file', text: item.original_filename }).appendTo($row);
                    const $actions = $('<div>', { class: 'wtai-history-actions' }).appendTo($row);
                    if (item.view_url) $('<a>', { class: 'button', href: item.view_url, target: '_blank', rel: 'noopener', text: 'View Post' }).appendTo($actions);
                    if (item.edit_url) $('<a>', { class: 'button', href: item.edit_url, target: '_blank', rel: 'noopener', text: 'Edit Post' }).appendTo($actions);
                    if (item.can_trash) $('<button>', { type: 'button', class: 'button wtai-trash-import', 'data-post-id': item.post_id, text: 'Move to Trash' }).appendTo($actions);
                    if (!item.view_url) $('<span>', { text: item.post_status === 'trash' ? 'Post is in Trash.' : 'Post is unavailable.' }).appendTo($actions);
                    $container.append($row);
                });
            }).fail(() => $('#wtai-history-status').text('Import history could not be loaded. Please refresh to retry.'));
        },

        async copyImports() {
            const entries = this.importHistory || [];
            if (!entries.length) return;
            const list = document.createElement('ol');
            entries.forEach(item => {
                const row = document.createElement('li');
                const link = document.createElement('a');
                link.href = item.view_url;
                link.textContent = item.post_title;
                row.appendChild(link);
                list.appendChild(row);
            });
            const text = entries.map((item, index) => (index + 1) + '. ' + item.post_title + ' — ' + item.view_url).join('\n');
            $('[data-wtai-copy-imports]').prop('disabled', true);
            try {
                let copied = false;
                if (navigator.clipboard?.write && typeof ClipboardItem !== 'undefined') {
                    try {
                        await navigator.clipboard.write([new ClipboardItem({
                            'text/html': new Blob([list.outerHTML], { type: 'text/html' }),
                            'text/plain': new Blob([text], { type: 'text/plain' }),
                        })]);
                        copied = true;
                    } catch (error) { /* Fall back to a user-initiated copy on HTTP or denied access. */ }
                }
                if (!copied) {
                    const copy = event => {
                        if (!event.clipboardData) return;
                        event.clipboardData.setData('text/html', list.outerHTML);
                        event.clipboardData.setData('text/plain', text);
                        event.preventDefault();
                        copied = true;
                    };
                    document.addEventListener('copy', copy);
                    try { copied = document.execCommand('copy') && copied; }
                    finally { document.removeEventListener('copy', copy); }
                }
                $('[data-wtai-copy-status]').text(copied ? entries.length + ' imports copied. Paste into a rich text editor for clickable titles.' : 'Copy failed. Your browser blocked clipboard access.');
            } catch (error) {
                $('[data-wtai-copy-status]').text('Copy failed. Your browser blocked clipboard access.');
            } finally {
                $('[data-wtai-copy-imports]').prop('disabled', Boolean(this.importing) || !(this.importHistory || []).length);
            }
        },

        async trashImport(event) {
            const button = event.currentTarget;
            $(button).prop('disabled', true);
            try {
                const data = this.importData([], 'wtai_trash_import', 0);
                data.append('post_id', button.dataset.postId);
                const response = await this.request(data);
                if (!response.success) throw new Error(response.data?.message || 'The post could not be moved to Trash.');
                $('#wtai-history-status').text(response.data.message);
                this.loadImportHistory();
            } catch (error) {
                $('#wtai-history-status').text(error.message);
                $(button).prop('disabled', false);
            }
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
                this.importHistory = [];
                $('[data-wtai-copy-imports]').prop('disabled', true);
                $('#wtai-import-history').html('<p>No recent imports.</p>');
            });
        },
        async resetSettings() {
            if (this.importing || this.resetting || !confirm('Are you sure you want to reset all settings to defaults?')) return;
            this.resetting = true;
            clearTimeout(this.settingsSaveTimer);
            $('#wtai-import-form, #wtai-image-settings-form').find('input, select, button').prop('disabled', true);
            $('.wtai-settings-tools button').prop('disabled', true);
            try {
                // Let any outstanding autosave finish before applying the reset.
                if (this.settingsSavePromise) await this.settingsSavePromise.catch(() => {});
                const response = await this.request(this.importData([], 'wtai_reset_settings', 0));
                if (!response.success) throw new Error(response.data?.message || 'Options could not be reset.');
                this.monthControlValue = null;
                this.applySettings(response.data.settings);
                this.savedSettings = JSON.stringify(this.settingsFields());
                $('[data-wtai-options-status]').text('Options reset and saved.').removeClass('wtai-save-error');
            } catch (error) {
                $('[data-wtai-options-status]').text('Options not reset: ' + error.message).addClass('wtai-save-error');
            } finally {
                this.resetting = false;
                $('#wtai-import-form, #wtai-image-settings-form').find('input, select, button').prop('disabled', false);
                $('.wtai-settings-tools button').prop('disabled', false);
                this.syncOptions();
                $('[data-wtai-copy-imports]').prop('disabled', !(this.importHistory || []).length);
            }
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
