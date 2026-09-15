<?php
/**
 * Modern Events Calendar occurrence adapter.
 *
 * MEC stores occurrence timestamps as UTC-shaped representations of local wall
 * time. This adapter converts those values to real Unix timestamps in the
 * event timezone before returning them to the landing-page backend.
 */

namespace VCAC\LandingPage;

defined('ABSPATH') || exit;

const MEC_MIN_SUPPORTED_VERSION = '5.21.2';
const MEC_MAX_SUPPORTED_VERSION = '7.35.1';

/**
 * Return the current or next usable occurrence of a MEC event.
 *
 * @param int      $post_id MEC event post ID.
 * @param int|null $now     Real Unix timestamp; defaults to the current time.
 * @return array{start:int,end:int,status:string}|null
 */
function event_occurrence($post_id, $now = null) {
    $post_id = absint($post_id);
    $post = $post_id ? get_post($post_id) : null;

    if (!$post
        || 'mec-events' !== $post->post_type
        || 'publish' !== $post->post_status
        || !defined('MEC_VERSION')
        || version_compare(MEC_VERSION, MEC_MIN_SUPPORTED_VERSION, '<')
        || version_compare(MEC_VERSION, MEC_MAX_SUPPORTED_VERSION, '>')
        || !class_exists('MEC\\Events\\Event')
        || !class_exists('MEC_feature_occurrences')
        || !class_exists('MEC_cache')
        || !class_exists('MEC\\Base')
        || !method_exists('MEC\\Events\\Event', 'get_occurrences_times')
        || !method_exists('MEC_feature_occurrences', 'param')
        || !method_exists('MEC_cache', 'delete')
        || !method_exists('MEC\\Base', 'get_main')
    ) {
        return null;
    }

    $now = null === $now ? time() : $now;
    if (!is_int($now) && !(is_string($now) && ctype_digit($now))) {
        return null;
    }
    $now = (int) $now;
    if ($now <= 0) {
        return null;
    }

    try {
        $main = \MEC\Base::get_main();
        if (!is_object($main)
            || !method_exists($main, 'get_timezone')
            || !method_exists($main, 'is_sold')
        ) {
            return null;
        }
        $timezone_name = (string) $main->get_timezone($post_id);
        $timezone = new \DateTimeZone($timezone_name);
        $event = new \MEC\Events\Event($post_id);
        $mec_now = _mec_wall_timestamp($now, $timezone);
    } catch (\Throwable $error) {
        return null;
    }

    if (null === $mec_now) {
        return null;
    }

    $default_status = (string) get_post_meta($post_id, 'mec_event_status', true);
    if ('' === $default_status) {
        $default_status = 'EventScheduled';
    }
    if ('EventCancelled' === $default_status) {
        return null;
    }
    if (!in_array($default_status, array('EventScheduled', 'EventMovedOnline'), true)) {
        return null;
    }

    $rows = array();
    $ongoing = _mec_ongoing_row($post_id, $mec_now);
    if (null !== $ongoing) {
        $rows[] = $ongoing;
    }

    try {
        $next_rows = $event->get_occurrences_times($mec_now, 100);
    } catch (\Throwable $error) {
        return null;
    }
    if (!is_array($next_rows)) {
        return null;
    }
    foreach ($next_rows as $row) {
        $rows[] = $row;
    }

    $seen = array();
    foreach ($rows as $row) {
        $times = _mec_row_times($row);
        if (null === $times || $times['end'] <= $mec_now || !_mec_row_is_public($post_id, $times)) {
            continue;
        }

        $key = $times['start'] . ':' . $times['end'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        if (!_mec_clear_request_cache($post_id, $times['start'])) {
            return null;
        }

        try {
            $status = \MEC_feature_occurrences::param(
                $post_id,
                $times['start'],
                'event_status',
                $default_status
            );
        } catch (\Throwable $error) {
            return null;
        }
        if ('EventCancelled' === $status) {
            continue;
        }
        if (!in_array($status, array('EventScheduled', 'EventMovedOnline'), true)) {
            return null;
        }

        $start = _mec_epoch_timestamp($times['start'], $timezone);
        $end = _mec_epoch_timestamp($times['end'], $timezone);
        if (null === $start || null === $end || $end < $start || $end <= $now) {
            continue;
        }

        try {
            $sold = $main->is_sold($post_id, $times['start']);
        } catch (\Throwable $error) {
            _mec_clear_request_cache($post_id, $times['start']);
            return null;
        }
        _mec_clear_request_cache($post_id, $times['start']);

        return array(
            'start' => $start,
            'end' => $end,
            'status' => $sold ? 'full' : 'open',
        );
    }

    return null;
}

/**
 * Clear MEC's request-local keys, which do not include the multisite blog ID.
 */
function _mec_clear_request_cache($post_id, $timestamp) {
    if (!class_exists('MEC_cache') || !method_exists('MEC_cache', 'delete')) {
        return false;
    }

    $keys = array(
        'mec_occ_param_' . $post_id . '_' . $timestamp,
        $post_id . ':' . $timestamp,
    );
    $tickets = get_post_meta($post_id, 'mec_tickets', true);
    if (is_array($tickets)) {
        foreach (array_keys($tickets) as $ticket_id) {
            if (is_numeric($ticket_id)) {
                $keys[] = $post_id . ':' . $ticket_id . ':' . $timestamp;
            }
        }
    }

    foreach ($keys as $key) {
        \MEC_cache::delete($key);
    }

    return true;
}

/**
 * Confirm the row is a public occurrence in MEC's canonical schedule table.
 */
function _mec_row_is_public($post_id, array $times) {
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_var')) {
        return false;
    }

    $table = $wpdb->prefix . 'mec_dates';
    $query = $wpdb->prepare(
        "SELECT 1 FROM `{$table}` WHERE `post_id` = %d AND `tstart` = %d AND `tend` = %d AND `public` = 1 LIMIT 1",
        $post_id,
        $times['start'],
        $times['end']
    );

    return '1' === (string) $wpdb->get_var($query);
}

/**
 * Convert a real Unix timestamp to MEC's local-wall-time timestamp form.
 */
function _mec_wall_timestamp($timestamp, \DateTimeZone $timezone) {
    try {
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        $wall = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $date->format('Y-m-d H:i:s'),
            new \DateTimeZone('UTC')
        );
    } catch (\Throwable $error) {
        return null;
    }

    return false === $wall ? null : $wall->getTimestamp();
}

/**
 * Convert MEC's UTC-shaped wall timestamp to a real Unix timestamp.
 */
function _mec_epoch_timestamp($timestamp, \DateTimeZone $timezone) {
    if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
        return null;
    }

    $wall = gmdate('Y-m-d H:i:s', (int) $timestamp);
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall, $timezone);
    $errors = \DateTimeImmutable::getLastErrors();
    if (false === $date
        || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d H:i:s') !== $wall
    ) {
        return null;
    }

    return $date->getTimestamp();
}

/**
 * Normalize an occurrence row returned by MEC's occurrence API.
 */
function _mec_row_times($row) {
    if (is_object($row)) {
        $row = get_object_vars($row);
    }
    if (!is_array($row)
        || !array_key_exists('tstart', $row)
        || !array_key_exists('tend', $row)
        || !is_numeric($row['tstart'])
        || !is_numeric($row['tend'])
    ) {
        return null;
    }

    $start = (int) $row['tstart'];
    $end = (int) $row['tend'];
    if ($start <= 0 || $end < $start) {
        return null;
    }

    return array('start' => $start, 'end' => $end);
}

/**
 * MEC's public API starts at tstart, so use its canonical schedule table to
 * retain an occurrence that began before $now and has not ended yet.
 */
function _mec_ongoing_row($post_id, $mec_now) {
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
        return null;
    }

    $table = $wpdb->prefix . 'mec_dates';
    $query = $wpdb->prepare(
        "SELECT `tstart`, `tend` FROM `{$table}` WHERE `post_id` = %d AND `tstart` <= %d AND `tend` > %d AND `public` = 1 ORDER BY `tstart` DESC LIMIT 1",
        $post_id,
        $mec_now,
        $mec_now
    );
    $row = $wpdb->get_row($query);

    return null === _mec_row_times($row) ? null : $row;
}
