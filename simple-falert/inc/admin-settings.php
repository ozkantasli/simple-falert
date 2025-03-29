<?php
/**
 * Simple Falert Admin Settings Class
 * @package Simple_Falert
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Simple_Falert_Admin_Settings {

    private $options;
    private $settings_page_hook;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_plugin_page() {
        $this->settings_page_hook = add_submenu_page(
            'woocommerce',
            __( 'Simple Falert Settings', 'simple-falert' ),
            __( 'Simple Falert', 'simple-falert' ),
            'manage_woocommerce',
            'sfalert-settings',
            [ $this, 'create_admin_page' ]
        );
    }

    public function enqueue_admin_assets( $hook_suffix ) {
        if ( !isset($this->settings_page_hook) || $hook_suffix !== $this->settings_page_hook ) { return; }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'sfalert-admin-style', SFALERT_PLUGIN_URL . 'assets/css/sfalert-admin-style.css', [], SFALERT_VERSION );
        add_action( 'admin_print_footer_scripts', [ $this, 'initialize_color_pickers_script' ], 99 );
    }

    public function initialize_color_pickers_script() {
        global $hook_suffix;
        if ( ! isset($this->settings_page_hook) || $hook_suffix !== $this->settings_page_hook ) { return; }
        ?>
        <script type="text/javascript"> jQuery(document).ready(function($){ $('.sfalert-color-picker').wpColorPicker(); }); </script>
        <?php
    }

    public function create_admin_page() {
        $this->options = get_option( 'sfalert_settings' );
        ?>
        <div class="wrap sfalert-setting-admin">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields( 'sfalert_option_group' ); ?>
                <?php do_settings_sections( 'sfalert-settings' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting( 'sfalert_option_group', 'sfalert_settings', [ $this, 'sanitize_settings' ] ); // Warning about dynamic callback is noted, but function is implemented
        $page_slug = 'sfalert-settings';

        // Sections
        add_settings_section( 'sfalert_section_general', __( 'General Settings', 'simple-falert' ), null, $page_slug );
        add_settings_section( 'sfalert_section_content', __( 'Content Settings', 'simple-falert' ), null, $page_slug );
        add_settings_section( 'sfalert_section_timing', __( 'Appearance & Timing', 'simple-falert' ), null, $page_slug );

        // Fields - General
        add_settings_field( 'sfalert_enable', __( 'Enable Plugin', 'simple-falert' ), [ $this, 'render_enable_callback' ], $page_slug, 'sfalert_section_general' );
        // Fields - Content
        add_settings_field( 'sfalert_names', __( 'Name List (one per line)', 'simple-falert' ), [ $this, 'render_names_callback' ], $page_slug, 'sfalert_section_content' );
        add_settings_field( 'sfalert_cities', __( 'City List (one per line)', 'simple-falert' ), [ $this, 'render_cities_callback' ], $page_slug, 'sfalert_section_content' );
        add_settings_field( 'sfalert_min_qty', __( 'Minimum Quantity', 'simple-falert' ), [ $this, 'render_min_qty_callback' ], $page_slug, 'sfalert_section_content' );
        add_settings_field( 'sfalert_max_qty', __( 'Maximum Quantity', 'simple-falert' ), [ $this, 'render_max_qty_callback' ], $page_slug, 'sfalert_section_content' );
        add_settings_field( 'sfalert_max_time_ago', __( 'Maximum Time Ago (Hours)', 'simple-falert' ), [ $this, 'render_max_time_ago_callback' ], $page_slug, 'sfalert_section_content' );
        add_settings_field( 'sfalert_message_template', __( 'Message Template', 'simple-falert' ), [ $this, 'render_message_template_callback' ], $page_slug, 'sfalert_section_content' );
        // Fields - Appearance & Timing
        add_settings_field( 'sfalert_display_duration', __( 'Display Duration (Seconds)', 'simple-falert' ), [ $this, 'render_display_duration_callback' ], $page_slug, 'sfalert_section_timing' );
        add_settings_field( 'sfalert_interval_time', __( 'Interval Between Notifications (Seconds)', 'simple-falert' ), [ $this, 'render_interval_time_callback' ], $page_slug, 'sfalert_section_timing' );
        add_settings_field( 'sfalert_show_image', __( 'Show Product Image', 'simple-falert' ), [ $this, 'render_show_image_callback' ], $page_slug, 'sfalert_section_timing' );
        add_settings_field( 'sfalert_link_to_product', __( 'Link Notification to Product', 'simple-falert' ), [ $this, 'render_link_to_product_callback' ], $page_slug, 'sfalert_section_timing' );
        add_settings_field( 'sfalert_position', __( 'Popup Position', 'simple-falert' ), [ $this, 'render_position_callback' ], $page_slug, 'sfalert_section_timing' );
        add_settings_field( 'sfalert_bg_color', __( 'Background Color', 'simple-falert' ), [ $this, 'render_color_picker_callback' ], $page_slug, 'sfalert_section_timing', [ 'field_id' => 'sfalert_bg_color' ] );
        add_settings_field( 'sfalert_text_color', __( 'Text Color', 'simple-falert' ), [ $this, 'render_color_picker_callback' ], $page_slug, 'sfalert_section_timing', [ 'field_id' => 'sfalert_text_color' ] );
    }

    public function sanitize_settings( $input ) {
        $new_input = []; $defaults = $this->get_default_settings();
        $checkboxes = ['sfalert_enable', 'sfalert_show_image', 'sfalert_link_to_product'];
        foreach ( $checkboxes as $key ) { $new_input[$key] = ( isset( $input[$key] ) && $input[$key] === 'on' ) ? 'on' : 'off'; }
        if ( isset( $input['sfalert_names'] ) ) { $new_input['sfalert_names'] = sanitize_textarea_field( $input['sfalert_names'] ); } else { $new_input['sfalert_names'] = $defaults['sfalert_names']; }
        if ( isset( $input['sfalert_cities'] ) ) { $new_input['sfalert_cities'] = sanitize_textarea_field( $input['sfalert_cities'] ); } else { $new_input['sfalert_cities'] = $defaults['sfalert_cities']; }
        if ( isset( $input['sfalert_message_template'] ) ) { $allowed_html = [ 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'span' => ['class' => []] ]; $new_input['sfalert_message_template'] = wp_kses( $input['sfalert_message_template'], $allowed_html ); } else { $new_input['sfalert_message_template'] = $defaults['sfalert_message_template']; }
        $integers = ['sfalert_min_qty', 'sfalert_max_qty', 'sfalert_display_duration', 'sfalert_interval_time', 'sfalert_max_time_ago'];
        foreach ( $integers as $key ) { if ( isset( $input[$key] ) ) { $new_input[$key] = max( 1, absint( $input[$key] ) ); } else { $new_input[$key] = $defaults[$key]; } }
        $allowed_positions = ['bottom-left', 'bottom-right'];
        if ( isset( $input['sfalert_position'] ) && in_array( $input['sfalert_position'], $allowed_positions, true ) ) { $new_input['sfalert_position'] = $input['sfalert_position']; } else { $new_input['sfalert_position'] = $defaults['sfalert_position']; }
        $color_keys = ['sfalert_bg_color', 'sfalert_text_color'];
        foreach ( $color_keys as $key ) { if ( isset( $input[$key] ) ) { $color = sanitize_hex_color( $input[$key] ); $new_input[$key] = ! empty( $color ) ? $color : $defaults[$key]; } else { $new_input[$key] = $defaults[$key]; } }
        if ( isset( $new_input['sfalert_min_qty'], $new_input['sfalert_max_qty'] ) && $new_input['sfalert_min_qty'] > $new_input['sfalert_max_qty'] ) { $new_input['sfalert_max_qty'] = $new_input['sfalert_min_qty']; add_settings_error( 'sfalert_settings', 'qty_error', __( 'Maximum Quantity cannot be less than Minimum Quantity. It has been automatically adjusted.', 'simple-falert' ), 'warning' ); }
        return $new_input;
    }

    private function get_default_settings() {
        return [
            'sfalert_enable'           => 'on', 'sfalert_names' => "John Doe\nJane Smith\nAlex Green\nSarah Connor",
            'sfalert_cities'           => "London\nNew York\nParis\nTokyo\nSydney", 'sfalert_min_qty' => 1, 'sfalert_max_qty' => 1,
            'sfalert_max_time_ago'     => 12, 'sfalert_message_template' => __( '{name} from {city} purchased {qty} {product_name} {time_ago}.', 'simple-falert' ),
            'sfalert_display_duration' => 5, 'sfalert_interval_time' => 10, 'sfalert_show_image' => 'on', 'sfalert_link_to_product' => 'on',
            'sfalert_position'         => 'bottom-left', 'sfalert_bg_color' => '#ffffff', 'sfalert_text_color' => '#4a5568',
        ];
    }

    private function get_option_value( $key ) {
        if ( $this->options === null ) { $this->options = get_option( 'sfalert_settings', [] ); } $defaults = $this->get_default_settings();
        return isset( $this->options[$key] ) ? $this->options[$key] : ( isset( $defaults[$key] ) ? $defaults[$key] : null );
    }

    // --- Field Rendering Callbacks ---
    public function render_enable_callback() { printf( '<input type="checkbox" id="sfalert_enable" name="sfalert_settings[sfalert_enable]" value="on" %s />', checked( 'on', $this->get_option_value('sfalert_enable'), false ) ); printf( ' <label for="sfalert_enable">%s</label>', esc_html__( 'Yes, show fake sale notifications.', 'simple-falert' ) ); }
    public function render_names_callback() { printf( '<textarea id="sfalert_names" name="sfalert_settings[sfalert_names]" rows="5" class="large-text">%s</textarea>', esc_textarea( $this->get_option_value('sfalert_names') ) ); printf( '<p class="description">%s</p>', esc_html__( 'Enter one name per line.', 'simple-falert' ) ); }
    public function render_cities_callback() { printf( '<textarea id="sfalert_cities" name="sfalert_settings[sfalert_cities]" rows="5" class="large-text">%s</textarea>', esc_textarea( $this->get_option_value('sfalert_cities') ) ); printf( '<p class="description">%s</p>', esc_html__( 'Enter one city per line.', 'simple-falert' ) ); }
    public function render_min_qty_callback() { printf( '<input type="number" id="sfalert_min_qty" name="sfalert_settings[sfalert_min_qty]" value="%s" min="1" step="1" class="small-text" />', esc_attr( $this->get_option_value('sfalert_min_qty') ) ); }
    public function render_max_qty_callback() { printf( '<input type="number" id="sfalert_max_qty" name="sfalert_settings[sfalert_max_qty]" value="%s" min="1" step="1" class="small-text" />', esc_attr( $this->get_option_value('sfalert_max_qty') ) ); printf( '<p class="description">%s</p>', esc_html__( 'Should not be less than Minimum Quantity.', 'simple-falert' ) ); }
    public function render_display_duration_callback() { printf( '<input type="number" id="sfalert_display_duration" name="sfalert_settings[sfalert_display_duration]" value="%s" min="1" step="1" class="small-text" /> %s', esc_attr( $this->get_option_value('sfalert_display_duration') ), esc_html__( 'Seconds', 'simple-falert' ) ); printf( '<p class="description">%s</p>', esc_html__( 'How long each notification stays visible.', 'simple-falert' ) ); }
    public function render_interval_time_callback() { printf( '<input type="number" id="sfalert_interval_time" name="sfalert_settings[sfalert_interval_time]" value="%s" min="1" step="1" class="small-text" /> %s', esc_attr( $this->get_option_value('sfalert_interval_time') ), esc_html__( 'Seconds', 'simple-falert' ) ); printf( '<p class="description">%s</p>', esc_html__( 'The pause between showing notifications.', 'simple-falert' ) ); }
    public function render_max_time_ago_callback() { printf( '<input type="number" id="sfalert_max_time_ago" name="sfalert_settings[sfalert_max_time_ago]" value="%s" min="1" step="1" class="small-text" /> %s', esc_attr( $this->get_option_value('sfalert_max_time_ago') ), esc_html__( 'Hours', 'simple-falert' ) ); printf( '<p class="description">%s</p>', esc_html__( 'Maximum limit for the random "X time ago" display.', 'simple-falert' ) ); }
    public function render_message_template_callback() {
        printf( '<textarea id="sfalert_message_template" name="sfalert_settings[sfalert_message_template]" rows="3" class="large-text">%s</textarea>', esc_textarea( $this->get_option_value('sfalert_message_template') ) );
        /* translators: %1$s: {name}, %2$s: {city}, %3$s: {qty}, %4$s: {product_name}, %5$s: {time_ago} */
        printf( '<p class="description">%s<br>%s</p>', sprintf( esc_html__( 'Available variables: %1$s, %2$s, %3$s, %4$s, %5$s.', 'simple-falert' ), '<code>{name}</code>', '<code>{city}</code>', '<code>{qty}</code>', '<code>{product_name}</code>', '<code>{time_ago}</code>' ), esc_html__( 'Product name will be automatically bolded.', 'simple-falert' ) );
    }
    public function render_show_image_callback() { printf( '<input type="checkbox" id="sfalert_show_image" name="sfalert_settings[sfalert_show_image]" value="on" %s />', checked( 'on', $this->get_option_value('sfalert_show_image'), false ) ); printf( ' <label for="sfalert_show_image">%s</label>', esc_html__( 'Yes, show the product thumbnail in the notification.', 'simple-falert' ) ); }
    public function render_link_to_product_callback() { printf( '<input type="checkbox" id="sfalert_link_to_product" name="sfalert_settings[sfalert_link_to_product]" value="on" %s />', checked( 'on', $this->get_option_value('sfalert_link_to_product'), false ) ); printf( ' <label for="sfalert_link_to_product">%s</label>', esc_html__( 'Yes, link the notification to the corresponding product page.', 'simple-falert' ) ); }
    public function render_position_callback() { $current_position = $this->get_option_value('sfalert_position'); $positions = [ 'bottom-left' => __( 'Bottom Left', 'simple-falert' ), 'bottom-right' => __( 'Bottom Right', 'simple-falert' ), ]; ?> <select id="sfalert_position" name="sfalert_settings[sfalert_position]"><?php foreach ( $positions as $value => $label ) : ?> <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_position, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?> </select> <p class="description"><?php esc_html_e( 'Select where the notification popup should appear on the screen.', 'simple-falert' ); ?></p> <?php }
    public function render_color_picker_callback( $args ) { if ( ! isset( $args['field_id'] ) ) { return; } $field_id = $args['field_id']; $value = $this->get_option_value( $field_id ); printf( '<input type="text" id="%1$s" name="sfalert_settings[%1$s]" value="%2$s" class="sfalert-color-picker" data-default-color="%3$s" />', esc_attr( $field_id ), esc_attr( $value ), esc_attr( $this->get_default_settings()[$field_id] ?? '' ) ); }
    // render_animation_select_callback() function REMOVED

} // End class Simple_Falert_Admin_Settings