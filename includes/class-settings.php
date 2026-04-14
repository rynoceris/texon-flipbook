<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Texon_Flipbook_Settings {

    const OPTION = 'texon_flipbook_settings';

    public static function defaults() {
        return [
            'tracking_enabled' => 1,
            'retention_days'   => 180,
            'push_to_datalayer' => 1,
        ];
    }

    public static function get() {
        $opts = get_option( self::OPTION, [] );
        return array_merge( self::defaults(), is_array( $opts ) ? $opts : [] );
    }

    public static function screen() {
        $saved = false;
        if ( isset( $_POST['texon_flipbook_settings_nonce'] ) && current_user_can( 'manage_options' )
             && wp_verify_nonce( $_POST['texon_flipbook_settings_nonce'], 'texon_flipbook_settings' ) ) {
            $new = [
                'tracking_enabled'  => empty( $_POST['tracking_enabled'] ) ? 0 : 1,
                'push_to_datalayer' => empty( $_POST['push_to_datalayer'] ) ? 0 : 1,
                'retention_days'    => max( 7, min( 3650, (int) ( $_POST['retention_days'] ?? 180 ) ) ),
            ];
            update_option( self::OPTION, $new );
            $saved = true;
        }

        $s = self::get();
        global $wpdb;
        $total_events = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Texon_Flipbook_Events::table_name() );
        ?>
        <div class="wrap">
            <h1>Flipbook Settings</h1>
            <?php if ( $saved ): ?><div class="notice notice-success"><p>Saved.</p></div><?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'texon_flipbook_settings', 'texon_flipbook_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tracking_enabled">Analytics tracking</label></th>
                        <td>
                            <label><input type="checkbox" id="tracking_enabled" name="tracking_enabled" value="1" <?php checked( $s['tracking_enabled'], 1 ); ?>> Log flipbook events to the local database</label>
                            <p class="description">Anonymous events (page views, hotspot clicks, searches, tool usage). Respects DNT and GPC browser signals.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="push_to_datalayer">Google Tag Manager</label></th>
                        <td>
                            <label><input type="checkbox" id="push_to_datalayer" name="push_to_datalayer" value="1" <?php checked( $s['push_to_datalayer'], 1 ); ?>> Also push events to <code>window.dataLayer</code></label>
                            <p class="description">So your events show up alongside the rest of your GA4/GTM tracking.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="retention_days">Retention</label></th>
                        <td>
                            <input type="number" id="retention_days" name="retention_days" value="<?php echo (int) $s['retention_days']; ?>" min="7" max="3650" class="small-text"> days
                            <p class="description">Events older than this are deleted by a daily cron. Currently storing <?php echo number_format_i18n( $total_events ); ?> events.</p>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Save Settings</button></p>
            </form>
        </div>
        <?php
    }
}
