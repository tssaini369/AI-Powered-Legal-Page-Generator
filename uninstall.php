<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // Exit if accessed directly
}

// Delete all plugin options
$options = [
    // API Settings
    'alg_chatgpt_api_key',
    'alg_gemini_api_key',
    'alg_model_selection',
    'alg_debug_mode',
    
    // Business Profile
    'alg_business_name',
    'alg_business_type',
    'alg_website_url',
    'alg_business_address',
    'alg_country',
    'alg_contact_emails',
    'alg_custom_prompt'
];

foreach ($options as $option) {
    delete_option($option);
}

// Clean up transients
delete_transient('alg_error');
delete_transient('alg_success');
delete_transient('alg_error_details');

// Clear scheduled events
wp_clear_scheduled_hook('alg_daily_compliance_check');

// Remove custom database tables using dbDelta
if (!function_exists('dbDelta')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}

global $wpdb;
$table_name = $wpdb->prefix . 'alg_legal_clauses';

// Get cached table status
$cache_key = 'alg_table_exists_' . $table_name;
$table_exists = wp_cache_get($cache_key, 'alg_db_checks');

if (false === $table_exists) {
    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )
    );
    wp_cache_set($cache_key, $table_exists ? 1 : 0, 'alg_db_checks', HOUR_IN_SECONDS);
}

if ($table_exists) {
    // Clear related caches first
    wp_cache_delete($cache_key, 'alg_db_checks');
    wp_cache_delete($table_name, 'alg_legal_clauses');

    // Build the query safely
    $table_name_escaped = esc_sql($table_name);
    $sql = "DROP TABLE IF EXISTS `$table_name_escaped`";

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $result = $wpdb->query($sql);

    if (false !== $result) {
        wp_cache_delete('alg_tables_list', 'alg_db_checks');
    }
}

// Clear any remaining plugin caches
wp_cache_flush();