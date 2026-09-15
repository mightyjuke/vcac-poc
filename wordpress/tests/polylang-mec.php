<?php
// Disposable local Playground only. Never execute fixtures on a VCAC site.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
$checks = array();
function pll_mec_check($condition, $message) {
    global $checks;
    if (!$condition) throw new Exception($message);
    $checks[] = $message;
}
pll_mec_check(function_exists('PLL') && PLL() instanceof PLL_Frontend, 'Real Polylang frontend loaded on a fresh request');
PLL()->curlang = PLL()->model->get_language('en');
$network = get_network();
$sites = array();
foreach (array('en'=>'english', 'zh-Hant'=>'cantonese', 'zh-Hans'=>'mandarin') as $locale=>$path) {
    $site = wpmu_create_blog($network->domain, '/'.$path.'/', $path, 1, array('public'=>1), $network->id);
    pll_mec_check(!is_wp_error($site), 'Created untranslated ministry '.$locale);
    $sites[$locale] = $site;
}
switch_to_blog($sites['en']);
pll_mec_check(!is_plugin_active('polylang/polylang.php'), 'Polylang inactive on English ministry, matching staging');
update_option('timezone_string', 'America/Vancouver');
global $wpdb;
// Fixtures represent MEC's generated occurrence tables; the adapter uses real MEC APIs.
$wpdb->query("CREATE TABLE {$wpdb->prefix}mec_dates (id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id bigint, dstart date, dend date, tstart bigint, tend bigint, public int)");
$wpdb->query("CREATE TABLE {$wpdb->prefix}mec_occurrences (id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id bigint, occurrence bigint, params text)");
$id = wp_insert_post(array('post_type'=>'mec-events','post_status'=>'publish','post_title'=>'Alpha Course','post_content'=>'Join us on Tuesday.'));
update_post_meta($id, '_vcac_listing', 'community');
update_post_meta($id, 'mec_event_status', 'EventScheduled');
$start = strtotime('+1 day'); $end = $start + 9900;
pll_mec_check(false !== $wpdb->insert($wpdb->prefix.'mec_dates', array('post_id'=>$id,'dstart'=>gmdate('Y-m-d',$start),'dend'=>gmdate('Y-m-d',$end),'tstart'=>$start,'tend'=>$end,'public'=>1)), 'Upcoming MEC occurrence stored');
pll_mec_check(is_array(VCAC\LandingPage\event_occurrence($id)), 'MEC returns a valid occurrence before the feed query');
$args = array('post_type'=>array('post','mec-events'),'post_status'=>'publish','meta_key'=>'_vcac_listing','meta_value'=>'community');
$legacy = new WP_Query($args);
pll_mec_check(count($legacy->posts)===0, 'Reproduced 0.4.1: inherited Polylang filter hides Alpha Course');
$explicit = new WP_Query(array_merge($args, array('lang'=>'')));
pll_mec_check(count($explicit->posts)===1, 'Explicit source query retrieves Alpha without a translation tag');
restore_current_blog();
$feed = VCAC\LandingPage\feed_payload();
pll_mec_check(count($feed['locales']['en']['community'])===1 && $feed['locales']['en']['community'][0]['id']===$sites['en'].':'.$id, 'Packaged feed displays Alpha Course in English');
pll_mec_check(count($feed['locales']['zh-Hant']['community'])===0 && count($feed['locales']['zh-Hans']['community'])===0, 'Ministry-only event stays out of Chinese feeds');
pll_mec_check(get_current_blog_id()===1 && !ms_is_switched(), 'Main blog context restored after feed');
switch_to_blog($sites['en']);
update_post_meta($id, '_vcac_audience', 'all');
restore_current_blog();
$shared = VCAC\LandingPage\feed_payload();
pll_mec_check(count($shared['locales']['zh-Hant']['community'])===1 && count($shared['locales']['zh-Hans']['community'])===1, 'Explicit shared audience still reaches both Chinese feeds');
foreach (array('draft','private') as $status) {
    switch_to_blog($sites['en']); wp_update_post(array('ID'=>$id,'post_status'=>$status)); restore_current_blog();
    pll_mec_check(count(VCAC\LandingPage\feed_payload()['locales']['en']['community'])===0, $status.' event stays excluded');
}
switch_to_blog($sites['en']); wp_update_post(array('ID'=>$id,'post_status'=>'publish','post_password'=>'local-test')); restore_current_blog();
pll_mec_check(count(VCAC\LandingPage\feed_payload()['locales']['en']['community'])===0, 'Password-protected event stays excluded');
switch_to_blog($sites['en']); wp_update_post(array('ID'=>$id,'post_password'=>'')); update_post_meta($id,'mec_event_status','EventCancelled'); restore_current_blog();
pll_mec_check(count(VCAC\LandingPage\feed_payload()['locales']['en']['community'])===0, 'Cancelled event stays excluded');
switch_to_blog($sites['en']); update_post_meta($id,'mec_event_status','EventScheduled'); update_post_meta($id,'_vcac_listing','off'); restore_current_blog();
pll_mec_check(count(VCAC\LandingPage\feed_payload()['locales']['en']['community'])===0, 'Opt-out still removes event');
switch_to_blog($sites['en']); update_post_meta($id,'_vcac_listing','community'); wp_update_post(array('ID'=>$id,'post_title'=>'Alpha updated once')); restore_current_blog();
pll_mec_check(VCAC\LandingPage\feed_payload()['locales']['en']['community'][0]['title']==='Alpha updated once', 'Source update appears on the next feed request');
file_put_contents('/test-output/polylang-results.json', wp_json_encode(array('passed'=>count($checks),'checks'=>$checks,'wp'=>$GLOBALS['wp_version'],'php'=>PHP_VERSION,'mec'=>MEC_VERSION,'polylang'=>POLYLANG_VERSION,'feed'=>$feed), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
