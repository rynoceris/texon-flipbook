<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Analytics event storage + tracking API.
 *
 * Table: {prefix}texon_flipbook_events
 * Events are anonymous: only a client-generated session_id is stored.
 * The tracker respects DNT and Sec-GPC (Global Privacy Control).
 */
class Texon_Flipbook_Events {

    const TABLE = 'texon_flipbook_events';

    const EVENT_TYPES = [
        'session_start', 'page_view', 'hotspot_click',
        'search', 'download', 'print', 'share',
        'fullscreen', 'zoom',
    ];

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create or upgrade the events table. Called on plugin activation.
     */
    public static function install() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            book_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            page SMALLINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(40) DEFAULT NULL,
            data TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY book_date (book_id, created_at),
            KEY book_event (book_id, event_type),
            KEY session (session_id)
        ) $charset;";
        dbDelta( $sql );
    }

    /**
     * AJAX handler: log one event.
     * Public endpoint, rate-limited per session via transient.
     */
    public static function ajax_track() {
        // Respect privacy signals
        $dnt = isset( $_SERVER['HTTP_DNT'] ) && $_SERVER['HTTP_DNT'] === '1';
        $gpc = isset( $_SERVER['HTTP_SEC_GPC'] ) && $_SERVER['HTTP_SEC_GPC'] === '1';
        if ( $dnt || $gpc ) wp_send_json_success( [ 'skipped' => 'privacy_signal' ] );

        $settings = Texon_Flipbook_Settings::get();
        if ( empty( $settings['tracking_enabled'] ) ) wp_send_json_success( [ 'skipped' => 'disabled' ] );

        $book_id    = (int) ( $_POST['book_id'] ?? 0 );
        $event_type = sanitize_key( $_POST['event_type'] ?? '' );
        $page       = isset( $_POST['page'] ) ? max( 0, (int) $_POST['page'] ) : null;
        $session_id = substr( sanitize_text_field( $_POST['session_id'] ?? '' ), 0, 40 );
        $raw_data   = wp_unslash( $_POST['data'] ?? '' );

        if ( ! $book_id || ! $event_type ) wp_send_json_error( [ 'msg' => 'bad_params' ] );
        if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) wp_send_json_error( [ 'msg' => 'bad_event' ] );
        if ( get_post_type( $book_id ) !== Texon_Flipbook_Post_Type::TYPE ) wp_send_json_error( [ 'msg' => 'no_book' ] );

        // Rate limit: 120 events / 60s per session
        if ( $session_id ) {
            $key = 'tfb_rl_' . md5( $session_id );
            $count = (int) get_transient( $key );
            if ( $count >= 120 ) wp_send_json_success( [ 'skipped' => 'rate_limited' ] );
            set_transient( $key, $count + 1, 60 );
        }

        // Normalize data to a compact JSON object with capped lengths
        $data = null;
        if ( $raw_data !== '' ) {
            $decoded = json_decode( $raw_data, true );
            if ( is_array( $decoded ) ) {
                $clean = [];
                foreach ( $decoded as $k => $v ) {
                    $k = substr( sanitize_key( $k ), 0, 32 );
                    if ( is_scalar( $v ) ) $clean[ $k ] = substr( (string) $v, 0, 400 );
                }
                if ( $clean ) $data = wp_json_encode( $clean );
            }
        }

        global $wpdb;
        $wpdb->insert(
            self::table_name(),
            [
                'book_id'    => $book_id,
                'event_type' => $event_type,
                'page'       => $page,
                'session_id' => $session_id ?: null,
                'data'       => $data,
                'created_at' => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%d', '%s', '%s', '%s' ]
        );
        wp_send_json_success( [ 'ok' => 1 ] );
    }

    /**
     * Prune events older than retention_days. Called by daily cron.
     */
    public static function prune() {
        global $wpdb;
        $settings = Texon_Flipbook_Settings::get();
        $days = max( 7, (int) ( $settings['retention_days'] ?? 180 ) );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s',
            $cutoff
        ) );
    }

    /**
     * Summary stats for a flipbook within a date range.
     * $from / $to are 'Y-m-d' strings (UTC).
     */
    public static function summary( $book_id, $from, $to ) {
        global $wpdb;
        $t = self::table_name();
        $from_dt = $from . ' 00:00:00';
        $to_dt   = $to   . ' 23:59:59';

        $totals = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, COUNT(*) AS c FROM $t WHERE book_id=%d AND created_at BETWEEN %s AND %s GROUP BY event_type",
            $book_id, $from_dt, $to_dt
        ), ARRAY_A );
        $counts = [];
        foreach ( $totals as $r ) $counts[ $r['event_type'] ] = (int) $r['c'];

        $unique_sessions = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $t WHERE book_id=%d AND session_id IS NOT NULL AND created_at BETWEEN %s AND %s",
            $book_id, $from_dt, $to_dt
        ) );

        $per_page = $wpdb->get_results( $wpdb->prepare(
            "SELECT page, COUNT(*) AS c FROM $t WHERE book_id=%d AND event_type='page_view' AND created_at BETWEEN %s AND %s GROUP BY page ORDER BY page ASC",
            $book_id, $from_dt, $to_dt
        ), ARRAY_A );

        $hotspots = $wpdb->get_results( $wpdb->prepare(
            "SELECT page, data, COUNT(*) AS c FROM $t WHERE book_id=%d AND event_type='hotspot_click' AND created_at BETWEEN %s AND %s GROUP BY page, data ORDER BY c DESC LIMIT 50",
            $book_id, $from_dt, $to_dt
        ), ARRAY_A );

        $searches = $wpdb->get_results( $wpdb->prepare(
            "SELECT data, COUNT(*) AS c FROM $t WHERE book_id=%d AND event_type='search' AND created_at BETWEEN %s AND %s GROUP BY data ORDER BY c DESC LIMIT 50",
            $book_id, $from_dt, $to_dt
        ), ARRAY_A );

        $daily = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) AS d, COUNT(DISTINCT session_id) AS s, SUM(event_type='page_view') AS v FROM $t WHERE book_id=%d AND created_at BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY d ASC",
            $book_id, $from_dt, $to_dt
        ), ARRAY_A );

        return [
            'counts'          => $counts,
            'unique_sessions' => $unique_sessions,
            'per_page'        => $per_page,
            'hotspots'        => $hotspots,
            'searches'        => $searches,
            'daily'           => $daily,
        ];
    }
}
