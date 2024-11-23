<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class YouBeHero_Shortcodes {

	public function __construct() {
		// Register all shortcodes
		add_shortcode('total-donations', [$this, 'total_donations']);
		add_shortcode('total-number-of-donations', [$this, 'total_number_of_donations']);
		add_shortcode('total-number-supported-non-profits', [$this, 'total_number_supported_non_profits']);
		add_shortcode('total-donors', [$this, 'total_donors']);
		add_shortcode('total-achieved-goals', [$this, 'total_achieved_goals']);
		add_shortcode('marketing-widget', [$this, 'marketing_widget']);
		add_shortcode('donations-table', [$this, 'donations_table']);
	}

	public function total_donations($atts, $content = null) {
		// Logic for [total-donations] shortcode
		return '<span>Total Donations: 100,000€</span>'; // Example output
	}

	public function total_number_of_donations($atts, $content = null) {
		// Logic for [total-number-of-donations] shortcode
		return '<span>Total Number of Donations: 500</span>';
	}

	public function total_number_supported_non_profits($atts, $content = null) {
		// Logic for [total-number-supported-non-profits] shortcode
		return '<span>Supported Non-Profits: 50</span>';
	}

	public function total_donors($atts, $content = null) {
		// Logic for [total-donors] shortcode
		return '<span>Total Donors: 1,000</span>';
	}

	public function total_achieved_goals($atts, $content = null) {
		// Logic for [total-achieved-goals] shortcode
		return '<span>Achieved Goals: 30</span>';
	}

	public function marketing_widget($atts, $content = null) {
		// Logic for [marketing-widget] shortcode
		return '<div class="marketing-widget">Marketing Content Here</div>';
	}

	public function donations_table($atts, $content = null) {
		// Logic for [donations-table] shortcode
		return '<table class="donations-table"><tr><td>Sample Donation Table</td></tr></table>';
	}
}