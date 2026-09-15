<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
$checks = array();
function check_vcac($condition, $message) { global $checks; if (!$condition) { throw new Exception($message); } $checks[] = $message; }
function make_vcac_post($site, $title, $listing='update', $extra=array(), $fields=array()) {
    switch_to_blog($site);
    try {
        $id=wp_insert_post(array_merge(array('post_title'=>$title,'post_content'=>'Useful source content without a second entry.','post_excerpt'=>'Original ministry summary.','post_type'=>'post','post_status'=>'publish'),$fields),true);
        if(is_wp_error($id))throw new Exception($id->get_error_message());
        update_post_meta($id,'_vcac_listing',$listing);
        foreach($extra as $key=>$value)update_post_meta($id,$key,$value);
        return $id;
    }finally{restore_current_blog();}
}
function ids_vcac($rows){return array_column($rows,'id');}
check_vcac(is_multisite(),'Local WordPress is a Multisite network');
$before=array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'),wp_count_posts('page'),get_option('vcac_feed_source_sites'));
$activated=activate_plugin('vcac-landing-page/vcac-landing-page.php','',true);
check_vcac(!is_wp_error($activated),'Plugin activates network-wide');
$after=array(get_option('stylesheet'),get_option('template'),get_option('page_on_front'),wp_count_posts('page'),get_option('vcac_feed_source_sites'));
check_vcac($before==$after,'Activation leaves existing theme, homepage, pages and feed configuration unchanged');
$network=get_network();
$sites=array();
foreach(array('en'=>'english','zh-Hant'=>'cantonese','zh-Hans'=>'mandarin') as $lang=>$path){
    $site=wpmu_create_blog($network->domain,'/'.$path.'/',ucfirst($path).' ministry',1,array('public'=>1),$network->id);
    check_vcac(!is_wp_error($site),'Create test ministry '.$lang);
    $sites[$lang]=(int)$site;
    switch_to_blog($site);update_option('timezone_string','America/Vancouver');restore_current_blog();
}
update_option('timezone_string','America/Vancouver');
check_vcac(VCAC\LandingPage\feed_source_sites()===$sites,'Source sites resolve from ministry paths');
$future=wp_date('Y-m-d',time()+365*DAY_IN_SECONDS,wp_timezone());
$past=wp_date('Y-m-d',time()-2*DAY_IN_SECONDS,wp_timezone());
$updates=array();
foreach($sites as $lang=>$site)$updates[$lang]=make_vcac_post($site,'Update '.$lang);
$shared=make_vcac_post($sites['en'],'Shared English programme','community',array('_vcac_audience'=>'all','_vcac_programme_id'=>'shared-circle','_vcac_schedule'=>'Every Wednesday, 1–3 p.m.','_vcac_review_until'=>$future));
$translated=make_vcac_post($sites['zh-Hant'],'繁體社區活動','community',array('_vcac_programme_id'=>'shared-circle','_vcac_schedule'=>'每週三下午','_vcac_review_until'=>$future));
$private=make_vcac_post($sites['en'],'Private hidden','update',array(),array('post_status'=>'private'));
$draft=make_vcac_post($sites['en'],'Draft hidden','update',array(),array('post_status'=>'draft'));
$password=make_vcac_post($sites['en'],'Protected hidden','update',array(),array('post_password'=>'test-only'));
$unlisted=make_vcac_post($sites['en'],'Not opted in','off');
$expired=make_vcac_post($sites['en'],'Expired programme','community',array('_vcac_schedule'=>'Fridays','_vcac_review_until'=>$past));
$incomplete=make_vcac_post($sites['en'],'Incomplete programme','community',array('_vcac_review_until'=>$future));
$expiryUpdate=make_vcac_post($sites['en'],'Expired notice','update',array('_vcac_review_until'=>$past));
$malicious=make_vcac_post($sites['zh-Hans'],'Safe markup test','update',array('_vcac_summary'=>'<script>alert(1)</script>Readable','_vcac_action_url'=>'javascript:alert(1)'));
$originalBlog=get_current_blog_id();
$feed=VCAC\LandingPage\feed_payload();
check_vcac(get_current_blog_id()===$originalBlog&&!ms_is_switched(),'Feed always restores source-site context');
foreach($sites as $lang=>$site){
    $ids=ids_vcac($feed['locales'][$lang]['updates']);
    check_vcac(in_array($site.':'.$updates[$lang],$ids,true),'Correct updates for '.$lang);
    foreach($updates as $other=>$id)if($other!==$lang)check_vcac(!in_array($sites[$other].':'.$id,$ids,true),'No cross-ministry update leak into '.$lang);
    check_vcac($feed['locales'][$lang]['status']==='ok','Feed status healthy for '.$lang);
    check_vcac(strpos($feed['sources'][$lang]['updates'],'/about/announcements/')!==false,'Correct announcements destination for '.$lang);
}
check_vcac(count($feed['locales']['en']['community'])===1,'Expired and incomplete programmes are omitted');
check_vcac(count($feed['locales']['zh-Hant']['community'])===1 && $feed['locales']['zh-Hant']['community'][0]['id']===$sites['zh-Hant'].':'.$translated,'Matching Traditional translation wins without duplicate');
check_vcac(count($feed['locales']['zh-Hans']['community'])===1 && $feed['locales']['zh-Hans']['community'][0]['language']==='en','Shared programme remains discoverable with honest source language');
check_vcac(count($feed['locales']['en']['updates'])===1,'Draft, private, password, unlisted and expired updates excluded');
check_vcac($feed['locales']['en']['updates'][0]['summary']==='Original ministry summary.','Existing post excerpt reused without duplicate summary');
$safe=array_values(array_filter($feed['locales']['zh-Hans']['updates'],function($item)use($sites,$malicious){return $item['id']===$sites['zh-Hans'].':'.$malicious;}))[0];
check_vcac(strpos($safe['summary'],'<')===false&&strpos($safe['url'],'javascript:')!==0,'Feed text and action URLs are sanitized');
switch_to_blog($sites['en']);wp_update_post(array('ID'=>$updates['en'],'post_title'=>'Edited once'));restore_current_blog();
$updated=VCAC\LandingPage\feed_payload();check_vcac($updated['locales']['en']['updates'][0]['title']==='Edited once','Editing source updates feed immediately on next request');
switch_to_blog($sites['en']);wp_update_post(array('ID'=>$updates['en'],'post_status'=>'draft'));restore_current_blog();
check_vcac(count(VCAC\LandingPage\feed_payload()['locales']['en']['updates'])===0,'Unpublishing removes update without stale cache');
wp_update_site($sites['zh-Hans'],array('archived'=>1));
$archived=VCAC\LandingPage\feed_payload();check_vcac($archived['locales']['zh-Hans']['status']==='unavailable'&&count($archived['locales']['zh-Hans']['updates'])===0,'Archived ministry never contributes records');
check_vcac($archived['locales']['en']['status']==='partial','Missing source is distinguished from an empty feed');
wp_update_site($sites['zh-Hans'],array('archived'=>0));
wp_set_current_user(1);
switch_to_blog($sites['en']);
$_POST=array('vcac_content_listing_nonce'=>wp_create_nonce('vcac_save_content_listing'),'vcac_listing'=>'community','vcac_audience'=>'all','vcac_schedule'=>'Every Saturday','vcac_review_until'=>$future,'vcac_summary'=>'A single publishing form');
wp_update_post(array('ID'=>$updates['en'],'post_status'=>'publish'));
check_vcac(get_post_meta($updates['en'],'_vcac_listing',true)==='community','Staff form saves visibility through real WordPress save hooks');
$_POST=array('vcac_content_listing_nonce'=>'invalid','vcac_listing'=>'off');wp_update_post(array('ID'=>$updates['en'],'post_title'=>'Nonce test'));
check_vcac(get_post_meta($updates['en'],'_vcac_listing',true)==='community','Invalid nonce cannot change listing visibility');
wp_set_current_user(0);$_POST=array('vcac_content_listing_nonce'=>wp_create_nonce('vcac_save_content_listing'),'vcac_listing'=>'off');wp_update_post(array('ID'=>$updates['en'],'post_title'=>'Permission test'));
check_vcac(get_post_meta($updates['en'],'_vcac_listing',true)==='community','Unauthorised user cannot change listing metadata');
$_POST=array();restore_current_blog();wp_set_current_user(1);
require __DIR__ . '/image-crop.php';
$plain=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Existing page','post_content'=>'Original content'));
$landing=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Local VCAC landing test'));
update_post_meta($landing,'_wp_page_template','vcac-landing-page.php');
function query_vcac($id){global $wp_query,$wp_the_query,$post;$wp_query=new WP_Query(array('page_id'=>$id));$wp_the_query=$wp_query;$post=get_post($id);}
query_vcac($plain);check_vcac(apply_filters('template_include','original.php')==='original.php','Unrelated page template is preserved');
wp_enqueue_style('theme-test','http://example.test/theme.css');VCAC\LandingPage\isolate_assets();check_vcac(wp_style_is('theme-test','enqueued'),'Unrelated page styles are preserved');
query_vcac($landing);ob_start();include apply_filters('template_include','original.php');$html=ob_get_clean();
check_vcac(strpos($html,'id="vcac-feed-data"')!==false&&strpos($html,'<!--VCAC_FEED_DATA-->')===false,'Template embeds the real feed payload');
check_vcac(strpos($html,'site/community.js?ver=')!==false&&strpos($html,'site/community.css?ver=')!==false,'New assets resolve to packaged plugin paths');
check_vcac(strpos($html,'href="https://www.vcac.ca/')===false,'No hard-coded live ministry links leak into local WordPress');
check_vcac(strpos($html,'assets/VCAC-cover-V5.mp4')===false,'Plugin does not reference a bundled video');
check_vcac(strpos($html,'class="visit-link"')===false&&strpos($html,'class="ministry-shortcuts"')===false,'Redundant header and congregation navigation are omitted');
check_vcac(strpos($html,'class="button primary" href="#community"')!==false,'Primary hero action opens Community Services and Events');
check_vcac(strpos($html,'class="hero-community" href="#visit"')!==false,'Secondary hero action opens the first-Sunday section');
check_vcac(strpos($html,'class="back-to-top" href="#top"')!==false&&strpos($html,'data-i18n="backTop"')!==false,'Accessible back-to-top control is included');
$public_query_vars=apply_filters('query_vars',array());
check_vcac(!in_array('vcac_lang',$public_query_vars,true)&&!in_array('vcac_view',$public_query_vars,true),'Landing display state is not registered as a WordPress query variable');
$language_js=file_get_contents(WP_PLUGIN_DIR.'/vcac-landing-page/site/languages.js');
$community_js=file_get_contents(WP_PLUGIN_DIR.'/vcac-landing-page/site/community.js');
check_vcac(strpos($language_js,"searchParams.set('vcac_lang'")===false&&strpos($language_js,"searchParams.delete('vcac_lang')")!==false,'Language choice is remembered without leaving a routing query in the URL');
check_vcac(strpos($language_js,'hasRequestedLanguage')!==false&&strpos($language_js,"false, hasRequestedLanguage")!==false,'An existing language link saves its choice before the query is cleaned');
check_vcac(strpos($community_js,"allUrl.searchParams.set('vcac_lang'")===false&&strpos($community_js,"backUrl.searchParams.set('vcac_lang'")===false,'Internal landing links do not recreate the language query');
$hero_css=file_get_contents(WP_PLUGIN_DIR.'/vcac-landing-page/site/hero.css');
check_vcac(strpos($hero_css,'.hero-stage.is-playing .hero-media img{opacity:0}')!==false,'Playing video hides the fallback image');
check_vcac(strpos($hero_css,'aspect-ratio:16/9')!==false&&strpos($hero_css,'object-fit:contain')===false,'Mobile hero uses its own 16:9 width and cover crop');
check_vcac(substr_count($html,'<main id="main">')===1,'Only one main landmark is rendered');
file_put_contents('/test-output/rendered.html',$html);
file_put_contents('/test-output/feed.json',wp_json_encode(VCAC\LandingPage\feed_payload(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents('/test-output/results.json',wp_json_encode(array('passed'=>true,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'checks'=>$checks,'landing_id'=>$landing),JSON_PRETTY_PRINT));
echo 'PASS: '.count($checks).' WordPress Multisite integration checks';
