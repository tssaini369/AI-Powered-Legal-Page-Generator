<?php
if (!defined('ABSPATH')) exit;

class ALG_Debug {
    private static $messages = array();
    private static $enabled = false;

    public static function init() {
        // Enable debug if either WP_DEBUG or plugin setting is true
        self::$enabled = (defined('WP_DEBUG') && WP_DEBUG) || get_option('alg_debug_mode', false);
        
        // Log initial debug status
        if (self::$enabled) {
            $debug_status = [
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'plugin_debug' => get_option('alg_debug_mode', false)
            ];
            self::log($debug_status, 'Debug System Status: ');
        }
    }

    public static function log($data, $prefix = '') {
        if (!self::$enabled) {
            return;
        }

        $timestamp = '[' . current_time('mysql') . '] ';
        
        if (is_array($data) || is_object($data)) {
            $message = $timestamp . $prefix . wp_json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $message = $timestamp . $prefix . $data;
        }
        
        self::$messages[] = $message;
        
        // Optionally log to WordPress debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            file_put_contents(WP_CONTENT_DIR . '/debug.log', $message . PHP_EOL, FILE_APPEND);
        }
    }

    public static function get_messages() {
        return self::$messages;
    }

    public static function get_status() {
        return array(
            'enabled' => self::$enabled,
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'plugin_debug' => get_option('alg_debug_mode', false)
        );
    }

    public static function clear() {
        self::$messages = array();
    }
}
