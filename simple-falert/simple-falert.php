<?php
/**
 * Plugin Name:       Simple Falert
 * Plugin URI:        https://ozkantasli.com/plugins/simple-falert/
 * Description:       Displays fake recent sales notifications for WooCommerce products to create social proof.
 * Version:           1.0.0
 * Author:            Özkan Taşlı
 * Author URI:        https://ozkantasli.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-falert
 * Domain Path:       /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.5
 * Requires PHP: 7.2
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Declare HPOS Compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Define plugin constants
define( 'SFALERT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFALERT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SFALERT_VERSION', '1.0.0' );

/**
 * Initializes the Simple Falert plugin after all plugins are loaded.
 */
function sfalert_initialize() {

    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'sfalert_woocommerce_missing_notice' );
        return;
    }

    // Load plugin textdomain
    load_plugin_textdomain(
        'simple-falert',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );

    // --- PATH TO ADMIN SETTINGS FILE (Using 'inc') ---
    $admin_settings_path = SFALERT_PLUGIN_DIR . 'inc/admin-settings.php';

    // Include the admin settings file
    if ( file_exists( $admin_settings_path ) ) {
        require_once $admin_settings_path;
    } else {
         add_action( 'admin_notices', function() use ($admin_settings_path) {
            echo '<div class="notice notice-error"><p>Simple Falert Error: Required file <code>' . esc_html(basename($admin_settings_path)) . '</code> is missing from the <code>' . esc_html(basename(dirname($admin_settings_path))) . '</code> folder. Please reinstall the plugin.</p></div>';
         });
        return;
    }

    /**
     * Main Simple Falert Class.
     */
    class Simple_Falert {

        private $options;

        public function __construct() {
            $this->options = get_option( 'sfalert_settings' );

            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
            add_action( 'wp_footer', [ $this, 'add_popup_container' ] );
            add_action( 'wp_ajax_nopriv_sfalert_get_notification_data', [ $this, 'ajax_get_notification_data' ] );
            add_action( 'wp_ajax_sfalert_get_notification_data', [ $this, 'ajax_get_notification_data' ] );

            // Instantiate Admin Settings Class ONLY in admin area
            if ( is_admin() ) {
                if ( class_exists('Simple_Falert_Admin_Settings') ) {
                    new Simple_Falert_Admin_Settings();
                } else {
                    add_action( 'admin_notices', function() {
                        $admin_folder_name = basename(dirname(SFALERT_PLUGIN_DIR . 'inc/admin-settings.php'));
                        echo '<div class="notice notice-error"><p>Simple Falert Error: Admin settings class could not be loaded. Check the <code>'.esc_html($admin_folder_name).'/admin-settings.php</code> file for errors.</p></div>';
                    });
                }
            }
        } // End __construct()

        /**
         * Enqueues assets and adds inline styles.
         */
        public function enqueue_frontend_assets() {
            if ( empty( $this->options['sfalert_enable'] ) || $this->options['sfalert_enable'] !== 'on' ) { return; }

            $style_handle = 'sfalert-style';
            wp_enqueue_style( $style_handle, SFALERT_PLUGIN_URL . 'assets/css/sfalert-style.css', [], SFALERT_VERSION );

            $bg_color = isset($this->options['sfalert_bg_color']) ? sanitize_hex_color($this->options['sfalert_bg_color']) : '#ffffff';
            $text_color = isset($this->options['sfalert_text_color']) ? sanitize_hex_color($this->options['sfalert_text_color']) : '#4a5568';
            $custom_css = ":root {";
            if ( $bg_color ) { $custom_css .= "--sf-bg-color: " . esc_attr($bg_color) . ";"; }
            if ( $text_color ) { $custom_css .= "--sf-text-color: " . esc_attr($text_color) . ";"; }
            $custom_css .= "}";
            wp_add_inline_style( $style_handle, $custom_css );

            wp_enqueue_script( 'sfalert-script', SFALERT_PLUGIN_URL . 'assets/js/sfalert-script.js', [ 'jquery' ], SFALERT_VERSION, true );

            wp_localize_script(
                'sfalert-script',
                'sfalert_ajax_object',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'sfalert_notification_nonce' ),
                    'settings' => [
                        'display_duration' => !empty($this->options['sfalert_display_duration']) ? intval($this->options['sfalert_display_duration']) * 1000 : 5000,
                        'interval_time'    => !empty($this->options['sfalert_interval_time']) ? intval($this->options['sfalert_interval_time']) * 1000 : 10000,
                        'show_image'       => isset($this->options['sfalert_show_image']) && $this->options['sfalert_show_image'] === 'on',
                        'link_to_product'  => isset($this->options['sfalert_link_to_product']) && $this->options['sfalert_link_to_product'] === 'on',
                    ],
                    'i18n' => [
                        'closeButton' => __( 'Close Notification', 'simple-falert' ),
                        'closeTitle'  => __( 'Close', 'simple-falert' ),
                    ]
                ]
            );
        } // End enqueue_frontend_assets()

        /**
         * Adds the HTML container for the popup.
         */
        public function add_popup_container() {
             if ( empty( $this->options['sfalert_enable'] ) || $this->options['sfalert_enable'] !== 'on' ) { return; }
             $default_position = 'bottom-left';
             $selected_position = isset($this->options['sfalert_position']) ? $this->options['sfalert_position'] : $default_position;
             $allowed_positions = ['bottom-left', 'bottom-right'];
             if ( !in_array($selected_position, $allowed_positions, true) ) { $selected_position = $default_position; }
             $position_class = 'sfalert-position-' . $selected_position;
             echo '<div id="sfalert-popup-container" class="' . esc_attr($position_class) . '"></div>';
        } // End add_popup_container()

        /**
         * Handles the AJAX request to get notification data.
         */
        public function ajax_get_notification_data() {
            check_ajax_referer( 'sfalert_notification_nonce', 'nonce' );
            $this->options = get_option( 'sfalert_settings' );

            if ( empty( $this->options['sfalert_enable'] ) || $this->options['sfalert_enable'] !== 'on' ) {
                wp_send_json_error( [ 'message' => __( 'Simple Falert is not active.', 'simple-falert' ) ] );
            }

            // Fetch Products (Warnings about tax_query/meta_query are noted but acceptable for now)
             $args = [
                'post_type'      => 'product', 'post_status' => 'publish', 'posts_per_page' => 100,
                'orderby' => 'rand', 'fields' => 'ids',
                'tax_query' => [ 'relation' => 'AND',
                    [ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'exclude-from-catalog', 'operator' => 'NOT IN'],
                    [ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'outofstock', 'operator' => 'NOT IN']
                ],
                'meta_query' => [ ['key' => '_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'] ]
            ];
            $product_ids = get_posts( $args );
            if ( empty( $product_ids ) ) { wp_send_json_error( [ 'message' => __( 'No suitable products found to display.', 'simple-falert' ) ] ); }
            $random_index = empty($product_ids) ? 0 : wp_rand( 0, count( $product_ids ) - 1 );
            $random_product_id = $product_ids[ $random_index ];
            $product = wc_get_product( $random_product_id );
            if ( ! $product ) { wp_send_json_error( [ 'message' => __( 'Could not retrieve product data.', 'simple-falert' ) ] ); }

            // Get Random Data using wp_rand()
            $default_names = "John Doe\nJane Smith\nAlex Green\nSarah Connor";
            $names_str = !empty($this->options['sfalert_names']) ? $this->options['sfalert_names'] : $default_names;
            $names = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $names_str)));
            $random_name_index = empty($names) ? -1 : wp_rand( 0, count( $names ) - 1 );
            $random_name = ($random_name_index !== -1) ? $names[$random_name_index] : __( 'Someone', 'simple-falert' );

            $default_cities = "London\nNew York\nParis\nTokyo\nSydney";
            $cities_str = !empty($this->options['sfalert_cities']) ? $this->options['sfalert_cities'] : $default_cities;
            $cities = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cities_str)));
            $random_city_index = empty($cities) ? -1 : wp_rand( 0, count( $cities ) - 1 );
            $random_city = ($random_city_index !== -1) ? $cities[$random_city_index] : __( 'nearby', 'simple-falert' );

            $min_qty = max(1, !empty($this->options['sfalert_min_qty']) ? intval($this->options['sfalert_min_qty']) : 1);
            $max_qty = max($min_qty, !empty($this->options['sfalert_max_qty']) ? intval($this->options['sfalert_max_qty']) : 1);
            $random_qty = wp_rand($min_qty, $max_qty); // Use wp_rand

            $max_time_ago_hours = max(1, !empty($this->options['sfalert_max_time_ago']) ? intval($this->options['sfalert_max_time_ago']) : 12);
            $random_seconds_ago = wp_rand(30, $max_time_ago_hours * 3600); // Use wp_rand
            /* translators: %s: Human readable time difference (e.g. "5 minutes", "2 hours"). */
            $time_ago = sprintf( __( '%s ago', 'simple-falert' ), human_time_diff( time() - $random_seconds_ago, time() ) );

            // Build Message
            $default_template = __( '{name} from {city} purchased {qty} {product_name} {time_ago}.', 'simple-falert' );
            $message_template = !empty($this->options['sfalert_message_template']) ? $this->options['sfalert_message_template'] : $default_template;
            $product_name_html = '<strong>' . esc_html($product->get_name()) . '</strong>';
            $replacements = [
                '{name}' => esc_html($random_name), '{city}' => esc_html($random_city), '{qty}' => $random_qty,
                '{product_name}' => $product_name_html, '{time_ago}' => esc_html($time_ago)
            ];
            $message_raw = str_replace( array_keys($replacements), array_values($replacements), $message_template );
            $allowed_html = ['strong' => []];
            $message = wp_kses( $message_raw, $allowed_html );

            // Prepare JSON Data
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : null;
            $data = [
                'message'      => $message,
                'product_name' => $product->get_name(),
                'product_url'  => $product->get_permalink(),
                'image_url'    => $image_url ?: wc_placeholder_img_src('thumbnail'),
            ];

            // Send Data
            wp_send_json_success( $data );

        } // End ajax_get_notification_data()

    } // End class Simple_Falert

    // --- Instantiate the main class ---
    new Simple_Falert();

} // End sfalert_initialize()

/**
 * Displays an admin notice if WooCommerce is not active.
 */
function sfalert_woocommerce_missing_notice() {
    echo '<div class="notice notice-error is-dismissible"><p>';
    echo esc_html__( 'Simple Falert requires WooCommerce to be installed and activated to function properly.', 'simple-falert' );
    echo '</p></div>';
}

// Hook the initializer function to 'plugins_loaded'
add_action( 'plugins_loaded', 'sfalert_initialize' );

// --- END OF simple-falert.php ---