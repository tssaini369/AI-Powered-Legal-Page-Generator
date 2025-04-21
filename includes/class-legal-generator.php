<?php
class ALG_Legal_Generator {
    private $admin_settings;
    private $chatgpt_handler;
    private $gemini_handler;

    public function init() {
        // Initialize handlers
        $this->chatgpt_handler = new ALG_ChatGPT_Handler();
        $this->gemini_handler = new ALG_Gemini_Handler();
        
        // Initialize admin settings
        if (is_admin()) {
            $this->admin_settings = new ALG_Admin_Settings();
            $this->admin_settings->init();
        }

        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }

    public function generate_page($business_name, $document_type, $ai_provider) {
        try {
            if (!current_user_can('manage_options')) {
                return new WP_Error('unauthorized', 'Unauthorized access');
            }

            // Get template first
            $template = $this->get_template($document_type);
            if (empty($template)) {
                return new WP_Error('template_missing', 'Template not found for: ' . $document_type);
            }

            // Generate content and handle potential errors
            $content = $this->generate_content($business_name, $document_type, $ai_provider);
            if (is_wp_error($content)) {
                return $content; // Return the error without processing further
            }

            // Validate content
            if (empty($content)) {
                return new WP_Error('empty_content', 'No content was generated');
            }

            // Sanitize content before insertion
            $sanitized_content = wp_kses_post($content);

            // Create page
            $page_id = wp_insert_post([
                'post_title'   => ucwords(str_replace('-', ' ', $document_type)),
                'post_content' => $sanitized_content,
                'post_status'  => 'draft',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id()
            ]);

            if (is_wp_error($page_id)) {
                return $page_id;
            }

            // Add page edit link to success message
            $edit_link = get_edit_post_link($page_id);
            set_transient('alg_success', sprintf(
                'Legal page generated successfully! <a href="%s">Edit page</a>', 
                esc_url($edit_link)
            ), 45);

            return $page_id;

        } catch (Exception $e) {
            ALG_Debug::log($e->getMessage(), 'Legal Generator Error: ');
            return new WP_Error('generation_error', $e->getMessage());
        }
    }

    private function generate_content($business_name, $document_type, $ai_provider) {
        try {
            $template = $this->get_template($document_type);
            
            if (empty($template)) {
                return new WP_Error('template_missing', 'Template not found for: ' . $document_type);
            }

            if ($ai_provider === 'chatgpt') {
                $content = $this->chatgpt_handler->generate_content($template, $business_name, $document_type);
            } else {
                $content = $this->gemini_handler->generate_content($template, $business_name, $document_type);
            }

            // Handle potential WP_Error from handlers
            if (is_wp_error($content)) {
                return $content;
            }

            if (empty($content)) {
                return new WP_Error('empty_content', 'No content was generated');
            }

            return $content;

        } catch (Exception $e) {
            ALG_Debug::log($e->getMessage(), 'Content Generation Error: ');
            return new WP_Error('content_generation_failed', $e->getMessage());
        }
    }

    private function get_template($document_type) {
        $template_file = ALG_PLUGIN_DIR . 'templates/' . $document_type . '.txt';
        if (!file_exists($template_file)) {
            ALG_Debug::log('Template file not found: ' . $template_file, 'Template Error: ');
            return '';
        }
        return file_get_contents($template_file);
    }

    public function show_admin_notices() {
        if ($error = get_transient('alg_error')) {
            echo '<div class="error"><p>' . esc_html($error) . '</p></div>';
            delete_transient('alg_error');
        }
        
        if ($success = get_transient('alg_success')) {
            echo '<div class="updated"><p>' . esc_html($success) . '</p></div>';
            delete_transient('alg_success');
        }
    }

    private function sanitize_ai_output($content) {
        // Remove AI intro phrases (both APIs)
        $content = preg_replace('/^(?:\s|.)*?(?='.preg_quote(ucwords(str_replace('-', ' ', $document_type))).')/i', '', $content);
        
        // Remove ALL bold/italic disclaimers
        $content = preg_replace('/\*{1,2}.*?\*{1,2}/s', '', $content);
        
        // Remove "Note:"/"Disclaimer:" blocks
        $content = preg_replace('/^(?:Note|Disclaimer|Important):.*$/mi', '', $content);
        
        // Remove ChatGPT/Gemini-specific artifacts
        $artifacts = [
            '/^[\s\S]*?(?=\b'.preg_quote(ucwords(str_replace('-', ' ', $document_type))).'\b)/i',
            '/\b(?:Here(?:’s| is)|(?:Below|Please find)).*?(?=\n\n)/i',
            '/\b(?:Remember|Consult|Suggest).*$/mi'
        ];
        
        foreach ($artifacts as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }
        
        // Final cleanup
        return trim(preg_replace(['/\n{3,}/', '/\s{2,}/'], ["\n\n", ' '], $content));
    }
}