<?php
/**
 * Plugin Name: OmniGoCRM
 * Plugin URI: https://github.com/Shivanshukesarwani/omnigocrm-wordpress
 * Description: Self-hosted CRM for WordPress hosting. Leads, contacts, companies, opportunities, tasks, WhatsApp Click-to-Chat, templates, media assets, reports and settings.
 * Version: 0.1.0
 * Author: Shivanshu Kesarwani
 * License: GPL-2.0-or-later
 * Text Domain: omnigocrm
 */

if (!defined('ABSPATH')) exit;

define('OMNIGOCRM_VERSION', '0.1.0');
define('OMNIGOCRM_FILE', __FILE__);
define('OMNIGOCRM_DIR', plugin_dir_path(__FILE__));
define('OMNIGOCRM_URL', plugin_dir_url(__FILE__));

require_once OMNIGOCRM_DIR . 'includes/class-omnigocrm-db.php';
require_once OMNIGOCRM_DIR . 'includes/class-omnigocrm-rest.php';

register_activation_hook(__FILE__, array('OmniGoCRM_DB', 'activate'));

function omnigocrm_boot() {
    new OmniGoCRM_REST();
}
add_action('plugins_loaded', 'omnigocrm_boot');

function omnigocrm_admin_menu() {
    add_menu_page(
        'OmniGoCRM',
        'OmniGoCRM',
        'read',
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
    wp_enqueue_style('omnigocrm-admin', OMNIGOCRM_URL . 'assets/admin.css', array(), OMNIGOCRM_VERSION);
    wp_enqueue_script('omnigocrm-admin', OMNIGOCRM_URL . 'assets/admin.js', array(), OMNIGOCRM_VERSION, true);
    wp_localize_script('omnigocrm-admin', 'OmniGoCRMConfig', array(
        'restUrl' => esc_url_raw(rest_url('omnigocrm/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'adminUrl' => admin_url()
    ));
}
add_action('admin_enqueue_scripts', 'omnigocrm_assets');
