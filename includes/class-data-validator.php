<?php

class ALG_Data_Validator {
    public static function validate_emails($emails) {
        $cleaned = [];
        foreach (explode(',', $emails) as $email) {
            $email = sanitize_email(trim($email));
            if (is_email($email)) {
                $cleaned[] = $email;
            }
        }
        return implode(', ', $cleaned);
    }
    
    public static function sanitize_business_type($input) {
        $cleaned = sanitize_text_field($input);
        return empty($cleaned) ? 'General Business' : $cleaned;
    }
    
    public static function sanitize_country($input) {
        $cleaned = sanitize_text_field($input);
        return empty($cleaned) ? 'United States' : $cleaned;
    }

    public static function validate_business_name($name) {
        $cleaned = sanitize_text_field($name);
        return empty($cleaned) ? 'Unnamed Business' : $cleaned;
    }
}