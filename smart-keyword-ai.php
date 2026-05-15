<?php
/**
 * Plugin Name: Smart Keyword AI
 * Description: Use AI to auto-generate SEO tag keywords for the article being edited. Supports OpenAI, Anthropic, Gemini, DeepSeek.
 * Version:     1.1.0
 * Author:      Andrew
 * Text Domain: smart-keyword-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKAI_VERSION', '1.1.0' );
define( 'SKAI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SKAI_URL', plugin_dir_url( __FILE__ ) );

require_once SKAI_PATH . 'includes/class-skai-settings.php';
require_once SKAI_PATH . 'includes/class-skai-content.php';
require_once SKAI_PATH . 'includes/class-skai-usage.php';
require_once SKAI_PATH . 'includes/class-skai-pricing.php';
require_once SKAI_PATH . 'includes/providers/class-skai-provider.php';
require_once SKAI_PATH . 'includes/providers/class-skai-openai.php';
require_once SKAI_PATH . 'includes/providers/class-skai-anthropic.php';
require_once SKAI_PATH . 'includes/providers/class-skai-gemini.php';
require_once SKAI_PATH . 'includes/providers/class-skai-deepseek.php';
require_once SKAI_PATH . 'includes/class-skai-ajax.php';
require_once SKAI_PATH . 'includes/class-skai-metabox.php';

register_activation_hook( __FILE__, array( 'Skai_Settings', 'on_activate' ) );

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain(
		'smart-keyword-ai',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	new Skai_Settings();
	new Skai_Ajax();
	new Skai_Metabox();
} );
