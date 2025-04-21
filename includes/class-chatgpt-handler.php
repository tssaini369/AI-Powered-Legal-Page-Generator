<?php
require_once 'class-ai-handler.php';

class ALG_ChatGPT_Handler extends ALG_AI_Handler {
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    protected function init() {
        $this->api_key = get_option('alg_chatgpt_api_key');
    }

    public function generate_content($template, $business_name, $document_type = '') {
        $prompt = $this->prepare_prompt($template, $business_name, $document_type);
        return $this->make_api_request($prompt);
    }

    protected function make_api_request($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'no_api_key', 
                __('API key not configured. Please add your ChatGPT API key in settings.', 'AI-Powered Legal Page Generator')
            );
        }

        $body = [
            'model' => get_option('alg_model_selection', 'gpt-4'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a legal document generator. NEVER add commentary.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000
        ];

        $response = wp_remote_post($this->api_endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed', 
                __('API request failed: ', 'AI-Powered Legal Page Generator') . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = $body['error']['message'] ?? __('Unknown API error occurred', 'AI-Powered Legal Page Generator');
            $error_code = $body['error']['code'] ?? 'api_error';

            // Handle model access errors
            if (strpos(strtolower($error_message), 'does not have access to model') !== false) {
                
                return new WP_Error(
                    'model_access_denied',
                    sprintf(
                        // translators: %s: The name of the AI model (e.g., "gpt-4" or "gpt-3.5-turbo")
                        __('Your OpenAI account does not have access to the %s model. Please switch to a different model in settings.', 'AI-Powered Legal Page Generator'),
                        get_option('alg_model_selection', 'gpt-3.5-turbo')
                    ),
                    [
                        'current_model' => get_option('alg_model_selection', 'gpt-3.5-turbo'),
                        'suggested_model' => 'gpt-3.5-turbo',
                        'api_response' => $body
                    ]
                );
            }

            if (strpos(strtolower($error_message), 'quota') !== false) {
                return new WP_Error(
                    'quota_exceeded',
                    __('Your OpenAI API quota has been exceeded. Please check your OpenAI account usage.', 'AI-Powered Legal Page Generator')
                );
            }

            return new WP_Error(
                $error_code,
                __('API Error: ', 'AI-Powered Legal Page Generator') . $error_message,
                $body
            );
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if (empty($content)) {
            return new WP_Error(
                'empty_response',
                __('No content was generated. Please try again.', 'AI-Powered Legal Page Generator')
            );
        }

        return $content;
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

        // Log generated prompt if debug mode is enabled
        ALG_Debug::log([
            'document_type' => $document_type,
            'prompt' => $base_prompt
        ], 'Generated Prompt: ');

        return $base_prompt;
    }
}