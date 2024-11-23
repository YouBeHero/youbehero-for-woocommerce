<?php

if (!defined('ABSPATH')) {
	exit;
}

class YouBeHero_Settings {

	/**
	 * Fetch external settings with caching.
	 *
	 * @return array
	 */
	public static function fetch_external_settings() {
		// Cache key for transient.
		$transient_key = 'youbehero_settings';

//		// Attempt to retrieve cached settings.
//		$cached_settings = get_transient($transient_key);
//
//		if ($cached_settings) {
//			return $cached_settings;
//		}

		// Define the external URL endpoint
		$endpoint_url = 'https://www.webexpert.gr/ybh.php'; // Replace with the actual endpoint

		// Optional: Set up the arguments for the POST request
		$args = [
			'method'      => 'POST',
			'timeout'     => 10, // Set a reasonable timeout
			'redirection' => 5,
			'blocking'    => true,
			'headers'     => [
				'Content-Type' => 'application/json', // Optional: Define the content type
				'Authorization' => 'Bearer your-api-token', // Optional: Add an API key or token
			],
			'body'        => json_encode([
				'key' => 'value', // Optional: Any data you want to send in the request body
			]),
		];

		// Send the POST request using wp_remote_post
		$response = wp_remote_post($endpoint_url, $args);

		// Check for WP errors
		if (is_wp_error($response)) {
			error_log('Error fetching external settings: ' . $response->get_error_message());
			return []; // Return false to indicate failure
		}

		// Parse the response body
		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);


		if ($response_code !== 200) {
			error_log('Unexpected response code: ' . $response_code);
			return [];
		}

		// Decode the JSON response
		$settings = json_decode($response_body, true);

		// If the response is valid, cache it for 1 hour (3600 seconds).
		if ($settings) {
			set_transient($transient_key, $response, HOUR_IN_SECONDS);
		}

		return $settings;
	}

	/**
	 * Clear the cached settings.
	 */
	public static function clear_cached_settings() {
		delete_transient('youbehero_external_settings');
	}
}
