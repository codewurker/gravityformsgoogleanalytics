<?php

namespace Gravity_Forms\Gravity_Forms_Google_Analytics;

defined( 'ABSPATH' ) || die();

use GFForms;
use GFFeedAddOn;
use GFCommon;
use Gravity_Forms\Gravity_Forms_Google_Analytics\GF_Google_Analytics_Pagination;
use GFAPI;
use GFFormsModel;
use GFCache;
use Gravity_Forms\Gravity_Forms_Google_Analytics\Settings;
use WP_Error;

// Include the Gravity Forms Feed Add-On Framework.
GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Google Analytics Add-On.
 *
 * @since     1.0.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2019, Rocketgenius
 */
class GF_Google_Analytics extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @var    GF_Google_Analytics $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity Forms Google Analytics Add-On.
	 *
	 * @since  1.0
	 * @var    string $_version Contains the version.
	 */
	protected $_version = GF_GOOGLE_ANALYTICS_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_GOOGLE_ANALYTICS_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsgoogleanalytics';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsgoogleanalytics/googleanalytics.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Wrapper class for plugin settings.
	 *
	 * @since 1.0
	 * @var Settings\Plugin_Settings
	 */
	private $plugin_settings;

	/**
	 * Wrapper class for form settings.
	 *
	 * @since 1.0
	 * @var Settings\Form_Settings
	 */
	private $form_settings;

	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since  1.0
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'https://gravityforms.com';

	/**
	 * Defines the title of this add-on.
	 *
	 * @since  1.0
	 * @var    string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms Google Analytics Add-On';

	/**
	 * Defines the short title of the add-on.
	 *
	 * @since  1.0
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Google Analytics';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capabilities needed for the Google Analytics Add-On
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_googleanalytics', 'gravityforms_googleanalytics_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_googleanalytics';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_googleanalytics';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_googleanalytics_uninstall';

	/**
	 * Stores the plugin's options
	 *
	 * @since 1.0.0
	 * @var array $options
	 */
	private static $options = false;

	/**
	 * Defines the category if the page is redirected.
	 *
	 * @since  1.0.0
	 *
	 * @var    string Event category for the feed.
	 */
	private $conversion_category = '';

	/**
	 * Defines the label if the page is redirected.
	 *
	 * @since  1.0.0
	 *
	 * @var    string Event label for the feed.
	 */
	private $conversion_label = '';

	/**
	 * Defines the action if the page is redirected.
	 *
	 * @since  1.0.0
	 *
	 * @var    string Event action for the feed.
	 */
	private $conversion_action = '';

	/**
	 * Saves an API instance for Google Authorization.
	 *
	 * @since  1.0.0
	 *
	 * @var    GF_Google_Analytics_API null into object is set.
	 */
	protected $api = null;

	/**
	 * Sets whether an account has a connection error (i.e., GA or GTM are not installed for an account).
	 *
	 * @since  1.0.0
	 *
	 * @var    bool True if error, false if not.
	 */
	private $connect_error = false;

	/**
	 * Version of this add-on which requires reauthentication with the API.
	 *
	 * Anytime updates are made to this class that requires a site to reauthenticate Gravity Forms with Google Analytics, this
	 * constant should be updated to the value of GFForms::$version.
	 *
	 * @since 2.0
	 *
	 * @see   GFForms::$version
	 */
	const LAST_REAUTHENTICATION_VERSION = '2.0';

	/**
	 * The handle used to register and enqueue the frontend scripts.
	 *
	 * @since 2.1
	 *
	 * @var string
	 */
	const FE_SCRIPT_HANDLE = 'gforms_google_analytics_frontend';

	/**
	 * Get an instance of this class.
	 *
	 * @since  1.0.0
	 *
	 * @return GF_Google_Analytics
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	/**
	 * Run add-on pre-initialization processes.
	 *
	 * @since 1.0
	 */
	public function pre_init() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/settings/class-plugin-settings.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/settings/class-form-settings.php';
		$this->plugin_settings = new Settings\Plugin_Settings( $this );
		$this->form_settings   = new Settings\Form_Settings( $this );

		parent::pre_init();
	}

	/**
	 * Performs enqueuing tasks for JS, redirection, event save state, and pagination.
	 *
	 * @since  1.0.0
	 */
	public function init() {
		parent::init();
		add_action( 'gform_confirmation', array( $this, 'force_text_confirmation' ), 1000, 4 );
		add_action( 'wp_head', array( $this, 'maybe_install_analytics' ) );
		add_action( 'wp_head', array( $this, 'maybe_install_tag_manager_header' ) );
		add_action( 'admin_notices', array( $this, 'maybe_display_authentication_notice' ) );

		add_action( 'wp_body_open', array( $this, 'maybe_install_tag_manager_body' ) );

		// General pagination.
		add_action( 'gform_post_paging', array( $this, 'pagination' ), 10, 3 );
	}

	/**
	 * Add Admin handlers.
	 */
	public function init_admin() {
		$this->plugin_settings->maybe_update_auth_tokens();

		parent::init_admin();
	}

	/**
	 * Add Ajax handlers.
	 */
	public function init_ajax() {
		add_action( 'wp_ajax_nopriv_get_entry_meta', array( $this, 'ajax_get_entry_meta' ) );
		add_action( 'wp_ajax_get_entry_meta', array( $this, 'ajax_get_entry_meta' ) );
		add_action( 'wp_ajax_nopriv_save_entry_meta', array( $this, 'ajax_save_entry_meta' ) );
		add_action( 'wp_ajax_save_entry_meta', array( $this, 'ajax_save_entry_meta' ) );
		add_action( 'wp_ajax_save_google_analytics_data', array( $this, 'ajax_save_google_analytics_data' ) );
		add_action( 'wp_ajax_save_google_tag_manager_data', array( $this, 'ajax_save_google_tag_manager_data' ) );
		add_action( 'wp_ajax_save_manual_configuration_data', array( $this, 'ajax_save_manual_configuration_data' ) );
		add_action( 'wp_ajax_get_ga4_data_streams', array( $this, 'ajax_get_ga4_data_streams' ) );
		add_action( 'wp_ajax_get_gtm_workspaces', array( $this, 'ajax_get_gtm_workspaces' ) );
		add_action( 'wp_ajax_get_gtm_containers', array( $this, 'ajax_get_gtm_containers' ) );
		add_action( 'wp_ajax_redirect_to_api', array( $this, 'ajax_redirect_to_api' ) );
		add_action( 'wp_ajax_disconnect_account', array( $this, 'ajax_disconnect_account' ) );
		add_action( 'wp_ajax_gf_ga_log_event_sent', array( $this, 'ajax_log_ga_event_sent' ) );

		parent::init_ajax();
	}

	/**
	 * Initializes the Google Analytics API if credentials are valid.
	 *
	 * @since  1.0
	 *
	 * @return bool|null API initialization state. Returns null if no authentication token is provided.
	 */
	public function initialize_api() {

		// If the API is already initializes, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Initialize Google Analytics API library.
		if ( ! class_exists( 'GF_Google_Analytics_API' ) ) {
			require_once 'includes/class-gf-google-analytics-api.php';
		}

		// Get the authentication token.
		$auth_token = self::get_options( 'auth_token' );
		$mode       = self::get_options( '', 'mode' );
		if ( empty( $mode ) ) {
			$mode = self::get_options( '', 'tempmode' );
		}
		$token        = isset( $auth_token['token'] ) ? $auth_token['token'] : '';
		$date_created = isset( $auth_token['date_created'] ) ? $auth_token['date_created'] : 0;

		// If the authentication token is not set, return null.
		if ( empty( $auth_token ) || rgblank( $token ) || 'unset' === $mode || rgblank( $mode ) ) {
			return null;
		}

		// Initialize a new Google Analytics API instance.
		$google_analytics_api = new GF_Google_Analytics_API( $this, $token );
		if ( time() > ( $date_created + 3600 ) ) { // Access token expires in 1 hour = 3600 seconds.

			// Log that authentication test failed.
			$this->log_debug( __METHOD__ . '(): API tokens expired, start refreshing.' );

			// Refresh token.
			$auth_response = $google_analytics_api->refresh_token( $auth_token['refresh'] );

			if ( ! is_wp_error( $auth_response ) ) {
				$auth_settings = array(
					'token'        => rgars( $auth_response, 'token/access_token' ),
					'refresh'      => rgars( $auth_response, 'token/refresh_token' ),
					'date_created' => rgars( $auth_response, 'token/created' ),
				);
				// Save plugin settings.
				$this->update_options( $auth_settings, 'auth_token' );
				$this->log_debug( __METHOD__ . '(): API access token has been refreshed.' );
			} else {
				$this->log_debug( __METHOD__ . '(): API access token failed to be refreshed; ' . $auth_response->get_error_message() );

				return false;
			}
		}

		// Assign Google Analytics API instance to the Add-On instance.
		$this->api = $google_analytics_api;

		return true;
	}

	/**
	 * Maybe display an admin notice if Google Analytics needs to be reauthenticated.
	 *
	 * @since 2.0
	 */
	public function maybe_display_authentication_notice() {

		if ( ! $this->requires_api_reauthentication() ) {
			return;
		}

		$message = sprintf(
		/* translators: 1: reauthentication version number 2: open <a> tag, 3: close </a> tag */
			esc_html__(
				'Gravity Forms Google Analytics v%1$s requires re-authentication in order to correctly send data to Google Analytics 4. Please %2$sdisconnect and reconnect%3$s to ensure data can be sent to GA4.',
				'gravityformsgoogleanalytics'
			),
			self::LAST_REAUTHENTICATION_VERSION,
			'<a href="' . esc_attr( $this->get_plugin_settings_url() ) . '">',
			'</a>'
		)
		?>

		<div class="gf-notice notice notice-error">
			<p><?php echo wp_kses( $message, array( 'a' => array( 'href' => true ) ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Check whether this add-on needs to be reauthenticated with Google Analytics.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public function requires_api_reauthentication() {
		$settings = self::get_options();

		// don't show the reauth notice if the add-on is not connected.
		if ( ! rgar( $settings, 'mode' ) ) {
			return false;
		}

		return ! empty( $settings ) && version_compare( rgar( $settings, 'reauth_version' ), self::LAST_REAUTHENTICATION_VERSION, '<' );
	}

	/**
	 * Determines if logging is enabled for Google Analytics.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Returns true if logging is enabled. Returns false otherwise
	 */
	private function is_logging_enabled() {

		// Query string override to enable console logging if needed.
		if ( rgget( 'gfga_logging' ) == 1 ) {
			return true;
		}

		// Main logging setting is turned off. Do not log.
		if ( ! class_exists( 'GFLogging' ) || ! get_option( 'gform_enable_logging' ) ) {
			return false;
		}

		// Get logging setting for plugin.
		$plugin_setting = \GFLogging::get_instance()->get_plugin_setting( $this->_slug );

		return rgar( $plugin_setting, 'enable' );
	}

	/**
	 * Outputs admin scripts to handle form submission in back-end.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || rgget( 'gform_debug' ) ? '' : '.min';

		$scripts = array(
			array(
				'handle'    => 'google_analytics_admin',
				'src'       => $this->get_base_url() . "/js/google-analytics-admin{$min}.js",
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'wp-ajax-response' ),
				'strings'   => array(
					'update_settings'     => wp_strip_all_tags( __( 'Update Settings', 'gravityformsgoogleanalytics' ) ),
					'disconnect'          => wp_strip_all_tags( __( 'Disconnecting', 'gravityformsgoogleanalytics' ) ),
					'redirect'            => wp_strip_all_tags( __( 'Redirecting...', 'gravityformsgoogleanalytics' ) ),
					'connect'             => wp_strip_all_tags( __( 'Connect', 'gravityformsgoogleanalytics' ) ),
					'connecting'          => wp_strip_all_tags( __( 'Connecting', 'gravityformsgoogleanalytics' ) ),
					'workspace_required'  => wp_strip_all_tags( __( 'You must select a Workspace', 'gravityformsgoogleanalytics' ) ),
					'view_required'       => wp_strip_all_tags( __( 'You must select a View', 'gravityformsgoogleanalytics' ) ),
					'ga_required'         => wp_strip_all_tags( __( 'You must select a Google Analytics account', 'gravityformsgoogleanalytics' ) ),
					'gtm_required'        => wp_strip_all_tags( __( 'You must select a Tag Manager Account', 'gravityformsgoogleanalytics' ) ),
					'spinner'             => GFCommon::get_base_url() . '/images/spinner.svg',
				),
				'in_footer' => true,
				'enqueue'   => array(
					array(
						'query' => 'page=gf_settings&subview=gravityformsgoogleanalytics',
					),
				),
			),
			array(
				'handle'    => self::FE_SCRIPT_HANDLE,
				'src'       => $this->get_base_url() . "/js/google-analytics{$min}.js",
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'wp-ajax-response' ),
				'in_footer' => true,
				'enqueue'   => array(
					array( $this, 'frontend_script_callback' ),
				),
				'callback' => array( $this, 'frontend_script_strings_callback' ),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Get Gravity API URL.
	 *
	 * @since 1.0
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public function get_gravity_api_url( $path = '' ) {
		return ( defined( 'GRAVITY_API_URL' ) ? GRAVITY_API_URL : 'https://gravityapi.com/wp-json/gravityapi/v1' ) . $path;
	}

	/**
	 * Retrieve the plugin's options.
	 *
	 * Retrieve the plugin's options based on context.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context      Context to retrieve options for. This is used as an array key.
	 * @param string $key          Array key to retrieve.
	 * @param bool   $force_reload Whether to retrieve cached options or forcefully retrieve from the database.
	 *
	 * @return mixed All options if no context, or associative array if context is set. Empty array if no options. String if $key is set.
	 */
	public static function get_options( $context = '', $key = false, $force_reload = false ) {
		// Try to get cached options.
		$options = self::$options;
		if ( false === $options || true === $force_reload ) {
			$options = get_option( 'gravityformsaddon_gravityformsgoogleanalytics_settings', array() );
		}

		// Store options.
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Assign options for caching.
		self::$options = $options;

		if ( rgblank( $context ) && $key ) {
			if ( isset( $options[ $key ] ) ) {
				return $options[ $key ];
			} else {
				return '';
			}
		}

		// Attempt to get context.
		if ( ! empty( $context ) && is_string( $context ) ) {
			if ( array_key_exists( $context, $options ) ) {
				if ( false !== $key && is_string( $key ) ) {
					if ( isset( $options[ $context ][ $key ] ) ) {
						return $options[ $context ][ $key ];
					}
				} else {
					return (array) $options[ $context ];
				}
			} else {
				return array();
			}
		}

		return $options;
	}

	/**
	 * Save plugin options.
	 *
	 * Saves the plugin options based on context.  If no context is provided, updates all options.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $options Associative array of plugin options.
	 * @param string $context Array key of which options to update.
	 */
	public static function update_options( $options = array(), $context = '' ) {
		$options_to_save = self::get_options();

		if ( ! empty( $context ) && is_string( $context ) ) {
			$options_to_save[ $context ] = $options;
		} else {
			$options_to_save = $options;
		}
		update_option( 'gravityformsaddon_gravityformsgoogleanalytics_settings', $options_to_save );
		self::$options = $options_to_save;
	}

	/**
	 * Save feed settings.
	 *
	 * @since 1.0.0
	 *
	 * @param int $feed_id The Feed ID.
	 * @param int $form_id The Form ID.
	 *
	 * @return int
	 */
	public function maybe_save_feed_settings( $feed_id, $form_id ) {

		if ( ! rgpost( 'gform-settings-save' ) ) {
			return $feed_id;
		}

		check_admin_referer( $this->_slug . '_save_settings', '_' . $this->_slug . '_save_settings_nonce' );

		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			GFCommon::add_error_message( esc_html__( "You don't have sufficient permissions to update the form settings.", 'gravityformsgoogleanalytics' ) );

			return $feed_id;
		}

		// Store a copy of the previous settings for cases where action would only happen if value has changed.
		$feed = $this->get_feed( $feed_id );
		$this->set_previous_settings( $feed['meta'] );

		$settings = $this->get_posted_settings();
		$sections = $this->get_feed_settings_fields();
		$settings = $this->trim_conditional_logic_vales( $settings, $form_id );

		$is_valid = $this->validate_settings( $sections, $settings );

		if ( $is_valid ) {
			$settings = $this->filter_settings( $sections, $settings );
			$feed_id  = $this->save_feed_settings( $feed_id, $form_id, $settings );

			if ( $feed_id ) {
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			} else {
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		} else {
			GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
		}
		$redirect_url = add_query_arg(
			array(
				'page'    => 'gf_edit_forms',
				'view'    => 'settings',
				'subview' => 'gravityformsgoogleanalytics',
				'id'      => $form_id,
				'fid'     => $feed_id,
			),
			admin_url( 'admin.php' )
		);
		if ( 0 === absint( rgget( 'fid' ) ) ) {
			?>
			<script>
				setTimeout( function() {
					window.location.href = '<?php echo esc_url_raw( $redirect_url ); ?>';
				}, 1 );
			</script>
			<?php
		}
	}

	/**
	 * Installs GTAG Google Analytics if user has selected that option in settings.
	 *
	 * @since  1.0.0
	 */
	public function maybe_install_analytics() {
		$settings = self::get_options();
		if ( ! isset( $settings['ga'] ) ) {
			return;
		}
		if ( 'off' === $settings['ga'] ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Loading Google Analytics GTAG settings: ' . print_r( $settings, true ) );

		// Attempt to get options.
		$measurement_id = sanitize_text_field( self::get_options( 'ga4_account', 'measurement_id' ) );

		// User has requested GA installation. Proceed.
		echo "\r\n";
		?>

		<!-- Google tag (gtag.js) -->
		<script async
		        src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_html( $measurement_id ); ?>"></script> <?php //phpcs:ignore ?>
		<script>
			window.dataLayer = window.dataLayer || [];

			function gtag() {
				dataLayer.push( arguments );
			}

			gtag( 'js', new Date() );

			gtag( 'config', '<?php echo esc_html( $measurement_id ); ?>' );

			<?php
			/**
			 * Action: gform_googleanalytics_install_analytics
			 *
			 * Allow custom scripting for Google Analytics GTAG
			 *
			 * @since 1.0.0
			 *
			 * @param string $gmeasurement_id Google Analytics Property ID
			 */
			do_action( 'gform_googleanalytics_install_analytics', $measurement_id );
			?>
		</script>

		<?php
	}

	/**
	 * Installs Google Tag Manager if user has selected that option in settings.
	 *
	 * @since  1.0.0
	 */
	public function maybe_install_tag_manager_header() {
		$settings = self::get_options();
		if ( ! isset( $settings['install_gtm'] ) ) {
			return;
		}
		if ( 'off' === $settings['install_gtm'] ) {
			return;
		}

		$this->log_debug( __METHOD__ . '(): Loading Google Tag Manager Installation Setting: ' . print_r( $settings, true ) );

		// Attempt to get options.
		$gtm_code = sanitize_text_field( self::get_options( 'ga4_account', 'gtm_container_id' ) );
		if ( empty( $gtm_code ) ) {
			return;
		}

		// User has requested Tag Manager installation. Proceed.
		echo "\r\n";
		?>
		<!-- Google Tag Manager -->
		<script>( function( w, d, s, l, i ) {
				w[ l ] = w[ l ] || [];
				w[ l ].push( {
					'gtm.start':
						new Date().getTime(), event: 'gtm.js'
				} );
				var f = d.getElementsByTagName( s )[ 0 ],
					j = d.createElement( s ), dl = l != 'dataLayer' ? '&l=' + l : '';
				j.async = true;
				j.src =
					'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
				f.parentNode.insertBefore( j, f );
			} )( window, document, 'script', 'dataLayer', '<?php echo esc_html( $gtm_code ); ?>' );
			<?php
			/**
			 * Action: gform_googleanalytics_install_tag_manager
			 *
			 * Allow custom scripting for Google Tag Manager
			 *
			 * @since 1.0.0
			 *
			 * @param string $gtm_code Google Tag Manager ID
			 */
			do_action( 'gform_googleanalytics_install_tag_manager', $gtm_code );
			?>
		</script>
		<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * Installs Google Tag Manager if user has selected that option in settings.
	 *
	 * @since  1.0.0
	 */
	public function maybe_install_tag_manager_body() {
		$settings = self::get_options();
		if ( ! isset( $settings['install_gtm'] ) ) {
			return;
		}
		if ( 'off' === $settings['install_gtm'] ) {
			return;
		}

		$gtm_code = sanitize_text_field( self::get_options( 'account', 'container_id' ) );
		if ( empty( $gtm_code ) ) {
			return;
		}

		// User has requested Tag Manager installation. Proceed.
		?>
		<!-- Google Tag Manager (noscript) -->
		<noscript>
			<iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_html( $gtm_code ); ?>" height="0"
			        width="0" style="display:none;visibility:hidden"></iframe>
		</noscript>
		<!-- End Google Tag Manager (noscript) -->
		<?php
		/**
		 * Action: gform_googleanalytics_install_tag_manager
		 *
		 * Allow custom scripting for Google Tag Manager
		 *
		 * @since 1.0.0
		 *
		 * @param string $gtm_code Google Tag Manager ID
		 */
		do_action( 'gform_googleanalytics_install_tag_manager', $gtm_code );
	}

	/**
	 * Redirects to authentication screen for Google Analytics.
	 *
	 * @since  1.0.0
	 */
	public function authenticate_google_analytics() {
		$ga_options = get_option( 'gforms_google_analytics_ga' );
		$token      = isset( $ga_options['token'] ) ? $ga_options['token'] : false;
		if ( $token && ( isset( $ga_options['mode'] ) && 'ga' === $ga_options['mode'] ) ) {
			return;
		}
		$settings_mode = rgpost( '_gaddon_setting_mode' ) ? rgpost( '_gaddon_setting_mode' ) : 'gmp';
		$state         = array(
			'url'     => admin_url( 'admin.php' ),
			'page'    => 'gf_settings',
			'subview' => 'gravityformsgoogleanalytics',
			'mode'    => $settings_mode,
			'nonce'   => wp_create_nonce( 'gravityformsgoogleanalytics_ua' ),
		);
		$auth_url      = add_query_arg(
			array(
				'mode'        => $settings_mode,
				'redirect_to' => admin_url( 'admin.php' ),
				'state'       => base64_encode(
					json_encode(
						$state
					)
				),
				'license'     => GFCommon::get_key(),
			),
			$this->get_gravity_api_url( '/auth/googleanalytics' )
		);
		wp_safe_redirect( esc_url_raw( $auth_url ) );
		exit();
	}

	/**
	 * Redirects to authentication screen for Google Tag Manager.
	 *
	 * @since  1.0.0
	 */
	public function authenticate_google_tag_manager() {
		$ga_options = get_option( 'gforms_google_analytics_ga' );
		$token      = isset( $ga_options['token'] ) ? $ga_options['token'] : false;
		if ( $token && ( isset( $ga_options['mode'] ) && 'gtm' === $ga_options['mode'] ) ) {
			return;
		}
		$this->log_debug( __METHOD__ . '(): Before Authenticating With Tag Manager: ' . print_r( $ga_options, true ) );
		$state       = array(
			'url'     => admin_url( 'admin.php' ),
			'page'    => 'gf_settings',
			'subview' => 'gravityformsgoogleanalytics',
			'mode'    => 'gtm',
			'nonce'   => wp_create_nonce( 'gravityformsgoogleanalytics_ua' ),
		);
		$redirect_to = admin_url( 'admin.php' );
		$auth_url    = add_query_arg(
			array(
				'mode'        => 'gtm',
				'state'       => base64_encode(
					json_encode(
						$state
					)
				),
				'redirect_to' => $redirect_to,
				'license'     => GFCommon::get_key(),
			),
			$this->get_gravity_api_url( '/auth/googleanalytics' )
		);
		wp_safe_redirect( esc_url_raw( $auth_url ) );
		exit;
	}

	/**
	 * Returns an array with Google Analytics codes to be associated with the event. This only applies to Measurement Protocol.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form  Current form object.
	 * @param array $entry Current entry object.
	 * @param array $feed  Current feed object.
	 *
	 * @return array Returns an array of Google Analytics codes.
	 */
	private function get_ga_codes( $form, $entry = null, $feed = null ) {

		$ua_ids = array( $this->get_measurement_id() );

		/**
		 * Filter: gform_googleanalytics_ua_ids
		 *
		 * Filter all outgoing UA IDs to send events to using the measurement protocol.
		 *
		 * @since 1.0.0
		 *
		 * @param array $ua_ids UA codes.
		 * @param array $form   Gravity Form form object.
		 * @param array $entry  Gravity Form Entry Object.
		 * @param array $feed   Current feed.
		 *
		 * @return array Google anaylics codes.
		 */
		return apply_filters( 'gform_googleanalytics_ua_ids', $ua_ids, $form, $entry, $feed );
	}

	/**
	 * Initialize the pagination events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form                The form arguments.
	 * @param int   $source_page_number  The original page number.
	 * @param int   $current_page_number The new page number.
	 */
	public function pagination( $form, $source_page_number, $current_page_number ) {

		// Pagination tracking not enabled. Abort.
		if ( ! rgars( $form, 'gravityformsgoogleanalytics/google_analytics_pagination' ) ) {
			return;
		}

		$mode       = self::get_options( '', 'mode' );
		$parameters = $this->get_mapped_parameters( $form['gravityformsgoogleanalytics'], 'pagination_parameters', $form, \GFFormsModel::get_current_lead( $form ) );
		$parameters = $this->maybe_replace_pagination_merge_tags( $parameters, $source_page_number, $current_page_number );
		$is_ajax    = isset( $_REQUEST['gform_ajax'] ) ? true : false;
		$event_name = $this->get_pagination_event_name( $mode, $form );


		switch ( $mode ) {
			case 'gmp':
				// Send events via server side (measurement protocol).
				$ua_codes = $this->get_ga_codes( $form );
				$this->send_measurement_protocol( $ua_codes, $parameters, $event_name, \GFFormsModel::get_current_page_url() );

				break;

			case 'ga':
				$this->log_debug( __METHOD__ . '(): Attempting to send pagination event via Google Analytics. Event Name: ' . $event_name . '. Parameters: ' . print_r( $parameters, true ) );
				$this->render_script_send_to_ga( $parameters, $event_name, $is_ajax );
				break;

			case 'gtm':
				$trigger_name = rgar( $form['gravityformsgoogleanalytics'], 'pagination_trigger' ) == 'gf_custom' ? rgar( $form['gravityformsgoogleanalytics'], 'pagination_trigger_custom' ) : rgar( $form['gravityformsgoogleanalytics'], 'pagination_trigger' );
				$this->log_debug( __METHOD__ . '(): Attempting to send pagination event via Google Tag Manager. Trigger Name: ' . $trigger_name . '. Parameters: ' . print_r( $parameters, true ) );
				$this->render_script_send_to_gtm( $parameters, $trigger_name, $is_ajax );
				break;
		}
	}

	/**
	 * Maybe replace the pagination merge tags.
	 *
	 * @since 2.2.0
	 *
	 * @param array $parameters          The pagination parameters.
	 * @param int   $source_page_number  The source page number.
	 * @param int   $current_page_number The target page number.
	 *
	 * @return array
	 */
	public function maybe_replace_pagination_merge_tags( $parameters, $source_page_number, $current_page_number ) {

		foreach ( $parameters as $key => $value ) {
			$parameters[ $key ] = str_replace( array( '{source_page_number}', '{current_page_number}' ), array( $source_page_number, $current_page_number ), $value );
		}

		return $parameters;
	}

	/**
	 * Loads the parameter list with the actual values from the form submission and query string.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $settings               Settings array containing the mapped field configuration.
	 * @param string $parameter_setting_name The name of the parameter setting field.
	 * @param array  $form                   The current form object.
	 * @param array  $entry                  The current entry object.
	 *
	 * @return array An array with the mapped parameters
	 */
	private function get_mapped_parameters( $settings, $parameter_setting_name, $form, $entry ) {

		// Adding UTM values to entry.
		$entry['utm_source']   = rgget( 'utm_source' );
		$entry['utm_medium']   = rgget( 'utm_medium' );
		$entry['utm_campaign'] = rgget( 'utm_campaign' );
		$entry['utm_term']     = rgget( 'utm_term' );
		$entry['utm_content']  = rgget( 'utm_content' );

		// Loading parameters.
		return $this->get_generic_map_fields( $settings, $parameter_setting_name, $form, $entry );
	}

	/**
	 * Redirect to Gravity Forms API.
	 *
	 * @since 1.0.0
	 */
	public function ajax_redirect_to_api() {
		if ( ! wp_verify_nonce( rgpost( 'nonce' ), 'connect_google_analytics' ) || ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			wp_send_json_error(
				array(
					'errors'   => true,
					'redirect' => '',
				)
			);
		}

		$state = wp_create_nonce( 'gravityforms_googleanalytics_google_connect' );

		if ( get_transient( 'gravityapi_request_' . $this->get_slug() ) ) {
			delete_transient( 'gravityapi_request_' . $this->get_slug() );
		}

		set_transient( 'gravityapi_request_' . $this->get_slug(), $state, 10 * MINUTE_IN_SECONDS );
		$mode    = sanitize_text_field( rgpost( 'mode' ) );
		$options = self::get_options();

		if ( $mode == 'manual' ) {
			$redirect_url         = admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug );
			$options['is_manual'] = true;

		} else {
			$action = $mode == 'gtm' ? 'gtmselect' : 'gaselect';

			$settings_url = urlencode( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '&action=' . $action );

			$redirect_url = add_query_arg(
				array(
					'mode'        => sanitize_text_field( rgpost( 'mode' ) ),
					'redirect_to' => $settings_url,
					'state'       => $state,
					'license'     => GFCommon::get_key(),
				),
				$this->get_gravity_api_url( '/auth/googleanalytics' )
			);

			// Temporary mode for storing what the user has selected for authorization.
			$options['tempmode'] = sanitize_text_field( rgpost( 'mode' ) );

			$this->log_debug( "Redirecting to Gravity API: {$redirect_url}" );
		}

		// Updating options.
		self::update_options( $options );

		wp_send_json_success(
			array(
				'errors'   => false,
				'redirect' => esc_url_raw( $redirect_url ),
			)
		);
	}

	/**
	 * Disconnects user from the Google Analytics API.
	 *
	 * @since 1.0.0
	 */
	public function ajax_disconnect_account() {

		// Verify nonce and capability.
		$this->verify_ajax_nonce( 'gforms_google_analytics_disconnect' );
		$this->verify_capability();

		// Deleting option. Token expires in an hour, so no need to revoke it.
		delete_option( 'gravityformsaddon_gravityformsgoogleanalytics_settings' );
		wp_send_json_success( array() );
	}

	/**
	 * Gets views for the selected account and GA code
	 *
	 * @since 2.0.0
	 */
	public function ajax_get_ga4_data_streams() {

		// Verify nonce, capability and API connection.
		$this->verify_ajax_nonce();
		$this->verify_capability();
		$this->verify_api_connection();

		// Retrieving data streams.
		$property     = sanitize_text_field( rgpost( 'property' ) );
		$data_streams = $this->api->get_data_streams( $property );
		if ( is_wp_error( $data_streams ) ) {
			$this->log_debug( __METHOD__ . '(): Error retrieving Google Analytics data streams associated with the selected property.' );
			wp_send_json_error( new WP_Error( 'google_analytics_ga_error', wp_strip_all_tags( __( 'There was an error retrieving Google Analytics data streams.', 'gravityformsgoogleanalytics' ) ) ) );
		}

		// Create select field markup.
		$html = '<br /><select name="ga_data_stream">';
		$html .= '<option value="">' . esc_html__( 'Select a data stream', 'gravityformsgoogleanalytics' ) . '</option>';
		foreach ( $data_streams['dataStreams'] as $data_stream ) {

			// Ignore data streams that are not "webStreamData".
			if ( ! isset( $data_stream['webStreamData'] ) ) {
				continue;
			}
			$html .= sprintf(
				'<option value="%s" data-data-stream-id="%s" data-data-stream-name="%s">%s</option>',
				esc_attr( $data_stream['webStreamData']['measurementId'] ),
				esc_attr( $data_stream['name'] ),
				esc_attr( $data_stream['displayName'] ),
				esc_html( $data_stream['displayName'] )
			);
		}
		$html .= '</select>';

		wp_send_json_success( $html );
	}

	/**
	 * Gets containers for the selected GTM account
	 *
	 * @since 1.1
	 */
	public function ajax_get_gtm_containers() {

		// Verify nonce, capability and API connection.
		$this->verify_ajax_nonce();
		$this->verify_capability();
		$this->verify_api_connection();

		$account_id = rgpost( 'accountId' );
		$token      = rgpost( 'token' );

		// Get containers.
		$container_response = $this->api->get_tag_manager_containers( array(), $account_id );
		if ( is_wp_error( $container_response ) ) {
			$this->log_debug( __METHOD__ . '(): Error retrieving property containers.' );
			$error = new WP_Error(
				'google_analytics_gtm_error',
				$container_response->get_error_message()
			);
			wp_send_json_error( $error );
		}

		// Output HTML.
		$success = array( 'success' => true );
		$html    = '';
		$html    .= '<br /><select name="gacontainer" id="gacontainer">';
		$html    .= '<option value="">' . esc_html__( 'Select a Container', 'gravityformsgoogleanalytics' ) . '</option>';
		foreach ( $container_response['container'] as $container ) {
			$html .= sprintf(
				'<option data-account-id="%s" data-path="%s" data-token="%s" value="%s">%s</option>',
				esc_attr( $account_id ),
				esc_attr( $container['path'] ),
				esc_attr( $token ),
				esc_attr( $container['publicId'] ),
				esc_attr( $container['publicId'] )
			);
		}
		$html .= '</select>';

		$success['body'] = $html;
		wp_send_json( $success );

	}

	/**
	 * Gets workspaces for the selected GTM account
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_gtm_workspaces() {

		// Verify nonce, capability and API connection.
		$this->verify_ajax_nonce();
		$this->verify_capability();
		$this->verify_api_connection();

		// Get workspace ID.
		$response = $this->api->get_tag_manager_workspaces( array(), rgpost( 'path' ) );
		if ( is_wp_error( $response ) ) {
			$error = new WP_Error(
				'google_analytics_gtm_error',
				wp_strip_all_tags( __( 'Could not retrieve Tag Manager workspaces', 'gravityformsgoogleanalytics' ) )
			);
			wp_send_json_error( $error );
		}

		// Output HTML.
		$success = array( 'success' => true );
		$html    = '';
		$html    .= '<select name="gaworkspace">';
		$html    .= '<option value="">' . esc_html__( 'Select a Workspace', 'gravityformsgoogleanalytics' ) . '</option>';
		foreach ( $response as $index => $workspaces ) {
			foreach ( $workspaces as $workspace ) {
				$html .= sprintf(
					'<option value="%s">%s</option>',
					esc_attr( $workspace['workspaceId'] ),
					esc_html( $workspace['name'] )
				);
			}
		}
		$html .= '</select>';

		$success['body'] = $html;
		wp_send_json( $success );
	}

	/**
	 * Updates plugin settings with the provided settings. Overrides parent to correctly update needed settings.
	 *
	 * @since 1.0
	 *
	 * @param array $settings Plugin settings to be saved.
	 */
	public function update_plugin_settings( $settings ) {
		$current_settings = self::get_options();

		// Set the API authentication version.
		$current_settings['reauth_version'] = self::LAST_REAUTHENTICATION_VERSION;
		if ( is_array( $current_settings ) ) {
			$settings = array_merge( $current_settings, $settings );
		}
		update_option( 'gravityformsaddon_' . $this->_slug . '_settings', $settings );
	}

	/**
	 * Saves analytics data to settings and provides redirect callback.
	 *
	 * @since  1.0.0
	 */
	public function ajax_save_google_analytics_data() {

		// Verify nonce, capability and API connection.
		$this->verify_ajax_nonce();
		$this->verify_capability();
		$this->verify_api_connection();

		$options = $this->get_google_analytics_options( self::get_options() );

		if ( rgar( $options, 'mode' ) === 'gmp' ) {
			$api_secret = $this->get_api_secret();
			if ( is_wp_error( $api_secret ) ) {
				wp_send_json_error( $api_secret );
			}
			$options['ga4_account']['gmp_api_secret'] = $api_secret['secretValue'];
		}

		$this->log_debug( __METHOD__ . '(): Saving Google Analytics settings.' );
		$this->update_plugin_settings( $options );

		// Build redirect url and return it.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'gf_settings',
				'subview' => 'gravityformsgoogleanalytics',
			),
			admin_url( 'admin.php' )
		);
		wp_send_json_success( esc_url_raw( $redirect_url ) );
	}

	/**
	 * Gets the API secret for Measurement Protocol.
	 *
	 * @since 2.0.0
	 *
	 * @return string Returns the API secret.
	 */
	private function get_api_secret() {
		$secrets = $this->api->get_api_secrets( rgpost( 'data_stream_id' ) );
		if ( rgar( $secrets, 'measurementProtocolSecrets' ) ) {
			foreach ( $secrets['measurementProtocolSecrets'] as $secret ) {
				if ( $secret['displayName'] === 'GravityFormsSecret' ) {
					return $secret;
				}
			}
		}

		return $this->api->create_api_secret( rgpost( 'data_stream_id' ) );
	}

	/**
	 * Creates an array with the Google Analytics settings to be saved.
	 *
	 * @since 2.0.0
	 *
	 * @param array $options Current options.
	 *
	 * @return array Returns an array with the settings set.
	 */
	public function get_google_analytics_options( $options ) {

		$options['auth_token']  = array(
			'token'        => sanitize_text_field( rgpost( 'token' ) ),
			'refresh'      => sanitize_text_field( rgpost( 'refresh' ) ),
			'date_created' => time(),
		);
		$options['connected']   = true;
		$options['mode']        = $options['tempmode'];
		$options['gfgamode']    = $options['tempmode'];
		$options['ga4_account'] = array(
			'account_id'       => sanitize_text_field( rgpost( 'account_id' ) ),
			'account_name'     => sanitize_text_field( rgpost( 'account_name' ) ),
			'property_id'      => sanitize_text_field( rgpost( 'property_id' ) ),
			'property_name'    => sanitize_text_field( rgpost( 'property_name' ) ),
			'data_stream_name' => sanitize_text_field( rgpost( 'data_stream_name' ) ),
			'measurement_id'   => sanitize_text_field( rgpost( 'measurement_id' ) ),
		);

		return $options;
	}

	/**
	 * Saves analytics data to settings and provides redirect callback.
	 *
	 * @since  1.0.0
	 */
	public function ajax_save_google_tag_manager_data() {

		// Verify nonce, capability and API connection.
		$this->verify_ajax_nonce();
		$this->verify_capability();
		$this->verify_api_connection();

		$options = $this->get_tag_manager_options( self::get_options() );

		// Save Google Tag Manager data.
		$this->log_debug( __METHOD__ . '(): Saving Google Tag Manager:' . print_r( $options, true ) );
		$this->update_plugin_settings( $options );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'gf_settings',
				'subview' => 'gravityformsgoogleanalytics',
			),
			admin_url( 'admin.php' )
		);
		wp_send_json_success( esc_url_raw( $redirect_url ) );
	}

	/**
	 * Creates an array with Tag Manager settings to be saved.
	 *
	 * @since 2.0.0
	 *
	 * @param array $options Current options.
	 *
	 * @return array Returns an array with the settings set.
	 */
	public function get_tag_manager_options( $options ) {

		$options['auth_token']  = array(
			'token'        => sanitize_text_field( rgpost( 'token' ) ),
			'refresh'      => sanitize_text_field( rgpost( 'refresh' ) ),
			'date_created' => time(),
		);
		$options['mode']        = 'gtm';
		$options['gfgamode']    = $options['mode'];
		$options['connected']   = true;
		$options['ga4_account'] = array(
			'account_id'         => sanitize_text_field( rgpost( 'account_id' ) ),
			'account_name'       => sanitize_text_field( rgpost( 'account_name' ) ),
			'property_id'        => sanitize_text_field( rgpost( 'property_id' ) ),
			'property_name'      => sanitize_text_field( rgpost( 'property_name' ) ),
			'data_stream_name'   => sanitize_text_field( rgpost( 'data_stream_name' ) ),
			'measurement_id'     => sanitize_text_field( rgpost( 'measurement_id' ) ),
			'gtm_account_id'     => sanitize_text_field( rgpost( 'gtm_account_id' ) ),
			'gtm_account_name'   => sanitize_text_field( rgpost( 'gtm_account_name' ) ),
			'gtm_container_id'   => sanitize_text_field( rgpost( 'gtm_container' ) ),
			'gtm_workspace_id'   => sanitize_text_field( rgpost( 'gtm_workspace_id' ) ),
			'gtm_workspace_name' => sanitize_text_field( rgpost( 'gtm_workspace_name' ) ),
			'gtm_api_path'       => sanitize_text_field( rgpost( 'gtm_api_path' ) ),
		);

		return $options;
	}

	/**
	 * Saves manual configuration data to settings and provides redirect callback.
	 *
	 * @since  2.0.0
	 */
	public function ajax_save_manual_configuration_data() {

		// Verify nonce and capability.
		$this->verify_ajax_nonce();
		$this->verify_capability();

		$options = $this->get_manual_configuration_options( self::get_options() );

		$this->log_debug( __METHOD__ . '(): Saving manual configuration settings:' . print_r( $options, true ) );
		$this->update_plugin_settings( $options );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'gf_settings',
				'subview' => 'gravityformsgoogleanalytics',
				'updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_send_json_success( esc_url_raw( $redirect_url ) );
	}

	/**
	 * Creates an array with the manual configuration settings to be saved.
	 *
	 * @since 2.0.0
	 *
	 * @param array $options Current options.
	 *
	 * @return array Returns an array with the settings set.
	 */
	public function get_manual_configuration_options( $options ) {

		$options['mode']        = sanitize_text_field( rgpost( 'mode' ) );
		$options['connected']   = false;
		$options['ga4_account'] = array(
			'measurement_id'   => sanitize_text_field( rgpost( 'measurement_id' ) ),
			'gmp_api_secret'   => sanitize_text_field( rgpost( 'gmp_api_secret' ) ),
			'gtm_container_id' => sanitize_text_field( rgpost( 'gtm_container' ) ),
			'gtm_workspace_id' => sanitize_text_field( rgpost( 'gtm_workspace_id' ) ),
		);

		return $options;
	}

	private function validate_ajax_nonce() {
		if ( ! wp_verify_nonce( rgar( $_REQUEST, 'nonce' ), 'gforms_google_analytics_confirmation' ) ) {
			$this->log_debug( __METHOD__ . '(): Nonce verification was not successful.' );
			wp_send_json_error( new WP_Error( 'google_analytics_nonce_error', esc_html__( 'Nonce validation failed.', 'gravityformsgoogleanalytics' ) ) );
		}
	}

	/**
	 * When a page is redirected, check if event is already sent via Ajax.
	 *
	 * @since  1.0.0
	 */
	public function ajax_get_entry_meta() {
		// Validate nonce.
		$this->validate_ajax_nonce();

		// Getting paramters.
		$entry_id = absint( rgpost( 'entry_id' ) );
		$feed_id  = absint( rgpost( 'feed_id' ) );

		// Checking if event for entry id and feed id has been sent.
		$feeds_sent = gform_get_meta( $entry_id, 'googleanalytics_feeds_sent' );
		$has_sent   = is_array( $feeds_sent ) && in_array( $feed_id, $feeds_sent );

		wp_send_json_success( array( 'event_sent' => $has_sent ) );
	}

	/**
	 * When a page is redirected, save entry meta to avoid duplicate events.
	 *
	 * @since  1.0.0
	 */
	public function ajax_save_entry_meta() {
		// Validate nonce.
		$this->validate_ajax_nonce();


		// Getting paramters.
		$entry_id = absint( rgpost( 'entry_id' ) );
		$feed_id  = absint( rgpost( 'feed_id' ) );

		// Adding current feed to meta and saving.
		$feeds_sent = gform_get_meta( $entry_id, 'googleanalytics_feeds_sent' );
		if ( ! is_array( $feeds_sent ) ) {
			$feeds_sent = array();
		}
		$feeds_sent[] = $feed_id;
		gform_update_meta( $entry_id, 'googleanalytics_feeds_sent', $feeds_sent );

		wp_send_json_success( array( 'meta_saved' => true ) );
	}

	/**
	 * Verifies nonce and sends a JSON error if unsuccessful.
	 *
	 * @since 2.0.0
	 *
	 * @param string $nonce_action The nonce action to be verified.
	 *
	 * @return void
	 */
	private function verify_ajax_nonce( $nonce_action = 'connect_google_analytics' ) {
		if ( ! wp_verify_nonce( rgpost( 'nonce' ), $nonce_action ) ) {
			$this->log_debug( __METHOD__ . '(): Nonce validation failed.' );
			wp_send_json_error( new WP_Error( 'google_analytics_ga_error', wp_strip_all_tags( __( 'Nonce validation has failed.', 'gravityformsgoogleanalytics' ) ) ) );
		}
	}

	/**
	 * Verifies Gravity Forms capabilities and sends a JSON error if user doesn't have required permission.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function verify_capability() {
		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			$this->log_debug( __METHOD__ . '(): Permissions for form settings not met.' );
			wp_send_json_error( new WP_Error( 'google_analytics_ga_error', wp_strip_all_tags( __( 'User does not have required permissions to setup Google Analytics.', 'gravityformsgoogleanalytics' ) ) ) );
		}
	}

	/**
	 * Attempts to initialize Google Analytics API and sends a JSON error if unsuccessful.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function verify_api_connection() {
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Unable to initialize Google Anaylics API.' );
			wp_send_json_error( new WP_Error( 'google_analytics_ga_error', wp_strip_all_tags( __( 'Unable to initialize Google Anaylics API.', 'gravityformsgoogleanalytics' ) ) ) );
		}
	}

	/**
	 * Append event data to the URL.
	 *
	 * @since  2.0.0
	 *
	 * @param string|array $confirmation Confirmation for the form, can be a page redirect.
	 * @param object       $form         Form object.
	 * @param object       $entry        Current Entry.
	 * @param bool         $ajax         Whether the form was subitted via ajax.
	 *
	 * @return string|array $confirmation
	 */
	public function force_text_confirmation( $confirmation, $form, $entry, $ajax ) {

		// Ignore blank or spam entries
		if ( empty( $entry ) || rgar( $entry, 'status' ) === 'spam' ) {
			return $confirmation;
		}

		// Ignore confirmations that are not of type redirect and submissions not associated with a GA feed.
		if ( ! isset( $confirmation['redirect'] ) || ! $this->has_feed( $form['id'] ) ) {
			return $confirmation;
		}

		// Ignore submissions associated with Measurement Protocol. Those will be handled server side and will go throught the standard flow.
		if ( self::get_options( '', 'mode' ) === 'gmp' ) {
			return $confirmation;
		}

		// Update confirmation to handle redirect.
		$processing_message = esc_html__( 'Processing... Please wait.', 'gravityformsgoogleanalytics' );

		return "<div id='gf_{$form['id']}' class='gform_anchor' tabindex='-1'></div><div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>{$processing_message}</div></div>" .
		       '<script>
					if ( window["all_ga_events_sent"] ) {
						document.location.href="' . sanitize_url( $confirmation['redirect'] ) . '";
					}
					window.parent.addEventListener("googleanalytics/all_events_sent", function(){ document.location.href="' . sanitize_url( $confirmation['redirect'] ) . '" }); 
				</script>';
	}

	/**
	 * Remove unneeded settings.
	 *
	 * @since  1.0.0
	 */
	public function uninstall() {
		parent::uninstall();

		// Delete backed up feeds as well.
		global $wpdb;
		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s", 'gravityformsgoogleanalytics_ua_ga4_upgrade' );
		$wpdb->query( $sql );

		delete_option( 'gravityformsaddon_gravityformsgoogleanalytics_settings' );
		delete_option( 'gforms_google_analytics_goal_labels' );
		GFCache::delete( 'google_analytics_plugin_settings' );
		GFCache::delete( 'all_ga4_feeds_upgraded' );
	}

	/**
	 * Configures the settings for a connected option.
	 *
	 * Allows for disconnecting services and shows current status.
	 *
	 * @since  1.0.0
	 */
	public function settings_connection_method() {
		$options = self::get_options();

		if ( ! isset( $options['mode'] ) ) {
			$options['mode'] = 'unset';
		}
		$disconnect_uri  = esc_url_raw(
			add_query_arg(
				array(
					'page'    => 'gf_settings',
					'subview' => 'gravityformsgoogleanalytics',
					'action'  => 'gfgadisconnect',
					'nonce'   => wp_create_nonce( 'gforms_google_analytics_disconnect' ),
				),
				admin_url( 'admin.php' )
			)
		);
		$disconnect_link = sprintf( '<a href="%s" class="gfga-disconnect">%s</a> ', esc_url_raw( $disconnect_uri ), esc_html__( 'Disconnect', 'gravityformsgoogleanalytics' ) );
		if ( rgar( $options, 'is_manual' ) ) {
			echo esc_html__( 'Manual Configuration', 'gravityformsgoogleanalytics' ) . ' | ' . wp_kses_post( $disconnect_link );
		} elseif ( 'ga' === $options['mode'] ) {
			echo esc_html__( 'Google Analytics', 'gravityformsgoogleanalytics' ) . ' | ' . wp_kses_post( $disconnect_link );
		} elseif ( 'gtm' === $options['mode'] ) {
			echo esc_html__( 'Google Tag Manager', 'gravityformsgoogleanalytics' ) . ' | ' . wp_kses_post( $disconnect_link );
		} elseif ( 'gmp' === $options['mode'] ) {
			echo esc_html__( 'Google Measurement Protocol', 'gravityformsgoogleanalytics' ) . ' | ' . wp_kses_post( $disconnect_link );
		} else {
			echo esc_html__( 'Not connected', 'gravityformsgoogleanalytics' );
		}
	}

	/**
	 * Outputs the configured analitics account and data stream.
	 *
	 * @since  2.0.0
	 */
	public function settings_analytics_account() {
		$options = self::get_options( 'ga4_account' );
		if ( ! empty( $options['account_name'] ) ) {
			echo esc_html( $options['account_name'] ) . ' / ' . esc_html( $options['property_name'] ) . ' / ' . esc_html( $options['data_stream_name'] );
		}
	}

	/**
	 * Outputs the configured tag manager account information.
	 *
	 * @since  2.0.0
	 */
	public function settings_tag_manager_account() {
		$options = self::get_options( 'ga4_account' );
		if ( ! empty( $options['gtm_account_name'] ) ) {
			echo esc_html( $options['gtm_account_name'] ) . ' / ' . esc_html( $options['gtm_container_id'] ) . ' / ' . esc_html( $options['gtm_workspace_name'] );
		}
	}

	/**
	 * Outputs the configured tag manager create assets setting.
	 *
	 * @since  2.0.0
	 */
	public function settings_tag_manager_create_assets() {
		$options = self::get_options( 'ga4_account' );
		echo rgar( $options, 'gtm_create_assets' ) ? 'Yes' : 'No';
	}

	/**
	 * Outputs the configured measurement id
	 *
	 * @since  2.0.0
	 */
	public function settings_measurement_id() {
		$options = self::get_options( 'ga4_account' );
		if ( isset( $options['measurement_id'] ) ) {
			echo esc_html( $options['measurement_id'] );
		} else {
			esc_html_e( 'Measurement ID not specified.', 'gravityformsgoogleanalytics' );
		}
	}

	/**
	 * Adds the inline script containing the gforms_google_analytics_frontend_strings variable.
	 *
	 * @since 2.1
	 *
	 * @return void
	 */
	public function frontend_script_strings_callback() {
		static $done;
		if ( $done ) {
			return;
		}

		$strings = array(
			'ajaxurl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'gforms_google_analytics_confirmation' ),
			'logging_enabled' => $this->is_logging_enabled(),
			'logging_nonce'   => wp_create_nonce( 'log_google_analytics_event_sent' ),
			'ua_tracker'      => self::get_options( '', 'ua_tracker' ),
		);

		if ( function_exists( 'wp_add_inline_script' ) ) {
			wp_add_inline_script( self::FE_SCRIPT_HANDLE, sprintf( 'var %s_strings = %s;', self::FE_SCRIPT_HANDLE, json_encode( $strings ) ), 'before' );
		} else {
			wp_localize_script( self::FE_SCRIPT_HANDLE, self::FE_SCRIPT_HANDLE . '_strings', $strings );
		}

		$done = true;
	}

	/**
	 * Check if the form has an active Google Analytics feed and mode is valid.
	 *
	 * @since  1.0.0
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return bool If the script should be enqueued.
	 */
	public function frontend_script_callback( $form ) {

		if ( is_admin() ) {
			return false;
		}

		$settings = self::get_options();

		// Check for mode setting.
		if ( ! $this->is_frontend_scripts_mode_valid( $settings ) ) {
			return false;
		}

		// Load on a redirected page. Skip if measurement protocol is selected.
		$redirect_action = rgget( 'gfaction' );
		if ( $redirect_action ) {
			return true;
		}

		if ( ! $this->has_feed( $form['id'] ) && ! rgars( $form, 'gravityformsgoogleanalytics/google_analytics_pagination' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if the mode is valid for frontend scripts
	 *
	 * @param array $settings The current plugin settings.
	 *
	 * @since  1.0.0
	 *
	 * @return bool false if there is no gfgamode or it is gmp, true otherwise.
	 */
	public function is_frontend_scripts_mode_valid( $settings ) {
		$mode = rgar( $settings, 'mode' );
		if ( empty( $mode ) || $mode === 'gmp' ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the tokens from the auth payload, or settings if appropriate
	 *
	 * @since  1.0.0
	 *
	 * @param array $auth_payload the auth payload returned form Google.
	 * @param array $settings     an array of plugin settings.
	 *
	 * @return bool|array false if there is no auth payload or settings array, otherwise an array of auth tokens.
	 */
	public function maybe_get_tokens_from_auth_payload( $auth_payload, $settings ) {
		if ( empty( $auth_payload ) && ! rgar( $settings, 'auth_token' ) ) {
			return false;
		}

		$auth_tokens = array();

		if ( $auth_payload ) {
			$auth_tokens['token']   = rgar( $auth_payload, 'access_token' );
			$auth_tokens['refresh'] = rgar( $auth_payload, 'refresh_token' );
		} else {
			$auth_tokens['token']   = rgar( $settings['auth_token'], 'token' );
			$auth_tokens['refresh'] = rgar( $settings['auth_token'], 'refresh' );
		}

		return $auth_tokens;
	}

	/**
	 * Displays a Google Analytics Account box.
	 *
	 * @since  1.0.0
	 */
	public function settings_ga_select_account() {

		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Unable to initialize Google Analytics API.' );

			return false;
		}

		if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
			$this->log_debug( __METHOD__ . '(): User does not have Form Settings capability.' );

			return false;
		}

		$auth_payload = $this->plugin_settings->get_auth_payload();
		$auth_tokens  = $this->maybe_get_tokens_from_auth_payload( $auth_payload, self::get_options() );

		if ( ! $auth_tokens ) {
			$this->log_debug( __METHOD__ . '(): Unable to retrieve API Token.' );

			return false;
		}

		$response = $this->api->get_ga4_accounts();

		if ( is_wp_error( $response ) ) {
			$this->log_debug( __METHOD__ . '(): Could not retrieve Google Analytics accounts' );

			return esc_html_e( 'It appears that you do not have a Google Analytics account for the account you selected.', 'gravityformsgoogleanalytics' );
		}

		if ( ! isset( $response['accountSummaries'] ) || empty( $response['accountSummaries'] ) ) {
			$this->connect_error = true;
			$gravity_url         = admin_url( 'admin.php?page=gf_settings&subview=gravityformsgoogleanalytics' );
			?>
			<style>
                #gform-settings-save {
                    display: none;
                }
			</style>
			<p><?php esc_html_e( 'It appears that you do not have a Google Analytics account for the account you selected.', 'gravityformsgoogleanalytics' ); ?></p>
			<p><a class="button primary"
			      href="<?php echo esc_url_raw( $gravity_url ); ?>"><?php esc_html_e( 'Return to Settings', 'gravityformsgoogleanalytics' ); ?></a>
			</p>
			<?php
		} else {
			echo sprintf( '<input type="hidden" name="gfga_token" value="%s" />', esc_attr( rgar( $auth_tokens, 'token' ) ) );
			echo sprintf( '<input type="hidden" name="gfga_refresh" value="%s" />', esc_attr( rgar( $auth_tokens, 'refresh' ) ) );
			echo '<select name="gaproperty" id="gaproperty">';
			echo '<option value="">' . esc_html__( 'Select an account', 'gravityformsgoogleanalytics' ) . '</option>';
			foreach ( $response['accountSummaries'] as $account ) {
				?>
				<optgroup label="<?php echo esc_attr( $account['displayName'] ); ?>">
					<?php
					if ( ! isset( $account['propertySummaries'] ) || empty( $account['propertySummaries'] ) ) {
						printf( '<option disabled="disabled">%s</option>', esc_html__( 'This account does not have any properties...', 'gravityformsgoogleanalytics' ) );
						continue;
					}
					foreach ( $account['propertySummaries'] as $property ) {
						printf(
							'<option value="%s" data-account-id="%s" data-account-name="%s" data-property-name="%s">%s</option>',
							esc_attr( $property['property'] ),
							esc_attr( $account['account'] ),
							esc_html( $account['displayName'] ),
							esc_html( $property['displayName'] ),
							esc_html( $property['displayName'] )
						);
					}
					?>
				</optgroup>
				<?php
			}
			echo '</select>';
			?>
			<br/>
			<div id="ga-data-streams"></div>
			<?php
		}
	}


	/**
	 * Displays a Google Tag Manager box.
	 *
	 * @since  1.0.0
	 */
	public function settings_gtm_select() {
		$auth_payload = $this->plugin_settings->get_auth_payload();
		if ( $this->initialize_api() || empty( $auth_payload ) ) {
			if ( ! $this->current_user_can_any( $this->_capabilities_form_settings ) ) {
				return;
			}

			$auth_tokens = $this->maybe_get_tokens_from_auth_payload( $auth_payload, self::get_options() );

			if ( ! $auth_tokens ) {
				return false;
			}

			$token   = rgar( $auth_tokens, 'token' );
			$refresh = rgar( $auth_tokens, 'refresh' );


			$body = array();

			$response = $this->api->get_tag_manager_account( $body );
			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}

			if ( isset( $response['account'] ) ) {
				echo sprintf( '<input type="hidden" name="gfga_token" value="%s" />', esc_attr( $token ) );
				echo sprintf( '<input type="hidden" name="gfga_refresh" value="%s" />', esc_attr( $refresh ) );
				echo '<select name="gtmproperty" id="gtmproperty">';
				echo '<option value="">' . esc_html__( 'Select a Tag Manager Account', 'gravityformsgoogleanalytics' ) . '</option>';

				$google_tag_manager_array = array();

				foreach ( $response['account'] as $account ) {
					$google_tag_manager_array[ $account['name'] ] = array();
					?>
					<option data-account-name="<?php echo esc_attr( $account['name'] ); ?>"
					        data-account-id="<?php echo esc_attr( $account['accountId'] ); ?>"
					        data-token="<?php echo esc_attr( rgar( $auth_payload, 'access_token' ) ); ?>"
					        value="<?php echo esc_attr( $account['name'] ); ?>"><?php echo esc_attr( $account['name'] ); ?></option>
					<?php
				}

				echo '</select>';
				?>
				<br/>
				<div id="gtm-containers"></div>
				<br/>
				<div id="gtm-workspaces"></div>
				<?php

				return;
			}

			// No GTM installed - Display a message.
			$this->connect_error = true;
			$gravity_url         = admin_url( 'admin.php?page=gf_settings&subview=gravityformsgoogleanalytics' );
			?>
			<style>
                #gform-settings-save {
                    display: none;
                }
			</style>
			<p><?php esc_html_e( 'It appears that you do not have a Google Tag Manager account for the account you selected.', 'gravityformsgoogleanalytics' ); ?></p>
			<p><a class="button primary"
			      href="<?php echo esc_url_raw( $gravity_url ); ?>"><?php esc_html_e( 'Return to Settings', 'gravityformsgoogleanalytics' ); ?></a>
			</p>
			<?php
		}
	}

	/**
	 * Get Tag Manager Triggers.
	 *
	 * @since 2.0
	 *
	 * @param array  $body      The add-on's options.
	 * @param string $path      The path for the api request.
	 * @param string $workspace The workspace ID.
	 *
	 * @return array Array of triggers
	 */
	public function get_tag_manager_triggers( $body, $path, $workspace ) {
		if ( $this->initialize_api() ) {
			$triggers = $this->api->get_tag_manager_triggers( array(), $path, $workspace );

			return $triggers;
		}

		return array();
	}

	/**
	 * Get Tag Manager Variables.
	 *
	 * @since 2.0
	 *
	 * @param array  $body      The add-on's options.
	 * @param string $path      The path for the api request.
	 * @param string $workspace The workspace ID.
	 *
	 * @return array Array of variables.
	 */
	public function get_tag_manager_variables( $body, $path, $workspace ) {
		if ( $this->initialize_api() ) {
			$variables = $this->api->get_tag_manager_variables( array(), $path, $workspace );

			return $variables;
		}

		return array();
	}

	/**
	 * Sets a nonce for Google Analytics and GTM
	 *
	 * @since  1.0.0
	 */
	public function settings_nonce_connect() {
		echo sprintf( '<input type="hidden" name="gfganonce" value="%s" />', esc_attr( wp_create_nonce( 'connect_google_analytics' ) ) );
	}

	/**
	 * Sets an action variable for Google Analytics.
	 *
	 * @since  1.0.0
	 */
	public function settings_ga_action() {
		echo '<input type="hidden" name="gfgaaction" value="ga" />';
	}

	/**
	 * Sets an action variable for Google Tag Manager.
	 *
	 * @since  1.0.0
	 */
	public function settings_gtm_action() {
		echo '<input type="hidden" name="gfgaaction" value="gtm" />';
	}

	/**
	 * Sets an action variable for manual configuration.
	 *
	 * @since  1.0.0
	 */
	public function settings_manual_action() {
		echo '<input type="hidden" name="gfgaaction" value="manual" />';
	}

	/**
	 * Update auth tokens.
	 *
	 * @since  1.0.0
	 */
	public function plugin_settings_page() {

		$this->plugin_settings->maybe_update_auth_tokens();
		$this->plugin_settings->maybe_display_settings_updated();

		parent::plugin_settings_page();

	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @since  1.0.0
	 */
	public function plugin_settings_fields() {
		return $this->plugin_settings->get_fields();
	}

	/**
	 * Get the Google Analytics UA Code
	 *
	 * @since 1.0.0
	 *
	 * @return string/bool Returns string UA code, false otherwise
	 */
	private function get_measurement_id() {
		return self::get_options( 'ga4_account', 'measurement_id' );
	}

	/**
	 * Call form_settings from the form settings class.
	 *
	 * @since  1.0.0
	 *
	 * @param array $form The current form.
	 */
	public function form_settings( $form ) {
		return $this->form_settings->form_settings_page( $form );
	}

	/**
	 * Call form_settings_fields from the form settings class.
	 *
	 * @since  1.0.0
	 *
	 * @param array $form The current form.
	 */
	public function form_settings_fields( $form ) {
		return $this->form_settings->pagination_form_settings( $form );
	}

	/**
	 * Call feed_settings_fields from the form settings class.
	 *
	 * @since  1.0.0
	 */
	public function feed_settings_fields() {
		return $this->form_settings->get_feed_settings_fields();
	}

	/**
	 * Call feed_list_columns from the form settings class.
	 *
	 * @since  1.0.0
	 */
	public function feed_list_columns() {
		return $this->form_settings->feed_list_columns();
	}

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		$options = self::get_options();

		if ( rgar( $options, 'is_manual' ) === true ) {
			return true;
		}

		if ( $this->initialize_api() ) {
			return true;
		}

		return false;
	}

	/**
	 * Processes the feed.
	 *
	 * @since  1.0.0
	 *
	 * @param array $feed  The feed to process.
	 * @param array $entry The entry to process.
	 * @param array $form  The form the feed is coming from.
	 */
	public function process_feed( $feed, $entry, $form ) {
		$mode       = self::get_options( '', 'mode' );
		$parameters = $this->get_mapped_parameters( $feed, 'submission_parameters', $form, $entry );
		$is_ajax    = isset( $_REQUEST['gform_ajax'] ) ? true : false;
		$event_name = $this->get_submission_event_name( $mode, $feed, $entry, $form );

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && $mode !== 'gmp' ) {
			$this->add_event_to_rest_response( $entry, $feed, $form, $mode, $parameters, $is_ajax, $event_name );

			return $entry;
		}

		switch ( $mode ) {
			case 'gmp':
				// Send events via server side (measurement protocol)
				$ua_codes = $this->get_ga_codes( $form, $entry, $feed );
				$this->send_measurement_protocol( $ua_codes, $parameters, $event_name, $entry['source_url'] );
				break;

			case 'ga':
				$this->log_debug( __METHOD__ . '(): Attempting to send event via Google Analytics. Event Name: ' . $event_name . '. Page URL: ' . $entry['source_url'] . '. Parameters: ' . print_r( $parameters, true ) );
				$this->render_script_feed_counter();
				$this->render_script_send_unique_to_ga( $entry['id'], $feed['id'], $parameters, $event_name, $is_ajax );
				break;

			case 'gtm':
				$trigger_name = rgar( $feed['meta'], 'submission_trigger' ) !== 'gf_custom' ? rgar( $feed['meta'], 'submission_trigger' ) : rgar( $feed['meta'], 'submission_trigger_custom' );
				$this->log_debug( __METHOD__ . '(): Attempting to send event via Google Tag Manager. Trigger Name: ' . $trigger_name . '. Page URL: ' . $entry['source_url'] . '. Parameters: ' . print_r( $parameters, true ) );
				$this->render_script_feed_counter();
				$this->render_script_send_unique_to_gtm( $entry['id'], $feed['id'], $parameters, $trigger_name, $is_ajax );
				break;
		}

		return $entry;
	}

	/**
	 * Adds the event data to a REST response object in lieu of echoing it in the response.
	 *
	 * @since 2.1.0
	 *
	 * @param $entry
	 * @param $feed
	 * @param $form
	 * @param $mode
	 * @param $parameters
	 * @param $is_ajax
	 * @param $event_name
	 *
	 * @return void
	 */
	protected function add_event_to_rest_response( $entry, $feed, $form, $mode, $parameters, $is_ajax, $event_name ) {
		$details = array();

		switch ( $mode ) {
			case 'ga':
				$details = array(
					'entry_id'   => $entry['id'],
					'feed_id'    => $feed['id'],
					'parameters' => $parameters,
					'event_name' => $event_name,
				);
				break;

			case 'gtm':
				$trigger_name = rgar( $feed['meta'], 'submission_trigger' ) !== 'gf_custom' ? rgar( $feed['meta'], 'submission_trigger' ) : rgar( $feed['meta'], 'submission_trigger_custom' );
				$details      = array(
					'entry_id'     => $entry['id'],
					'feed_id'      => $feed['id'],
					'parameters'   => $parameters,
					'trigger_name' => $trigger_name,
				);
				break;
		}

		add_filter( 'rest_request_after_callbacks', function ( $response ) use ( $details ) {
			$response->data['ga_event'] = $details;

			return $response;
		} );
	}

	/***
	 * Determines the name of the event to be sent to Google Analytics or Measurement Protocol.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode  Google analytics connection mode. "ga" for Analytics, "gtm" for Tag Manager, "gmp" for Management Protocol.
	 * @param array  $feed  Current feed object.
	 * @param array  $entry Current entry object.
	 * @param array  $form  Current form object.
	 *
	 * @return string Returns the event name that will be sent to track the event.
	 */
	private function get_submission_event_name( $mode, $feed, $entry, $form ) {

		/***
		 * Filters the name of the submission event to be sent to Google Analytics. This filter only applies to Google Analytics or Measurement Protocol (not Tag Manger)
		 *
		 * @since 2.0.0
		 *
		 * @param string $event_name Event name being filtered.
		 * @param string $mode       Google analytics connection mode. "ga" for Analytics, "gtm" for Tag Manager, "gmp" for Management Protocol.
		 * @param array  $feed       Current feed object.
		 * @param array  $entry      Current entry object.
		 * @param array  $form       Current form object.
		 */
		return apply_filters( 'gform_googleanalytics_submission_event_name', 'gforms_submission', $mode, $feed, $entry, $form );
	}

	/***
	 * Determines the name of the pagination event to be sent to Google Analytics or Measurement Protocol.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode Google analytics connection mode. "ga" for Analytics and "gmp" for Measurement Protocol.
	 * @param array  $form Current form object.
	 *
	 * @return string Returns the event name.
	 */
	private function get_pagination_event_name( $mode, $form ) {

		/***
		 * Filters the name of the pagination event to be sent to Google Analytics. This filter only applies to Google Analytics or Measurement Protocol (not Tag Manger)
		 *
		 * @since 2.0.0
		 *
		 * @param string $event_name Event name being filtered.
		 * @param string $mode       Google analytics connection mode. "ga" for Analytics, "gtm" for Tag Manager, "gmp" for Management Protocol.
		 * @param array  $form       Current form object.
		 */
		return apply_filters( 'gform_googleanalytics_pagination_event_name', 'gforms_pagination', $mode, $form );
	}

	/**
	 * Renders the script to send an event to Google Analytics, making sure the event is not sent if it has been sent before.
	 *
	 * @since 2.0
	 *
	 * @param int    $entry_id   The current entry id.
	 * @param int    $feed_id    The current feed id.
	 * @param array  $parameters An array of event parameters to be sent.
	 * @param string $event_name The event name to be recorded in Google Analytics.
	 * @param bool   $is_ajax    Wether or not the form submission was done with AJAX enabled.
	 *
	 */
	private function render_script_send_unique_to_ga( $entry_id, $feed_id, $parameters, $event_name, $is_ajax ) {

		$body = "window.parent.GF_Google_Analytics.send_unique_to_ga( '" . esc_js( $entry_id ) . "', '" . esc_js( $feed_id ) . "',  " . wp_json_encode( $parameters ) . ", '" . esc_js( $event_name ) . "' );";
		$this->render_script_wrapper( $body, $is_ajax );
	}

	/**
	 * Renders the script to send an event to Google Tag Manager, making sure the event is not sent if it has been sent before.
	 *
	 * @since 2.0
	 *
	 * @param int    $entry_id     The current entry id.
	 * @param int    $feed_id      The current feed id.
	 * @param array  $parameters   An array of event parameters to be sent.
	 * @param string $trigger_name The trigger name to be associated with this event in Tag Manager.
	 * @param bool   $is_ajax      Wether or not the form submission was done with AJAX enabled.
	 *
	 */
	private function render_script_send_unique_to_gtm( $entry_id, $feed_id, $parameters, $trigger_name, $is_ajax ) {

		$body = "window.parent.GF_Google_Analytics.send_unique_to_gtm( '" . esc_js( $entry_id ) . "', '" . esc_js( $feed_id ) . "',  " . wp_json_encode( $parameters ) . ", '" . esc_js( $trigger_name ) . "' );";
		$this->render_script_wrapper( $body, $is_ajax );
	}

	/**
	 * Renders the script to send an event to Google Analytics.
	 *
	 * @since 2.0
	 *
	 * @param array  $parameters An array of event parameters to be sent.
	 * @param string $event_name The event name to be recorded in Google Analytics.
	 * @param bool   $is_ajax    Wether or not the form submission was done with AJAX enabled.
	 *
	 */
	private function render_script_send_to_ga( $parameters, $event_name, $is_ajax ) {

		$body = 'window.parent.GF_Google_Analytics.send_to_ga( ' . wp_json_encode( $parameters ) . ', "' . esc_js( $event_name ) . '" );';
		$this->render_script_wrapper( $body, $is_ajax );
	}

	/**
	 * Renders the script to send an event to Google Tag Manager.
	 *
	 * @since 2.0
	 *
	 * @param array  $parameters   An array of event parameters to be sent.
	 * @param string $trigger_name The trigger name to be associated with this event in Tag Manager.
	 * @param bool   $is_ajax      Wether or not the form submission was done with AJAX enabled.
	 *
	 */
	private function render_script_send_to_gtm( $parameters, $trigger_name, $is_ajax ) {

		$body = 'window.parent.GF_Google_Analytics.send_to_gtm( ' . wp_json_encode( $parameters ) . ', "' . esc_js( $trigger_name ) . '" );';
		$this->render_script_wrapper( $body, $is_ajax );
	}

	/**
	 * Renders the wrapper script for the specified $script_body script, taking AJAX into account.
	 *
	 * @since 2.0
	 *
	 * @param string $script_body The script body to be wrapped.
	 * @param bool   $is_ajax     Wether or not the form submission was done with AJAX enabled.
	 */
	private function render_script_wrapper( $script_body, $is_ajax ) {
		?>
		<script>
			<?php
			if ( $is_ajax ) {
				echo $script_body;
			} else {
			// For non-AJAX forms, script body must be wrapped inside the googleanalytics/script_loaded so that it is
			// executed after the google analytics script is loaded.
			?>
			window.addEventListener( "googleanalytics/script_loaded", function() {
				<?php
				echo $script_body;
				?>
			} );
			<?php
			}
			?>
		</script>
		<?php
	}

	/**
	 * Renders the script that will count how many feeds were executed. This is needed so that an event can be fired after all the feeds are sent.
	 *
	 * @since 2.0
	 */
	private function render_script_feed_counter() {
		?>
		<script>
			window.parent[ 'ga_feed_count' ] = window.parent[ 'ga_feed_count' ] ? window.parent[ 'ga_feed_count' ] + 1 : 1;
		</script>
		<?php
	}

	/**
	 * Sends an event via the Measurement Protocol.
	 *
	 * @since 2.0
	 *
	 * @param array  $ga_codes   An array of GA Codes to receive the event. Each GA code here will receive the event.
	 * @param array  $parameters An array of event parameters to be sent.
	 * @param string $event_name The event name to be recorded in Google Analytics.
	 * @param string $page_url   The current page URL.
	 */
	private function send_measurement_protocol( $ga_codes, $parameters, $event_name, $page_url ) {

		// Initialize the measurement protocol.
		if ( ! class_exists( 'GF_Google_Analytics_Measurement_Protocol' ) ) {
			include_once 'includes/class-gf-google-analytics-measurement-protocol.php';
		}
		$event      = new GF_Google_Analytics_Measurement_Protocol();
		$api_secret = self::get_options( 'ga4_account', 'gmp_api_secret' );
		$event->init( $api_secret, $event_name );

		// Set document variables.
		$event->set_document_path( str_replace( home_url(), '', $page_url ) );
		$event->set_document_location( esc_url( $page_url ) );
		$event_url_parsed = wp_parse_url( home_url() );
		$event->set_document_host( $event_url_parsed['host'] );


		// Set IP address
		$event->set_user_ip_address( \GFFormsModel::get_ip() );

		// Set document title.
		global $post;
		$document_title = isset( $post ) && isset( $post->post_title ) ? sanitize_text_field( $post->post_title ) : esc_html__( 'No title found', 'gravityformsgoogleanalytics' );
		$event->set_document_title( $document_title );

		// Set submission parameters.
		$event->set_params( $parameters );

		// Begin sending events using the measurement protocol.
		foreach ( $ga_codes as $code ) {

			$this->log_debug( __METHOD__ . '(): Attempting to send event via Measurement Protocol. Secret (last 4): XXXXXX' . substr( $api_secret, - 4 ) . '. GA code: ' . $code . '. Event Name: ' . $event_name . '. Page URL: ' . $page_url . '. Parameters: ' . print_r( $parameters, true ) );

			$response = $event->send( $code );

			if ( is_wp_error( $response ) ) {
				$this->log_debug( __METHOD__ . '(): Failed to send event to Google Analytics via Measurement Protocol. Secret (last 4): XXXXXX' . substr( $api_secret, - 4 ) . '. GA code: ' . $code . '. Event Name: ' . $event_name . '. Page URL: ' . $page_url . '. Parameters: ' . print_r( $parameters, true ) );
			} else {
				$this->log_debug( __METHOD__ . '(): Successfully sent event to Google Analytics via Measurement Protocol. Secret (last 4): XXXXXX' . substr( $api_secret, - 4 ) . '. GA code: ' . $code . '. Event Name: ' . $event_name . '. Page URL: ' . $page_url . '. Parameters: ' . print_r( $parameters, true ) );
			}
		}
	}

	/**
	 * Retrieves the value for the feed item.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $feed   The Feed item.
	 * @param mixed $column The Feed column name.
	 *
	 * @return string The column value.
	 */
	public function get_column_value( $feed, $column ) {

		$value = '';

		if ( empty( $value ) ) {
			if ( isset( $feed[ $column ] ) ) {
				$value = $feed[ $column ];
			} elseif ( isset( $feed['meta'][ $column ] ) ) {
				$value = $feed['meta'][ $column ];
			}
		}

		return $value;
	}

	/**
	 * Get the menu icon for this plugin.
	 *
	 * @since 1.0
	 *
	 * @return string the class for the plugin menu icon.
	 */
	public function get_menu_icon() {
		return $this->is_gravityforms_supported( '2.5-beta-4' ) ? 'gform-icon--analytics' : 'dashicons-admin-generic';
	}

	/**
	 * Log if an event is successfully sent.
	 *
	 * @since 2.1.0
	 */
	public function ajax_log_ga_event_sent() {
		$this->verify_ajax_nonce( 'log_google_analytics_event_sent' );
		$connection = rgpost( 'connection' );
		$parameters = rgpost( 'parameters' );
		if ( $connection === 'ga' ) {
			$event_name = rgpost( 'eventName' );
			$this->log_debug( __METHOD__ . '(): Completed sending event via Google Analytics. Event Name: ' . $event_name . '. Parameters: ' . print_r( $parameters, true ) );
		} elseif ( $connection = 'gtm' ) {
			$trigger_name = rgpost( 'triggerName' );
			$this->log_debug( __METHOD__ . '(): Completed sending event via Google Tag Manager. Trigger Name: ' . $trigger_name . '. Parameters: ' . print_r( $parameters, true ) );
		}
	}

}
