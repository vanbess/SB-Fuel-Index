<?php 

/**
* Plugin Name: SB Fuel Index
* Description: Provides current fuel index pricing using Fuel SA price index API
* Author: WC Bessinger @ Silverbackdev
* Author URI: https://silverbackdev.co.za
* Version: 1.0.0
*/

defined('ABSPATH') || exit();

add_action('plugins_loaded', 'sbwc_fi_index');

function sbwc_fi_index(){

    define('FI_PATH', plugin_dir_path(__FILE__));
    define('FI_URL', plugin_dir_url(__FILE__));

    // back
    include FI_PATH.'functions/back.php';
    
    // front
    include FI_PATH.'functions/front.php';

}

?>