<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Texon_Flipbook_Shortcode {

    public static function register() {
        add_shortcode( 'texon_flipbook', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ) {
        $atts = shortcode_atts( [
            'id'      => 0,
            'trigger' => 'inline',     // inline | button
            'label'   => 'View Catalog',
            'height'  => '820',        // inline height in px
        ], $atts, 'texon_flipbook' );

        $id = (int) $atts['id'];
        $data = Texon_Flipbook_Post_Type::get_data( $id );
        if ( ! $data || ! $data['page_count'] ) {
            return '<em>Flipbook not available.</em>';
        }

        wp_enqueue_script( 'texon-pageflip', TEXON_FLIPBOOK_URL . 'vendor/page-flip.browser.js', [], '2.0.7', true );
        wp_enqueue_script( 'texon-flipbook', TEXON_FLIPBOOK_URL . 'assets/flipbook.js', [ 'texon-pageflip' ], TEXON_FLIPBOOK_VERSION, true );
        wp_enqueue_style( 'texon-flipbook', TEXON_FLIPBOOK_URL . 'assets/flipbook.css', [], TEXON_FLIPBOOK_VERSION );

        $uploads = wp_upload_dir();
        $pages_url = str_replace( $uploads['basedir'], $uploads['baseurl'], $data['pages_dir'] );

        $config = [
            'pagesUrl'   => $pages_url,
            'pageCount'  => $data['page_count'],
            'pageWidth'  => $data['page_width'],
            'pageHeight' => $data['page_height'],
            'hotspots'   => (object) $data['hotspots'],
            'title'      => $data['title'],
        ];
        $config_json = esc_attr( wp_json_encode( $config ) );
        $uid = 'texon-fb-' . $id . '-' . wp_rand( 1000, 9999 );

        if ( $atts['trigger'] === 'button' ) {
            ob_start(); ?>
            <button class="texon-fb-open-btn" data-texon-fb-open="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $atts['label'] ); ?></button>
            <div class="texon-fb-modal" id="<?php echo esc_attr( $uid ); ?>" style="display:none;" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $data['title'] ); ?>">
                <div class="texon-fb-modal-backdrop" data-texon-fb-close="1"></div>
                <div class="texon-fb-modal-inner">
                    <button class="texon-fb-close" data-texon-fb-close="1" aria-label="Close">×</button>
                    <div class="texon-fb-inline">
                        <div class="texon-fb-viewer" data-texon-fb-config="<?php echo $config_json; ?>"></div>
                    </div>
                </div>
            </div>
            <?php return ob_get_clean();
        }

        ob_start(); ?>
        <div class="texon-fb-inline" style="height:<?php echo (int) $atts['height']; ?>px;">
            <div class="texon-fb-viewer" data-texon-fb-config="<?php echo $config_json; ?>"></div>
        </div>
        <?php return ob_get_clean();
    }
}
