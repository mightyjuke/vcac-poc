<?php
namespace VCAC\LandingPage;
defined('ABSPATH') || exit;

const EMERGENCY_OPTION = 'vcac_emergency_notice';

function emergency_defaults() {
    return array('enabled'=>false,'tone'=>'yellow','expires'=>'','expires_at'=>0,'url'=>'','copy'=>array());
}

function emergency_settings() {
    $value=get_option(EMERGENCY_OPTION,array());
    return array_merge(emergency_defaults(),is_array($value)?$value:array());
}

function emergency_text($value,$multiline=false) {
    return is_string($value) ? ($multiline ? sanitize_textarea_field($value) : sanitize_text_field($value)) : '';
}

function emergency_validate($input) {
    if(!is_array($input))return new \WP_Error('invalid','The notice could not be read. Please try again.');
    $result=emergency_defaults();
    $result['tone']=isset($input['tone'])&&'red'===$input['tone']?'red':'yellow';
    $result['enabled']=isset($input['enabled'])&&'1'===$input['enabled'];
    $result['expires']=emergency_text(isset($input['expires'])?$input['expires']:'');
    if($result['expires']!==''){
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$result['expires'],new \DateTimeZone('America/Vancouver'));
        if(!$date||$date->format('Y-m-d\TH:i')!==$result['expires'])return new \WP_Error('expiry','Enter a valid expiry date and time in Vancouver time.');
        $result['expires_at']=$date->getTimestamp();
    }
    $raw_url=emergency_text(isset($input['url'])?$input['url']:'');
    if($raw_url!==''){
        $parts=wp_parse_url($raw_url);
        if(!$parts||empty($parts['host'])||empty($parts['scheme'])||!in_array(strtolower($parts['scheme']),array('http','https'),true))return new \WP_Error('url','Use a full http:// or https:// link, or leave the link empty.');
        $result['url']=esc_url_raw($raw_url,array('http','https'));
    }
    foreach(array('en','zh-Hant','zh-Hans')as $lang){
        $copy=isset($input['copy'][$lang])&&is_array($input['copy'][$lang])?$input['copy'][$lang]:array();
        $result['copy'][$lang]=array();
        foreach(array('title'=>120,'message'=>700,'link'=>60)as $field=>$limit){
            $text=emergency_text(isset($copy[$field])?$copy[$field]:'','message'===$field);
            $length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):preg_match_all('/./us',$text);
            if($length>$limit)return new \WP_Error('length',sprintf('The %s %s is too long (maximum %d characters).',$lang,$field,$limit));
            $result['copy'][$lang][$field]=$text;
        }
        if('en'!==$lang&&(''!==$result['copy'][$lang]['title'])!==(''!==$result['copy'][$lang]['message']))return new \WP_Error('translation','Enter both a heading and message for each translation, or leave both empty to show English.');
    }
    if($result['enabled']){
        if(empty($result['copy']['en']['title'])||empty($result['copy']['en']['message']))return new \WP_Error('required','Add an English heading and message before turning the banner on.');
        if($result['expires_at']<=time())return new \WP_Error('expiry','Choose a future expiry time before turning the banner on.');
    }
    return $result;
}

// Editors and administrators of the main site can manage notices; no roles are changed.
function emergency_save($input,$nonce) {
    if(!allowed_site()||!current_user_can('edit_others_pages'))return new \WP_Error('permission','You need editor access on the main VCAC site to manage this notice.');
    if(!is_string($nonce)||!wp_verify_nonce($nonce,'vcac_emergency_save'))return new \WP_Error('nonce','Your session expired. Reload the form and try again.');
    $settings=emergency_validate($input);
    if(is_wp_error($settings))return $settings;
    update_option(EMERGENCY_OPTION,$settings,false);
    return $settings;
}

function emergency_active($settings=null,$now=null) {
    if(!allowed_site())return false;
    $settings=null===$settings?emergency_settings():$settings;
    $now=null===$now?time():$now;
    return !empty($settings['enabled'])&&(int)$settings['expires_at']>$now&&!empty($settings['copy']['en']['title'])&&!empty($settings['copy']['en']['message']);
}

function emergency_markup($settings=null) {
    $settings=null===$settings?emergency_settings():$settings;
    if(!emergency_active($settings))return '';
    $labels=array('en'=>'Read update','zh-Hant'=>'查看最新安排','zh-Hans'=>'查看最新安排');
    ob_start(); ?>
    <section id="vcac-emergency" class="emergency-banner" data-tone="<?php echo esc_attr($settings['tone']); ?>" aria-labelledby="vcac-emergency-label" data-remaining="<?php echo max(0,(int)$settings['expires_at']-time()); ?>">
      <span id="vcac-emergency-label" class="emergency-label">Important notice</span>
      <?php foreach($labels as $lang=>$label):
          $copy=isset($settings['copy'][$lang])?$settings['copy'][$lang]:array();
          if(empty($copy['title'])||empty($copy['message']))continue; ?>
          <div data-notice-language="<?php echo esc_attr($lang); ?>" lang="<?php echo esc_attr($lang); ?>"<?php if('en'!==$lang)echo ' hidden'; ?>>
            <h2 class="emergency-title"><?php echo esc_html($copy['title']); ?></h2>
            <p class="emergency-message"><?php echo esc_html($copy['message']); ?></p>
            <?php if(!empty($settings['url'])): ?><a class="emergency-link" href="<?php echo esc_url($settings['url'],array('http','https')); ?>"><?php echo esc_html(!empty($copy['link'])?$copy['link']:$label); ?></a><?php endif; ?>
          </div>
      <?php endforeach; ?>
      <p class="emergency-fallback" hidden></p>
    </section>
    <?php return ob_get_clean();
}

add_action('admin_menu',function(){
    if(allowed_site())add_menu_page('Emergency notice','Emergency notice','edit_others_pages','vcac-emergency',__NAMESPACE__.'\emergency_admin','dashicons-megaphone',21);
});
add_action('admin_enqueue_scripts',function($hook){
    if('toplevel_page_vcac-emergency'!==$hook||!allowed_site())return;
    wp_enqueue_style('vcac-emergency',plugin_dir_url(__FILE__).'site/emergency.css',array(),'0.4.8');
    wp_enqueue_style('vcac-emergency-admin',plugin_dir_url(__FILE__).'emergency-admin.css',array('vcac-emergency'),'0.4.8');
    wp_enqueue_script('vcac-emergency-admin',plugin_dir_url(__FILE__).'emergency-admin.js',array(),'0.4.8',true);
});
add_action('admin_post_vcac_emergency_save',function(){
    $nonce=isset($_POST['_wpnonce'])?wp_unslash($_POST['_wpnonce']):'';
    $input=isset($_POST['notice'])?wp_unslash($_POST['notice']):array();
    $result=emergency_save($input,$nonce);
    if(is_wp_error($result)){
        wp_die(esc_html($result->get_error_message()),'Notice was not saved',array('response'=>400,'back_link'=>true));
    }
    wp_safe_redirect(admin_url('admin.php?page=vcac-emergency&saved=1'));
    exit;
});

function emergency_admin() {
    if(!allowed_site()||!current_user_can('edit_others_pages'))wp_die('You do not have permission to manage this notice.');
    $settings=emergency_settings();
    $active=emergency_active($settings);
    $expired=!empty($settings['enabled'])&&!$active;
    $status=$active?'On — showing on the landing page':($expired?'Expired — no longer showing':'Off — hidden from visitors');
    ?>
    <div class="wrap vcac-notice-admin">
    <h1>Emergency announcement</h1>
    <p class="notice-intro">Keep everyone informed about closures and urgent changes. This banner appears on the VCAC landing page and its events directory.</p>
    <?php if(isset($_GET['saved'])&&'1'===$_GET['saved']): ?><div class="notice notice-success"><p>Notice saved. <?php echo esc_html($status); ?>.</p></div><?php endif; ?>
    <p class="notice-saved-status"><strong>Current status:</strong> <?php echo esc_html($status); ?></p>
    <form id="vcac-notice-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
    <input type="hidden" name="action" value="vcac_emergency_save">
    <?php wp_nonce_field('vcac_emergency_save'); ?>
    <div class="notice-editor-layout"><div>
    <section class="notice-editor-panel"><h2>1. Visibility &amp; expiry</h2>
    <label class="notice-toggle"><input type="checkbox" name="notice[enabled]" value="1"<?php checked($settings['enabled']); ?>> Show emergency banner</label>
    <label for="notice-tone">Banner colour</label><select id="notice-tone" name="notice[tone]"><option value="yellow"<?php selected($settings['tone'],'yellow'); ?>>Yellow — important notice</option><option value="red"<?php selected($settings['tone'],'red'); ?>>Red — urgent notice</option></select>
    <p>When on, the notice is shown to every visitor. There is no dismiss button.</p>
    <label for="notice-expires">Hide automatically after <span>(Vancouver time)</span></label>
    <input id="notice-expires" name="notice[expires]" type="datetime-local" value="<?php echo esc_attr($settings['expires']); ?>">
    <p class="description">Required when the banner is on. You can also turn it off early. Times follow Vancouver daylight saving time.</p>
    </section>
    <section class="notice-editor-panel"><h2>2. Write the notice</h2>
    <p>Keep it brief: what changed, when it applies, and what people should do.</p>
    <?php foreach(array('en'=>'English (required to turn on)','zh-Hant'=>'中文 (繁體) — optional','zh-Hans'=>'中文 (简体) — optional')as $lang=>$label):
      $copy=isset($settings['copy'][$lang])?$settings['copy'][$lang]:array(); ?>
      <fieldset class="notice-translation"><legend><?php echo esc_html($label); ?></legend>
      <?php foreach(array('title'=>'Heading','message'=>'Message','link'=>'Link text (optional)')as $key=>$field):$id='notice-'.$lang.'-'.$key; ?>
      <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field); ?></label>
      <?php if('message'===$key): ?><textarea id="<?php echo esc_attr($id); ?>" name="notice[copy][<?php echo esc_attr($lang); ?>][message]" rows="4" maxlength="700"><?php echo esc_textarea(isset($copy[$key])?$copy[$key]:''); ?></textarea>
      <?php else: ?><input id="<?php echo esc_attr($id); ?>" name="notice[copy][<?php echo esc_attr($lang); ?>][<?php echo esc_attr($key); ?>]" type="text" maxlength="<?php echo 'title'===$key?120:60; ?>" value="<?php echo esc_attr(isset($copy[$key])?$copy[$key]:''); ?>"><?php endif; ?>
      <?php endforeach; ?></fieldset>
    <?php endforeach; ?>
    <p>Chinese translations follow the visitor's display language. If a translation is empty, the English notice is shown with a language note. Text is not translated automatically.</p>
    </section>
    <section class="notice-editor-panel"><h2>3. Optional details link</h2>
    <label for="notice-url">Link to an Announcement or full update</label>
    <input id="notice-url" name="notice[url]" type="url" placeholder="https://" value="<?php echo esc_attr($settings['url']); ?>">
    <p>Paste the published Announcement URL from any ministry site. The same link is used for all languages. Leave empty to show the notice without a link.</p>
    </section>
    <p class="notice-save-row"><button class="button button-primary button-large" type="submit">Save notice</button><span>Changes appear after saving. Refresh the landing page to check.</span></p>
    </div><aside class="notice-preview-panel"><h2>Banner preview</h2><p id="notice-preview-state" role="status">Unsaved preview</p>
    <label for="notice-preview-language">Preview language</label><select id="notice-preview-language"><option value="en">English</option><option value="zh-Hant">中文 (繁體)</option><option value="zh-Hans">中文 (简体)</option></select>
    <div class="emergency-banner" id="notice-preview"><span class="emergency-label">Important notice</span><div id="notice-preview-copy"><h2 class="emergency-title"></h2><p class="emergency-message"></p><span class="emergency-link" hidden></span></div><p class="emergency-fallback" hidden></p></div>
    <p class="description">Preview only. Saving is required to change what visitors see. The banner stays above the header and scrolls with the page.</p>
    </aside></div></form></div>
    <?php
}
