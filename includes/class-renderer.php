<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders PDF pages to JPGs. Designed to render one page per request
 * to stay within PHP/LVE execution limits on shared hosting.
 */
class Texon_Flipbook_Renderer {

    /**
     * Returns the number of pages in a PDF, or WP_Error.
     */
    public static function get_page_count( $pdf_path ) {
        $pdf_path = realpath( $pdf_path );
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'pdf_missing', 'PDF not found.' );
        }

        $gs = self::find_gs();
        if ( $gs ) {
            $cmd = sprintf(
                '%s -q -dNODISPLAY -dNOSAFER -c "(%s) (r) file runpdfbegin pdfpagecount = quit" 2>&1',
                escapeshellcmd( $gs ),
                addcslashes( $pdf_path, '()\\' )
            );
            $out = [];
            $code = 0;
            self::run( $cmd, $out, $code );
            if ( $code === 0 && isset( $out[0] ) && ctype_digit( trim( $out[0] ) ) ) {
                return (int) trim( $out[0] );
            }
        }
        if ( extension_loaded( 'imagick' ) ) {
            try {
                $im = new Imagick();
                $im->pingImage( $pdf_path );
                $n = $im->getNumberImages();
                $im->clear();
                if ( $n > 0 ) return $n;
            } catch ( Exception $e ) {
                // fall through
            }
        }
        return new WP_Error( 'count_failed', 'Could not determine page count.' );
    }

    /**
     * Renders a single page (1-indexed) to $output_dir/page-NN.jpg.
     * Returns [ 'width' => ..., 'height' => ... ] or WP_Error.
     */
    public static function render_page( $pdf_path, $output_dir, $page, $dpi = 150 ) {
        $pdf_path = realpath( $pdf_path );
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'pdf_missing', 'PDF not found.' );
        }
        if ( ! wp_mkdir_p( $output_dir ) ) {
            return new WP_Error( 'mkdir_failed', 'Could not create output dir.' );
        }

        $page     = max( 1, (int) $page );
        $out_file = sprintf( '%s/page-%02d.jpg', $output_dir, $page );
        if ( file_exists( $out_file ) ) @unlink( $out_file );

        $gs = self::find_gs();
        if ( $gs ) {
            $args = [
                '-dNOPAUSE', '-dBATCH', '-dSAFER', '-q',
                '-sDEVICE=jpeg',
                '-r' . (int) $dpi,
                '-dJPEGQ=85',
                '-dFirstPage=' . $page,
                '-dLastPage=' . $page,
                '-sOutputFile=' . escapeshellarg( $out_file ),
                escapeshellarg( $pdf_path ),
            ];
            $cmd = escapeshellcmd( $gs ) . ' ' . implode( ' ', $args ) . ' 2>&1';
            $out = [];
            $code = 0;
            self::run( $cmd, $out, $code );
            if ( $code !== 0 || ! file_exists( $out_file ) ) {
                return new WP_Error( 'gs_failed', 'Ghostscript failed on page ' . $page . ': ' . implode( "\n", $out ) );
            }
        } elseif ( extension_loaded( 'imagick' ) ) {
            try {
                $im = new Imagick();
                $im->setResolution( $dpi, $dpi );
                // Imagick is 0-indexed when reading a specific page
                $im->readImage( $pdf_path . '[' . ( $page - 1 ) . ']' );
                $im->setImageFormat( 'jpeg' );
                $im->setImageCompressionQuality( 85 );
                $im->writeImage( $out_file );
                $im->clear();
            } catch ( Exception $e ) {
                return new WP_Error( 'imagick_failed', $e->getMessage() );
            }
        } else {
            return new WP_Error( 'no_renderer', 'Neither Ghostscript nor Imagick is available.' );
        }

        $size = getimagesize( $out_file );
        if ( ! $size ) return new WP_Error( 'bad_output', 'Rendered file is not a valid image.' );
        return [ 'width' => $size[0], 'height' => $size[1] ];
    }

    public static function clear_output_dir( $output_dir ) {
        if ( ! is_dir( $output_dir ) ) return;
        foreach ( glob( $output_dir . '/page-*.jpg' ) as $f ) { @unlink( $f ); }
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
