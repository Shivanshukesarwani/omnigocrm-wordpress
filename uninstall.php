<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

if (get_option('omnigocrm_delete_data_on_uninstall') !== 'yes') {
    return;
}

global $wpdb;
$t = array(
    $wpdb->prefix . 'omnigo_leads',
    $wpdb->prefix . 'omnigo_contacts',
    $wpdb->prefix . 'omnigo_companies',
    $wpdb->prefix . 'omnigo_opportunities',
    $wpdb->prefix . 'omnigo_tasks',
    $wpdb->prefix . 'omnigo_notes',
    $wpdb->prefix . 'omnigo_products',
    $wpdb->prefix . 'omnigo_pipelines',
    $wpdb->prefix . 'omnigo_pipeline_stages',
    $wpdb->prefix . 'omnigo_message_templates',
    $wpdb->prefix . 'omnigo_media_assets',
    $wpdb->prefix . 'omnigo_conversations',
    $wpdb->prefix . 'omnigo_messages',
    $wpdb->prefix . 'omnigo_calls',
    $wpdb->prefix . 'omnigo_campaigns',
    $wpdb->prefix . 'omnigo_automations',
    $wpdb->prefix . 'omnigo_automation_jobs',
    $wpdb->prefix . 'omnigo_notifications',
    $wpdb->prefix . 'omnigo_audit_logs',
    $wpdb->prefix . 'omnigo_tags',
    $wpdb->prefix . 'omnigo_entity_tags',
    $wpdb->prefix . 'omnigo_quotes',
    $wpdb->prefix . 'omnigo_quote_items',
    $wpdb->prefix . 'omnigo_orders',
    $wpdb->prefix . 'omnigo_order_items',
    $wpdb->prefix . 'omnigo_invoices',
    $wpdb->prefix . 'omnigo_payments',
    $wpdb->prefix . 'omnigo_plans',
    $wpdb->prefix . 'omnigo_subscriptions',
    $wpdb->prefix . 'omnigo_integrations'
);
foreach ($t as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('omnigocrm_db_version');
delete_option('omnigocrm_settings');
delete_option('omnigocrm_delete_data_on_uninstall');

foreach (array('omnigocrm_owner','omnigocrm_admin','omnigocrm_manager','omnigocrm_agent','omnigocrm_viewer') as $role_name) {
    remove_role($role_name);
}
