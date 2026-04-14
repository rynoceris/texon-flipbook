<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Texon_Flipbook_Admin {

    public static function menu() {
        add_menu_page(
            'Flipbooks', 'Flipbooks', 'manage_options',
            'texon-flipbook', [ __CLASS__, 'screen_router' ],
            'dashicons-book-alt', 26
        );
        add_submenu_page(
            'texon-flipbook', 'Flipbook Settings', 'Settings', 'manage_options',
            'texon-flipbook-settings', [ 'Texon_Flipbook_Settings', 'screen' ]
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
        if ( $action === 'edit' )          self::screen_edit();
        elseif ( $action === 'hotspots' )  self::screen_hotspots();
        elseif ( $action === 'render' )    self::screen_render();
        elseif ( $action === 'analytics' ) self::screen_analytics();
        elseif ( $action === 'new' )       self::screen_edit();
        else                               self::screen_list();
    }

    public static function screen_list() {
        $books = Texon_Flipbook_Post_Type::get_all();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Flipbooks</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=new' ) ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            <?php if ( ! empty( $_GET['deleted'] ) ): ?>
                <div class="notice notice-success is-dismissible"><p>Flipbook deleted.</p></div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped texon-flipbook-list">
                <thead><tr><th class="texon-thumb-col">Cover</th><th>Title</th><th>Pages</th><th>Shortcodes</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ( ! $books ): ?>
                    <tr><td colspan="5">No flipbooks yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=new' ) ); ?>">Create one</a>.</td></tr>
                <?php endif; ?>
                <?php
                $uploads = wp_upload_dir();
                foreach ( $books as $b ):
                    $d = Texon_Flipbook_Post_Type::get_data( $b->ID );
                    $thumb_url = '';
                    if ( $d['pages_dir'] && file_exists( $d['pages_dir'] . '/page-01.jpg' ) ) {
                        $thumb_url = str_replace( $uploads['basedir'], $uploads['baseurl'], $d['pages_dir'] ) . '/page-01.jpg';
                    }
                    $edit_url = admin_url( 'admin.php?page=texon-flipbook&action=edit&id=' . $d['id'] );
                    ?>
                    <tr>
                        <td class="texon-thumb-col">
                            <a href="<?php echo esc_url( $edit_url ); ?>" class="texon-thumb">
                                <?php if ( $thumb_url ): ?>
                                    <img src="<?php echo esc_url( $thumb_url ); ?>" alt="Cover of <?php echo esc_attr( $d['title'] ); ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="texon-thumb-placeholder" aria-label="No cover rendered">—</span>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $d['title'] ); ?></a></strong></td>
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
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=texon-flipbook&action=analytics&id=' . $d['id'] ) ); ?>">Analytics</a> |
                            <?php endif; ?>
                            <?php
                                $confirm_msg = sprintf(
                                    'Delete "%s"? This will permanently remove the flipbook, its %d rendered page images, all hotspots, and its analytics history. The source PDF will not be affected.',
                                    esc_js( $d['title'] ),
                                    (int) $d['page_count']
                                );
                            ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=texon_flipbook_delete&id=' . $d['id'] ), 'texon_flipbook_delete_' . $d['id'] ) ); ?>" onclick="return confirm(<?php echo wp_json_encode( $confirm_msg ); ?>)" style="color:#b32d2e">Delete</a>
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

    public static function screen_analytics() {
        $id = (int) ( $_GET['id'] ?? 0 );
        $data = Texon_Flipbook_Post_Type::get_data( $id );
        if ( ! $data ) { echo '<div class="wrap"><p>Flipbook not found.</p></div>'; return; }

        $default_from = gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
        $default_to   = gmdate( 'Y-m-d' );
        $from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : $default_from;
        $to   = isset( $_GET['to'] )   ? sanitize_text_field( $_GET['to'] )   : $default_to;
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) $from = $default_from;
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) )   $to   = $default_to;

        $stats = Texon_Flipbook_Events::summary( $id, $from, $to );
        $counts = $stats['counts'];
        $page_views = $counts['page_view'] ?? 0;
        $sessions   = $counts['session_start'] ?? 0;
        $unique     = $stats['unique_sessions'];
        $pages_per_session = $unique ? round( $page_views / $unique, 1 ) : 0;

        // Build per-page view map
        $page_map = [];
        foreach ( $stats['per_page'] as $r ) $page_map[ (int) $r['page'] ] = (int) $r['c'];
        $max_page_views = 0;
        for ( $p = 1; $p <= (int) $data['page_count']; $p++ ) {
            if ( ( $page_map[ $p ] ?? 0 ) > $max_page_views ) $max_page_views = $page_map[ $p ];
        }

        ?>
        <div class="wrap texon-analytics">
            <h1>Analytics: <?php echo esc_html( $data['title'] ); ?></h1>
            <form method="get" class="texon-analytics-filter">
                <input type="hidden" name="page" value="texon-flipbook">
                <input type="hidden" name="action" value="analytics">
                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                <label>From <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
                <label>To <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
                <button type="submit" class="button">Apply</button>
                <span class="texon-range-presets">
                    <?php foreach ( [ 7 => '7d', 30 => '30d', 90 => '90d', 180 => '180d' ] as $n => $label ): ?>
                        <a class="button button-small" href="<?php echo esc_url( add_query_arg( [ 'from' => gmdate( 'Y-m-d', time() - $n * DAY_IN_SECONDS ), 'to' => $default_to ] ) ); ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </span>
            </form>

            <div class="texon-analytics-cards">
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $sessions ); ?></span><span class="l">Sessions</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $unique ); ?></span><span class="l">Unique visitors</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $page_views ); ?></span><span class="l">Page views</span></div>
                <div class="texon-card"><span class="n"><?php echo esc_html( $pages_per_session ); ?></span><span class="l">Pages / session</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $counts['hotspot_click'] ?? 0 ); ?></span><span class="l">Hotspot clicks</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $counts['search'] ?? 0 ); ?></span><span class="l">Searches</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( ( $counts['download'] ?? 0 ) + ( $counts['print'] ?? 0 ) + ( $counts['share'] ?? 0 ) ); ?></span><span class="l">Downloads / print / share</span></div>
                <div class="texon-card"><span class="n"><?php echo number_format_i18n( $counts['fullscreen'] ?? 0 ); ?></span><span class="l">Fullscreen opens</span></div>
            </div>

            <h2>Views per page</h2>
            <div class="texon-bar-chart">
                <?php for ( $p = 1; $p <= (int) $data['page_count']; $p++ ):
                    $v = $page_map[ $p ] ?? 0;
                    $h = $max_page_views ? round( $v / $max_page_views * 100 ) : 0; ?>
                    <div class="texon-bar" title="Page <?php echo $p; ?>: <?php echo $v; ?> views">
                        <div class="texon-bar-fill" style="height:<?php echo $h; ?>%;"><span><?php echo $v ?: ''; ?></span></div>
                        <div class="texon-bar-label"><?php echo $p; ?></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="texon-analytics-grid">
                <div>
                    <h2>Top hotspot clicks</h2>
                    <?php if ( $stats['hotspots'] ): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th>Page</th><th>Destination</th><th class="c">Clicks</th></tr></thead>
                            <tbody>
                            <?php foreach ( $stats['hotspots'] as $r ):
                                $info = json_decode( $r['data'] ?: '{}', true ) ?: [];
                                $url = $info['url'] ?? '';
                                $label = $info['label'] ?? ''; ?>
                                <tr>
                                    <td><?php echo (int) $r['page']; ?></td>
                                    <td><?php if ( $url ): ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ?: $url ); ?></a><?php else: ?>—<?php endif; ?></td>
                                    <td class="c"><?php echo number_format_i18n( $r['c'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?><p><em>No hotspot clicks in this range.</em></p><?php endif; ?>
                </div>

                <div>
                    <h2>Top searches</h2>
                    <?php if ( $stats['searches'] ): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th>Query</th><th class="c">Searches</th></tr></thead>
                            <tbody>
                            <?php foreach ( $stats['searches'] as $r ):
                                $info = json_decode( $r['data'] ?: '{}', true ) ?: [];
                                $q = $info['q'] ?? ''; if ( $q === '' ) continue; ?>
                                <tr><td><?php echo esc_html( $q ); ?></td><td class="c"><?php echo number_format_i18n( $r['c'] ); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?><p><em>No searches in this range.</em></p><?php endif; ?>
                </div>
            </div>

            <?php if ( $stats['daily'] ): ?>
                <h2>Daily activity</h2>
                <div class="texon-bar-chart daily">
                    <?php
                    $max_daily = 0;
                    foreach ( $stats['daily'] as $d ) if ( (int) $d['v'] > $max_daily ) $max_daily = (int) $d['v'];
                    foreach ( $stats['daily'] as $d ):
                        $v = (int) $d['v']; $s = (int) $d['s'];
                        $h = $max_daily ? round( $v / $max_daily * 100 ) : 0;
                    ?>
                        <div class="texon-bar" title="<?php echo esc_attr( $d['d'] ); ?>: <?php echo $v; ?> views, <?php echo $s; ?> sessions">
                            <div class="texon-bar-fill" style="height:<?php echo $h; ?>%;"></div>
                            <div class="texon-bar-label"><?php echo esc_html( substr( $d['d'], 5 ) ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

        // Remove rendered page images + text.json
        $pages_dir = get_post_meta( $id, '_pages_dir', true );
        if ( $pages_dir && is_dir( $pages_dir ) && strpos( $pages_dir, '/texon-flipbook/' ) !== false ) {
            foreach ( glob( $pages_dir . '/*' ) as $f ) @unlink( $f );
            @rmdir( $pages_dir );
        }

        // Remove analytics events for this flipbook
        global $wpdb;
        $wpdb->delete( Texon_Flipbook_Events::table_name(), [ 'book_id' => $id ], [ '%d' ] );

        // Remove the post (and its post_meta) — does not touch the source PDF
        wp_delete_post( $id, true );

        wp_safe_redirect( admin_url( 'admin.php?page=texon-flipbook&deleted=1' ) );
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

        // Extract text for search (best-effort; don't fail the render if this fails)
        $text = Texon_Flipbook_Renderer::extract_text_page( $pdf_path, $page );
        if ( is_wp_error( $text ) ) $text = '';
        $idx_file = $dir . '/text.json';
        $index = [];
        if ( $page > 1 && file_exists( $idx_file ) ) {
            $decoded = json_decode( (string) file_get_contents( $idx_file ), true );
            if ( is_array( $decoded ) ) $index = $decoded;
        }
        $index[ (string) $page ] = (string) $text;
        @file_put_contents( $idx_file, wp_json_encode( $index ) );

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
