<?php
/**
 * Plugin Name: AI-Powered Legal Page Generator
 * Description: Generate GDPR-compliant legal pages using Google Gemini or ChatGPT
 * Version: 1.0.0
 * Author: TeeJay
 * License: GPL2
 * Text Domain: AI-Powered Legal Page Generator
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load text domain
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'AI-Powered Legal Page Generator',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// Define plugin constants
define('ALG_VERSION', '1.0.0');
define('ALG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize Debug System
require_once ALG_PLUGIN_DIR . 'includes/class-alg-debug.php';

// Initialize plugin components
require_once ALG_PLUGIN_DIR . 'includes/class-ai-handler.php';
require_once ALG_PLUGIN_DIR . 'includes/class-chatgpt-handler.php';
require_once ALG_PLUGIN_DIR . 'includes/class-gemini-handler.php';
require_once ALG_PLUGIN_DIR . 'includes/class-legal-generator.php';
require_once ALG_PLUGIN_DIR . 'admin/class-admin-settings.php';

global $alg_legal_generator;

function alg_init() {
    global $alg_legal_generator;
    $alg_legal_generator = new ALG_Legal_Generator();
    $alg_legal_generator->init();
}
add_action('plugins_loaded', 'alg_init');

register_activation_hook(__FILE__, function() {
    if (!get_option('alg_website_url')) {
        update_option('alg_website_url', get_site_url());
    }
});

function alg_enqueue_admin_scripts($hook) {
    if ('settings_page_legal-page-generator' !== $hook) {
        return;
    }
    wp_enqueue_style('alg-admin-style', 
        plugins_url('admin/css/admin-style.css', __FILE__),
        array(),
        ALG_VERSION
    );
}
add_action('admin_enqueue_scripts', 'alg_enqueue_admin_scripts');

add_action('init', function() {
    if (is_admin()) {
        ALG_Debug::log('Plugin initialized', 'TEST: ');
    }
});

add_action('admin_init', function() {
    if (current_user_can('manage_options')) {
        $debug_status = ALG_Debug::get_status();
        ALG_Debug::log($debug_status, 'Debug Status: ');
    }
});