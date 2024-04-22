<?php

namespace Gravity_Forms\Gravity_Forms_Google_Analytics;

defined( 'ABSPATH' ) || die();

use GFFormsModel;

/**
 * Gravity Forms Google Analytics Measurement Protocol.
 *
 * @since     1.0.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */
class GF_Google_Analytics_Measurement_Protocol {
	/**
	 * The Endpoint for the Measurement Protocol
	 *
	 * @since 1.0.0
	 * @var string $endpoint The Measurement Protocol endpoint.
	 */
	//private $endpoint = 'https://www.google-analytics.com/debug/mp/collect?'; // Debug endpoint.
	private $endpoint = 'https://www.google-analytics.com/mp/collect?';

	/**
	 * The Client ID for the Measurement Protocol
	 *
	 * @since 1.0.0
	 * @var string $cid The Client ID.
	 */
	private $cid = '';

	/**
	 * The Measurement Protocol hit type
	 *
	 * @since 1.0.0
	 * @var string $t Hit Type.
	 */
	private $t = 'event';

	/**
	 * The document path
	 *
	 * @since 1.0.0
	 * @var string $dp The document path.
	 */
	private $dp = '';

	/**
	 * The document location
	 *
	 * @since 1.0.0
	 * @var string $dl The document location.
	 */
	private $dl = '';

	/**
	 * The document title
	 *
	 * @since 1.0.0
	 * @var string $dt The document title.
	 */
	private $dt = '';

	/**
	 * The document host name
	 *
	 * @since 1.0.0
	 * @var string $dh The document host name.
	 */
	private $dh = '';

	/**
	 * The IP Address of the user.
	 *
	 * @since 1.0.0
	 * @var string $uip The IP Address of the user.
	 */
	private $uip = '';

	/**
	 * The API secret for sending events.
	 *
	 * @since 1.0.0
	 * @var string $api_secret The API secret
	 */
	private $api_secret = '';

	/**
	 * The Submission Parameters for the feed.
	 *
	 * @since 2.0.0
	 * @var array $parameters The submission parameters
	 */
	private $parameters = array();

	/**
	 * The name for the event.
	 *
	 * @since 2.0.0
	 * @var bool $event_name The event name to be sent to Google Analytics.
	 */
	private $event_name = '';

	/**
	 * Init function. Attempts to get the client's CID
	 *
	 * @since 1.0.0
	 */
	public function init( $api_secret, $event_name = 'gform_submission' ) {
		$this->cid        = $this->create_client_id();
		$this->api_secret = $api_secret;
		$this->event_name = $event_name;
	}

	/**
	 * Sets the custom event parameters
	 *
	 * @since 2.0.0
	 *
	 * @param array $parameters The user's IP address.
	 */
	public function set_params( $parameters ) {
		$this->parameters = $parameters;
	}

	/**
	 * Sets the User's IP
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_ip The user's IP address.
	 */
	public function set_user_ip_address( $user_ip ) {
		$this->uip = $user_ip;
	}

	/**
	 * Sets the document path
	 *
	 * @since 1.0.0
	 *
	 * @param string $document_path The path of the document.
	 */
	public function set_document_path( $document_path ) {
		$this->dp = $document_path;
	}

	/**
	 * Sets the document host
	 *
	 * @since 1.0.0
	 *
	 * @param string $document_host The host of the document.
	 */
	public function set_document_host( $document_host ) {
		$this->dh = $document_host;
	}

	/**
	 * Sets the document location
	 *
	 * @since 1.0.0
	 *
	 * @param string $document_location The location of the document.
	 */
	public function set_document_location( $document_location ) {
		$this->dl = $document_location;
	}

	/**
	 * Sets the document title
	 *
	 * @since 1.0.0
	 *
	 * @param string $document_title The document title for the page being submitted.
	 */
	public function set_document_title( $document_title ) {
		$this->dt = $document_title;
	}

	/**
	 * Sends the data to the measurement protocol
	 *
	 * @since 1.0.0
	 *
	 * @param string $ua_code    The UA code to send the event to.
	 * @param string $event_name The event name to be used.
	 */
	public function send( $google_analytics_code ) {

		// Get variables in wp_remote_post body format.
		$user_properties_vars = array( 'dp', 'dl', 'dt', 'dh', 'uip' );
		$user_properties      = array();
		foreach ( $user_properties_vars as $key => $user_properties_var ) {
			if ( empty( $this->{ $user_properties_vars[ $key ] } ) ) {
				// Empty params cause the payload to fail in testing.
				continue;
			}
			$user_properties[ $user_properties_var ] = $this->{$user_properties_vars[ $key ]};
		}

		$url = $this->endpoint . 'measurement_id=' . $google_analytics_code . '&api_secret=' . $this->api_secret;

		// Perform the POST.
		return wp_remote_post(
			$url,
			array(
				'body' => wp_json_encode(
					array(
						'client_id' => $this->cid,
						'events'    => array(
							'name'   => $this->event_name,
							'params' => $this->parameters,
						),
					),
				),
			)
		);
	}


	/**
	 * Create a GUID on Client specific values
	 *
	 * @since 1.0.0
	 *
	 * @return string New Client ID.
	 */
	private function create_client_id() {

		// collect user specific data.
		if ( isset( $_COOKIE['_ga'] ) ) {

			$ga_cookie = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
			if ( isset( $ga_cookie[2] ) ) {

				// check if uuid.
				if ( $this->check_uuid( $ga_cookie[2] ) ) {

					// uuid set in cookie.
					return $ga_cookie[2];
				} elseif ( isset( $ga_cookie[2] ) && isset( $ga_cookie[3] ) ) {

					// google default client id.
					return $ga_cookie[2] . '.' . $ga_cookie[3];
				}
			}
		}

		// nothing found - return random uuid client id.
		return GFFormsModel::get_uuid();
	}

	/**
	 * Check if is a valid uuid v4
	 *
	 * @since 1.0.0
	 *
	 * @param string $uuid The UUID to check.
	 *
	 * @return bool If the UUID is valid
	 */
	private function check_uuid( $uuid ) {
		return preg_match( '#^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$#i', $uuid );
	}
}
