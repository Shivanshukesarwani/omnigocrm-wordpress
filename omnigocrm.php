<?php
/**
 * Plugin Name: OmniGoCRM
 * Plugin URI: https://github.com/ShivanshuKesarwani/omnigocrm-wordpress
 * Description: Full WordPress-native CRM and sales workspace with leads, contacts, companies, pipeline, quotes, orders, invoices, payments, omnichannel conversations, automation, reports and settings.
 * Version: 0.5.4
 * Author: Shivanshu Kesarwani
 * License: GPL-2.0-or-later
 * Text Domain: omnigocrm
 */

if (!defined('ABSPATH')) exit;

define('OMNIGOCRM_VERSION', '0.5.4');
define('OMNIGOCRM_FILE', __FILE__);
define('OMNIGOCRM_DIR', plugin_dir_path(__FILE__));
define('OMNIGOCRM_URL', plugin_dir_url(__FILE__));

require_once OMNIGOCRM_DIR . 'includes/class-omnigocrm-db.php';
require_once OMNIGOCRM_DIR . 'includes/class-omnigocrm-rest.php';

function omnigocrm_activate() {
    OmniGoCRM_DB::activate();
    if (!wp_next_scheduled('omnigocrm_process_jobs')) {
        wp_schedule_event(time() + 60, 'hourly', 'omnigocrm_process_jobs');
    }
}
register_activation_hook(__FILE__, 'omnigocrm_activate');

function omnigocrm_deactivate() {
    wp_clear_scheduled_hook('omnigocrm_process_jobs');
}
register_deactivation_hook(__FILE__, 'omnigocrm_deactivate');

function omnigocrm_boot() {
    if (get_option('omnigocrm_db_version') !== OMNIGOCRM_VERSION) {
        OmniGoCRM_DB::activate();
    }
    new OmniGoCRM_REST();
}
add_action('plugins_loaded', 'omnigocrm_boot');

function omnigocrm_admin_menu() {
    add_menu_page(
        'OmniGoCRM',
        'OmniGoCRM',
        'omnigocrm_access',
        'omnigocrm',
        'omnigocrm_render_app',
        'dashicons-groups',
        25
    );
}
add_action('admin_menu', 'omnigocrm_admin_menu');

function omnigocrm_render_app() {
    echo '<div id="omnigocrm-app" class="wrap"></div>';
}

function omnigocrm_assets($hook) {
    if ($hook !== 'toplevel_page_omnigocrm') return;

    $react_dist = OMNIGOCRM_DIR . 'assets/react-dist/omnigocrm.js';
    $react_dist_css = OMNIGOCRM_DIR . 'assets/react-dist/omnigocrm.css';

    // Prefer the Vite production bundle. Keep the source/Babel loader as a fallback
    // for deployments where the build artifacts were not included in the ZIP.
    if (file_exists($react_dist) && file_exists($react_dist_css)) {
        wp_enqueue_style('omnigocrm-react', OMNIGOCRM_URL . 'assets/react-dist/omnigocrm.css', array(), OMNIGOCRM_VERSION);
        wp_enqueue_script('omnigocrm-react', OMNIGOCRM_URL . 'assets/react-dist/omnigocrm.js', array(), OMNIGOCRM_VERSION, true);
        wp_localize_script('omnigocrm-react', 'OmniGoCRMConfig', array(
            'restUrl' => esc_url_raw(rest_url('omnigocrm/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'version' => OMNIGOCRM_VERSION,
            'userId' => get_current_user_id()
        ));
        return;
    }

    $react_source = OMNIGOCRM_DIR . 'app/src/App.jsx';
    $react_css = OMNIGOCRM_DIR . 'app/src/styles.css';
    if (file_exists($react_source) && file_exists($react_css)) {
        wp_enqueue_style('omnigocrm-react', OMNIGOCRM_URL . 'app/src/styles.css', array(), OMNIGOCRM_VERSION);
        wp_enqueue_script('omnigocrm-react-loader', OMNIGOCRM_URL . 'assets/wp-react-loader.js', array('wp-element'), OMNIGOCRM_VERSION, true);
        wp_localize_script('omnigocrm-react-loader', 'OmniGoCRMConfig', array(
            'restUrl' => esc_url_raw(rest_url('omnigocrm/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'version' => OMNIGOCRM_VERSION,
            'userId' => get_current_user_id(),
            'appUrl' => OMNIGOCRM_URL . 'app/src/App.jsx'
        ));
        return;
    }

    wp_enqueue_style('omnigocrm-admin', OMNIGOCRM_URL . 'assets/admin.css', array(), OMNIGOCRM_VERSION);
    wp_enqueue_script('omnigocrm-admin', OMNIGOCRM_URL . 'assets/admin.js', array(), OMNIGOCRM_VERSION, true);
    wp_localize_script('omnigocrm-admin', 'OmniGoCRMConfig', array(
        'restUrl' => esc_url_raw(rest_url('omnigocrm/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'adminUrl' => admin_url(),
        'version' => OMNIGOCRM_VERSION,
        'userId' => get_current_user_id()
    ));
}
add_action('admin_enqueue_scripts', 'omnigocrm_assets');

// Vite's production output may contain ES module imports. WordPress prints
// enqueued scripts as classic scripts by default, so mark the CRM bundle as a module.
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle === 'omnigocrm-react' && strpos($tag, ' type=') === false) {
        return '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}, 10, 3);

