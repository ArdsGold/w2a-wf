"""Run from the WordPress root. Requires Playwright; Google listing/imports are browser-mocked."""
import json, subprocess
from time import monotonic
from urllib.parse import parse_qs
from email.parser import BytesParser
from email import policy
from playwright.sync_api import sync_playwright
PHP=['php','-n','-d','extension_dir=C:/php/ext','-d','extension=mysqli','-d','extension=mbstring','-d','extension=zip','-d','extension=curl','-d','extension=openssl','-d','extension=fileinfo','-d','mysqli.default_port=10018','-r']
def php(code, data=None):
    result=subprocess.run(PHP+[code],input=data,text=True,capture_output=True,check=True)
    return result.stdout
state=json.loads(php(r"""require 'wp-load.php'; require_once 'wp-content/plugins/w2a-wf/word-to-article-importer.php'; $users=get_users(array('role'=>'administrator','number'=>1)); $id=$users[0]->ID; wp_set_current_user($id); $expires=time()+1800; $token=WP_Session_Tokens::get_instance($id)->create($expires); $options=array(); foreach(array_merge(array_keys(WTAI_Settings::defaults()),array('google_drive_api_key','drive_folder_url')) as $name) $options[$name]=get_option('wtai_'.$name,null); echo wp_json_encode(array('id'=>$id,'token'=>$token,'options'=>$options,'source'=>WTAI_Google_Drive::status()['source'],'cookies'=>array(array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie($id,$expires,'auth',$token),'url'=>home_url('/')),array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($id,$expires,'logged_in',$token),'url'=>home_url('/')))));"""))
try:
    with sync_playwright() as pw:
        browser=pw.chromium.launch()
        context=browser.new_context(viewport={'width':1280,'height':1000})
        context.add_cookies(state['cookies'])
        context.add_init_script("""window.ClipboardItem = class { constructor(data) { this.data = data; } };
Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { write: async items => {
window.testCopyHTML = await items[0].data['text/html'].text();
window.testCopyText = await items[0].data['text/plain'].text();
} } });""")
        page=context.new_page()
        errors=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        quota=[True]
        imports=[]
        cancel_mode=[None]
        held=[]
        def route_ajax(route):
            content_type=route.request.headers.get('content-type','')
            if 'multipart/form-data' in content_type:
                message=BytesParser(policy=policy.default).parsebytes(('Content-Type: '+content_type+'\r\nMIME-Version: 1.0\r\n\r\n').encode()+(route.request.post_data_buffer or b''))
                body={part.get_param('name',header='content-disposition'):[part.get_payload(decode=True).decode()] for part in message.iter_parts()}
            else:
                body=parse_qs(route.request.post_data or '')
            def value(name): return body.get(name,[''])[0]
            if value('action')=='wtai_get_import_history':
                return route.fulfill(json={'success':True,'data':{'history':[{'status':'completed','post_id':321,'post_title':'Clipboard test article','original_filename':'clipboard.docx','view_url':'https://example.test/clipboard/','edit_url':'','can_trash':False}]}})
            if (cancel_mode[0]=='local' and value('action')=='wtai_upload_documents') or (cancel_mode[0]=='preflight' and value('action')=='wtai_validate_import' and not value('preview')):
                held.append(route)
                return
            if value('action')!='wtai_google_drive': return route.continue_()
            operation=value('operation')
            if operation=='list':
                files=[{'id':'BrowserPublicDoc'+str(i),'name':str(i+1)+'. Article '+str(i+1)+'.docx','size':100,'resource_key':'','kind':'google_doc' if i==1 else 'docx'} for i in range(3)]
                return route.fulfill(json={'success':True,'data':{'files':files,'next_page':'','status':{'source':'override','connection':'connected','connection_label':'Connected','notice':''}}})
            if operation=='import':
                if cancel_mode[0]=='drive':
                    held.append(route)
                    return
                imports.append(value('file_id'))
                if quota[0] and len(imports)==2:
                    return route.fulfill(status=429,json={'success':False,'data':{'code':'wtai_drive_quota','message':'Google Drive quota reached. Use Settings & Logs or retry later.','drive_status':{'source':'override','connection':'quota','connection_label':'Rate or download limit reached','notice':'Quota reached.'}}})
                return route.fulfill(json={'success':True,'data':{'file':'Article.docx','post_id':321,'status':'publish','publish_date':'Immediate','existing':not quota[0],'images':{'inserted':1 if value('document_index')=='0' else 3,'requested':3},'links':{'inserted':3 if value('document_index')=='1' else 5,'requested':5}}})
            route.continue_()
        page.route('**/admin-ajax.php',route_ajax)
        page.goto('http://krakentesting.local/wp-admin/admin.php?page=word-to-article-importer')
        page.wait_for_function("document.querySelector('#wtai-drive-key-status').textContent.length > 0")
        page.wait_for_function("!document.querySelector('#wtai-generate-copy-imports').disabled")
        page.locator('#wtai-generate-copy-imports').click()
        page.wait_for_function("document.querySelector('#wtai-generate-copy-status').textContent.includes('1 imports copied')")
        generated_copy=page.evaluate('window.testCopyHTML')
        assert '<ol>' in generated_copy and 'https://example.test/clipboard/' in generated_copy
        assert 'Clipboard test article' in generated_copy
        assert 'https://example.test/clipboard/' in page.evaluate('window.testCopyText')
        page.evaluate('window.driveBrowserMarker=42')
        page.locator('.wtai-tabs a[href*="tab=settings"]').click()
        page.locator('#wtai-copy-imports').click()
        assert page.evaluate('window.testCopyHTML')==generated_copy,'Both buttons copy the same completed history entries'
        assert '1 imports copied' in page.locator('#wtai-history-status').inner_text()
        page.locator('#wtai-drive-api-key').fill('browser-test-drive-override-a-12345')
        page.locator('#wtai-drive-save-key').click()
        page.wait_for_function("document.querySelector('#wtai-drive-key-status').textContent.includes('override')")
        page.on('dialog',lambda dialog:dialog.accept())
        page.locator('#wtai-reset-settings').click()
        page.wait_for_function("document.querySelector('#wtai-max-internal-links').value === '5'")
        for selector in ['#wtai-auto-phone-links','#wtai-convert-lists','#wtai-unbold-text','#wtai-generate-auto-images','#wtai-auto-images']:
            assert page.locator(selector).is_checked(),selector
        assert page.locator('#wtai-generate-max-images').input_value()=='3'
        assert page.locator('#wtai-cancel-import').is_disabled(),'Reset leaves Cancel disabled when idle'
        assert 'override' in page.locator('#wtai-drive-key-status').inner_text()
        page.locator('.wtai-tabs a').first.click()
        page.locator('#wtai-document-source').select_option('drive')
        assert not page.locator('#wtai-local-upload').is_visible()
        page.locator('#wtai-drive-folder').fill('https://drive.google.com/drive/folders/BrowserPublicFolder123')
        page.locator('#wtai-drive-load').click()
        page.wait_for_function("document.querySelectorAll('#wtai-drive-files input').length === 3")
        assert page.locator('#wtai-drive-files input:checked').count()==3
        assert page.locator('#wtai-drive-api-status').inner_text()=='Connected'
        assert 'wtai-status-good' in page.locator('#wtai-drive-api-status').get_attribute('class')
        assert 'Google Doc; exports to Word' in page.locator('#wtai-drive-files').inner_text()
        page.locator('#wtai-publish-schedule').select_option('immediate')
        page.wait_for_function("Array.from(document.querySelectorAll('[data-wtai-options-status]')).some(x=>x.textContent === 'Options saved.')")
        page.locator('#wtai-import-form button[type=submit]').click()
        page.wait_for_function("document.querySelector('#wtai-drive-status').textContent.includes('quota reached')")
        page.wait_for_function("!document.querySelector('#wtai-import-form button[type=submit]').disabled")
        assert len(imports)==2,'Quota must stop the third request'
        assert page.locator('#wtai-drive-api-status').inner_text()=='Rate or download limit reached'
        assert page.locator('#wtai-drive-files input:checked').count()==3
        page.locator('#wtai-drive-options a[href*="tab=settings"]').click()
        assert page.evaluate('window.driveBrowserMarker')==42,'Settings navigation must preserve the page'
        page.locator('#wtai-drive-api-key').fill('browser-test-drive-override-b-12345')
        page.locator('#wtai-drive-save-key').click()
        page.wait_for_function("document.querySelector('#wtai-drive-api-key').value === ''")
        page.locator('.wtai-tabs a').first.click()
        assert page.locator('#wtai-drive-files input:checked').count()==3
        quota[0]=False
        page.locator('#wtai-import-form button[type=submit]').click()
        page.wait_for_function("document.querySelector('#wtai-progress-text').textContent.includes('Import complete.')")
        assert len(imports)==5,'Retry should retain and process the same three documents'
        assert page.locator('.wtai-result-item.warning').count()==2,'Image and link shortfalls each flag their article'
        assert page.locator('.wtai-result-item.success').count()==1,'Complete articles stay green'
        edge=page.locator('.wtai-result-item.warning').first.evaluate('(element) => ({color: getComputedStyle(element).borderLeftColor, width: getComputedStyle(element).borderLeftWidth})')
        assert edge=={'color':'rgb(217, 119, 6)','width':'4px'},edge
        for mode in ['drive','local','preflight']:
            cancel_mode[0]=mode
            held.clear()
            page.locator('#wtai-document-source').select_option('local' if mode=='local' else 'drive')
            if mode=='local':
                page.locator('#wtai-documents').set_input_files([{'name':name,'mimeType':'application/vnd.openxmlformats-officedocument.wordprocessingml.document','buffer':b'client-cancel-test'} for name in ['cancel-first.docx','cancel-second.docx']])
            assert page.locator('#wtai-cancel-import').is_disabled()
            page.locator('#wtai-import-button').click()
            page.wait_for_timeout(200)
            deadline=monotonic()+10
            while not held and monotonic()<deadline:
                page.wait_for_timeout(100)
            assert held,'The test request must reach the interception before cancellation'
            assert page.locator('#wtai-cancel-import').is_enabled()
            page.locator('#wtai-cancel-import').click()
            page.wait_for_function("document.querySelector('#wtai-progress-text').textContent.includes('Import cancelled.')")
            assert len(held)==1,'Cancel prevents further requests'
            assert page.locator('#wtai-cancel-import').is_disabled()
            assert page.locator('#wtai-import-button').is_enabled()
            if mode!='preflight':
                assert 'may still finish on the server' in page.locator('#wtai-results-content').inner_text()
            assert page.locator('#wtai-drive-files input:checked').count()==3
            try:
                held[0].abort()
            except Exception:
                pass
        cancel_mode[0]=None
        page.locator('#wtai-document-source').select_option('drive')
        page.set_viewport_size({'width':390,'height':844})
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),'No mobile horizontal overflow'
        page.locator('#wtai-drive-folder').fill('not a url')
        page.locator('#wtai-document-source').select_option('local')
        assert page.locator('#wtai-drive-folder').is_disabled(),'Hidden invalid URL must not block local submissions'
        assert not errors,errors
        browser.close()
    print('PASS: browser Drive selection, shared Copy All Imports buttons, quota pause, key override, retained tabs/retry, upload/Drive/preflight cancellation, first-launch reset, and mobile checks. Initial server key source: '+state['source'])
finally:
    php(r"""require 'wp-load.php'; $state=json_decode(stream_get_contents(STDIN),true); foreach($state['options'] as $name=>$value) { if($value===null) delete_option('wtai_'.$name); else update_option('wtai_'.$name,$value); } WP_Session_Tokens::get_instance($state['id'])->destroy($state['token']); foreach(array('browser-test-drive-override-a-12345','browser-test-drive-override-b-12345') as $key) delete_transient('wtai_drive_'.substr(hash('sha256',$key),0,24));""",json.dumps(state))
