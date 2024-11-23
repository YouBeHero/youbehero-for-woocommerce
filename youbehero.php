<?php
/**
 * Plugin Name: YouBeHero
 * Plugin URI: https://example.com
 * Description: A WooCommerce plugin that allows shop owners and customers to donate to organizations through the cart and checkout process.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: youbehero
 * Domain Path: /languages
 * License: GPL-2.0+
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define constants.
define('YOUBEHERO_PLUGIN_VERSION', '1.0.0');
define('YOUBEHERO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YOUBEHERO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin class.
require_once YOUBEHERO_PLUGIN_DIR . 'includes/class-youbehero-main.php';

// Initialize the plugin.
add_action('plugins_loaded', ['YouBeHero_Main', 'init']);

// Clear cached settings on deactivation.
register_deactivation_hook(__FILE__, ['YouBeHero_Settings', 'clear_cached_settings']);
