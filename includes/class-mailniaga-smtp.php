<?php
// includes/class-mailniaga-smtp.php

class MailNiaga_SMTP {
    public function run() {
        // Plugin initialization code here
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'mailniaga-smtp',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}