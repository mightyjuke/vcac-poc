<?php

require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function vcac_calendar_check($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function vcac_calendar_wall($datetime) {
    return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))->getTimestamp();
}

function vcac_calendar_epoch($datetime) {
    return (new DateTimeImmutable($datetime, new DateTimeZone('America/Vancouver')))->getTimestamp();
}

function vcac_calendar_event($title) {
    $post_id = wp_insert_post(array(
        'post_type' => 'mec-events',
        'post_status' => 'publish',
        'post_title' => $title,
    ));
    vcac_calendar_check(!is_wp_error($post_id) && $post_id > 0, 'Could not create MEC event');
    update_post_meta($post_id, 'mec_timezone', 'America/Vancouver');
    update_post_meta($post_id, 'mec_event_status', 'EventScheduled');
    return $post_id;
}

function vcac_calendar_occurrence($post_id, $start, $end, $public = 1) {
    global $wpdb;
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'mec_dates',
        array(
            'post_id' => $post_id,
            'dstart' => gmdate('Y-m-d', $start),
            'dend' => gmdate('Y-m-d', $end),
            'tstart' => $start,
            'tend' => $end,
            'public' => $public,
        ),
        array('%d', '%s', '%s', '%d', '%d', '%d')
    );
    vcac_calendar_check(false !== $inserted, 'Could not create MEC occurrence: ' . $wpdb->last_error);
}

$activation = activate_plugin('modern-events-calendar-lite/modern-events-calendar-lite.php');
vcac_calendar_check(!is_wp_error($activation), 'MEC activation failed');
vcac_calendar_check(in_array(MEC_VERSION, array('5.21.2', '6.5.6'), true), 'Unexpected integration-test MEC version');

update_option('timezone_string', 'America/Vancouver');
require dirname(__DIR__) . '/calendar-adapter.php';

$plain = wp_insert_post(array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Not an event',
));
vcac_calendar_check(null === VCAC\LandingPage\event_occurrence($plain, vcac_calendar_epoch('2026-05-10 09:00:00')), 'Non-MEC post accepted');

$dated = vcac_calendar_event('Dated event');
vcac_calendar_occurrence($dated, vcac_calendar_wall('2026-05-10 10:00:00'), vcac_calendar_wall('2026-05-10 11:30:00'));

$before = VCAC\LandingPage\event_occurrence($dated, vcac_calendar_epoch('2026-05-10 09:00:00'));
vcac_calendar_check(array(
    'start' => vcac_calendar_epoch('2026-05-10 10:00:00'),
    'end' => vcac_calendar_epoch('2026-05-10 11:30:00'),
    'status' => 'open',
) === $before, 'Dated event was not converted from MEC wall time');

$ongoing = VCAC\LandingPage\event_occurrence($dated, vcac_calendar_epoch('2026-05-10 10:30:00'));
vcac_calendar_check($before === $ongoing, 'Ongoing occurrence was not retained');
vcac_calendar_check(null === VCAC\LandingPage\event_occurrence($dated, vcac_calendar_epoch('2026-05-10 11:31:00')), 'Expired event was returned');

$visibility = vcac_calendar_event('Public occurrences only');
vcac_calendar_occurrence($visibility, vcac_calendar_wall('2026-05-11 10:00:00'), vcac_calendar_wall('2026-05-11 11:00:00'), 0);
vcac_calendar_occurrence($visibility, vcac_calendar_wall('2026-05-12 10:00:00'), vcac_calendar_wall('2026-05-12 11:00:00'));
$visible = VCAC\LandingPage\event_occurrence($visibility, vcac_calendar_epoch('2026-05-01 12:00:00'));
vcac_calendar_check($visible['start'] === vcac_calendar_epoch('2026-05-12 10:00:00'), 'Private MEC occurrence was returned');

$recurring = vcac_calendar_event('Recurring event');
$march_1 = vcac_calendar_wall('2026-03-01 10:00:00');
$march_8 = vcac_calendar_wall('2026-03-08 10:00:00');
$march_15 = vcac_calendar_wall('2026-03-15 10:00:00');
foreach (array($march_1, $march_8, $march_15) as $start) {
    vcac_calendar_occurrence($recurring, $start, $start + 3600);
}

global $wpdb;
$wpdb->insert(
    $wpdb->prefix . 'mec_occurrences',
    array(
        'post_id' => $recurring,
        'occurrence' => $march_8,
        'params' => wp_json_encode(array('event_status' => 'EventCancelled')),
    ),
    array('%d', '%d', '%s')
);
vcac_calendar_check('' === $wpdb->last_error, 'Could not create cancelled occurrence override: ' . $wpdb->last_error);

// MEC's request cache keys omit the multisite blog ID. Seed the values that a
// same-ID event on a previously visited blog could leave behind.
MEC_cache::set('mec_occ_param_' . $recurring . '_' . $march_8, array('event_status' => 'EventScheduled'));

$after_first = VCAC\LandingPage\event_occurrence($recurring, vcac_calendar_epoch('2026-03-02 12:00:00'));
vcac_calendar_check($after_first['start'] === vcac_calendar_epoch('2026-03-15 10:00:00'), 'Cancelled recurrence was not skipped');
vcac_calendar_check(
    $after_first['start'] - vcac_calendar_epoch('2026-03-01 10:00:00') === (14 * DAY_IN_SECONDS - HOUR_IN_SECONDS),
    'Vancouver DST transition was not reflected in epoch output'
);
vcac_calendar_check(null === VCAC\LandingPage\event_occurrence($recurring, vcac_calendar_epoch('2026-03-15 11:01:00')), 'Recurrence end was ignored');

$at_end = VCAC\LandingPage\event_occurrence($recurring, vcac_calendar_epoch('2026-03-01 11:00:00'));
vcac_calendar_check($at_end['start'] === vcac_calendar_epoch('2026-03-15 10:00:00'), 'Occurrence ending now suppressed the next recurrence');

$exception = vcac_calendar_event('Excluded recurrence');
$march_22 = vcac_calendar_wall('2026-03-22 10:00:00');
$march_29 = vcac_calendar_wall('2026-03-29 10:00:00');
vcac_calendar_occurrence($exception, $march_15, $march_15 + 3600);
// March 22 is intentionally absent: MEC's materialized schedule excludes it.
vcac_calendar_occurrence($exception, $march_29, $march_29 + 3600);
$after_exception = VCAC\LandingPage\event_occurrence($exception, vcac_calendar_epoch('2026-03-16 12:00:00'));
vcac_calendar_check($after_exception['start'] === vcac_calendar_epoch('2026-03-29 10:00:00'), 'Excluded recurrence was synthesized');

$cancelled = vcac_calendar_event('Cancelled event');
update_post_meta($cancelled, 'mec_event_status', 'EventCancelled');
vcac_calendar_occurrence($cancelled, vcac_calendar_wall('2026-06-01 10:00:00'), vcac_calendar_wall('2026-06-01 11:00:00'));
vcac_calendar_check(null === VCAC\LandingPage\event_occurrence($cancelled, vcac_calendar_epoch('2026-05-01 12:00:00')), 'Cancelled event was returned');

$postponed = vcac_calendar_event('Postponed event');
update_post_meta($postponed, 'mec_event_status', 'EventPostponed');
vcac_calendar_occurrence($postponed, vcac_calendar_wall('2026-06-02 10:00:00'), vcac_calendar_wall('2026-06-02 11:00:00'));
vcac_calendar_check(null === VCAC\LandingPage\event_occurrence($postponed, vcac_calendar_epoch('2026-05-01 12:00:00')), 'Postponed event was returned');

$full = vcac_calendar_event('Full event');
$full_start = vcac_calendar_wall('2026-07-01 10:00:00');
vcac_calendar_occurrence($full, $full_start, $full_start + 3600);
update_post_meta($full, 'mec_tickets', array(1 => array('limit' => 1, 'unlimited' => 0)));
update_post_meta($full, 'mec_booking', array('bookings_limit' => 1, 'bookings_limit_unlimited' => 0));
$booking = wp_insert_post(array(
    'post_type' => 'mec-books',
    'post_status' => 'publish',
    'post_title' => 'Confirmed booking',
    'post_date' => '2026-07-01 10:00:00',
));
update_post_meta($booking, 'mec_event_id', $full);
update_post_meta($booking, 'mec_ticket_id', ',1,');
update_post_meta($booking, 'mec_confirmed', 1);
update_post_meta($booking, 'mec_verified', 1);
if (version_compare(MEC_VERSION, '6.0.0', '>=')) {
    $wpdb->insert(
        $wpdb->prefix . 'mec_bookings',
        array(
            'booking_id' => $booking,
            'event_id' => $full,
            'ticket_ids' => ',1,',
            'status' => 'publish',
            'confirmed' => 1,
            'verified' => 1,
            'all_occurrences' => 0,
            'date' => '2026-07-01 10:00:00',
            'timestamp' => $full_start,
        ),
        array('%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d')
    );
    vcac_calendar_check('' === $wpdb->last_error, 'Could not create MEC booking record: ' . $wpdb->last_error);
}
$cache = MEC_cache::getInstance();
$cache->set($full . ':' . $full_start, false);
$cache->set($full . ':1:' . $full_start, array());
$full_result = VCAC\LandingPage\event_occurrence($full, vcac_calendar_epoch('2026-06-01 12:00:00'));
vcac_calendar_check('full' === $full_result['status'], 'MEC sold-out state was not exposed');

echo "VCAC MEC calendar adapter integration checks passed.\n";
