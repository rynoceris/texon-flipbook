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
        $pdf_url   = self::path_to_url( $data['pdf_path'] );
        $text_url  = $pages_url . '/text.json';

        $settings = Texon_Flipbook_Settings::get();
        $config = [
            'pagesUrl'   => $pages_url,
            'pageCount'  => $data['page_count'],
            'pageWidth'  => $data['page_width'],
            'pageHeight' => $data['page_height'],
            'hotspots'   => (object) $data['hotspots'],
            'title'      => $data['title'],
            'pdfUrl'     => $pdf_url,
            'textUrl'    => $text_url,
            'bookId'     => (int) $id,
            'track'      => [
                'enabled'  => ! empty( $settings['tracking_enabled'] ),
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'dataLayer' => ! empty( $settings['push_to_datalayer'] ),
            ],
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

    private static function path_to_url( $path ) {
        if ( ! $path ) return '';
        $uploads = wp_upload_dir();
        $candidates = [
            [ $uploads['basedir'], $uploads['baseurl'] ],
            [ WP_CONTENT_DIR,      content_url() ],
            [ ABSPATH,             site_url() ],
        ];
        $path_real = realpath( $path ) ?: $path;
        foreach ( $candidates as $c ) {
            list( $dir, $url ) = $c;
            $dir_real = realpath( $dir ) ?: $dir;
            if ( strpos( $path_real, $dir_real ) === 0 ) {
                return rtrim( $url, '/' ) . str_replace( '\\', '/', substr( $path_real, strlen( $dir_real ) ) );
            }
        }
        return '';
    }
}
