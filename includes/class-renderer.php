<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders PDF pages to JPGs.
 *
 * Backends (tried in order):
 *   1. Imagick (if extension is loaded and policy allows PDF)
 *   2. Ghostscript via whichever process-exec function is available
 *      (proc_open > shell_exec > exec > popen > passthru)
 *
 * Designed to render one page per request to stay within PHP/LVE
 * execution limits on shared hosting.
 */
class Texon_Flipbook_Renderer {

    public static function get_page_count( $pdf_path ) {
        $pdf_path = realpath( $pdf_path );
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            return new WP_Error( 'pdf_missing', 'PDF not found.' );
        }

        if ( self::imagick_available() ) {
            try {
                $im = new Imagick();
                $im->pingImage( $pdf_path );
                $n = $im->getNumberImages();
                $im->clear();
                if ( $n > 0 ) return (int) $n;
            } catch ( Exception $e ) {
                // fall through to gs
            }
        }

        $gs = self::find_gs();
        if ( $gs && self::has_process_exec() ) {
            $cmd = sprintf(
                '%s -q -dNODISPLAY -dNOSAFER -c "(%s) (r) file runpdfbegin pdfpagecount = quit" 2>&1',
                escapeshellcmd( $gs ),
                addcslashes( $pdf_path, '()\\' )
            );
            $res = self::run( $cmd );
            if ( $res['code'] === 0 ) {
                $first = trim( strtok( $res['out'], "\n" ) );
                if ( ctype_digit( $first ) ) return (int) $first;
            }
        }

        return new WP_Error( 'count_failed', 'Could not determine page count. Neither Imagick nor Ghostscript is usable in this PHP environment.' );
    }

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

        if ( self::imagick_available() ) {
            try {
                $im = new Imagick();
                $im->setResolution( $dpi, $dpi );
                $im->readImage( $pdf_path . '[' . ( $page - 1 ) . ']' );
                $im->setImageFormat( 'jpeg' );
                $im->setImageCompressionQuality( 85 );
                $im->setImageBackgroundColor( 'white' );
                $im = $im->flattenImages();
                $im->writeImage( $out_file );
                $im->clear();
                if ( file_exists( $out_file ) ) {
                    $size = getimagesize( $out_file );
                    if ( $size ) return [ 'width' => $size[0], 'height' => $size[1] ];
                }
            } catch ( Exception $e ) {
                // fall through to gs; Imagick policy often blocks PDF
                $imagick_err = $e->getMessage();
            }
        }

        $gs = self::find_gs();
        if ( $gs && self::has_process_exec() ) {
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
            $res = self::run( $cmd );
            if ( $res['code'] !== 0 || ! file_exists( $out_file ) ) {
                return new WP_Error( 'gs_failed', 'Ghostscript failed on page ' . $page . ': ' . $res['out'] );
            }
            $size = getimagesize( $out_file );
            if ( ! $size ) return new WP_Error( 'bad_output', 'Rendered file is not a valid image.' );
            return [ 'width' => $size[0], 'height' => $size[1] ];
        }

        $msg = 'No PDF rendering backend available. ';
        if ( isset( $imagick_err ) ) $msg .= 'Imagick error: ' . $imagick_err . '. ';
        $msg .= 'Process-exec functions may be disabled on this PHP configuration (disable_functions).';
        return new WP_Error( 'no_renderer', $msg );
    }

    public static function clear_output_dir( $output_dir ) {
        if ( ! is_dir( $output_dir ) ) return;
        foreach ( glob( $output_dir . '/page-*.jpg' ) as $f ) { @unlink( $f ); }
    }

    /**
     * Runs a shell command via the best available process-exec function.
     * Returns [ 'out' => string, 'code' => int ].
     */
    private static function run( $cmd ) {
        // proc_open — most flexible, often allowed when exec() isn't
        if ( function_exists( 'proc_open' ) ) {
            $desc = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
            $proc = @proc_open( $cmd, $desc, $pipes );
            if ( is_resource( $proc ) ) {
                $out = stream_get_contents( $pipes[1] );
                $err = stream_get_contents( $pipes[2] );
                fclose( $pipes[1] ); fclose( $pipes[2] );
                $code = proc_close( $proc );
                return [ 'out' => $out . $err, 'code' => (int) $code ];
            }
        }
        if ( function_exists( 'shell_exec' ) ) {
            $out = @shell_exec( $cmd );
            // shell_exec gives no exit code; treat non-empty output as success if expected
            return [ 'out' => (string) $out, 'code' => $out === null ? 1 : 0 ];
        }
        if ( function_exists( 'exec' ) ) {
            $lines = []; $code = 0;
            @call_user_func( 'exec', $cmd, $lines, $code );
            return [ 'out' => implode( "\n", $lines ), 'code' => (int) $code ];
        }
        if ( function_exists( 'passthru' ) ) {
            ob_start(); $code = 0;
            @call_user_func( 'passthru', $cmd, $code );
            return [ 'out' => ob_get_clean(), 'code' => (int) $code ];
        }
        return [ 'out' => 'No process-exec function available.', 'code' => 127 ];
    }

    private static function has_process_exec() {
        $disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
        foreach ( [ 'proc_open', 'shell_exec', 'exec', 'passthru' ] as $fn ) {
            if ( function_exists( $fn ) && ! in_array( $fn, $disabled, true ) ) return true;
        }
        return false;
    }

    private static function imagick_available() {
        if ( ! extension_loaded( 'imagick' ) ) return false;
        // Imagick policy.xml often blocks PDF — we can't easily detect that
        // without a probe, so we rely on try/catch at the call site.
        return true;
    }

    private static function find_gs() {
        foreach ( [ '/usr/bin/gs', '/usr/local/bin/gs' ] as $path ) {
            if ( is_executable( $path ) ) return $path;
        }
        return null;
    }
}
