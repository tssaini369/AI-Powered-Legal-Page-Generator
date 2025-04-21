<?php
abstract class ALG_AI_Handler {
    protected $api_key;

    public function __construct() {
        $this->init();
    }

    abstract protected function init();
    abstract public function generate_content($template, $business_name);
    abstract protected function make_api_request($prompt);
}
