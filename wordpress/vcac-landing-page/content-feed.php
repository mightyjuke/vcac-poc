<?php

namespace VCAC\LandingPage;

defined('ABSPATH') || exit;

const FEED_VERSION = '0.4.3';
const FEED_SOURCE_OPTION = 'vcac_feed_source_sites';
const LISTING_META_KEY = '_vcac_listing';
const AUDIENCE_META_KEY = '_vcac_audience';
const PROGRAMME_ID_META_KEY = '_vcac_programme_id';
const SUMMARY_META_KEY = '_vcac_summary';
const ACTION_URL_META_KEY = '_vcac_action_url';
const SCHEDULE_META_KEY = '_vcac_schedule';
const REVIEW_UNTIL_META_KEY = '_vcac_review_until';
const FEED_QUERY_LIMIT = 100;

/**
 * Site IDs are deliberately detected at read time. Installing or activating the
 * plugin never writes options or changes existing sites/pages.
 */
function default_feed_source_sites() {
    $current = get_current_blog_id();
    if (!is_multisite()) {
        return array('en' => $current, 'zh-Hant' => $current, 'zh-Hans' => $current);
    }

    $defaults = array('en' => 0, 'zh-Hant' => 0, 'zh-Hans' => 0);
    $paths = array('en' => '/english/', 'zh-Hant' => '/cantonese/', 'zh-Hans' => '/mandarin/');
    $sites = get_sites(array(
        'network_id' => get_current_network_id(),
        'number' => 0,
        'public' => 1,
        'archived' => 0,
        'spam' => 0,
        'deleted' => 0,
    ));
    foreach ($sites as $site) {
        foreach ($paths as $locale => $path) {
            if (!$defaults[$locale] && trailingslashit((string) $site->path) === $path) {
                $defaults[$locale] = (int) $site->blog_id;
            }
        }
    }

    return $defaults;
}

function feed_source_sites() {
    $saved = get_option(FEED_SOURCE_OPTION, null);
    if (!is_array($saved)) {
        return default_feed_source_sites();
    }
    return array(
        'en' => isset($saved['en']) && is_scalar($saved['en']) ? absint($saved['en']) : 0,
        'zh-Hant' => isset($saved['zh-Hant']) && is_scalar($saved['zh-Hant']) ? absint($saved['zh-Hant']) : 0,
        'zh-Hans' => isset($saved['zh-Hans']) && is_scalar($saved['zh-Hans']) ? absint($saved['zh-Hans']) : 0,
    );
}

function valid_feed_source_site($site_id) {
    $site_id = absint($site_id);
    if (!$site_id) {
        return false;
    }
    if (!is_multisite()) {
        return $site_id === get_current_blog_id();
    }
    $site = get_site($site_id);
    return $site
        && (int) $site->network_id === (int) get_current_network_id()
        && (int) $site->public === 1
        && !(int) $site->archived
        && !(int) $site->spam
        && !(int) $site->deleted;
}

function sanitize_feed_source_sites($input) {
    $input = is_array($input) ? $input : array();
    $candidate = array(
        'en' => isset($input['en']) && is_scalar($input['en']) ? absint($input['en']) : 0,
        'zh-Hant' => isset($input['zh-Hant']) && is_scalar($input['zh-Hant']) ? absint($input['zh-Hant']) : 0,
        'zh-Hans' => isset($input['zh-Hans']) && is_scalar($input['zh-Hans']) ? absint($input['zh-Hans']) : 0,
    );
    $valid = true;
    foreach ($candidate as $locale => $site_id) {
        if (!valid_feed_source_site($site_id)) {
            add_settings_error(
                FEED_SOURCE_OPTION,
                'vcac_invalid_source_' . sanitize_key($locale),
                sprintf('Site ID %d is not an active public site on this network.', $site_id)
            );
            $valid = false;
        }
    }
    if (count(array_unique(array_values($candidate))) !== count($candidate)) {
        add_settings_error(FEED_SOURCE_OPTION, 'vcac_duplicate_sources', 'Choose a different source site for each language.');
        $valid = false;
    }
    if ($valid) {
        return $candidate;
    }
    $previous = get_option(FEED_SOURCE_OPTION, null);
    return is_array($previous) ? $previous : default_feed_source_sites();
}

add_action('admin_init', function () {
    if (!allowed_site()) {
        return;
    }
    register_setting('vcac_feed_settings', FEED_SOURCE_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_feed_source_sites',
        'default' => default_feed_source_sites(),
    ));
});

add_action('admin_menu', function () {
    if (!allowed_site()) {
        return;
    }
    add_options_page(
        'VCAC Content Feed',
        'VCAC Content Feed',
        'manage_options',
        'vcac-content-feed',
        __NAMESPACE__ . '\\render_feed_settings_page'
    );
});

function render_feed_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $sources = feed_source_sites();
    $labels = array('en' => 'English', 'zh-Hant' => 'Cantonese (Traditional Chinese)', 'zh-Hans' => 'Mandarin (Simplified Chinese)');
    ?>
    <div class="wrap">
        <h1>VCAC Content Feed</h1>
        <p>Choose the active public network site that owns content for each language. Defaults are detected from the <code>/english/</code>, <code>/cantonese/</code>, and <code>/mandarin/</code> site paths.</p>
        <form method="post" action="options.php">
            <?php settings_fields('vcac_feed_settings'); ?>
            <table class="form-table" role="presentation">
                <?php foreach ($labels as $locale => $label) : ?>
                    <tr>
                        <th scope="row"><label for="vcac-source-<?php echo esc_attr($locale); ?>"><?php echo esc_html($label); ?> source site ID</label></th>
                        <td><input class="small-text" type="number" min="1" required id="vcac-source-<?php echo esc_attr($locale); ?>" name="<?php echo esc_attr(FEED_SOURCE_OPTION); ?>[<?php echo esc_attr($locale); ?>]" value="<?php echo esc_attr($sources[$locale]); ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('add_meta_boxes', function ($post_type) {
    if (!in_array($post_type, array('post', 'mec-events'), true)) {
        return;
    }
    add_meta_box(
        'vcac-content-listing',
        'VCAC Landing Page Listing',
        __NAMESPACE__ . '\\render_listing_box',
        $post_type,
        'normal',
        'default'
    );
});

function render_listing_box($post) {
    $listing = get_post_meta($post->ID, LISTING_META_KEY, true);
    $audience = get_post_meta($post->ID, AUDIENCE_META_KEY, true);
    $listing = in_array($listing, array('update', 'community'), true) ? $listing : 'off';
    $audience = 'all' === $audience ? 'all' : 'ministry';
    wp_nonce_field('vcac_save_content_listing', 'vcac_content_listing_nonce');
    ?>
    <p><label for="vcac-listing"><strong>Show on landing page</strong></label><br>
        <select id="vcac-listing" name="vcac_listing">
            <option value="off"<?php selected($listing, 'off'); ?>>Off</option>
            <option value="update"<?php selected($listing, 'update'); ?>>Ministry update</option>
            <option value="community"<?php selected($listing, 'community'); ?>>Community programme or event</option>
        </select><br><span class="description">Only published, non-password-protected items can appear. Events also need a current or upcoming occurrence.</span></p>
    <p><label for="vcac-audience"><strong>Audience</strong></label><br>
        <select id="vcac-audience" name="vcac_audience">
            <option value="ministry"<?php selected($audience, 'ministry'); ?>>This language ministry</option>
            <option value="all"<?php selected($audience, 'all'); ?>>All language ministries</option>
        </select><br><span class="description">“All language ministries” applies only to community listings. Updates always stay with this site's language.</span></p>
    <p><label for="vcac-programme-id"><strong>Programme ID</strong> (optional)</label><br>
        <input class="widefat" id="vcac-programme-id" name="vcac_programme_id" maxlength="128" value="<?php echo esc_attr(get_post_meta($post->ID, PROGRAMME_ID_META_KEY, true)); ?>"><br><span class="description">A stable ID shared by language versions, for example <code>food-bank</code>. A listing in the selected language wins when IDs match.</span></p>
    <p><label for="vcac-summary"><strong>Listing summary</strong> (optional)</label><br>
        <textarea class="widefat" rows="3" maxlength="500" id="vcac-summary" name="vcac_summary"><?php echo esc_textarea(get_post_meta($post->ID, SUMMARY_META_KEY, true)); ?></textarea></p>
    <p><label for="vcac-action-url"><strong>Action URL</strong> (optional)</label><br>
        <input class="widefat" type="url" id="vcac-action-url" name="vcac_action_url" value="<?php echo esc_attr(get_post_meta($post->ID, ACTION_URL_META_KEY, true)); ?>"><br><span class="description">Leave empty to link to this post or event.</span></p>
    <?php if ('post' === $post->post_type) : ?>
        <p><label for="vcac-schedule"><strong>Schedule</strong></label><br>
            <input class="widefat" id="vcac-schedule" name="vcac_schedule" maxlength="200" value="<?php echo esc_attr(get_post_meta($post->ID, SCHEDULE_META_KEY, true)); ?>"><br><span class="description">Required for an ongoing community programme, for example “Tuesdays, 10 a.m.–noon”.</span></p>
    <?php endif; ?>
    <p><label for="vcac-review-until"><strong>Review by</strong></label><br>
        <input type="date" id="vcac-review-until" name="vcac_review_until" value="<?php echo esc_attr(get_post_meta($post->ID, REVIEW_UNTIL_META_KEY, true)); ?>"><br><span class="description"><?php echo 'post' === $post->post_type ? 'Required and must be today or later for an ongoing community programme. ' : ''; ?>After this date, the listing is hidden until reviewed. Optional for updates.</span></p>
    <?php
}

function sanitize_listing_choice($value) {
    if (!is_scalar($value)) {
        return 'off';
    }
    $value = sanitize_key($value);
    return in_array($value, array('update', 'community'), true) ? $value : 'off';
}

function sanitize_audience_choice($value, $listing) {
    if (!is_scalar($value)) {
        return 'ministry';
    }
    return 'community' === $listing && 'all' === sanitize_key($value) ? 'all' : 'ministry';
}

function sanitize_programme_id($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $value = sanitize_text_field($value);
    $value = preg_replace('/[^A-Za-z0-9._:-]/', '-', $value);
    return substr(trim($value, '-'), 0, 128);
}

function sanitize_listing_summary($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $value = sanitize_textarea_field($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 500);
    }
    return wp_check_invalid_utf8(substr($value, 0, 500), true);
}

function sanitize_action_url($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $url = esc_url_raw(trim($value), array('http', 'https'));
    return $url && wp_http_validate_url($url) ? $url : '';
}

function sanitize_listing_schedule($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $value = sanitize_text_field($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 200);
    }
    return wp_check_invalid_utf8(substr($value, 0, 200), true);
}

function sanitize_review_until($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $value = trim(sanitize_text_field($value));
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function update_or_delete_post_meta($post_id, $key, $value) {
    if ('' === $value || 'off' === $value) {
        delete_post_meta($post_id, $key);
    } else {
        update_post_meta($post_id, $key, $value);
    }
}

$GLOBALS['vcac_listing_validation_error'] = false;

add_action('save_post', function ($post_id, $post) {
    if (!in_array($post->post_type, array('post', 'mec-events'), true)
        || !isset($_POST['vcac_content_listing_nonce'])
        || !is_scalar($_POST['vcac_content_listing_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vcac_content_listing_nonce'])), 'vcac_save_content_listing')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $listing = sanitize_listing_choice(isset($_POST['vcac_listing']) ? wp_unslash($_POST['vcac_listing']) : 'off');
    $audience = sanitize_audience_choice(isset($_POST['vcac_audience']) ? wp_unslash($_POST['vcac_audience']) : 'ministry', $listing);
    $programme_id = sanitize_programme_id(isset($_POST['vcac_programme_id']) ? wp_unslash($_POST['vcac_programme_id']) : '');
    $summary = sanitize_listing_summary(isset($_POST['vcac_summary']) ? wp_unslash($_POST['vcac_summary']) : '');
    $action_url = sanitize_action_url(isset($_POST['vcac_action_url']) ? wp_unslash($_POST['vcac_action_url']) : '');
    $schedule = 'post' === $post->post_type ? sanitize_listing_schedule(isset($_POST['vcac_schedule']) ? wp_unslash($_POST['vcac_schedule']) : '') : '';
    $review_until = sanitize_review_until(isset($_POST['vcac_review_until']) ? wp_unslash($_POST['vcac_review_until']) : '');

    update_or_delete_post_meta($post_id, LISTING_META_KEY, $listing);
    update_or_delete_post_meta($post_id, AUDIENCE_META_KEY, $audience);
    update_or_delete_post_meta($post_id, PROGRAMME_ID_META_KEY, $programme_id);
    update_or_delete_post_meta($post_id, SUMMARY_META_KEY, $summary);
    update_or_delete_post_meta($post_id, ACTION_URL_META_KEY, $action_url);
    update_or_delete_post_meta($post_id, SCHEDULE_META_KEY, $schedule);
    update_or_delete_post_meta($post_id, REVIEW_UNTIL_META_KEY, $review_until);

    if ('post' === $post->post_type && 'community' === $listing
        && (!$schedule || !review_date_is_current($review_until))) {
        $GLOBALS['vcac_listing_validation_error'] = true;
    }
}, 10, 2);

add_filter('redirect_post_location', function ($location) {
    return !empty($GLOBALS['vcac_listing_validation_error'])
        ? add_query_arg('vcac_listing_error', 'ongoing', $location)
        : $location;
});

add_action('admin_notices', function () {
    if (!isset($_GET['vcac_listing_error'])
        || !is_scalar($_GET['vcac_listing_error'])
        || 'ongoing' !== sanitize_key(wp_unslash($_GET['vcac_listing_error']))) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>This community listing is not live:</strong> ongoing posts need a nonempty schedule and a “Review by” date of today or later.</p></div>';
});

function review_date_is_current($date, $now = null) {
    $date = sanitize_review_until($date);
    if (!$date) {
        return false;
    }
    $now = null === $now ? current_time('timestamp', true) : (int) $now;
    return $date >= wp_date('Y-m-d', $now, wp_timezone());
}

function feed_source_details($site_id) {
    $details = array('available' => false, 'name' => '', 'home' => '', 'visitors' => '', 'events' => '', 'updates' => '');
    if (!valid_feed_source_site($site_id)) {
        return $details;
    }
    $switched = false;
    try {
        if (is_multisite() && (int) $site_id !== get_current_blog_id()) {
            switch_to_blog($site_id);
            $switched = true;
        }
        $home = home_url('/');
        return array(
            'available' => true,
            'name' => wp_strip_all_tags(get_bloginfo('name')),
            'home' => $home,
            'visitors' => home_url('/visitors/'),
            'events' => home_url('/events/'),
            'updates' => home_url('/about/announcements/'),
        );
    } catch (\Throwable $error) {
        return $details;
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }
}

function feed_payload() {
    $now = current_time('timestamp', true);
    $source_ids = feed_source_sites();
    $sources = array();
    $records_by_locale = array();
    $availability = array();

    foreach ($source_ids as $locale => $site_id) {
        $details = feed_source_details($site_id);
        $availability[$locale] = $details['available'];
        $sources[$locale] = array(
            'home' => $details['home'],
            'visitors' => $details['visitors'],
            'events' => $details['events'],
            'updates' => $details['updates'],
        );
        $records = $details['available'] ? feed_records_for_site($site_id, $locale, $details, $now) : null;
        if (null === $records) {
            $availability[$locale] = false;
            $records = array('community' => array(), 'updates' => array());
        }
        $records_by_locale[$locale] = $records;
    }

    $locales = array();
    foreach ($source_ids as $locale => $site_id) {
        if (!$availability[$locale]) {
            $locales[$locale] = array('community' => array(), 'updates' => array(), 'status' => 'unavailable');
            continue;
        }
        $community = array();
        $seen = array();

        // Local records are first so a language-specific item wins over a shared translation sibling.
        foreach ($records_by_locale[$locale]['community'] as $record) {
            $key = feed_record_key($record);
            if (!isset($seen[$key])) {
                $community[] = $record;
                $seen[$key] = true;
            }
        }
        foreach ($records_by_locale as $source_locale => $groups) {
            if ($source_locale === $locale) {
                continue;
            }
            foreach ($groups['community'] as $record) {
                if ('all' !== $record['_audience']) {
                    continue;
                }
                $key = feed_record_key($record);
                if (!isset($seen[$key])) {
                    $community[] = $record;
                    $seen[$key] = true;
                }
            }
        }
        usort($community, __NAMESPACE__ . '\\compare_community_records');
        $locales[$locale] = array(
            'community' => array_map(__NAMESPACE__ . '\\public_feed_record', $community),
            'updates' => array_map(__NAMESPACE__ . '\\public_feed_record', $records_by_locale[$locale]['updates']),
            'status' => in_array(false, $availability, true) ? 'partial' : 'ok',
        );
    }

    $page_url = get_permalink(get_queried_object_id());
    return array(
        'version' => FEED_VERSION,
        'directoryUrl' => $page_url ? add_query_arg('vcac_view', 'community', $page_url) : '',
        'sources' => $sources,
        'locales' => $locales,
    );
}

function feed_records_for_site($site_id, $locale, $details, $now) {
    $result = array('community' => array(), 'updates' => array());
    $switched = false;
    try {
        if (is_multisite() && (int) $site_id !== get_current_blog_id()) {
            switch_to_blog($site_id);
            $switched = true;
        }
        foreach (array('community', 'update') as $listing) {
            $query = new \WP_Query(array(
                'post_type' => array('post', 'mec-events'),
                // Source-site mapping already chooses the ministry/language.
                // Polylang on the main site otherwise filters this mixed query
                // by its language taxonomy, even on an untranslated ministry.
                'lang' => '',
                'post_status' => 'publish',
                'has_password' => false,
                'posts_per_page' => FEED_QUERY_LIMIT,
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_key' => LISTING_META_KEY,
                'meta_value' => $listing,
            ));
            foreach ($query->posts as $post) {
                $record = build_feed_record($post, $site_id, $locale, $details, $listing, $now);
                if ($record) {
                    $result['community' === $listing ? 'community' : 'updates'][] = $record;
                }
            }
        }
        wp_reset_postdata();
    } catch (\Throwable $error) {
        return null;
    } finally {
        if ($switched) {
            restore_current_blog();
        }
    }
    return $result;
}

function build_feed_record($post, $site_id, $locale, $details, $listing, $now) {
    if ('publish' !== $post->post_status || '' !== $post->post_password || !in_array($listing, array('update', 'community'), true)) {
        return null;
    }
    $review_until = get_post_meta($post->ID, REVIEW_UNTIL_META_KEY, true);
    $schedule = get_post_meta($post->ID, SCHEDULE_META_KEY, true);
    $start = null;
    $end = null;
    $status = 'ongoing';
    $kind = 'update' === $listing ? 'update' : 'programme';

    if ('mec-events' === $post->post_type) {
        if (!function_exists(__NAMESPACE__ . '\\event_occurrence')) {
            return null;
        }
        $occurrence = event_occurrence($post->ID, $now);
        if (!is_array($occurrence)
            || !isset($occurrence['status'])
            || !in_array($occurrence['status'], array('open', 'full', 'cancelled'), true)) {
            return null;
        }
        $start = isset($occurrence['start']) ? (int) $occurrence['start'] : null;
        $end = isset($occurrence['end']) ? (int) $occurrence['end'] : null;
        $status = $occurrence['status'];
        $kind = 'update' === $listing ? 'update' : 'event';
        if ('cancelled' === $status || (null !== $end && $end <= $now)) {
            return null;
        }
        if ($review_until && !review_date_is_current($review_until, $now)) {
            return null;
        }
    } elseif ('community' === $listing && (!$schedule || !review_date_is_current($review_until, $now))) {
        return null;
    } elseif ('update' === $listing && $review_until && !review_date_is_current($review_until, $now)) {
        return null;
    }

    $thumbnail_id = get_post_thumbnail_id($post->ID);
    $image = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'large') : '';
    $action_url = get_post_meta($post->ID, ACTION_URL_META_KEY, true);
    $action_url = sanitize_action_url($action_url);
    return array(
        'id' => (int) $site_id . ':' . (int) $post->ID,
        'programmeId' => sanitize_programme_id(get_post_meta($post->ID, PROGRAMME_ID_META_KEY, true)),
        'title' => wp_strip_all_tags(get_the_title($post)),
        'summary' => feed_record_summary($post),
        'url' => $action_url ? $action_url : get_permalink($post),
        'image' => $image ? $image : '',
        'imageAlt' => $thumbnail_id ? sanitize_text_field(get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)) : '',
        'language' => $locale,
        'sourceName' => $details['name'],
        'sourceUrl' => $details['home'],
        'start' => $start,
        'end' => $end,
        'schedule' => sanitize_listing_schedule($schedule),
        'status' => $status,
        'kind' => $kind,
        'published' => (int) get_post_time('U', true, $post),
        '_audience' => 'all' === get_post_meta($post->ID, AUDIENCE_META_KEY, true) && 'community' === $listing ? 'all' : 'ministry',
    );
}

function feed_record_key($record) {
    return $record['programmeId'] ? 'programme:' . strtolower($record['programmeId']) : 'record:' . $record['id'];
}

function feed_record_summary($post) {
    $summary = sanitize_listing_summary(get_post_meta($post->ID, SUMMARY_META_KEY, true));
    if ('' !== $summary) {
        return $summary;
    }
    $summary = '' !== trim((string) $post->post_excerpt)
        ? $post->post_excerpt
        : strip_shortcodes((string) $post->post_content);
    return sanitize_listing_summary(trim(wp_strip_all_tags($summary, true)));
}

function compare_community_records($left, $right) {
    $left_is_event = null !== $left['start'];
    $right_is_event = null !== $right['start'];
    if ($left_is_event && $right_is_event && $left['start'] !== $right['start']) {
        return $left['start'] < $right['start'] ? -1 : 1;
    }
    if ($left_is_event !== $right_is_event) {
        return $left_is_event ? -1 : 1;
    }
    if ($left['published'] === $right['published']) {
        return strcmp($left['id'], $right['id']);
    }
    return $left['published'] > $right['published'] ? -1 : 1;
}

function public_feed_record($record) {
    unset($record['_audience']);
    return $record;
}
