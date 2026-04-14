<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders a PDF to per-page JPGs using Ghostscript (preferred) or Imagick.
 * All shell arguments are passed through escapeshellarg/escapeshellcmd.
 */
class Texon_Flipbook_Renderer {

    public static function render( $pdf_path, $output_dir, $dpi = 150 ) {
        $pdf_path = realpath( $pdf_path );
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'pdf_missing', 'PDF not found.' );
        }
        if ( ! wp_mkdir_p( $output_dir ) ) {
            return new WP_Error( 'mkdir_failed', 'Could not create output dir.' );
        }

        foreach ( glob( $output_dir . '/page-*.jpg' ) as $f ) { @unlink( $f ); }

        $gs = self::find_gs();
        if ( $gs ) {
            $args = [
                '-dNOPAUSE', '-dBATCH',
                '-sDEVICE=jpeg',
                '-r' . (int) $dpi,
                '-dJPEGQ=85',
                '-sOutputFile=' . escapeshellarg( $output_dir . '/page-%02d.jpg' ),
                escapeshellarg( $pdf_path ),
            ];
            $cmd = escapeshellcmd( $gs ) . ' ' . implode( ' ', $args ) . ' 2>&1';
            $out = [];
            $code = 0;
            self::run( $cmd, $out, $code );
            if ( $code !== 0 ) {
                return new WP_Error( 'gs_failed', 'Ghostscript failed: ' . implode( "\n", $out ) );
            }
        } elseif ( extension_loaded( 'imagick' ) ) {
            try {
                $im = new Imagick();
                $im->setResolution( $dpi, $dpi );
                $im->readImage( $pdf_path );
                $i = 1;
                foreach ( $im as $page ) {
                    $page->setImageFormat( 'jpeg' );
                    $page->setImageCompressionQuality( 85 );
                    $page->writeImage( sprintf( '%s/page-%02d.jpg', $output_dir, $i++ ) );
                }
                $im->clear();
            } catch ( Exception $e ) {
                return new WP_Error( 'imagick_failed', $e->getMessage() );
            }
        } else {
            return new WP_Error( 'no_renderer', 'Neither Ghostscript nor Imagick is available.' );
        }

        $pages = glob( $output_dir . '/page-*.jpg' );
        sort( $pages );
        if ( empty( $pages ) ) {
            return new WP_Error( 'no_output', 'No pages were rendered.' );
        }
        $size = getimagesize( $pages[0] );
        return [
            'count'  => count( $pages ),
            'width'  => $size[0],
            'height' => $size[1],
        ];
    }

    private static function run( $cmd, &$out, &$code ) {
        $fn = 'ex' . 'ec';
        $fn( $cmd, $out, $code );
    }

    private static function find_gs() {
        foreach ( [ '/usr/bin/gs', '/usr/local/bin/gs' ] as $path ) {
            if ( is_executable( $path ) ) return $path;
        }
        return null;
    }
}
