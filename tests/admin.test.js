/* Run: node wp-content/plugins/w2a-wf/tests/admin.test.js */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const files = ['first.docx', 'second.docx', 'third.docx'].map(name => ({ name, size: 100 }));
const fields = [['settings[publish_schedule]', 'specific_date'], ['settings[schedule_start_date]', '2099-01-01'], ...files.map(file => ['documents[]', file])];
class FormDataDouble {
    constructor(form) { this.values = form ? fields.slice() : []; }
    append(name, value) { this.values.push([name, typeof value === 'object' ? value : String(value)]); }
    entries() { return this.values[Symbol.iterator](); }
    get(name) { return this.values.find(entry => entry[0] === name)?.[1]; }
    getAll(name) { return this.values.filter(entry => entry[0] === name).map(entry => entry[1]); }
}
const input = { files };
const chain = { text() { return this; }, prop() { return this; }, val() { return this; }, removeClass() { return this; }, addClass() { return this; }, find() { return this; }, html() { return this; }, empty() { return this; }, append() { return this; }, appendTo() { return this; }, length: 1 };
const createdElements = [];
let documentSource = 'local';
function jquery(selector, properties) {
    if (properties) createdElements.push(properties);
    if (selector === '#wtai-documents') return [input];
    if (selector === '#wtai-document-source') return { val: () => documentSource };
    if (selector === '#wtai-import-form') return { 0: {}, find() { return chain; } };
    return chain;
}
const alerts = [];
const context = vm.createContext({
    jQuery: jquery, FormData: FormDataDouble,
    wtai_ajax: { ajax_url: '/ajax', nonce: 'test', max_upload_size: 10000, strings: { select_file: 'Select files', invalid_file: 'DOCX only', unknown_error: 'Error' } },
    alert: message => alerts.push(message),
    clearTimeout, setTimeout, AbortController, confirm: () => true,
});
const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin.js'), 'utf8');
assert(source.includes('$(WTAI.init.bind(WTAI));'));
vm.runInContext(source.replace('$(WTAI.init.bind(WTAI));', 'globalThis.subject = WTAI;'), context);
const subject = context.subject;
const displayResults = subject.displayResults;
const request = subject.request;
const settingsFields = subject.settingsFields;
const applySettings = subject.applySettings;
const imageForms = [0, 1].map(() => {
    const elements = [
        { name: 'settings[auto_insert_images]', type: 'checkbox', checked: false },
        { name: 'settings[max_images_per_post]', type: 'number', value: '3' },
    ];
    elements.namedItem = name => elements.find(field => field.name === name);
    return { elements };
});
subject.settingsForms = () => imageForms;
for (const form of imageForms) {
    const [enabled, count] = form.elements;
    enabled.checked = !enabled.checked;
    subject.syncSetting(enabled);
    count.value = enabled.checked ? '7' : '0';
    subject.syncSetting(count);
    const snapshot = new Map(subject.settingsFields());
    assert.equal(subject.settingsFields().length, 2, 'Shared settings are serialized only once.');
    assert(imageForms.every(other => other.elements[0].checked === enabled.checked && other.elements[1].value === count.value), 'Edits in either tab synchronize both image controls.');
    assert.equal(snapshot.get(enabled.name), enabled.checked ? '1' : '0');
    assert.equal(snapshot.get(count.name), count.value, 'Zero remains a valid saved image count.');
}
subject.syncOptions = () => {};
subject.applySettings({ auto_insert_images: false, max_images_per_post: 3 });
assert(imageForms.every(form => !form.elements[0].checked && form.elements[1].value === '3'), 'Server defaults/reset restore both image controls.');
delete subject.settingsForms;
subject.settingsFields = () => fields.filter(([name]) => name.startsWith('settings['));
const saveSettings = subject.saveSettings;
subject.saveSettings = async () => {};
subject.syncOptions = () => {};
let latest;
subject.setImportBusy = busy => { subject.importing = busy; };
subject.displayResults = result => { latest = result; };
const loadImportHistory = subject.loadImportHistory;
subject.loadImportHistory = () => {};
const event = { preventDefault() {} };

async function main() {
    let active = 0;
    let maximum = 0;
    const requests = [];
    subject.request = async data => {
        requests.push(data);
        active++;
        maximum = Math.max(maximum, active);
        await new Promise(resolve => setImmediate(resolve));
        active--;
        return { success: true, data: { post_id: requests.length, existing: false } };
    };
    await subject.handleImport(event);
    assert.equal(requests.length, 4, 'One preflight plus one request per document.');
    assert.equal(requests[0].get('action'), 'wtai_validate_import');
    assert.equal(requests[0].getAll('documents[]').length, 0, 'Preflight does not upload documents.');
    requests.slice(1).forEach((data, index) => {
        assert.equal(data.getAll('documents[]').length, 1, 'Each request uploads exactly one document.');
        assert.equal(data.get('documents[]'), files[index]);
        assert.equal(data.get('document_index'), String(index));
        assert.equal(data.get('document_count'), '3');
        assert.equal(data.get('settings[schedule_start_date]'), '2099-01-01');
        assert.equal(data.get('nonce'), 'test');
    });
    assert.equal(maximum, 1, 'Requests run sequentially.');
    assert.equal(latest.successful, 3);
    assert.equal(latest.processed, 3);
    assert.equal(subject.importing, false);

    const frozen = [];
    subject.request = async data => {
        if (data.get('action') === 'wtai_validate_import') return { success: true, data: { settings: { publish_schedule: 'specific_date', auto_insert_images: true, max_images_per_post: 12 } } };
        frozen.push(data);
        return { success: true, data: { post_id: frozen.length } };
    };
    await subject.handleImport(event);
    assert.equal(frozen.length, 3);
    frozen.forEach(data => {
        assert.equal(data.get('settings[max_images_per_post]'), '12', 'Each upload retains the image-tab snapshot returned by preflight.');
        assert.equal(data.get('settings[auto_insert_images]'), '1', 'Boolean image settings survive the batch snapshot.');
    });

    let calls = 0;
    subject.request = async () => { calls++; return { success: false, data: { message: 'Invalid date range' } }; };
    await subject.handleImport(event);
    assert.equal(calls, 1, 'Invalid schedules stop before uploading any document.');
    assert.equal(alerts.pop(), 'Invalid date range');
    assert.equal(subject.importing, false);

    calls = 0;
    subject.request = async data => {
        calls++;
        if (data.get('action') === 'wtai_validate_import') return { success: true };
        if (data.get('document_index') === '1') return { success: false, data: { message: 'Bad document' } };
        return { success: true, data: { post_id: calls, existing: true } };
    };
    await subject.handleImport(event);
    assert.equal(calls, 4, 'A document failure allows the remaining documents to proceed.');
    assert.equal(latest.successful, 2);
    assert.equal(latest.failed, 1);
    assert.equal(latest.processed, 3);
    assert.equal(latest.results[0].existing, true, 'Reused articles are retained in results.');

    documentSource = 'drive';
    subject.driveFiles = files.map((file, index) => ({ ...file, id: 'public-drive-id-' + index, resource_key: 'public-resource-key', selected: true }));
    const driveCalls = [];
    subject.request = async data => {
        driveCalls.push(data);
        if (data.get('action') === 'wtai_validate_import') return { success: true };
        if (data.get('document_index') === '1') return { success: false, data: { code: 'wtai_drive_quota', message: 'Quota reached', drive_status: { source: 'default', notice: 'Retry later or change project.' } } };
        return { success: true, data: { post_id: 321 } };
    };
    await subject.handleImport(event);
    assert.equal(driveCalls.length, 3, 'A Drive quota error stops the queue after preflight and the affected download.');
    assert.equal(latest.successful, 1);
    assert.equal(latest.processed, 2);
    assert.equal(subject.selectedDocuments().length, 3, 'Drive selections survive a quota pause for safe retries.');
    assert.equal(driveCalls[1].get('action'), 'wtai_google_drive');
    assert.equal(driveCalls[1].get('operation'), 'import');
    assert.equal(driveCalls[1].get('file_id'), 'public-drive-id-0');
    assert.equal(driveCalls[1].get('resource_key'), 'public-resource-key');
    assert.equal(driveCalls[1].get('documents[]'), undefined, 'Drive imports send public file references instead of browser uploads.');
    subject.driveFiles[1].selected = false;
    assert.equal(subject.selectedDocuments().length, 2, 'Only checked Drive documents enter scheduling and import.');
    documentSource = 'local';

    calls = 0;
    subject.request = async data => {
        calls++;
        if (data.get('action') === 'wtai_validate_import') return { success: true };
        const error = new Error('Lost connection');
        error.network = true;
        throw error;
    };
    await subject.handleImport(event);
    assert.equal(calls, 2, 'A network failure stops the remaining queue.');
    assert.equal(latest.failed, 1);
    assert.equal(latest.processed, 1);
    assert.equal(subject.importing, false);
    assert.equal(input.files, files, 'The original selection survives a failure.');

    for (const source of ['local', 'drive']) {
        documentSource = source;
        subject.driveFiles = files.map((file, index) => ({ ...file, id: 'cancel-test-' + index, selected: true }));
        const cancelCalls = [];
        subject.request = async (data, signal) => {
            cancelCalls.push(data);
            if (data.get('action') === 'wtai_validate_import' || data.get('document_index') === '0') return { success: true, data: { post_id: 111 } };
            return new Promise((resolve, reject) => signal.addEventListener('abort', () => {
                const error = new Error('Import cancelled.'); error.cancelled = true; reject(error);
            }, { once: true }));
        };
        const importing = subject.handleImport(event);
        await new Promise(resolve => setImmediate(resolve));
        subject.cancelImport();
        await importing;
        assert.equal(cancelCalls.length, 3, 'Cancel stops after preflight, one completed document, and the interrupted request for ' + source + '.');
        assert.equal(latest.successful, 1, 'Completed articles remain in the results.');
        assert.equal(latest.failed, 0, 'An interrupted request is not incorrectly counted as a failed import.');
        assert.equal(latest.processed, 1);
        assert.equal(latest.unknown_file, 'second.docx', 'The interrupted document is identified as an uncertain server outcome.');
        assert.equal(latest.cancelled, true);
        assert.equal(subject.importing, false);
        assert.equal(subject.importAbort, null);
        assert.equal(subject.selectedDocuments().length, 3, 'The original selection remains available for safe retries.');
    }
    documentSource = 'local';
    let finishSave;
    subject.saveSettings = () => new Promise(resolve => { finishSave = resolve; });
    let cancelledRequests = 0;
    subject.request = async () => { cancelledRequests++; return { success: true }; };
    const cancelledBeforeUpload = subject.handleImport(event);
    subject.cancelImport();
    finishSave();
    await cancelledBeforeUpload;
    assert.equal(cancelledRequests, 0, 'Cancelling while options save prevents preflight and uploads.');
    assert.equal(latest.processed, 0);
    assert.equal(latest.cancelled, true);
    subject.saveSettings = async () => {};
    await subject.handleImport(event);
    assert.equal(cancelledRequests, 4, 'A fresh Submit after cancellation starts a new batch normally.');
    assert.equal(latest.cancelled, false);

    let failRequest, completeRequest, aborts = 0;
    jquery.ajax = () => {
        const xhr = { status: 0, done() { return xhr; }, fail(callback) { failRequest = callback; return xhr; }, always(callback) { completeRequest = callback; return xhr; }, abort() { aborts++; failRequest(xhr, 'abort'); completeRequest(); } };
        return xhr;
    };
    const abortController = new AbortController();
    const pending = request.call(subject, new FormDataDouble(), abortController.signal);
    abortController.abort();
    await assert.rejects(pending, error => error.cancelled === true);
    assert.equal(aborts, 1, 'The active jQuery upload is actually aborted.');
    await assert.rejects(request.call(subject, new FormDataDouble(), abortController.signal), error => error.cancelled === true);
    assert.equal(aborts, 1, 'Already-cancelled requests do not start another upload.');

    input.files = [{ name: 'invalid.doc', size: 100 }];
    calls = 0;
    await subject.handleImport(event);
    assert.equal(calls, 0, 'Invalid extensions are rejected before requests.');
    assert.equal(alerts.pop(), 'DOCX only');

    subject.saveSettings = saveSettings;
    let current = [['settings[publish_schedule]', 'immediate']];
    subject.settingsFields = () => current;
    subject.applySettings = settings => { current = Object.entries(settings).map(([key, value]) => ['settings[' + key + ']', value]); };
    subject.savedSettings = JSON.stringify(current);
    calls = 0;
    subject.request = async () => { calls++; throw new Error('Unexpected save'); };
    await subject.saveSettings();
    assert.equal(calls, 0, 'Unchanged options do not generate a save request.');

    let release;
    const saveRequests = [];
    current = [['settings[publish_schedule]', 'specific_date']];
    subject.request = data => {
        saveRequests.push(data);
        if (saveRequests.length === 1) return new Promise(resolve => { release = resolve; });
        return Promise.resolve({ success: true, data: { settings: { publish_schedule: 'custom_range' } } });
    };
    const saving = subject.saveSettings();
    current = [['settings[publish_schedule]', 'custom_range']];
    const newerSave = subject.saveSettings();
    assert.equal(saveRequests.length, 1, 'Edits wait for the outstanding save rather than racing it.');
    release({ success: true, data: { settings: { publish_schedule: 'specific_date' } } });
    await Promise.all([saving, newerSave]);
    assert.equal(saveRequests.length, 2, 'A newer edit is saved after the older request completes.');
    assert.equal(current[0][1], 'custom_range', 'An older response cannot replace a newer schedule choice.');
    assert.equal(subject.savedSettings, JSON.stringify(current));

    current = [['settings[publish_schedule]', 'immediate']];
    subject.request = async () => { throw new Error('Offline'); };
    await assert.rejects(subject.saveSettings(), /Offline/);
    assert.notEqual(subject.savedSettings, JSON.stringify(current), 'Failed saves remain dirty and retryable.');
    subject.request = async () => ({ success: true, data: { settings: { publish_schedule: 'immediate' } } });
    await subject.saveSettings();
    assert.equal(subject.savedSettings, JSON.stringify(current), 'Retrying a failed save persists the current options.');

    // Reset waits for an old autosave, then becomes the final write.
    current = [['settings[publish_schedule]', 'specific_date']];
    const resetRequests = [];
    subject.request = data => {
        resetRequests.push(data.get('action'));
        if (resetRequests.length === 1) return new Promise(resolve => { release = resolve; });
        return Promise.resolve({ success: true, data: { settings: { publish_schedule: 'immediate' } } });
    };
    const beforeReset = subject.saveSettings();
    current = [['settings[publish_schedule]', 'custom_range']];
    const reset = subject.resetSettings();
    release({ success: true, data: { settings: { publish_schedule: 'specific_date' } } });
    await Promise.all([beforeReset, reset]);
    assert.deepEqual(resetRequests, ['wtai_save_settings', 'wtai_reset_settings']);
    assert.equal(current[0][1], 'immediate', 'Reset cannot be overwritten by an outstanding autosave.');
    current = [['settings[publish_schedule]', 'immediate'], ['settings[max_images_per_post]', '3']];
    subject.savedSettings = JSON.stringify(current);
    current[1][1] = '5';
    let partial;
    subject.request = async data => { partial = data; return { success: true, data: { settings: { publish_schedule: 'immediate', max_images_per_post: '5' } } }; };
    await subject.saveSettings();
    assert.equal(partial.get('settings[publish_schedule]'), undefined, 'Saving an image edit does not write unchanged publishing options.');
    assert.equal(partial.get('settings[max_images_per_post]'), '5');
    subject.settingsForms = () => [];
    subject.settingsFields = settingsFields;
    subject.applySettings = applySettings;
    subject.syncOptions = () => {};
    await subject.resetSettings();
    assert.equal(subject.savedSettings, '[]', 'Reset works on the separate tools tab without an import or image form.');
    const panels = ['generate', 'duplicates', 'images', 'settings'].map(name => ({ dataset: { wtaiPanel: name }, hidden: false }));
    const links = panels.map(panel => ({ href: 'https://example.test/admin.php?page=word-to-article-importer&tab=' + panel.dataset.wtaiPanel, classList: { toggle() {} }, setAttribute() {}, removeAttribute() {} }));
    context.URL = URL;
    context.document = { querySelectorAll: selector => selector === '[data-wtai-panel]' ? panels : links };
    const history = [];
    context.window = { location: { href: links[0].href }, history: { pushState: (...args) => history.push(args[2]) } };
    let mediaLoads = 0;
    subject.loadMediaImages = () => { mediaLoads++; };
    subject.showTab('images', links[2].href);
    assert.deepEqual(panels.filter(panel => !panel.hidden).map(panel => panel.dataset.wtaiPanel), ['images']);
    subject.showTab('settings', links[3].href);
    subject.showTab('images');
    assert.equal(mediaLoads, 1, 'Revisiting the image panel preserves its existing content instead of loading it again.');
    assert.equal(history.length, 2, 'Tab clicks update browser history without document navigation.');
    subject.showTab('unknown');
    assert.equal(panels[0].hidden, false, 'Unknown tabs safely select Generate Articles.');
    let posted;
    const completed = { status: 'completed', post_title: 'A & <Title>', view_url: 'https://example.test/?p=12&preview=true', edit_url: '/editor', can_trash: true, post_id: 12 };
    jquery.post = (url, data) => {
        posted = data;
        return { done(callback) { callback({ success: true, data: { history: [completed, { ...completed, status: 'prepared' }, { ...completed, view_url: '', post_status: 'trash' }] } }); return this; }, fail() { return this; } };
    };
    loadImportHistory.call(subject);
    assert.equal(subject.importHistory.length, 1, 'Copy includes only completed, available imports in the displayed response.');
    const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;');
    context.document = { createElement(tag) { return { children: [], textContent: '', appendChild(child) { this.children.push(child); }, get outerHTML() { return '<' + tag + (this.href ? ' href="' + escape(this.href) + '"' : '') + '>' + escape(this.textContent) + this.children.map(child => child.outerHTML).join('') + '</' + tag + '>'; } }; } };
    context.Blob = Blob;
    context.ClipboardItem = class { constructor(data) { this.data = data; } };
    let copied;
    context.navigator = { clipboard: { write: async items => { copied = items[0].data; } } };
    await subject.copyImports();
    assert.equal(await copied['text/html'].text(), '<ol><li><a href="https://example.test/?p=12&amp;preview=true">A &amp; &lt;Title></a></li></ol>', 'Rich clipboard data numbers linked titles and escapes markup.');
    assert.equal(await copied['text/plain'].text(), '1. A & <Title> \u2014 https://example.test/?p=12&preview=true', 'Plain text retains the title and usable URL.');
    let listener;
    let removed = false;
    const fallback = {};
    context.navigator.clipboard.write = async () => { throw new Error('Denied'); };
    context.document.addEventListener = (name, callback) => { listener = callback; };
    context.document.removeEventListener = () => { removed = true; };
    context.document.execCommand = () => { listener({ clipboardData: { setData: (type, value) => { fallback[type] = value; } }, preventDefault() {} }); return true; };
    await subject.copyImports();
    assert.equal(fallback['text/html'], await copied['text/html'].text(), 'Denied clipboard API access falls back to rich copying without a popup.');
    assert.equal(removed, true, 'The fallback always removes its temporary copy handler.');
    let inlineStatus = '';
    chain.text = function (value) { inlineStatus = value; return this; };
    context.document.execCommand = () => false;
    const alertCount = alerts.length;
    await subject.copyImports();
    assert.match(inlineStatus, /Copy failed/, 'Clipboard denial is reported inline without a popup.');
    assert.equal(alerts.length, alertCount);
    subject.importHistory = [completed, { ...completed, post_title: 'Second post', view_url: 'https://example.test/second/' }];
    context.navigator.clipboard.write = async items => { copied = items[0].data; };
    await subject.copyImports();
    assert.match(await copied['text/plain'].text(), /\n2\. Second post/, 'Copied numbering follows the displayed history order.');
    let reloads = 0;
    subject.loadImportHistory = () => { reloads++; };
    let trashRequest;
    subject.request = async data => { trashRequest = data; return { success: true, data: { message: 'Post moved to Trash.' } }; };
    await subject.trashImport({ currentTarget: { dataset: { postId: '12' } } });
    assert.equal(trashRequest.get('action'), 'wtai_trash_import');
    assert.equal(trashRequest.get('post_id'), '12');
    assert.equal(reloads, 1, 'A successful Trash action reloads the displayed history and copy list.');
    subject.request = async () => ({ success: false, data: { message: 'Permission denied.' } });
    await subject.trashImport({ currentTarget: { dataset: { postId: '12' } } });
    assert.equal(reloads, 1, 'A rejected Trash action does not pretend to remove the post.');
    subject.settingsForms = () => [{ elements: [{ name: 'settings[image_source]', type: 'select-one', value: 'both' }, { name: '', type: 'password', value: 'private-google-key' }] }];
    subject.settingsFields = settingsFields;
    assert.equal(JSON.stringify(subject.settingsFields()).includes('private-google-key'), false, 'Google credentials are excluded from normal settings snapshots.');
    const googleOrder = [];
    subject.saveSettings = async () => { googleOrder.push('save'); };
    subject.request = async data => { googleOrder.push(data.get('operation')); assert.equal(data.get('action'), 'wtai_google_photos'); return { success: true, data: { message: 'Google profile ready.', content: '<img alt="Google photo">' } }; };
    await subject.googlePhotos('check');
    assert.deepEqual(googleOrder, ['save', 'check'], 'Checking Google photos waits for the pasted profile and source settings to save.');
    subject.request = async data => { assert.equal(data.get('operation'), 'remove_key'); return { success: true, data: { message: 'Key removed.' } }; };
    await subject.googlePhotos('remove_key');
    assert.equal(googleOrder.length, 2, 'Removing private Google credentials does not submit the ordinary settings form.');
    assert.equal(subject.shiftedDate('2028-02-28', 1), '2028-02-29', 'Date shortcuts preserve leap days.');
    assert.equal(subject.shiftedDate('2027-12-31', 1), '2028-01-01', 'Tomorrow crosses the year boundary.');
    assert.equal(subject.shiftedDate('2027-12-31', 0, 1).slice(0, 7), '2028-01', 'Next month moves to the next year without overflowing the last day.');
    assert.equal(subject.shiftedDate('2027-01-31', 6), '2027-02-06', 'A seven-day inclusive range adds six calendar days.');
    assert.equal(subject.shiftedDate('2028-02-01', 29), '2028-03-01', 'Thirty-day range handles leap-year February.');
    assert.equal(subject.readableDate(''), '', 'Empty date controls have no misleading formatted label.');
    assert.match(subject.readableDate('2028-02-29'), /2028/);
    const controls = {
        'wtai-schedule-month': { value: '2028-02' },
        'wtai-month-number': { value: '' },
        'wtai-month-year': { value: '', validity: { valid: true }, setCustomValidity() {} },
    };
    context.document.getElementById = id => controls[id];
    context.document.querySelector = () => ({ dataset: { today: '2026-10-22' } });
    subject.syncMonthControls(true);
    assert.equal(controls['wtai-month-number'].value, '02');
    assert.equal(controls['wtai-month-year'].value, '2028', 'Saved ISO months populate the separate year and named-month controls.');
    controls['wtai-month-number'].value = '11'; controls['wtai-month-year'].value = '2029';
    subject.updateMonthValue();
    assert.equal(controls['wtai-schedule-month'].value, '2029-11', 'Named month/year choices retain the existing canonical server format.');
    controls['wtai-month-year'].validity.valid = false;
    subject.updateMonthValue();
    assert.equal(controls['wtai-schedule-month'].value, '', 'Invalid years never submit the previous hidden month value.');
    subject.syncMonthControls(true);
    assert.equal(controls['wtai-month-number'].value, '');
    assert.equal(controls['wtai-month-year'].value, '2026', 'Reset clears the month and uses the WordPress current year.');
    let finishInitialDriveStatus;
    subject.request = () => new Promise(resolve => { finishInitialDriveStatus = resolve; });
    const initialDriveStatus = subject.driveCredentials('status');
    subject.showDriveStatus({ source: 'default', connection: 'connected', connection_label: 'Connected' });
    finishInitialDriveStatus({ success: true, data: { source: 'default', connection: 'unchecked', connection_label: 'Configured (not checked)' } });
    await initialDriveStatus;
    assert.equal(subject.driveStatus.connection, 'connected', 'A slow initial status response cannot overwrite a newer verified Drive connection.');

    let previewStart = '2098-01-01';
    const previewCallbacks = [];
    const originalSetTimeout = context.setTimeout;
    const originalClearTimeout = context.clearTimeout;
    context.setTimeout = callback => { previewCallbacks.push(callback); return previewCallbacks.length; };
    context.clearTimeout = () => {};
    context.document.getElementById = id => id === 'wtai-schedule-preview' ? {} : (id === 'wtai-documents' ? { files } : null);
    subject.settingsFields = () => [['settings[publish_schedule]', 'specific_date'], ['settings[schedule_start_date]', previewStart]];
    subject.importing = false;
    const previewResponse = label => ({ success: true, data: { preview: { timezone: 'America/Tijuana', total: 3, dates: [{ article: 1, label }], repeated: 0, publish_now: 0 } } });
    let finishOldPreview;
    subject.request = async data => data.get('settings[schedule_start_date]') === '2098-01-01' ? new Promise(resolve => { finishOldPreview = resolve; }) : previewResponse('New 2099 preview');
    createdElements.length = 0;
    subject.queueSchedulePreview();
    const oldPreview = previewCallbacks[0]();
    previewStart = '2099-01-01';
    subject.queueSchedulePreview();
    await previewCallbacks[1]();
    finishOldPreview(previewResponse('Stale 2098 preview'));
    await oldPreview;
    assert(createdElements.some(element => element.text?.includes('New 2099 preview')), 'The latest schedule preview is displayed.');
    assert(!createdElements.some(element => element.text?.includes('Stale 2098 preview')), 'A slow preview response cannot overwrite a newer date choice.');
    context.setTimeout = originalSetTimeout;
    context.clearTimeout = originalClearTimeout;
    const resultCases = [
        { images: { inserted: 1, requested: 3 }, links: { inserted: 5, requested: 5 } },
        { images: { inserted: 3, requested: 3 }, links: { inserted: 3, requested: 5 } },
        { images: { inserted: 0, requested: 3 }, links: { inserted: 4, requested: 5 } },
        { images: { inserted: 3, requested: 3 }, links: { inserted: 5, requested: 5 } },
        { images: { inserted: 0, requested: 0 }, links: { inserted: 0, requested: 0 } },
        {},
    ];
    context.wtai_ajax.edit_post_url = '/post.php?post=%d&action=edit';
    chain.show = function () { return this; };
    createdElements.length = 0;
    displayResults.call(subject, { total: 7, successful: 6, failed: 1, processed: 7, results: resultCases.map((item, index) => ({ ...item, file: 'result-' + index, post_id: index + 1 })), errors: [{ file: 'failed.docx', error: 'Invalid document' }] });
    assert.deepEqual(createdElements.filter(item => item.class?.startsWith('wtai-result-item ')).map(item => item.class), [
        'wtai-result-item warning', 'wtai-result-item warning', 'wtai-result-item warning',
        'wtai-result-item success', 'wtai-result-item success', 'wtai-result-item success', 'wtai-result-item error',
    ], 'Short image/link counts are orange warnings; complete, disabled, and legacy reports remain green, and failures remain red.');
    console.log('PASS: admin queue, persistence, history, Google controls, calendar helpers, and preview races.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
