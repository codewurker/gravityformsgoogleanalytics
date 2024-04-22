<?php

namespace Gravity_Forms\Gravity_Forms_Google_Analytics;

defined( 'ABSPATH' ) || die();

use WP_Error;
/**
 * Gravity Forms Google Analytics Add-On.
 *
 * @since     1.0.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */

/**
 * Helper class for retrieving the Google Analytics API validation.
 */
class GF_Google_Analytics_API {

	/**
	 * Google Analytics API URL.
	 *
	 * @since  1.0
	 * @var    string $ga_api_url Google Analytics API URL.
	 */
	protected $ga_api_url = 'https://www.googleapis.com/analytics/v3/';

	/**
	 * Google Analytics Admin API URL.
	 *
	 * @since  2.0
	 * @var    string $ga_admin_api_url Google Analytics Admin API URL.
	 */
	protected $ga_admin_api_url = 'https://analyticsadmin.googleapis.com/v1beta/';

	/**
	 * Google Tag Manager API URL.
	 *
	 * @since  1.0
	 * @var    string $gtm_api_url Google Tag Manager API URL.
	 */
	protected $gtm_api_url = 'https://www.googleapis.com/tagmanager/v2/';

	/**
	 * Google Analytics API token.
	 *
	 * @since  1.0
	 * @var    string $token Google Analytics Token.
	 */
	protected $token = null;

	/**
	 * Add-on instance.
	 *
	 * @var GF_Google_Analytics
	 */
	private $addon;

	/**
	 * Initialize API library.
	 *
	 * @since  1.0
	 *
	 * @param GF_Google_Analytics $addon GF_Google_Analytics instance.
	 * @param string              $token Google Analytics API token.
	 */
	public function __construct( $addon, $token = null ) {
		$this->token = $token;
		$this->addon = $addon;
	}

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 *
	 * @param string $path         Request path.
	 * @param string $mode         ga or gtm for Google Analytics or Tag Manager.
	 * @param array  $body         Body arguments.
	 * @param string $method       Request method. Defaults to GET.
	 * @param string $return_key   Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array|WP_Error
	 */
	private function make_request( $path = '', $mode = 'ga', $body = array(), $method = 'GET', $return_key = null ) {

		// Log API call succeed.
		gf_google_analytics()->log_debug( __METHOD__ . '(): Making request to: ' . $path );

		// Get API Key.
		$token = $this->token;

		// Get mode.
		$api_url = '';
		switch ( $mode ) {
			case 'ga4':
				$api_url = $this->ga_admin_api_url;
				break;
			case 'ga':
				$api_url = $this->ga_api_url;
				break;
			case 'gtm':
				$api_url = $this->gtm_api_url;
				break;
			default:
				return new WP_Error( 'google_analytics_invalid_mode', esc_html__( 'The API mode supplied is not supported by the Google Analytics API.', 'gravityformsgoogleanalytics' ), array() );
		}

		// Build request URL.
		$request_url     = $api_url . $path;

		$args = array(
			'method'    => $method,
			/**
			 * Filters if SSL verification should occur.
			 *
			 * @param bool false          If the SSL certificate should be verified. Defaults to false.
			 * @param string $request_url The request URL.
			 *
			 * @return bool
			 */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false, $request_url ),
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int 30              The timeout limit, in seconds. Defaults to 30.
			 * @param string $request_url The request URL.
			 *
			 * @return int
			 */
			'timeout'   => apply_filters( 'http_request_timeout', 30, $request_url ),
		);
		if ( 'GET' === $method || 'POST' === $method || 'PUT' === $method ) {
			$args['body']    = empty( $body ) ? '' : $body;
			$args['headers'] = array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json;ver=1.0',
				'Content-Type'  => 'application/json; charset=UTF-8',
			);
		}

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $body );
		}

		// Execute request.
		$response = wp_remote_request(
			$request_url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body           = gf_google_analytics()->maybe_decode_json( wp_remote_retrieve_body( $response ) );
		$retrieved_response_code = $response['response']['code'];

		if ( 200 !== $retrieved_response_code ) {
			$error_message = rgars( $response_body, 'error/message', "Expected response code: 200. Returned response code: {$retrieved_response_code}." );
			$error_code    = rgars( $response_body, 'error/errors/reason', 'google_analytics_api_error' );

			gf_google_analytics()->log_error( __METHOD__ . '(): Unable to validate with the Google Analytics API: ' . $error_message );

			return new WP_Error( $error_code, $error_message, $retrieved_response_code );
		}

		return $response_body;
	}

	/**
	 * Retrieve a refresh token when the original expires.
	 *
	 * @since 1.0.0
	 *
	 * @param string $refresh_token Refresh token to re-authorize.
	 *
	 * @return array|WP_Error
	 */
	public function refresh_token( $refresh_token ) {
		// Connect to Gravity Form's API.
		$response = wp_remote_post(
			$this->addon->get_gravity_api_url( '/auth/googleanalytics/refresh' ),
			array(
				'body' => array(
					'refresh_token' => rawurlencode( $refresh_token ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response_body = gf_google_analytics()->maybe_decode_json( wp_remote_retrieve_body( $response ) );

		$retrieved_response_code = $response['response']['code'];
		if ( 200 !== absint( $retrieved_response_code ) || ! rgars( $response_body, 'token/access_token' ) ) {
			$error_message = "Expected response code: 200. Returned response code: {$retrieved_response_code}.";

			gf_google_analytics()->log_error( __METHOD__ . '(): Unable to Reauthorize refresh token: ' . $error_message );
			gf_google_analytics()->log_error( __METHOD__ . '(): Response body: ' . var_export( $response_body, true ) );

			return new WP_Error( 'google_analytics_api_error', $error_message, $retrieved_response_code );
		}
		return $response_body;
	}

	/**
	 * Get a list of Google Analytics accounts.
	 *
	 * @since 2.0.0
	 *
	 * @return array|WP_Error
	 */
	public function get_ga4_accounts() {
		return $this->make_request(
			'accountSummaries',
			'ga4',
			array()
		);
	}

	/**
	 * Get a list of GA4 data streams.
	 *
	 * @since 2.0.0
	 *
	 * @param array $property GA4 property associated with the data streams. In the format "property/XXXX".
	 *
	 * @return array|WP_Error Returns an array of data streams.
	 */
	public function get_data_streams( $property ) {
		return $this->make_request(
			"{$property}/dataStreams",
			'ga4',
			array()
		);
	}

	/**
	 * Creates a new Measurement Protocol API Secret.
	 *
	 * @since 2.0.0
	 *
	 * @param array $path The path to the data stream for which we're creating a secret.
	 *
	 * @return array|WP_Error Returns an array of measurement protocol secrets.
	 */
	public function create_api_secret( $path ) {
		return $this->make_request(
			$path . '/measurementProtocolSecrets',
			'ga4',
			array(
				'displayName' => 'GravityFormsSecret',
			),
			'POST'
		);
	}

	/**
	 * List Measurement Protocol API Secrets.
	 *
	 * @since 2.0.0
	 *
	 * @param array $path The path to the data stream for which we're retrieving secrets.
	 *
	 * @return array|WP_Error Returns an array of measurement protocol secrets.
	 */
	public function get_api_secrets( $path ) {
		return $this->make_request(
			$path . '/measurementProtocolSecrets',
			'ga4',
			array(),
			'GET'
		);
	}

	/**
	 * Get a list of tag manager accounts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $body Body information.
	 *
	 * @return array|WP_Error
	 */
	public function get_tag_manager_account( $body ) {
		return $this->make_request(
			'accounts',
			'gtm',
			$body
		);
	}

	/**
	 * Get a list of tag manager containers.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $body Body information.
	 * @param string $account_id Account to retrieve containers for.
	 *
	 * @return array|WP_Error
	 */
	public function get_tag_manager_containers( $body, $account_id ) {
		return $this->make_request(
			sprintf( 'accounts/%s/containers', $account_id ),
			'gtm',
			$body
		);
	}

	/**
	 * Get a list of tag manager workspaces.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $body Body information.
	 * @param string $path Account path to request.
	 *
	 * @return array|WP_Error
	 */
	public function get_tag_manager_workspaces( $body, $path ) {
		return $this->make_request(
			sprintf( '%s/workspaces', $path ),
			'gtm',
			$body
		);
	}

	/**
	 * Get a list of tag manager triggers.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $body      Body information.
	 * @param string $path      Account path to request.
	 * @param string $workspace The workspace to retrieve variables for.
	 *
	 * @return array|WP_Error
	 */
	public function get_tag_manager_triggers( $body, $path, $workspace ) {
		return $this->make_request(
			sprintf( '%s/workspaces/%s/triggers', $path, $workspace ),
			'gtm',
			$body
		);
	}

	/**
	 * Get a list of tag manager variables.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $body      Body information.
	 * @param string $path      Account path to request.
	 * @param string $workspace The workspace to retrieve variables for.
	 *
	 * @return array|WP_Error
	 */
	public function get_tag_manager_variables( $body, $path, $workspace ) {
		return $this->make_request(
			sprintf( '%s/workspaces/%s/variables', $path, $workspace ),
			'gtm',
			$body
		);
	}
}
