<?php
require_once 'class-ai-handler.php';

class ALG_Gemini_Handler extends ALG_AI_Handler {
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${GEMINI_API_KEY}';

    protected function init() {
        $this->api_key = get_option('alg_gemini_api_key');
    }

    protected function make_api_request($prompt) {
        // Add rate limiting
        $last_request = get_option('alg_last_gemini_request');
        if ($last_request && (time() - $last_request) < 2) {
            return new WP_Error(
                'rate_limit', 
                __('Please wait a few seconds before generating another page.', 'AI-Powered Legal Page Generator')
            );
        }
        update_option('alg_last_gemini_request', time());

        if (empty($this->api_key)) {
            return new WP_Error(
                'no_api_key', 
                __('API key not configured. Please check settings.', 'AI-Powered Legal Page Generator')
            );
        }

        $url = add_query_arg('key', $this->api_key, $this->api_endpoint);

        $body = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array(
                            'text' => "You are a legal document generator. NEVER add commentary.\n\n" . $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            )
        );

        // Log API request
        ALG_Debug::log($body, 'Gemini API Request: ');

        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                __('API request failed: ', 'AI-Powered Legal Page Generator') . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Log API response
        ALG_Debug::log([
            'code' => $response_code,
            'body' => $response_body
        ], 'Gemini API Response: ');

        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? __('Unknown API error occurred', 'AI-Powered Legal Page Generator');
            $error_code = $response_body['error']['code'] ?? 'api_error';

            // Handle quota errors
            if (strpos(strtolower($error_message), 'quota') !== false) {
                return new WP_Error(
                    'quota_exceeded',
                    __('Your Gemini API quota has been exceeded. Please check your Google Cloud usage.', 'AI-Powered Legal Page Generator')
                );
            }

            return new WP_Error(
                $error_code,
                __('API Error: ', 'AI-Powered Legal Page Generator') . $error_message,
                $response_body
            );
        }

        if (empty($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error(
                'empty_response',
                __('No content was generated. Please try again.', 'AI-Powered Legal Page Generator')
            );
        }

        return $response_body['candidates'][0]['content']['parts'][0]['text'];
    }

    public function generate_content($template, $business_name, $document_type = '') {
        $prompt = $this->prepare_prompt($template, $business_name, $document_type);
        return $this->make_api_request($prompt);
    }

    private function prepare_prompt($template, $business_name, $document_type) {
        $business_data = [
            'name'    => get_option('alg_business_name'),
            'type'    => get_option('alg_business_type'),
            'address' => get_option('alg_business_address'),
            'country' => get_option('alg_country'),
            'emails'  => get_option('alg_contact_emails'),
            'custom'  => get_option('alg_custom_prompt')
        ];
        
        $base_prompt = sprintf(
            "Generate a professional %s following these requirements:\n\n" .
            "Business Details:\n- Name: %s\n- Type: %s\n- Country: %s\n- Address: %s\n" .
            "- Contact: %s\n- Additional Info: %s\n\n",
            ucwords(str_replace('-', ' ', $document_type)),
            $business_data['name'],
            $business_data['type'],
            $business_data['country'],
            $business_data['address'],
            $business_data['emails'],
            $business_data['custom']
        );

        // Keep existing e-commerce specific details
        if (stripos($business_data['type'], 'e-commerce') !== false || 
            stripos($business_data['type'], 'ecommerce') !== false) {
            
            switch ($document_type) {
                case 'shipping-policy':
                    $base_prompt .= "\nE-commerce Shipping Details:" .
                        "\n- Include standard shipping timeframes" .
                        "\n- International shipping policies" .
                        "\n- Order tracking information" .
                        "\n- Return shipping procedures" .
                        "\n- Shipping restrictions and limitations";
                    break;
                    
                case 'refund-policy':
                    $base_prompt .= "\nE-commerce Refund Details:" .
                        "\n- Return window timeframe" .
                        "\n- Product condition requirements" .
                        "\n- Refund processing timeline" .
                        "\n- Digital product refund policy" .
                        "\n- Restocking fees if applicable";
                    break;
                    
                case 'privacy-policy':
                    $base_prompt .= "\nE-commerce Privacy Considerations:" .
                        "\n- Shopping cart data collection" .
                        "\n- Payment processing information" .
                        "\n- Order history retention" .
                        "\n- Marketing communications opt-out" .
                        "\n- Cookie usage for shopping experience";
                    break;
                    
                case 'terms-of-service':
                    $base_prompt .= "\nE-commerce Terms Details:" .
                        "\n- Product pricing and availability" .
                        "\n- Order acceptance and confirmation" .
                        "\n- Payment terms and security" .
                        "\n- Account creation requirements" .
                        "\n- Product description disclaimers";
                    break;
            }
        }

        // Add formatting requirements after e-commerce details
        $base_prompt .= "\n\nSTRICT FORMATTING REQUIREMENTS:\n" .
            "1. START IMMEDIATELY with the document title\n" .
            "2. DO NOT include phrases like 'Here is your', 'Draft policy', or 'Please note'\n" .
            "3. DO NOT add disclaimers in **bold** or *italics*\n" .
            "4. DO NOT include legal advice or suggestions\n" .
            "5. ONLY output clean policy text in standard formatting\n" .
            "6. USE proper heading hierarchy (H1 for title, H2 for sections)\n" .
            "7. MAINTAIN professional, direct language throughout\n\n" .
            "BEGIN WITH: '" . ucwords(str_replace('-', ' ', $document_type)) . "'\n\n" .
            "TEMPLATE STRUCTURE:\n" . $template . "\n\n" .
            "Ensure compliance with " . strtoupper($business_data['country']) . " laws.";

        // Replace error_log with ALG_Debug
        ALG_Debug::log([
            'document_type' => $document_type,
            'prompt' => $base_prompt
        ], 'Generated Prompt: ');

        return $base_prompt;
    }
}