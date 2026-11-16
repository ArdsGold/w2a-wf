"""Real local DOCX browser import. Creates isolated fixtures and restores site options."""
import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[4]
PHP = ['php', '-n', '-d', 'extension_dir=C:/php/ext', '-d', 'extension=mysqli', '-d', 'extension=gd', '-d', 'extension=mbstring', '-d', 'extension=zip', '-d', 'extension=curl', '-d', 'extension=openssl', '-d', 'extension=fileinfo', '-d', 'mysqli.default_port=10018', '-r']

def php(code, data=None):
    return subprocess.run(PHP + [code], input=data, text=True, capture_output=True, check=True, cwd=ROOT).stdout

state = json.loads(php(r"""
require 'wp-load.php';
require_once 'wp-content/plugins/w2a-wf/word-to-article-importer.php';
require 'wp-content/plugins/w2a-wf/tests/fixtures.php';
$admin=get_users(array('role'=>'administrator','number'=>1))[0]->ID;
wp_set_current_user($admin);
$token='wfaui'.bin2hex(random_bytes(6));
$options=array();
foreach(WTAI_Settings::defaults() as $name=>$unused) $options[$name]=get_option('wtai_'.$name,null);
$menu_locations=get_theme_mod('nav_menu_locations',null);
$menu=wp_create_nav_menu($token.' Navigation');
$menu_root=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>'Our Services','menu-item-url'=>'#','menu-item-type'=>'custom','menu-item-status'=>'publish'));
$menu_parent=$menu_root;
set_theme_mod('nav_menu_locations',array('wtai_test'=>$menu));
$targets=array(); $images=array(); $xml='';
foreach(array('forest','ocean','mountain') as $position=>$word) {
    $targets[]=wp_insert_post(array('post_type'=>$position<2?'page':'post','post_status'=>'publish','post_title'=>$token.' '.$word.' photography','post_parent'=>0));
    if($position<2) $menu_parent=wp_update_nav_menu_item($menu,0,array('menu-item-title'=>$word.' photography','menu-item-type'=>'post_type','menu-item-object'=>'page','menu-item-object-id'=>end($targets),'menu-item-parent-id'=>$menu_parent,'menu-item-status'=>'publish'));
    $pixels=imagecreatetruecolor(1200,800);
    imagefill($pixels,0,0,imagecolorallocate($pixels,40+$position*50,100,160-$position*40));
    ob_start(); imagejpeg($pixels); $bytes=ob_get_clean();
    $upload=wp_upload_bits($token.'-'.$word.'.jpg',null,$bytes);
    if($upload['error']) throw new RuntimeException('Temporary image upload failed.');
    $id=wp_insert_attachment(array('post_title'=>$token.' '.$word.' photography','post_mime_type'=>'image/jpeg','post_status'=>'inherit','post_excerpt'=>'TEST CAPTION MUST STAY IN MEDIA','post_content'=>'TEST DESCRIPTION MUST STAY IN MEDIA'),$upload['file']);
    update_post_meta($id,'_wp_attachment_image_alt',$token.' '.$word.' photography');
    wp_update_attachment_metadata($id,array('width'=>1200,'height'=>800,'file'=>_wp_relative_upload_path($upload['file']),'sizes'=>array()));
    $images[]=$id;
    $xml.='<w:p><w:r><w:t>'.str_repeat($token.' '.$word.' photography ',40).'</w:t></w:r></w:p>';
}
$xml.='<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Conclusion</w:t></w:r></w:p><w:p><w:r><w:t>Call (216) 399-7569 for details.</w:t></w:r></w:p>';
$document=wfag_test_archive(wfag_test_document($xml));
WTAI_Settings::save(array('publish_schedule'=>'immediate','auto_insert_images'=>true,'image_source'=>'media_library','pixabay_fallback'=>false,'max_images_per_post'=>3,'max_internal_links_per_post'=>3,'auto_link_phone_numbers'=>true));
$expires=time()+1800; $session=WP_Session_Tokens::get_instance($admin)->create($expires);
echo wp_json_encode(array('admin'=>$admin,'token'=>$token,'session'=>$session,'options'=>$options,'document'=>$document,'targets'=>$targets,'images'=>$images,'menu'=>$menu,'menu_locations'=>$menu_locations,'cookies'=>array(array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie($admin,$expires,'auth',$session),'url'=>home_url('/')),array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($admin,$expires,'logged_in',$session),'url'=>home_url('/')))));
"""))

try:
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        context = browser.new_context(viewport={'width':1280, 'height':1000})
        context.add_cookies(state['cookies'])
        page = context.new_page()
        errors = []
        ajax_responses = []
        page.on('response', lambda response: ajax_responses.append(response) if 'admin-ajax.php' in response.url else None)
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto('http://krakentesting.local/wp-admin/admin.php?page=word-to-article-importer')
        page.locator('#wtai-document-source').select_option('local')
        assert page.locator('#wtai-service-parent-title').count()==0
        assert 'Services detected' in page.locator('#wtai-services-detection-status').inner_text()
        assert 'Our Services' in page.locator('#wtai-services-status').inner_text()
        listed_targets = page.locator('#wtai-service-descendants option[value]:not([value=""])').evaluate_all('(options) => options.map(option => Number(option.value))')
        assert set(state['targets'][:2]).issubset(listed_targets)
        assert state['targets'][2] not in listed_targets
        assert page.locator('#wtai-service-descendants').get_attribute('name') is None
        page.locator('#wtai-service-descendants').select_option(str(state['targets'][0]))
        page.locator('#wtai-documents').set_input_files({'name':'1. '+state['token']+' Landscape Photography.docx', 'mimeType':'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'buffer':Path(state['document']).read_bytes()})
        page.locator('#wtai-import-button').click()
        page.wait_for_function("!document.querySelector('#wtai-import-button').disabled && document.querySelector('#wtai-progress-text').textContent.includes('Import complete.')", timeout=60000)
        responses = [response.json() for response in ajax_responses]
        response = next(item for item in responses if isinstance(item.get('data'), dict) and item['data'].get('post_id') and item['data'].get('file'))
        print('PASS: live browser Submit created the article.', flush=True)
        assert response['success'], response
        result = response['data']
        assert result['images']['inserted'] == 3, result['images']
        assert result['links']['inserted'] == 3 and result['links']['service_inserted'] == 2, result['links']
        page.wait_for_function("document.querySelector('#wtai-progress-text').textContent.includes('Import complete.')")
        assert page.locator('.wtai-result-item.success').count() == 1
        assert page.locator('.wtai-result-images .wtai-result-thumbnail').count() == 3
        assert 'service links: 2' in page.locator('#wtai-results-content').inner_text()
        ajax_responses.clear()
        page.locator('#wtai-import-button').click()
        page.wait_for_function("!document.querySelector('#wtai-import-button').disabled && document.querySelector('#wtai-progress-text').textContent.includes('Import complete.')", timeout=60000)
        responses = [response.json() for response in ajax_responses]
        retry = next(item['data'] for item in responses if isinstance(item.get('data'), dict) and item['data'].get('post_id') and item['data'].get('file'))
        assert retry['existing'] and retry['post_id'] == result['post_id'], retry
        details = json.loads(php(r"""
require 'wp-load.php'; $input=json_decode(stream_get_contents(STDIN),true); $id=$input['post_id'];
$post=get_post($id); $meta=array();
foreach($input['images'] as $image) $meta[]=array('caption'=>get_post_field('post_excerpt',$image),'description'=>get_post_field('post_content',$image),'crop'=>wp_get_attachment_metadata($image)['sizes']['wtai-article-600x400']);
echo wp_json_encode(array('body'=>$post->post_content,'title'=>$post->post_title,'status'=>$post->post_status,'urls'=>array_map('get_permalink',$input['targets']),'meta'=>$meta,'preview'=>get_preview_post_link($id)));
""", json.dumps({**state, 'post_id':result['post_id']})))
        assert details['status'] == 'publish' and not details['title'].startswith('1.')
        assert '<h1' not in details['body']
        assert details['body'].count('width="600" height="400"') == 3
        assert details['body'].rfind('<figure') < details['body'].find('<h2>Conclusion</h2>')
        assert 'TEST CAPTION' not in details['body'] and 'TEST DESCRIPTION' not in details['body']
        assert 'tel:2163997569' in details['body']
        assert all(url in details['body'] for url in details['urls'])
        assert all(item['caption']=='TEST CAPTION MUST STAY IN MEDIA' and item['description']=='TEST DESCRIPTION MUST STAY IN MEDIA' for item in details['meta'])
        preview = context.new_page()
        preview.goto(details['preview'])
        rendered = preview.locator('figure.wtai-inserted-image img')
        assert rendered.count() == 3
        for image in rendered.all():
            image.scroll_into_view_if_needed()
            image.evaluate('(el) => el.loading = "eager"')
            image.wait_for(state='visible')
        preview.wait_for_function("[...document.querySelectorAll('figure.wtai-inserted-image img')].every(img => img.complete && img.naturalWidth === 600 && img.naturalHeight === 400)")
        assert not errors, errors
        browser.close()
    print('PASS: real browser DOCX import, 3 rendered 600x400 crops, preserved media metadata, 2 service links plus 1 blog link, phone link, Conclusion boundary, thumbnails, and duplicate-safe retry.')
finally:
    php(r"""
require 'wp-load.php'; $state=json_decode(stream_get_contents(STDIN),true); global $wpdb;
wp_delete_nav_menu($state['menu']);
if($state['menu_locations']===null) remove_theme_mod('nav_menu_locations'); else set_theme_mod('nav_menu_locations',$state['menu_locations']);
$ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s",$wpdb->esc_like($state['token']).'%'));
foreach($ids as $id) { $wpdb->delete($wpdb->prefix.'wtai_import_history',array('post_id'=>$id)); if(get_post_type($id)==='attachment') wp_delete_attachment($id,true); else wp_delete_post($id,true); }
foreach($state['options'] as $name=>$value) { if($value===null) delete_option('wtai_'.$name); else update_option('wtai_'.$name,$value); }
if(is_file($state['document'])) unlink($state['document']);
WP_Session_Tokens::get_instance($state['admin'])->destroy($state['session']);
""", json.dumps(state))
