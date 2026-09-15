<?php
/**
 * Plugin Name: VCAC Landing Page
 * Description: An opt-in standalone page template serving the locally maintained VCAC landing page.
 * Version: 0.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace VCAC\LandingPage;

defined('ABSPATH') || exit;

const TEMPLATE = 'vcac-landing-page.php';
const VIDEO_META_KEY = '_vcac_landing_video_id';

function allowed_site() {
    return !is_multisite() || is_main_site();
}

function selected_page() {
    return allowed_site() && is_singular('page')
        && TEMPLATE === get_page_template_slug(get_queried_object_id());
}

add_filter('theme_page_templates', function ($templates) {
    if (allowed_site()) {
        $templates[TEMPLATE] = 'VCAC Landing Page';
    }
    return $templates;
});

add_filter('template_include', function ($template) {
    if (!selected_page() || post_password_required()) {
        return $template;
    }
    $file = __DIR__ . '/page-template.php';
    return is_readable(__DIR__ . '/site/document.php') ? $file : $template;
}, 99);

add_action('add_meta_boxes_page', function () {
    if (allowed_site()) {
        add_meta_box(
            'vcac-landing-video',
            'Landing Page Video',
            __NAMESPACE__ . '\\render_video_box',
            'page',
            'side',
            'default'
        );
    }
});

function render_video_box($post) {
    $video_id = absint(get_post_meta($post->ID, VIDEO_META_KEY, true));
    $filename = $video_id ? basename((string) get_attached_file($video_id)) : '';
    wp_nonce_field('vcac_landing_video', 'vcac_landing_video_nonce');
    ?>
    <p>This video is used only when the <strong>VCAC Landing Page</strong> template is selected.</p>
    <input type="hidden" id="vcac-landing-video-id" name="vcac_landing_video_id" value="<?php echo esc_attr($video_id); ?>">
    <p id="vcac-landing-video-name"><?php echo $filename ? esc_html($filename) : 'No video selected — the poster image will be shown.'; ?></p>
    <p>
        <button type="button" class="button" id="vcac-landing-video-choose">Choose from Media Library</button>
        <button type="button" class="button-link-delete" id="vcac-landing-video-remove"<?php echo $video_id ? '' : ' hidden'; ?>>Remove</button>
    </p>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!allowed_site() || !in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || 'page' !== $screen->post_type) {
        return;
    }
    wp_enqueue_media();
    $script = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    const choose = document.querySelector('#vcac-landing-video-choose');
    const remove = document.querySelector('#vcac-landing-video-remove');
    const input = document.querySelector('#vcac-landing-video-id');
    const name = document.querySelector('#vcac-landing-video-name');
    if (!choose || !remove || !input || !name || !window.wp?.media) return;
    let frame;
    choose.addEventListener('click', () => {
        if (!frame) {
            frame = wp.media({title:'Choose landing-page video', button:{text:'Use this video'}, library:{type:'video'}, multiple:false});
            frame.on('select', () => {
                const file = frame.state().get('selection').first().toJSON();
                input.value = file.id;
                name.textContent = file.filename || file.title;
                remove.hidden = false;
            });
        }
        frame.open();
    });
    remove.addEventListener('click', () => {
        input.value = '';
        name.textContent = 'No video selected — the poster image will be shown.';
        remove.hidden = true;
    });
});
JS;
    wp_add_inline_script('media-editor', $script);
});

add_action('save_post_page', function ($post_id) {
    if (!allowed_site()
        || !isset($_POST['vcac_landing_video_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vcac_landing_video_nonce'])), 'vcac_landing_video')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id)
        || !current_user_can('edit_page', $post_id)
    ) {
        return;
    }
    $video_id = isset($_POST['vcac_landing_video_id']) ? absint($_POST['vcac_landing_video_id']) : 0;
    if ($video_id && 0 === strpos((string) get_post_mime_type($video_id), 'video/')) {
        update_post_meta($post_id, VIDEO_META_KEY, $video_id);
    } else {
        delete_post_meta($post_id, VIDEO_META_KEY);
    }
});

/**
 * Only the explicitly selected page uses the standalone asset set. Core head,
 * footer, SEO and access-control hooks still run. No persistent options change.
 * Directly printed third-party CSS/JS requires a staging compatibility check.
 */
function isolate_assets() {
    if (!selected_page() || post_password_required()) {
        return;
    }
    foreach (wp_styles()->queue as $handle) {
        wp_dequeue_style($handle);
    }
    foreach (wp_scripts()->queue as $handle) {
        wp_dequeue_script($handle);
    }
}

function render() {
    $html = file_get_contents(__DIR__ . '/site/document.php');
    $guard = "<?php http_response_code(404); exit; ?>\n";
    if (false === $html || 0 !== strpos($html, $guard)) {
        wp_die('The VCAC landing-page files could not be read.');
    }
    $html = substr($html, strlen($guard));
    $base = plugin_dir_url(__FILE__) . 'site/';
    $manifest = json_decode(file_get_contents(__DIR__ . '/build-manifest.json'), true);
    $version = isset($manifest['build']) ? $manifest['build'] : '0.2.1';

    $video_id = absint(get_post_meta(get_queried_object_id(), VIDEO_META_KEY, true));
    $video_url = $video_id && 0 === strpos((string) get_post_mime_type($video_id), 'video/')
        ? wp_get_attachment_url($video_id)
        : '';
    $html = str_replace('%%VCAC_HERO_VIDEO_URL%%', esc_url((string) $video_url), $html);

    // Replace local relative asset attributes without changing the source layout.
    foreach (array('style.css', 'hero.css', 'theme.css', 'readability.css', 'app.js', 'languages.js') as $asset) {
        $html = str_replace('="' . $asset . '"', '="' . esc_url($base . $asset . '?ver=' . $version) . '"', $html);
    }
    $html = str_replace('="assets/', '="' . esc_url($base . 'assets/'), $html);
    $network = is_multisite() ? network_home_url('/') : home_url('/');
    foreach (array('cantonese/', 'english/', 'mandarin/', 'english/visitors/') as $path) {
        $html = str_replace('href="https://www.vcac.ca/' . $path . '"', 'href="' . esc_url($network . $path) . '"', $html);
    }

    // Keep the original title and language switch; do not duplicate <title>.
    remove_action('wp_head', '_wp_render_title_tag', 1);
    add_filter('show_admin_bar', '__return_false');
    add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\isolate_assets', PHP_INT_MAX);
    add_action('wp_print_styles', __NAMESPACE__ . '\\isolate_assets', PHP_INT_MAX);
    add_action('wp_print_scripts', __NAMESPACE__ . '\\isolate_assets', PHP_INT_MAX);
    add_action('wp_footer', __NAMESPACE__ . '\\isolate_assets', 19);

    ob_start();
    wp_head();
    $head = ob_get_clean();
    ob_start();
    wp_body_open();
    $body_open = ob_get_clean();
    ob_start();
    wp_footer();
    $footer = ob_get_clean();

    // WordPress metadata precedes the landing-page CSS so its design remains last.
    $html = str_replace('<head>', '<head>' . $head, $html);
    // Download Manager prints an unused modal even without its scripts/styles.
    // Hide it on this standalone template to prevent 750px mobile overflow.
    $html = str_replace('</head>', '<style id="vcac-template-compatibility">#wpdm-popup-link{display:none!important}</style></head>', $html);
    $html = str_replace('<body>', '<body>' . $body_open, $html);
    $html = str_replace('</body>', $footer . '</body>', $html);
    echo $html; // Trusted, versioned local HTML; never evaluates PHP or editor content.
}
