<?php

if (!defined('ABSPATH')) {
	exit;
}

class YouBeHero_Product_Widget {

	public static function init() {
		// Hook for simple products.
		add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'display_widget']);
		// Hook for variation products.
		add_action('woocommerce_after_variations_form', [__CLASS__, 'display_widget']);
		// Enqueue frontend styles.
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
	}

	/**
	 * Display the donation widget on product pages.
	 */
	public static function display_widget() {
		$icon_url = YOUBEHERO_PLUGIN_URL . 'assets/images/icon-leaf.png'; // Replace with your actual icon URL.

		echo '<div class="youbehero-donation-widget">';
		echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr__('Donation Icon', 'youbehero') . '" class="youbehero-icon">';
		echo '<p>' . esc_html__('With every purchase, you can contribute to planting trees in fire-affected areas.', 'youbehero') . '</p>';
		echo '</div>';
	}

	/**
	 * Enqueue styles for the widget.
	 */
	public static function enqueue_styles() {
		wp_enqueue_style(
			'youbehero-product-widget-style',
			YOUBEHERO_PLUGIN_URL . 'assets/css/youbehero-product-widget.css',
			[],
			YOUBEHERO_PLUGIN_VERSION
		);
	}
}
