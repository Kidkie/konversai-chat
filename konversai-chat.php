<?php
/**
 * Plugin Name: KonversAI Chat
 * Plugin URI: https://bygggrossen.se
 * Description: Adds the KonversAI chat bubble widget to the site footer.
 * Version: 1.0.0
 * Author: Byggrossen
 * Text Domain: konversai-chat
 *
 * @package KonversAIChat
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('KONVERSAI_CHAT_OPTION', 'konversai_chat_settings');

// The script source is fixed in code, not editable in the admin, so site admins
// without unfiltered_html cannot load arbitrary JS. Override in wp-config.php if needed.
if (!defined('KONVERSAI_CHAT_API_BASE')) {
    define('KONVERSAI_CHAT_API_BASE', 'https://app.prod.konversai.no');
}

/**
 * Position choices. The CSS is fixed here so admins never enter CSS themselves.
 * The widget's own default is 20px from the bottom right corner.
 */
function konversai_chat_position_choices() {
    return array(
        'side'   => array(
            'right' => array('label' => __('Bottom right', 'konversai-chat'), 'css' => ''),
            'left'  => array('label' => __('Bottom left', 'konversai-chat'), 'css' => 'left:20px !important;right:auto !important;'),
        ),
        'height' => array(
            'default' => array('label' => __('Standard (20px from the bottom)', 'konversai-chat'), 'css' => ''),
            'raised'  => array('label' => __('Raised (about 60px from the bottom)', 'konversai-chat'), 'css' => 'bottom:calc(20px + 3em) !important;'),
            'high'    => array('label' => __('High (about 100px from the bottom)', 'konversai-chat'), 'css' => 'bottom:calc(20px + 6em) !important;'),
        ),
    );
}

/**
 * Get plugin settings merged with defaults
 */
function konversai_chat_get_settings() {
    $settings = get_option(KONVERSAI_CHAT_OPTION, array());
    $settings = is_array($settings) ? $settings : array();

    // Earlier versions stored a CSS bottom offset, where empty meant the widget default
    if (!isset($settings['height']) && isset($settings['bottom']) && $settings['bottom'] === '') {
        $settings['height'] = 'default';
    }

    $settings = wp_parse_args($settings, array(
        'enabled'    => true,
        'tenant_key' => '',
        'side'       => 'right',
        'height'     => 'raised',
    ));

    // Values may have been written outside the settings form, so force known values
    $choices = konversai_chat_position_choices();

    return array(
        'enabled'    => (bool) $settings['enabled'],
        'tenant_key' => is_scalar($settings['tenant_key']) ? trim((string) $settings['tenant_key']) : '',
        'side'       => is_string($settings['side']) && isset($choices['side'][$settings['side']]) ? $settings['side'] : 'right',
        'height'     => is_string($settings['height']) && isset($choices['height'][$settings['height']]) ? $settings['height'] : 'raised',
    );
}

/**
 * Get the validated API base URL, or an empty string if it is not an https URL
 */
function konversai_chat_get_api_base() {
    $api_base = untrailingslashit(esc_url_raw(KONVERSAI_CHAT_API_BASE, array('https')));
    $parts = wp_parse_url($api_base);

    if (!$parts || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }

    return 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
}

/**
 * Register settings page under Settings > KonversAI Chat
 */
add_action('admin_menu', 'konversai_chat_admin_menu');
function konversai_chat_admin_menu() {
    add_options_page(
        __('KonversAI Chat', 'konversai-chat'),
        __('KonversAI Chat', 'konversai-chat'),
        'manage_options',
        'konversai-chat',
        'konversai_chat_render_settings_page'
    );
}

/**
 * Register settings and fields
 */
add_action('admin_init', 'konversai_chat_register_settings');
function konversai_chat_register_settings() {
    register_setting('konversai_chat', KONVERSAI_CHAT_OPTION, array(
        'type'              => 'array',
        'sanitize_callback' => 'konversai_chat_sanitize_settings',
    ));

    add_settings_section('konversai_chat_main', '', '__return_false', 'konversai-chat');

    add_settings_field(
        'enabled',
        __('Chat widget', 'konversai-chat'),
        'konversai_chat_render_toggle_field',
        'konversai-chat',
        'konversai_chat_main',
        array('key' => 'enabled', 'label_for' => 'konversai_chat_enabled')
    );

    add_settings_field(
        'tenant_key',
        __('Tenant key', 'konversai-chat'),
        'konversai_chat_render_field',
        'konversai-chat',
        'konversai_chat_main',
        array('key' => 'tenant_key', 'label_for' => 'konversai_chat_tenant_key')
    );

    add_settings_field(
        'side',
        __('Position', 'konversai-chat'),
        'konversai_chat_render_choice_field',
        'konversai-chat',
        'konversai_chat_main',
        array('key' => 'side')
    );

    add_settings_field(
        'height',
        __('Height', 'konversai-chat'),
        'konversai_chat_render_choice_field',
        'konversai-chat',
        'konversai_chat_main',
        array('key' => 'height')
    );
}

/**
 * Sanitize settings on save
 */
function konversai_chat_sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $tenant_key = isset($input['tenant_key']) && is_scalar($input['tenant_key']) ? (string) $input['tenant_key'] : '';
    $choices = konversai_chat_position_choices();

    return array(
        // Unchecked checkboxes are not submitted, so a missing value means off
        'enabled'    => !empty($input['enabled']),
        'tenant_key' => sanitize_text_field($tenant_key),
        // Only accept the predefined choices
        'side'       => isset($input['side']) && is_string($input['side']) && isset($choices['side'][$input['side']]) ? $input['side'] : 'right',
        'height'     => isset($input['height']) && is_string($input['height']) && isset($choices['height'][$input['height']]) ? $input['height'] : 'raised',
    );
}

/**
 * Render a text input for a setting
 */
function konversai_chat_render_field($args) {
    $settings = konversai_chat_get_settings();

    printf(
        '<input type="text" class="regular-text" id="%s" name="%s[%s]" value="%s" />',
        esc_attr($args['label_for']),
        esc_attr(KONVERSAI_CHAT_OPTION),
        esc_attr($args['key']),
        esc_attr($settings[$args['key']])
    );

    if ($args['key'] === 'tenant_key') {
        echo '<p class="description">' . esc_html__('The widget is only shown when a tenant key is set.', 'konversai-chat') . '</p>';
    }

}

/**
 * Render the checkbox that turns the widget on or off
 */
function konversai_chat_render_toggle_field($args) {
    $settings = konversai_chat_get_settings();

    printf(
        '<label><input type="checkbox" id="%s" name="%s[%s]" value="1" %s /> %s</label>',
        esc_attr($args['label_for']),
        esc_attr(KONVERSAI_CHAT_OPTION),
        esc_attr($args['key']),
        checked($settings[$args['key']], true, false),
        esc_html__('Show the chat widget on the site', 'konversai-chat')
    );

    echo '<p class="description">' . esc_html__('Turn off to hide the widget everywhere without removing the settings.', 'konversai-chat') . '</p>';
}

/**
 * Render radio buttons for a position setting
 */
function konversai_chat_render_choice_field($args) {
    $settings = konversai_chat_get_settings();
    $choices = konversai_chat_position_choices();

    echo '<fieldset>';

    foreach ($choices[$args['key']] as $value => $choice) {
        printf(
            '<label><input type="radio" name="%s[%s]" value="%s" %s /> %s</label><br />',
            esc_attr(KONVERSAI_CHAT_OPTION),
            esc_attr($args['key']),
            esc_attr($value),
            checked($settings[$args['key']], $value, false),
            esc_html($choice['label'])
        );
    }

    echo '</fieldset>';

    if ($args['key'] === 'height') {
        echo '<p class="description">' . esc_html__('Distance of the closed chat bubble from the bottom of the screen. On phones the open chat is always full screen.', 'konversai-chat') . '</p>';
    }
}

/**
 * Render the settings page
 */
function konversai_chat_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('konversai_chat');
            do_settings_sections('konversai-chat');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Add Settings link on the plugins list
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'konversai_chat_action_links');
function konversai_chat_action_links($links) {
    array_unshift($links, sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=konversai-chat')),
        esc_html__('Settings', 'konversai-chat')
    ));

    return $links;
}

/**
 * Print the KonversAI widget script in the footer
 */
add_action('wp_footer', 'konversai_chat_render_widget', 100);
function konversai_chat_render_widget() {
    $settings = konversai_chat_get_settings();
    $api_base = konversai_chat_get_api_base();

    // Never show the widget when turned off, or without a tenant key or a valid script source
    if (!$settings['enabled'] || $settings['tenant_key'] === '' || $api_base === '') {
        return;
    }

    // Keep third-party JS off payment and account pages, and away from admin sessions
    $enabled = !current_user_can('manage_options');

    if (function_exists('is_checkout') && (is_checkout() || is_account_page() || is_wc_endpoint_url())) {
        $enabled = false;
    }

    if (!apply_filters('konversai_chat_enabled', $enabled)) {
        return;
    }

    $choices = konversai_chat_position_choices();
    $css = $choices['side'][$settings['side']]['css'] . $choices['height'][$settings['height']]['css'];

    // The CSS comes from the fixed choices above, never from user input
    if ($css !== '') {
        echo '<style>.konversai-widget-container:not(.konversai-widget-mobile-open){' . $css . '}</style>' . "\n";
    }

    printf(
        '<script async src="%s" data-tenant-key="%s" data-api-base="%s"></script>' . "\n",
        esc_url($api_base . '/static/widget/bubble.js'),
        esc_attr($settings['tenant_key']),
        esc_url($api_base)
    );
}
