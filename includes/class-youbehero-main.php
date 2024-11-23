<?php

if (!defined('ABSPATH')) {
	exit;
}

class YouBeHero_Main {
	private static $settings;

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		// Check if WooCommerce is active.
		if (!self::is_woocommerce_active()) {
			add_action('admin_notices', [__CLASS__, 'admin_notice_missing_woocommerce']);
			return;
		}

		// Start WooCommerce session if not already started.
		if (!is_admin()) {
			add_action('init', function () {
				if (class_exists('WC_Session_Handler') && !WC()->session->has_session()) {
					WC()->session->set_customer_session_cookie(true);
				}
			});
		}

		// Load plugin textdomain.
		add_action('init', [__CLASS__, 'load_textdomain']);

		// Include necessary classes.
		self::includes();

		// Initialize the Shortcodes class
		new YouBeHero_Shortcodes();

		// Fetch settings.
		self::$settings = YouBeHero_Settings::fetch_external_settings();

		// Hook into WooCommerce processes.
		self::setup_hooks();

		// Enqueue assets.
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
	}

	/**
	 * Check if WooCommerce is active.
	 */
	private static function is_woocommerce_active() {
		return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
	}

	/**
	 * Admin notice if WooCommerce is missing.
	 */
	public static function admin_notice_missing_woocommerce() {
		echo '<div class="error"><p>' . esc_html__('YouBeHero requires WooCommerce to be installed and active.', 'youbehero') . '</p></div>';
	}

	/**
	 * Load plugin textdomain for translations.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain('youbehero', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Include plugin files.
	 */
	private static function includes() {
		require_once YOUBEHERO_PLUGIN_DIR . 'includes/class-youbehero-settings.php';
		require_once YOUBEHERO_PLUGIN_DIR . 'includes/class-youbehero-checkout.php';
		require_once YOUBEHERO_PLUGIN_DIR . 'includes/class-youbehero-donation-handler.php';
		require_once YOUBEHERO_PLUGIN_DIR . 'includes/class-youbehero-shortcodes.php';
	}

	/**
	 * Hook into WooCommerce processes.
	 */
	private static function setup_hooks() {
		// Example: Add donation widget only if credits > 0.
		if (isset(self::$settings['credits']) && self::$settings['credits'] > 0) {
			$widget_theme = self::$settings['settings']['widget_theme'];

			// Enqueue assets for frontend.
			add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

			// Hook widget to selected product page position.
			$widget_position = $widget_theme['product_page']['position'];
			add_action($widget_position, [__CLASS__, 'display_widget']);

			// Display widget on cart page.
			add_action('woocommerce_cart_collaterals', [__CLASS__, 'display_widget']);

			if ($widget_theme['checkout']['active']) {
				add_action('woocommerce_review_order_before_submit', [__CLASS__, 'display_checkout_widget']);
			}

			// Display widget on order confirmation (thank you page).
			add_action('woocommerce_thankyou', [__CLASS__, 'display_widget']);

			// Display widget in order confirmation emails.
			add_action('woocommerce_email_after_order_table', [__CLASS__, 'display_widget_in_email'], 10, 4);

			// Handle cart fee calculations.
			add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_donation_fee']);

			// Handle AJAX for adding/removing donation fees.
			add_action('wp_ajax_update_donation_fee', [__CLASS__, 'update_donation_fee']);
			add_action('wp_ajax_nopriv_update_donation_fee', [__CLASS__, 'update_donation_fee']);

			// Register AJAX handlers for round-up calculation
			add_action('wp_ajax_get_round_up_total', [__CLASS__, 'get_round_up_total']);
			add_action('wp_ajax_nopriv_get_round_up_total', [__CLASS__, 'get_round_up_total']);

			add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_donation_value_reversed']);
			add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'display_donation_value_reversed_in_admin']);
			add_filter('woocommerce_email_order_meta_fields', [__CLASS__, 'add_donation_value_reversed_to_emails'], 10, 3);
			add_action('woocommerce_thankyou', [__CLASS__, 'display_donation_value_reversed_on_thankyou_page']);
		}
	}

	public static function save_donation_value_reversed($order_id) {
		if (isset($_POST['donation_value_reversed'])) {
			// Sanitize and save the field value
			$donation_value_reversed = sanitize_text_field($_POST['donation_value_reversed']);
			update_post_meta($order_id, '_donation_value_reversed', $donation_value_reversed);
		}
	}

	public static function display_donation_value_reversed_in_admin($order) {

		$donation_value_reversed = get_post_meta($order->get_id(), '_donation_value_reversed', true);

		if ($donation_value_reversed) {
			echo '<p><strong>' . __('Reversed Donation Value:', 'youbehero') . '</strong> ' . esc_html($donation_value_reversed) . '</p>';
		}
	}

	public static function add_donation_value_reversed_to_emails($fields, $sent_to_admin, $order) {
		$donation_value_reversed = get_post_meta($order->get_id(), '_donation_value_reversed', true);

		if ($donation_value_reversed) {
			$fields['donation_value_reversed'] = [
				'label' => __('Reversed Donation Value', 'youbehero'),
				'value' => $donation_value_reversed,
			];
		}

		return $fields;
	}

	public static function display_donation_value_reversed_on_thankyou_page($order_id) {
		$donation_value_reversed = get_post_meta($order_id, '_donation_value_reversed', true);

		if ($donation_value_reversed) {
			echo '<p><strong>' . __('Reversed Donation Value:', 'youbehero') . '</strong> ' . esc_html($donation_value_reversed) . '</p>';
		}
	}

	public static function get_round_up_total() {
		// Ensure WooCommerce session is initialized
		if (!class_exists('WC_Cart')) {
			wp_send_json_error(['message' => 'WooCommerce cart not available']);
			return;
		}

		// Get the cart total
		$cart_total = WC()->cart->get_total(''); // Get the raw total (not formatted)
		$cart_total_float = floatval(strip_tags($cart_total)); // Strip tags and parse to float

		// Calculate the round-up amount
		$round_up_value = ceil($cart_total_float) - $cart_total_float;

		// Return the round-up amount and cart total
		wp_send_json_success([
			'cart_total' => $cart_total_float,
			'round_up_value' => number_format($round_up_value, 2, '.', ''),
		]);
	}


	/**
	 * Add or remove donation fee based on session data.
	 */
	public static function apply_donation_fee(WC_Cart $cart) {
		$donation_fee = WC()->session->get('youbehero_donation_fee', 0);
		if ($donation_fee > 0) {
			$cart->add_fee(__('Donation', 'youbehero'), $donation_fee, false);
		}
	}

	public static function update_donation_fee() {
		// Check for valid AJAX request and sanitize input.
		$donation_amount = isset($_POST['amount']) ? (float) sanitize_text_field($_POST['amount']) : 0;

		// Save the donation amount in WooCommerce's session.
		WC()->session->set('youbehero_donation_fee', $donation_amount);

		// Return success response.
		wp_send_json_success(['amount' => $donation_amount]);
	}

	/**
	 * Display the donation widget in the cart.
	 */
	public static function display_donation_widget() {
		// Example widget display using settings.
		$organisations = self::$settings['settings']['organisations'];

		if (!empty($organisations)) {
			echo '<div class="youbehero-donation-widget">';
			echo '<h3>' . esc_html__('Support a Cause', 'youbehero') . '</h3>';
			foreach ($organisations as $org) {
				echo '<div class="youbehero-organisation">';
				echo '<img src="' . esc_url($org['image']) . '" alt="' . esc_attr($org['name']) . '">';
				echo '<p><strong>' . esc_html($org['name']) . '</strong></p>';
				echo '<p>' . esc_html($org['text']) . '</p>';
				echo '<a href="' . esc_url($org['url']) . '" target="_blank">' . esc_html__('Learn More', 'youbehero') . '</a>';
				echo '</div>';
			}
			echo '</div>';
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public static function enqueue_assets() {
		// Enqueue SelectWoo (WooCommerce's version of Select2)
		wp_enqueue_script('selectWoo');
		wp_enqueue_style('selectWoo');

		// Enqueue CSS for the plugin.
		wp_enqueue_style(
			'youbehero-style',
			YOUBEHERO_PLUGIN_URL . 'assets/css/youbehero-style.css',
			[],
			time()
		);

		// Optionally enqueue JavaScript if needed.
		wp_enqueue_script(
			'youbehero-script',
			YOUBEHERO_PLUGIN_URL . 'assets/js/youbehero-script.js',
			['jquery'],
			time(),
			true
		);

		wp_enqueue_style(
			'youbehero-checkout-style',
			YOUBEHERO_PLUGIN_URL . 'assets/css/youbehero-checkout.css',
			[],
			time()
		);

		wp_enqueue_script(
			'youbehero-checkout-script',
			YOUBEHERO_PLUGIN_URL . 'assets/js/youbehero-checkout.js',
			['jquery','selectWoo'],
			time(),
			true
		);

		// Localize the script with AJAX URL and other needed data
		wp_localize_script('youbehero-checkout-script', 'youbehero_ajax', [
			'ajax_url' => admin_url('admin-ajax.php'), // The AJAX endpoint URL
		]);
	}

	/**
	 * Display the donation widget on product pages.
	 */
	public static function display_widget() {
		self::render_widget(false);
	}

	public static function display_widget_in_email($order, $sent_to_admin, $plain_text, $email) {
		self::render_widget(true);
	}

	private static function render_widget($is_email = false, $context = 'product_page') {
		// Get the widget settings for the specific context.
		$widget_theme = self::$settings['settings']['widget_theme'][$context];

		// Check if the widget is active for this context.
		if ( empty($widget_theme['active']) ) {
			return; // If not active, do not render the widget.
		}

		// Extract theme settings.
		$background_color = $widget_theme['background_color'] ?? '#f9f9f9';
		$text_color = $widget_theme['text_color'] ?? '#555';
		$display_option = $widget_theme['display'] ?? 'both';
		$theme = $widget_theme['theme'] ?? 'default';
		$icon_url = YOUBEHERO_PLUGIN_URL . 'assets/images/icon-leaf.png'; // Placeholder for icon URL.

		// For email, override background color to transparent (optional).
		if ($is_email) {
			$background_color = 'transparent';
		}

		// Start widget HTML.
		echo '<div class="youbehero-donation-widget ' . esc_attr($theme) . '" style="background-color:' . esc_attr($background_color) . '; color:' . esc_attr($text_color) . ';">';

		// Display icon if enabled.
		if ($display_option === 'icon' || $display_option === 'both') {
			echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr__('Donation Icon', 'youbehero') . '" class="youbehero-icon">';
		}

		// Display text if enabled.
		if ($display_option === 'text' || $display_option === 'both') {
			echo '<p>' . esc_html__('With every purchase, you can contribute to planting trees in fire-affected areas.', 'youbehero') . '</p>';
		}

		// Optional "Learn More" link or footer content.
		echo '<p class="youbehero-learn-more"><a href="https://example.com" target="_blank">' . esc_html__('Learn more', 'youbehero') . '</a></p>';

		echo '</div>';
	}

	public static function display_checkout_widget() {
		$organizations = self::$settings['organizations'];
		$settings = self::$settings['settings'];
		$donation_type = $settings['donation_type'];

		// Calculate the round-up value
		$cart_total = WC()->cart->get_total(''); // Get the cart total
		$cart_total_float = floatval(strip_tags($cart_total)); // Strip tags and convert to float

		// Generate widget HTML.
		echo '<div id="youbehero-donation-widget-checkout" class="youbehero-donation-widget">';
		echo '<h3>' . esc_html__('Would you like to make a donation with your order?', 'youbehero') . '</h3>';
		echo '<p>' . esc_html__('Select the organization and the amount you would like to donate.', 'youbehero') . '</p>';
		if ($donation_type['type'] === 'shop_owner') {
			if ($donation_type['shop_owner']['type']==="fixed_percentage") {
				$fixed = $donation_type['shop_owner']['fixed_percentage'];
				$calculated = wc_format_decimal($cart_total_float * ($fixed/100),2);
			}else {
				$fixed = $donation_type['shop_owner']['fixed_amount'];
				$calculated = wc_format_decimal($fixed,2);
			}

			// Render the hidden field with the stored value
			echo '<input type="hiddenn" id="donation_value_reversed" name="donation_value_reversed" value="' . esc_attr($calculated) . '" />';

			echo '<p>' . sprintf(__('We will donate %s%% (%s) of the order to the selected organization.', 'youbehero'), $fixed, wc_price($calculated)) . '</p>';
		}

		// Render the select dropdown
		echo '<div class="youbehero-organization">';
		echo '<select id="youbehero-organization-select" class="youbehero-select-organization">';

		echo '<option value="" data-image="' . esc_url("Select an organization") . '">';
		foreach ($organizations as $organization) {
			echo '<option value="' . esc_attr($organization['name']) . '" data-image="' . esc_url($organization['image']) . '">';
			echo esc_html($organization['name']);
			echo '</option>';
		}

		echo '</select>';
		echo '</div>';

		if ($donation_type['type'] === 'customer') {
			// Get the stored donation value from the session
			$donation_value = WC()->session->get('youbehero_donation_fee', 0);

			// Render the hidden field with the stored value
			echo '<input type="hiddenn" id="donation_value" name="donation_value" value="' . esc_attr($donation_value) . '" />';

			$fixed_amounts = $donation_type['fixed_amounts'];

			echo '<div class="youbehero-donation-buttons">';
			foreach ($fixed_amounts as $key => $amount) {
				echo '<div class="youbehero-donation-btn '.($amount==$donation_value ? 'selected' : '').'" data-amount="' . esc_attr($amount) . '">' . wc_price($amount,['decimals'=>0]) . '</div>';
			}

			$round_up_value = ceil($cart_total_float) - $cart_total_float;

			echo '<div class="youbehero-donation-btn other-btn" id="youbehero-other">' . esc_html__('Other', 'youbehero') . '</div>';
			echo '</div>';

			// Other (Custom Input and Round-Up) Section.
			echo '<div class="youbehero-other-input" style="display: none;">';
			echo '<p>' . esc_html__('Round-up amount or enter your donation:', 'youbehero') . '</p>';
			echo '<div id="youbehero-round-up" class="youbehero-donation-btn" data-price="' . wc_format_decimal($round_up_value) . '">';
			echo esc_html__('Round-Up', 'youbehero') . ' (' . wc_price(wc_format_decimal($round_up_value)) . ')';
			echo '</div>';

			echo '<input type="number" id="youbehero-custom-donation" placeholder="0.00" value="'.$donation_value.'" />';
		}

		echo '<p class="youbehero-supported-by">' . esc_html__('Supported by YouBeHero', 'youbehero') . '</p>';



		echo '</div>';
	}
}