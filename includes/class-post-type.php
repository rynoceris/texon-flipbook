<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Texon_Flipbook_Post_Type {
    const TYPE = 'texon_flipbook';

    public static function register() {
        register_post_type( self::TYPE, [
            'label'        => 'Flipbooks',
            'public'       => false,
            'show_ui'      => false,
            'supports'     => [ 'title' ],
        ] );
    }

    public static function get_all() {
        return get_posts( [
            'post_type'      => self::TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    public static function get_data( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::TYPE ) return null;
        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'pdf_path'    => get_post_meta( $post->ID, '_pdf_path', true ),
            'pages_dir'   => get_post_meta( $post->ID, '_pages_dir', true ),
            'page_count'  => (int) get_post_meta( $post->ID, '_page_count', true ),
            'page_width'  => (int) get_post_meta( $post->ID, '_page_width', true ),
            'page_height' => (int) get_post_meta( $post->ID, '_page_height', true ),
            'hotspots'    => json_decode( get_post_meta( $post->ID, '_hotspots', true ) ?: '{}', true ) ?: [],
        ];
    }
}
