<?php
/**
 * Plugin Name: AA Access Control for Visitors
 * Plugin URI: https://efeto.ru
 * Description: Плагин для управления доступом к материалам для неавторизованных посетителей сайта на ВордПресс
 * Version: 0.3
 * Author: efeto.ru
 * Author URI: https://efeto.ru
 * License: GPL v2 or later
 * Text Domain: access-control-visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ACV_VERSION', '0.3');
define('ACV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACV_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ACV_PLUGIN_DIR . 'includes/class-acv-frontend.php';
require_once ACV_PLUGIN_DIR . 'includes/class-acv-admin.php';
require_once ACV_PLUGIN_DIR . 'includes/class-acv-css-hide.php';

add_action('plugins_loaded', function() {
    new ACV_Frontend();
    new ACV_Admin();
    new ACV_CSS_Hide();
});