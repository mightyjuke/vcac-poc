<?php
/**
 * Plugin Name: VCAC Landing Page
 * Description: An opt-in standalone page template serving the locally maintained VCAC landing page.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace VCAC\LandingPage;

defined('ABSPATH') || exit;

const TEMPLATE = 'vcac-landing-page.php';

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
    $version = isset($manifest['build']) ? $manifest['build'] : '0.1.0';

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
    $html = str_replace('<body>', '<body>' . $body_open, $html);
    $html = str_replace('</body>', $footer . '</body>', $html);
    echo $html; // Trusted, versioned local HTML; never evaluates PHP or editor content.
}
