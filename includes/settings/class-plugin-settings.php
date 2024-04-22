<?php
/**
 * Object responsible for organizing and constructing the plugin settings page.
 */

namespace Gravity_Forms\Gravity_Forms_Google_Analytics\Settings;

defined( 'ABSPATH' ) || die();

use Gravity_Forms\Gravity_Forms_Google_Analytics\GF_Google_Analytics;
use GFCommon;

class Plugin_Settings {
	/**
	 * Add-on instance.
	 *
	 * @var GF_Google_Analytics
	 */
	private $addon;
	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_googleanalytics';

	/**
	 * Stores the auth_payload returned after OAuth has been completed.
	 *
	 * @since 1.0
	 *
	 * @var array
	 */
	protected $auth_payload;

	/**
	 * Plugin_Settings constructor.
	 *
	 * @since 1.0
	 *
	 * @param GF_Google_Analytics $addon GF_Google_Analytics instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get the plugin settings fields.
	 *
	 * @since 1.0
	 * @see   GF_Google_Analytics::plugin_settings_fields()
	 *
	 * @return array
	 */
	public function get_fields() {

		if ( $this->is_connected() ) {
			return array(
				$this->get_connection_display(),
				$this->get_advanced_fields(),
			);
		}

		if ( $this->is_manual_configuration() ) {
			return array( $this->manual_connection_settings() );
		}

		if ( $this->is_unconfigured_connection() ) {
			return $this->show_settings_for_action( rgget( 'action' ) );
		}

		return $this->get_connection_mode_fields();
	}

	/**
	 * Display the settings updated notice
	 *
	 * @since 2.0.0
	 */
	public function maybe_display_settings_updated() {
		if ( rgget( 'updated' ) ) {
			printf(
				'<div class="alert gforms_note_success" role="alert">%s</div>',
				esc_html__( 'Settings Updated.', 'gravityformsgoogleanalytics' )
			);
		}
	}
	/**
	 * Determine if this is connection mode selected was manual configuration.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	private function is_manual_configuration() {
		return $this->addon->get_options( 'is_manual' );
	}

	/**
	 * Checks if the add-on can initialize the API and that an action exists.
	 *
	 * Determines if the user has been redirected from the OAuth flow with a token and an action
	 * but the settings have not been saved yet.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	private function is_unconfigured_connection() {
		return empty( $this->addon->get_options( 'mode' ) ) && rgget( 'action' ) && $this->addon->initialize_api();
	}


	/**
	 * Checks if the addon can initialize the API and that settings were saved.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	private function is_connected() {
		return ! empty( $this->addon->get_options( 'mode' ) ) && $this->addon->initialize_api();
	}

	/**
	 * Shows the settings fields for a certain action.
	 *
	 * @since 1.0
	 *
	 * @param string $action The action extracted from the query args to show the settings for.
	 *
	 * @return array
	 */
	public function show_settings_for_action( $action ) {
		if ( 'gaselect' === $action ) {
			return $this->google_analytics_connection_settings();
		}
		if ( 'gtmselect' === $action ) {
			return $this->google_tag_manager_settings();
		}

		return $this->get_connection_mode_fields();
	}

	/**
	 * Get the connection mode fields. These are the fields that let the user choose between the three connection types.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function get_connection_mode_fields() {

		$card_choices = array(
			array(
				'label'       => esc_html__( 'Measurement Protocol', 'gravityformsgoogleanalytics' ),
				'value'       => 'gmp',
				'icon'        => $this->addon->get_base_url() . '/img/google.svg',
				'tag'         => esc_html__( 'Recommended', 'gravityformsgoogleanalytics' ),
				'color'       => 'orange',
				'title'       => esc_html__( 'Google Measurement Protocol', 'gravityformsgoogleanalytics' ),
				'description' => esc_html__( 'The Measurement Protocol is a server-to-server connection with Google Analytics.  It is the most reliable mechanism for event tracking, but it does not include data such as AdWords, Remarketing, or tracking variables.', 'gravityformsgoogleanalytics' ),
			),
			array(
				'label'       => esc_html__( 'Google Analytics', 'gravityformsgoogleanalytics' ),
				'value'       => 'ga',
				'icon'        => $this->addon->get_base_url() . '/img/analytics.svg',
				'tag'         => esc_html__( 'Flexible', 'gravityformsgoogleanalytics' ),
				'color'       => 'blue-ribbon',
				'title'       => esc_html__( 'Google Analytics', 'gravityformsgoogleanalytics' ),
				'description' => esc_html__( 'Google Analytics mode will send data such as Source/Medium (e.g., the page leading to a conversion).  Additionally, Google Analytics mode will send information about your user such as location, language, browser information, and AdWords/Remarketing information.', 'gravityformsgoogleanalytics' ),
			),
			array(
				'label'       => esc_html__( 'Tag Manager', 'gravityformsgoogleanalytics' ),
				'value'       => 'gtm',
				'icon'        => $this->addon->get_base_url() . '/img/gtm.svg',
				'tag'         => esc_html__( 'Advanced', 'gravityformsgoogleanalytics' ),
				'color'       => 'orange',
				'title'       => esc_html__( 'Google Tag Manager', 'gravityformsgoogleanalytics' ),
				'description' => esc_html__( 'If you need more control after a form has been submitted, such as setting up a remarketing tag, then Google Tag Manager may be the best option.', 'gravityformsgoogleanalytics' ),
			),
		);

		if ( $this->display_manual_connection_mode() ) {
			$card_choices[] = array(
				'label'       => esc_html__( 'Manual Configuration', 'gravityformsgoogleanalytics' ),
				'value'       => 'manual',
				'icon'        => $this->addon->get_base_url() . '/img/manual.svg',
				'tag'         => esc_html__( 'Advanced', 'gravityformsgoogleanalytics' ),
				'color'       => 'orange',
				'title'       => esc_html__( 'Manual Configuration', 'gravityformsgoogleanalytics' ),
				'description' => esc_html__( 'Use this option if you are an advanced user and would like to manually enter your Google Analytics information.', 'gravityformsgoogleanalytics' ),
			);
		}

		return array(
			array(
				'title'       => esc_html__( 'Select Tracking Connection Type', 'gravityformsgoogleanalytics' ),
				'description' => esc_html__( 'Using the Google Analytics Platform you can easily add your Google Analytics tracking code to any of your Gravity Forms, in order to track visitor behavior and demographics.  We offer three ways to connect to the service.', 'gravityformsgoogleanalytics' ),
				'fields'      => array(
					array(
						'name'     => 'nonce',
						'type'     => 'nonce_connect',
						'readonly' => true,
					),
					array(
						'name'     => 'action',
						'type'     => 'hidden',
						'readonly' => true,
						'value'    => 'google_analytics_setup',
						'id'       => 'google-analytics-setup',
					),
					array(
						'type'  => 'save',
						'value' => esc_html__( 'Continue &rarr;', 'gravityformsgoogleanalytics' ),
						'id'    => 'ga-connect',
					),

				),
			),
			array(
				'fields' => array(
					array(
						'name'    => 'mode',
						'type'    => 'card',
						'choices' => $card_choices,
					),
				),
			),
		);
	}

	/**
	 * Determines if the manual connection mode options should be displayed.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Returns true if the manual connection mode options should be displayed. Returns false otherwise.
	 */
	private function display_manual_connection_mode() {
		$version_info = GFCommon::get_version_info();
		return apply_filters( 'gform_googleanalytics_allow_manual_configuration', $version_info['is_valid_key'] );
	}

	/**
	 * Get the connection specific settings for Google Analytics.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function google_analytics_connection_settings() {
		return array(
			array(
				'id'     => 'google-analytics-settings',
				'fields' => array(
					array(
						'name'  => 'gafields',
						'type'  => 'ga_select_account',
						'label' => esc_html__( 'Select a Google Analytics Account', 'gravityformsgoogleanalytics' ),
					),
					array(
						'name' => 'nonce',
						'type' => 'nonce_connect',
					),
					array(
						'name' => 'action',
						'type' => 'ga_action',
					),
					array(
						'name'  => 'save',
						'type'  => 'save',
						'value' => __( 'Complete Setup', 'gravityformsgoogleanalytics' ),
					),
				),
			),
		);
	}

	/**
	 * Get the connection specific settings for Google Tag Manager.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function google_tag_manager_settings() {
		return array(
			array(
				'id'     => 'google-analytics-settings',
				'fields' => array(
					array(
						'name'  => 'gtmfields',
						'type'  => 'gtm_select',
						'label' => esc_html__( 'Select a Google Tag Manager Account', 'gravityformsgoogleanalytics' ),
					),
					array(
						'name'     => 'nonce',
						'type'     => 'nonce_connect',
						'readonly' => true,
					),
					array(
						'name' => 'action',
						'type' => 'gtm_action',
					),
					array(
						'name'  => 'save',
						'type'  => 'save',
						'value' => __( 'Complete Setup', 'gravityformsgoogleanalytics' ),
					),
				),
			),
		);
	}

	/**
	 * Get the manual connection settings.
	 *
	 * @since 2.0
	 *
	 * @return array
	 */
	private function manual_connection_settings() {
		$options = $this->addon->get_options();

		return array(
			'id'     => 'google-analytics-settings',
			'fields' => array(
				array(
					'name'  => 'connection_mode',
					'type'  => 'connection_method',
					'label' => esc_html__( 'Connection Mode', 'gravityformsgoogleanalytics' ),
				),
				array(
					'name'    => 'ga_connection_type',
					'type'    => 'select',
					'label'   => esc_html__( 'Connection Type', 'gravityformsgoogleanalytics' ),
					'value'   => rgar( $options, 'mode' ),
					'choices' => array(
						array(
							'label' => esc_html__( 'Select a connection type', 'gravityformsgoogleanalytics' ),
							'value' => '',
						),
						array(
							'label' => esc_html__( 'Google Analytics', 'gravityformsgoogleanalytics' ),
							'value' => 'ga',
						),
						array(
							'label' => esc_html__( 'Measurement Protocol', 'gravityformsgoogleanalytics' ),
							'value' => 'gmp',
						),
						array(
							'label' => esc_html__( 'Tag Manager', 'gravityformsgoogleanalytics' ),
							'value' => 'gtm',
						),
					),
				),
				array(
					'name'        => 'ga_measurement_id',
					'type'        => 'text',
					'label'       => esc_html__( 'Analytics Measurement ID', 'gravityformsgoogleanalytics' ),
					'value'       => rgars( $options, 'ga4_account/measurement_id' ),
					'dependency'  => array(
						'live'   => true,
						'fields' => array(
							array(
								'field'  => 'ga_connection_type',
								'values' => array( 'ga', 'gmp' ),
							),
						),
					),
					'description' => sprintf(
					// Translators: 1. Opening anchor tag with link to Gravity Forms documentation.  2. Closing anchor tag.
						__( 'Please enter the measurement ID (format: G-XXXXXX). %sLearn more about finding your measurement ID%s.', 'gravityformsgoogleanalytics' ),
						'<a href="https://docs.gravityforms.com/google-analytics-add-on-setup/#h-measurement-protocol" target="_blank">',
						'</a>'
					),
				),
				array(
					'name'        => 'gmp_api_secret',
					'type'        => 'text',
					'input_type'  => 'password',
					'label'       => esc_html__( 'Measurement Protocol API Secret', 'gravityformsgoogleanalytics' ),
					'value'       => rgars( $options, 'ga4_account/gmp_api_secret' ),
					'dependency'  => array(
						'live'   => true,
						'fields' => array(
							array(
								'field'  => 'ga_connection_type',
								'values' => array( 'gmp' ),
							),
						),
					),
					'description' => sprintf(
					// Translators: 1. Opening anchor tag with link to Gravity Forms documentation.  2. Closing anchor tag.
						__( 'Please enter your API secret. %1$sLearn more about finding your API secret%2$s.', 'gravityformsgoogleanalytics' ),
						'<a href="https://docs.gravityforms.com/google-analytics-add-on-setup/#h-measurement-protocol" target="_blank">',
						'</a>'
					),
				),
				array(
					'name'        => 'gtm_container_id',
					'type'        => 'text',
					'label'       => esc_html__( 'Tag Manager Container ID', 'gravityformsgoogleanalytics' ),
					'value'       => rgars( $options, 'ga4_account/gtm_container_id' ),
					'description' => sprintf(
					// Translators: 1. Opening anchor tag with link to Gravity Forms documentation.  2. Closing anchor tag.
						__( 'Please enter the container ID (format: GTM-XXXXXX). %1$sLearn more about finding your container ID%2$s.', 'gravityformsgoogleanalytics' ),
						'<a href="https://docs.gravityforms.com/google-analytics-add-on-setup/#h-tag-manager" target="_blank">',
						'</a>'
					),
					'dependency'  => array(
						'live'   => true,
						'fields' => array(
							array(
								'field'  => 'ga_connection_type',
								'values' => array( 'gtm' ),
							),
						),
					),
				),
				array(
					'name'        => 'gtm_workspace_id',
					'type'        => 'text',
					'label'       => esc_html__( 'Tag Manager Workspace ID', 'gravityformsgoogleanalytics' ),
					'value'       => rgars( $options, 'ga4_account/gtm_workspace_id' ),
					'description' => sprintf(
					// Translators: 1. Opening anchor tag with link to Gravity Forms documentation.  2. Closing anchor tag.
						__( 'Please enter the workspace name Id. %1$sLearn more about finding your Workspaces%2$s.', 'gravityformsgoogleanalytics' ),
						'<a href="https://docs.gravityforms.com/google-analytics-add-on-setup/#h-tag-manager" target="_blank">',
						'</a>'
					),
					'dependency'  => array(
						'live'   => true,
						'fields' => array(
							array(
								'field'  => 'ga_connection_type',
								'values' => array( 'gtm' ),
							),
						),
					),
				),
				array(
					'name' => 'manual_action',
					'type' => 'manual_action',
				),
				array(
					'name' => 'nonce',
					'type' => 'nonce_connect',
				),
			),
		);
	}

	/**
	 * Get the advanced settings fields.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function get_advanced_fields() {
		$options = $this->addon->get_options();
		$mode    = $options['mode'];

		if ( $mode === 'gtm' ) {
			return array(
				'title'  => esc_html__( 'Advanced', 'gravityformsgoogleanalytics' ),
				'fields' => array(
					array(
						'type'          => 'radio',
						'name'          => 'install_gtm',
						'horizontal'    => false,
						'label'         => esc_html__( 'Google Tag Manager Script', 'gravityformsgoogleanalytics' ),
						'default_value' => 'off',
						'choices'       => array(
							array(
								'name'    => 'gtm_off',
								'tooltip' => '<strong>' . esc_html__( 'Don\'t Output the Google Tag Manager Script', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Choose this option if you already have Google Tag Manager installed.', 'gravityformsgoogleanalytics' ),
								'label'   => esc_html__( 'I already have Google Tag Manager installed.', 'gravityformsgoogleanalytics' ),
								'value'   => 'off',
							),
							array(
								'name'    => 'gtm_on',
								'tooltip' => '<strong>' . esc_html__( 'Output Google Tag Manager Script', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Choose this option if you would like to have Google Tag Manager installed.', 'gravityformsgoogleanalytics' ),
								'label'   => esc_html__( 'Output the Google Tag Manager Script.', 'gravityformsgoogleanalytics' ),
								'value'   => 'on',
							),
						),
					),
				),
			);
		} elseif ( $mode === 'ga' ) {
			return array(
				'title'  => esc_html__( 'Advanced', 'gravityformsgoogleanalytics' ),
				'fields' => array(
					array(
						'type'          => 'radio',
						'name'          => 'ga',
						'horizontal'    => false,
						'label'         => esc_html__( 'Google Analytics Script', 'gravityformsgoogleanalytics' ),
						'default_value' => 'off',
						'choices'       => array(
							array(
								'name'    => 'ga_off',
								'tooltip' => '<strong>' . esc_html__( 'Don\'t Output Google Analytics Script', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Choose this option if you already have Google Analytics installed.', 'gravityformsgoogleanalytics' ),
								'label'   => esc_html__( 'I already have Google Analytics installed.', 'gravityformsgoogleanalytics' ),
								'value'   => 'off',
							),
							array(
								'name'    => 'ga_on',
								'tooltip' => '<strong>' . esc_html__( 'Output Google Analytics Script', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Choose this option if you would like to have Google Analytics installed.', 'gravityformsgoogleanalytics' ),
								'label'   => esc_html__( 'Output the Google Analytics Script.', 'gravityformsgoogleanalytics' ),
								'value'   => 'on',
							),
						),
					),
					array(
						'name'    => 'ua_tracker',
						'tooltip' => '<strong>' . esc_html__( 'UA Tracker Name', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Enter the Tracker Name you would like to send events from if you are using a custom Tracker for Google Analytics', 'gravityformsgoogleanalytics' ),
						'label'   => esc_html__( 'UA Tracker Name', 'gravityformsgoogleanalytics' ),
						'type'    => 'text',
						'class'   => 'small',
					),
				),
			);
		} else {
			return array(
				'fields' => array(),
			);
		}
	}

	/**
	 * Show information about the current connection.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function get_connection_display() {
		$options = $this->addon->get_options();
		$mode    = $options['mode'];
		return array(
			'title'  => esc_html__( 'Google Analytics Settings', 'gravityformsgoogleanalytics' ),
			'fields' => array(
				array(
					'name'    => 'connection_method',
					'tooltip' => '<strong>' . esc_html__( 'Connection Type', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Displays the current connection type. To change this, disconnect and reconnect using another type.', 'gravityformsgoogleanalytics' ),
					'label'   => esc_html__( 'Connection Type', 'gravityformsgoogleanalytics' ),
					'type'    => 'connection_method',
				),
				array(
					'name'    => 'analytics_account',
					'tooltip' => '<strong>' . esc_html__( 'Analytics Account', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'The Google Analytics Data Stream which will receive the conversion events.', 'gravityformsgoogleanalytics' ),
					'label'   => esc_html__( 'Analytics Account', 'gravityformsgoogleanalytics' ),
					'type'    => 'analytics_account',
					'hidden'  => $mode == 'gtm',
				),
				array(
					'name'    => 'measurement_id',
					'tooltip' => '<strong>' . esc_html__( 'Analytics Measurement ID', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'The measurement ID identifies the Google Analytics Data Stream which will receive the conversion events.', 'gravityformsgoogleanalytics' ),
					'label'   => esc_html__( 'Analytics Measurement ID', 'gravityformsgoogleanalytics' ),
					'type'    => 'measurement_id',
					'hidden'  => $mode == 'gtm',
				),
				array(
					'name'   => 'tag_manager_account',
					'label'  => esc_html__( 'Tag Manager Account', 'gravityformsgoogleanalytics' ),
					'type'   => 'tag_manager_account',
					'hidden' => $mode != 'gtm',
				),
				array(
					'type'     => 'nonce_connect',
					'name'     => 'nonce',
					'readonly' => true,
				),
			),
		);
	}

	/**
	 * Store auth tokens when we get auth payload from Google Analytics.
	 *
	 * @since 1.0
	 */
	public function maybe_update_auth_tokens() {

		$payload = $this->find_oauth_payload();

		if ( ! $payload ) {
			return;
		}

		if ( rgar( $payload, 'auth_error' ) ) {
			GFCommon::add_message( esc_html__( 'An error occured while connecting to the API.', 'gravityformsgoogleanalytics' ) );
			$this->addon->log_error( $payload['auth_error'] );
			return;
		}

		$auth_payload       = $this->get_decoded_auth_payload( $payload );
		$this->auth_payload = $auth_payload;

		// If access token is provided, save it.
		if ( rgar( $auth_payload, 'access_token' ) ) {
			// Get the authentication token.
			$settings                 = array();
			$settings['token']        = rgar( $auth_payload, 'access_token' );
			$settings['refresh']      = rgar( $auth_payload, 'refresh_token' );
			$settings['date_created'] = time();
			$this->addon->update_options( $settings, 'auth_token' );
			GFCommon::add_message( esc_html__( 'Google Analytics settings have been updated.', 'gravityformsgoogleanalytics' ) );
		}
	}
	/**
	 * Determine if we have a valid nonce and capabilities.
	 *
	 * @since 1.0
	 *
	 * @param array $container The array that contains the nonce, defaults to $_POST.
	 *
	 * @return bool
	 */
	private function is_valid_action( $container = array() ) {
		$container = empty( $container ) ? $_POST : $container;
		return wp_verify_nonce( rgar( $container, 'state' ), 'gravityforms_googleanalytics_google_connect' ) && $this->addon->current_user_can_any( $this->_capabilities_form_settings );
	}

	/**
	 * Decodes the auth_payload returned form Gravity API.
	 *
	 * @since 1.0
	 *
	 * @param array $payload The payload received from Gravity API.
	 *
	 * @return array
	 */
	private function get_decoded_auth_payload( $payload ) {
		$auth_payload_string = rgar( $payload, 'auth_payload' );
		return empty( $auth_payload_string ) ? array() : json_decode( $auth_payload_string, true );
	}

	/**
	 * Get the authorization payload data.
	 *
	 * Returns the auth POST request if it's present, otherwise attempts to return a recent transient cache.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	private function find_oauth_payload() {
		$payload = array_filter(
			array(
				'auth_payload' => rgpost( 'auth_payload' ),
				'auth_error'   => rgpost( 'auth_error' ),
				'state'        => rgpost( 'state' ),
			)
		);

		if (
			(
				$this->is_valid_action( $this->get_decoded_auth_payload( $payload ) )
				&& count( $payload ) === 2
			)
			|| isset( $payload['auth_error'] )
		) {
			return $payload;
		}

		$payload = get_transient( 'gravityapi_response_' . $this->addon->get_slug() );
		if ( rgar( $payload, 'state' ) !== get_transient( 'gravityapi_request_' . $this->addon->get_slug() ) ) {
			return array();
		}

		delete_transient( 'gravityapi_response_' . $this->addon->get_slug() );
		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Gets the auth_payload.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_auth_payload() {
		return $this->auth_payload;
	}
}
