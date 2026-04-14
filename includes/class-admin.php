<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Texon_Flipbook_Admin {

    public static function menu() {
        add_menu_page(
            'Flipbooks', 'Flipbooks', 'manage_options',
            'texon-flipbook', [ __CLASS__, 'screen_router' ],
            'dashicons-book-alt', 26
        );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'texon-flipbook' ) === false ) return;
        wp_enqueue_media();
        wp_enqueue_style( 'texon-flipbook-admin', TEXON_FLIPBOOK_URL . 'assets/admin.css', [], TEXON_FLIPBOOK_VERSION );
        wp_enqueue_script( 'texon-flipbook-admin', TEXON_FLIPBOOK_URL . 'assets/admin.js', [ 'jquery' ], TEXON_FLIPBOOK_VERSION, true );
        wp_localize_script( 'texon-flipbook-admin', 'TexonFlipbookAdmin', [
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'texon_flipbook_hotspots' ),
            'render_nonce'  => wp_create_nonce( 'texon_flipbook_render' ),
        ] );
    }

    public static function screen_router() {
        $action = $_GET['action'] ?? 'list';
        if ( $action === 'edit' )         self::screen_edit();
        elseif ( $action === 'hotspots' ) self::screen_hotspots();
        elseif ( $action === 'render' )   self::screen_render();
        elseif ( $action === 'new' )      self::screen_edit();
        else                              self::screen_list();
    }

    public static function screen_list() {
        $books = Texon_Flipbook_Post_Type::get_all();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Flipbooks</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=new' ) ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Title</th><th>Pages</th><th>Shortcodes</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ( ! $books ): ?>
                    <tr><td colspan="4">No flipbooks yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=new' ) ); ?>">Create one</a>.</td></tr>
                <?php endif; ?>
                <?php foreach ( $books as $b ): $d = Texon_Flipbook_Post_Type::get_data( $b->ID ); ?>
                    <tr>
                        <td><strong><?php echo esc_html( $d['title'] ); ?></strong></td>
                        <td><?php echo (int) $d['page_count']; ?></td>
                        <td>
                            <?php if ( $d['page_count'] ): ?>
                                <code>[texon_flipbook id="<?php echo (int) $d['id']; ?>"]</code><br>
                                <code>[texon_flipbook id="<?php echo (int) $d['id']; ?>" trigger="button" label="View Catalog"]</code>
                            <?php else: ?>
                                <em>Not rendered yet</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=edit&id=' . $d['id'] ) ); ?>">Edit</a> |
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=render&id=' . $d['id'] ) ); ?>">Render</a> |
                            <?php if ( $d['page_count'] ): ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=hotspots&id=' . $d['id'] ) ); ?>">Hotspots</a> |
                            <?php endif; ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=texon_flipbook_delete&id=' . $d['id'] ), 'texon_flipbook_delete_' . $d['id'] ) ); ?>" onclick="return confirm('Delete this flipbook?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function screen_edit() {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        $data = $id ? Texon_Flipbook_Post_Type::get_data( $id ) : null;
        ?>
        <div class="wrap">
            <h1><?php echo $data ? 'Edit Flipbook' : 'New Flipbook'; ?></h1>
            <?php if ( ! empty( $_GET['saved'] ) ): ?>
                <div class="notice notice-success"><p>Saved.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'texon_flipbook_save' ); ?>
                <input type="hidden" name="action" value="texon_flipbook_save">
                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                <table class="form-table">
                    <tr><th><label for="title">Title</label></th>
                        <td><input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $data['title'] ?? '' ); ?>" required></td></tr>
                    <tr><th><label for="pdf_path">PDF Path or URL</label></th>
                        <td>
                            <input type="text" id="pdf_path" name="pdf_path" class="large-text" value="<?php echo esc_attr( $data['pdf_path'] ?? '' ); ?>" placeholder="https://…/wp-content/uploads/catalog/your-catalog.pdf" required>
                            <button type="button" class="button" id="texon-pick-pdf">Choose from Media Library</button>
                            <p class="description">URL or filesystem path. URLs are auto-converted to paths on save.</p>
                        </td></tr>
                    <?php if ( $data ): ?>
                    <tr><th>Pages Rendered</th>
                        <td>
                            <?php if ( $data['page_count'] ): ?>
                                <?php echo (int) $data['page_count']; ?> pages at <?php echo (int) $data['page_width']; ?>×<?php echo (int) $data['page_height']; ?>px
                            <?php else: ?>
                                <em>Not yet rendered.</em>
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                </table>
                <p>
                    <button type="submit" class="button button-primary">Save</button>
                    <?php if ( $data ): ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=render&id=' . $id ) ); ?>" class="button"><?php echo $data['page_count'] ? 'Re-render Pages' : 'Render Pages'; ?></a>
                        <?php if ( $data['page_count'] ): ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=hotspots&id=' . $id ) ); ?>" class="button">Edit Hotspots →</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    public static function screen_render() {
        $id = (int) ( $_GET['id'] ?? 0 );
        $data = Texon_Flipbook_Post_Type::get_data( $id );
        if ( ! $data ) { echo '<div class="wrap"><p>Flipbook not found.</p></div>'; return; }
        if ( ! $data['pdf_path'] || ! file_exists( $data['pdf_path'] ) ) {
            echo '<div class="wrap"><h1>Render Pages</h1><div class="notice notice-error"><p>PDF not found at: <code>' . esc_html( $data['pdf_path'] ) . '</code></p></div></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>Rendering: <?php echo esc_html( $data['title'] ); ?></h1>
            <p>Source: <code><?php echo esc_html( $data['pdf_path'] ); ?></code></p>
            <div id="texon-render" data-book-id="<?php echo (int) $id; ?>">
                <div class="texon-render-bar"><div class="texon-render-bar-fill" style="width:0%"></div></div>
                <p class="texon-render-status">Preparing…</p>
                <pre class="texon-render-log" style="display:none"></pre>
                <p class="texon-render-done" style="display:none">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=hotspots&id=' . $id ) ); ?>" class="button button-primary">Edit Hotspots →</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=edit&id=' . $id ) ); ?>" class="button">Back to Flipbook</a>
                </p>
            </div>
        </div>
        <?php
    }

    public static function screen_hotspots() {
        $id = (int) ( $_GET['id'] ?? 0 );
        $data = Texon_Flipbook_Post_Type::get_data( $id );
        if ( ! $data || ! $data['page_count'] ) {
            echo '<div class="wrap"><p>Flipbook not found or pages not rendered yet.</p></div>';
            return;
        }
        $uploads = wp_upload_dir();
        $pages_url = str_replace( $uploads['basedir'], $uploads['baseurl'], $data['pages_dir'] );
        ?>
        <div class="wrap texon-hotspot-editor">
            <h1>Hotspots: <?php echo esc_html( $data['title'] ); ?></h1>
            <p class="description">Click and drag on a page to draw a hotspot. Click a hotspot to edit its URL. Right-click to delete. Hotspots are saved automatically.</p>
            <div class="texon-toolbar">
                <label>Page:
                    <select id="texon-page-select">
                        <?php for ( $i = 1; $i <= $data['page_count']; $i++ ): ?>
                            <option value="<?php echo $i; ?>">Page <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <span id="texon-save-status">Ready</span>
            </div>
            <div id="texon-page-canvas" data-book-id="<?php echo (int) $id; ?>"
                 data-pages-url="<?php echo esc_attr( $pages_url ); ?>"
                 data-page-count="<?php echo (int) $data['page_count']; ?>"
                 data-hotspots='<?php echo esc_attr( wp_json_encode( $data['hotspots'] ) ); ?>'>
            </div>
        </div>
        <?php
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'texon_flipbook_save' );
        $id = (int) ( $_POST['id'] ?? 0 );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $pdf_path = self::normalize_path( trim( $_POST['pdf_path'] ?? '' ) );

        $is_new = ! $id;
        if ( $id ) {
            wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
        } else {
            $id = wp_insert_post( [
                'post_type'   => Texon_Flipbook_Post_Type::TYPE,
                'post_status' => 'publish',
                'post_title'  => $title,
            ] );
        }
        $prev_pdf = get_post_meta( $id, '_pdf_path', true );
        update_post_meta( $id, '_pdf_path', $pdf_path );

        // If the PDF changed (or this is new), send the user to the render screen.
        if ( $pdf_path && ( $is_new || $pdf_path !== $prev_pdf ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=texon-flipbook&action=render&id=' . $id ) );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=texon-flipbook&action=edit&id=' . $id . '&saved=1' ) );
        exit;
    }

    public static function handle_delete() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'texon_flipbook_delete_' . $id );
        wp_delete_post( $id, true );
        wp_safe_redirect( admin_url( 'admin.php?page=texon-flipbook' ) );
        exit;
    }

    /**
     * AJAX: renders one page at a time.
     * First call (page=0) counts pages and clears output dir.
     * Subsequent calls render page 1..N and return progress.
     */
    public static function ajax_render_page() {
        check_ajax_referer( 'texon_flipbook_render', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'msg' => 'unauthorized' ] );

        $id   = (int) ( $_POST['id'] ?? 0 );
        $page = (int) ( $_POST['page'] ?? 0 );
        $data = Texon_Flipbook_Post_Type::get_data( $id );
        if ( ! $data ) wp_send_json_error( [ 'msg' => 'not_found' ] );

        $pdf_path = $data['pdf_path'];
        if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
            wp_send_json_error( [ 'msg' => 'PDF not found: ' . $pdf_path ] );
        }

        $uploads = wp_upload_dir();
        $slug = sanitize_title( get_the_title( $id ) ?: 'flipbook-' . $id );
        $dir  = $uploads['basedir'] . '/texon-flipbook/' . $slug . '-' . $id;

        if ( $page <= 0 ) {
            // Initialize: count pages and clear old output
            $count = Texon_Flipbook_Renderer::get_page_count( $pdf_path );
            if ( is_wp_error( $count ) ) {
                wp_send_json_error( [ 'msg' => $count->get_error_message() ] );
            }
            Texon_Flipbook_Renderer::clear_output_dir( $dir );
            update_post_meta( $id, '_pages_dir', $dir );
            update_post_meta( $id, '_page_count', 0 ); // reset until render completes
            wp_send_json_success( [ 'total' => (int) $count, 'next' => 1 ] );
        }

        $total = (int) ( $_POST['total'] ?? 0 );
        if ( $total <= 0 ) wp_send_json_error( [ 'msg' => 'bad_total' ] );

        $result = Texon_Flipbook_Renderer::render_page( $pdf_path, $dir, $page );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'msg' => $result->get_error_message() ] );
        }

        if ( $page === 1 ) {
            update_post_meta( $id, '_page_width',  $result['width'] );
            update_post_meta( $id, '_page_height', $result['height'] );
        }

        $done = ( $page >= $total );
        if ( $done ) {
            update_post_meta( $id, '_page_count', $total );
            update_post_meta( $id, '_pdf_path_rendered', $pdf_path );
        }

        wp_send_json_success( [
            'page'     => $page,
            'total'    => $total,
            'next'     => $done ? 0 : $page + 1,
            'width'    => $result['width'],
            'height'   => $result['height'],
            'done'     => $done,
        ] );
    }

    /**
     * Accepts either a filesystem path or a URL (full or relative) and returns a filesystem path.
     * Handles uploads URLs, site URLs, and /wp-content/... style paths.
     */
    private static function normalize_path( $input ) {
        if ( $input === '' ) return '';

        if ( $input[0] === '/' && strpos( $input, '://' ) === false && file_exists( $input ) ) {
            return $input;
        }

        $uploads = wp_upload_dir();
        $candidates = [
            [ $uploads['baseurl'], $uploads['basedir'] ],
            [ content_url(),       WP_CONTENT_DIR ],
            [ site_url(),          ABSPATH ],
            [ home_url(),          ABSPATH ],
        ];
        foreach ( $candidates as $c ) {
            list( $url, $dir ) = $c;
            $u = preg_replace( '#^https?:#', '', $url );
            $i = preg_replace( '#^https?:#', '', $input );
            if ( strpos( $i, $u ) === 0 ) {
                $rel = substr( $i, strlen( $u ) );
                $rel = ltrim( parse_url( $rel, PHP_URL_PATH ) ?: $rel, '/' );
                $path = rtrim( $dir, '/' ) . '/' . $rel;
                if ( file_exists( $path ) ) return $path;
            }
        }

        if ( $input[0] === '/' && strpos( $input, '://' ) === false ) {
            $path = rtrim( ABSPATH, '/' ) . $input;
            if ( file_exists( $path ) ) return $path;
        }

        return $input;
    }

    public static function ajax_save_hotspots() {
        check_ajax_referer( 'texon_flipbook_hotspots', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'unauthorized' );
        $id = (int) ( $_POST['id'] ?? 0 );
        $raw = wp_unslash( $_POST['hotspots'] ?? '{}' );
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) wp_send_json_error( 'bad_json' );
        $clean = [];
        foreach ( $decoded as $page => $spots ) {
            $page = (int) $page;
            if ( ! is_array( $spots ) ) continue;
            foreach ( $spots as $s ) {
                $clean[ $page ][] = [
                    'x'     => max( 0, min( 1, (float) ( $s['x'] ?? 0 ) ) ),
                    'y'     => max( 0, min( 1, (float) ( $s['y'] ?? 0 ) ) ),
                    'w'     => max( 0, min( 1, (float) ( $s['w'] ?? 0 ) ) ),
                    'h'     => max( 0, min( 1, (float) ( $s['h'] ?? 0 ) ) ),
                    'url'   => esc_url_raw( $s['url'] ?? '' ),
                    'label' => sanitize_text_field( $s['label'] ?? '' ),
                ];
            }
        }
        update_post_meta( $id, '_hotspots', wp_json_encode( $clean ) );
        wp_send_json_success();
    }
}
