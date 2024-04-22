<?php
/**
 * Object responsible for organizing and constructing the form settings page.
 */

namespace Gravity_Forms\Gravity_Forms_Google_Analytics\Settings;

defined( 'ABSPATH' ) || die();

use Gravity_Forms\Gravity_Forms_Google_Analytics\GF_Google_Analytics;
use Gravity_Forms\Gravity_Forms\Settings\Settings;
use GFCommon;
use GFAddOn;
use GFAPI;
use GFFormsModel;
use GFCache;

class Form_Settings {

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
	 * Get tab attributes so correct tab appears as selected.
	 *
	 * @since 1.0
	 *
	 * @return array Array of tab attributes.
	 */
	public function get_tab_attributes() {
		$active_attrs   = 'aria-selected=true class=active';
		$inactive_attrs = 'aria-selected=false';

		$tab_attributes = array();

		if ( rgget( 'settingstype' ) == 'form' ) {
			$tab_attributes['current_tab']     = 'form_settings';
			$tab_attributes['feed_link_attrs'] = $inactive_attrs;
			$tab_attributes['form_link_attrs'] = $active_attrs;
		} else {
			$tab_attributes['current_tab']     = 'feed';
			$tab_attributes['feed_link_attrs'] = $active_attrs;
			$tab_attributes['form_link_attrs'] = $inactive_attrs;
		}

		return $tab_attributes;
	}

	/**
	 * Display the form settings page.
	 *
	 * This form settings page has tabs for the feed settings and the form settings.
	 *
	 * @since 1.0
	 *
	 * @param array $form the current form.
	 */
	public function form_settings_page( $form ) {

		// Set up the data we need to display the tabs.
		$tab_attributes = $this->get_tab_attributes();

		// Remove the feed id from the form settings url to ensure correct saving.
		$form_settings_params = $_GET;
		unset( $form_settings_params['fid'] );
		$feed_settings_url = http_build_query( array_merge( $_GET, array( 'settingstype' => 'feed' ) ) );
		$form_settings_url = http_build_query( array_merge( $form_settings_params, array( 'settingstype' => 'form' ) ) );

		// Display the navigation tabs.
		echo '<nav class="gform-settings-tabs__navigation" role="tablist" style="margin-bottom:.875rem">
			<a role="tab" href="' . admin_url( 'admin.php?' . esc_html( $feed_settings_url ) ) . '" ' . esc_attr( $tab_attributes['feed_link_attrs'] ) . '>' . esc_html__( 'Feed Settings', 'gravityformsgoogleanalytics' ) . '</a>
			<a role="tab" href="' . admin_url( 'admin.php?' . esc_html( $form_settings_url ) ) . '" ' . esc_attr( $tab_attributes['form_link_attrs'] ) . '>' . esc_html__( 'Form Settings', 'gravityformsgoogleanalytics' ) . '</a>
		</nav>';

		// Display the tab contents.
		if ( 'form_settings' == $tab_attributes['current_tab'] ) {

			if ( ! $this->addon->can_create_feed() ) {
				printf( '<div>%s</div>', $this->addon->configure_addon_message() );
				return;
			}
			// Get fields.
			$sections = array_values( $this->addon->form_settings_fields( $form ) );
			$sections = $this->addon->prepare_settings_sections( $sections, 'form_settings' );
			$renderer = new Settings(
				array(
					'capability'     => $this->_capabilities_form_settings,
					'fields'         => $sections,
					'initial_values' => GF_Google_Analytics::get_instance()->get_form_settings( $form ),
					'save_callback'  => function( $values ) use ( $form ) {
						$this->save_form_settings( $form, $values );
					},
					'before_fields'  => function() use ( $form ) {
						?>

						<script type="text/javascript">

							gform.addFilter( 'gform_merge_tags', 'addPaginationMergeTags' );

							function addPaginationMergeTags( mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option ) {
								mergeTags[ 'other' ].tags.push( {
									tag: '{source_page_number}',
									label: <?php echo json_encode( __( 'Source Page Number', 'gravityforms' ) ) ?> } );
								mergeTags[ 'other' ].tags.push( {
									tag: '{current_page_number}',
									label: <?php echo json_encode( __( 'Current Page Number', 'gravityforms' ) ) ?> } );

								return mergeTags;
							}
						</script>
						<?php

					},
					'after_fields'   => function() use ( $form ) {

						printf(
							'<script type="text/javascript">var form = %s;</script>',
							wp_json_encode( $form )
						);
					},
				)
			);
			$renderer->render();
		} else {
			if ( $this->addon->is_detail_page() ) {
				// Feed edit page.
				$feed_id = $this->addon->get_current_feed_id();
				$this->addon->feed_edit_page( $form, $feed_id );
			} else {
				// Feed list UI.
				$this->addon->feed_list_page( $form );
			}
		}

	}

	/***
	 * Saves form settings to form object.
	 *
	 * @since 1.0
	 *
	 * @param array $form the current form.
	 * @param array $settings the settings to save.
	 *
	 * @return true|false True on success or false on error
	 */
	public function save_form_settings( $form, $settings ) {
		$form[ $this->addon->get_slug() ] = $settings;
		$result                           = GFFormsModel::update_form_meta( $form['id'], $form );

		return ! ( false === $result );
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function get_feed_settings_fields() {
		$form_id = rgget( 'id' );
		$form    = GFAPI::get_form( $form_id );

		return array(
			array(
				'title'  => esc_html__( 'Feed Name', 'gravityformsgoogleanalytics' ),
				'fields' => array(
					array(
						'label'    => esc_html__( 'Feed Name', 'gravityformsgoogleanalytics' ),
						'type'     => 'text',
						'name'     => 'feedName',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<strong>' . esc_html__( 'Feed Name', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'Enter a feed name to uniquely identify this feed.', 'gravityformsgoogleanalytics' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Event Parameters', 'gravityformsgoogleanalytics' ),
				'fields' => $this->get_ga4_event_fields( $form, 'feed' ),
			),
			array(
				'title'  => __( 'Conditional Logic Settings', 'gravityformsgoogleanalytics' ),
				'fields' => array(
					array(
						'name'    => 'conditionalLogic',
						'label'   => esc_html__( 'Conditional Logic', 'gravityformsgoogleanalytics' ),
						'type'    => 'feed_condition',
						'tooltip' => '<strong>' . __( 'Conditional Logic', 'gravityformsgoogleanalytics' ) . '</strong>' . esc_html__( 'When conditions are enabled, conversions will only be sent when the conditions are met.', 'gravityformsgoogleanalytics' ),
					),
				),
			),
		);
	}

	/**
	 * Get event parameter fieldmap field.
	 *
	 * @since 2.0
	 *
	 * @param array  $form The current form object.
	 * @param string $type Whether we're rendering feed or pagination fields.
	 *
	 * @return array Array of fields.
	 */
	public function get_ga4_event_fields( $form, $type ) {
		if ( $type !== 'pagination' && $type !== 'feed' ) {
			return;
		}
		$options = $this->addon->get_options();

		$mode   = rgar( $options, 'mode' );
		$prefix = ( $type === 'pagination' ) ? 'pagination_' : 'submission_';

		if ( $mode !== 'gtm' ) {
			return array(
				array(
					'name'                => $prefix . 'parameters',
					'description'         => esc_html__( 'Parameter names must be 40 characters or fewer and are limited to alphanumeric characters and underscores. They cannot begin or end with an underscore. Values are limited to 100 characters or fewer.', 'gravityformsgoogleanalytics' ),
					'label'               => esc_html__( 'Parameters', 'gravityformsgoogleanalytics' ),
					'type'                => 'generic_map',
					'tooltip'             => '<h6>' . esc_html__( 'Submission Parameters', 'gravityformsgoogleanalytics' ) . '</h6>' . esc_html__( 'Set the parameters that will be sent to Google Analytics for this feed.', 'gravityformsgoogleanalytics' ),
					'limit'               => 25,
					'key_field'           => array(
						'title'        => esc_html__( 'Parameter Name', 'gravityformsgoogleanalytics' ),
						'allow_custom' => true,
						'placeholder'  => esc_html__( 'Enter a parameter name', 'gravityformsgoogleanalytics' ),
					),
					'value_field'         => array(
						'title'        => esc_html__( 'Parameter Value', 'gravityformsgoogleanalytics' ),
						'allow_custom' => true,
						'placeholder'  => esc_html__( 'Enter a value', 'gravityformsgoogleanalytics' ),
					),
					'validation_callback' => array( $this, 'custom_parameter_validation_callback' ),
				),
			);
		} else {

			$trigger_choices  = $this->get_tag_manager_trigger_choices( $options );
			$variable_choices = $this->get_tag_manager_variable_choices( $options );

			if ( empty( $trigger_choices ) ) {
				$trigger_setting = array(
					'name'     => $prefix . 'trigger',
					'type'     => 'text',
					'label'    => esc_html__( 'Tag Manager Trigger', 'gravityformsgoogleanalytics' ),
					'tooltip'  => '<h6>' . esc_html__( 'Tag Manager Trigger', 'gravityformsgoogleanalytics' ) . '</h6>' . esc_html__( 'Set the trigger that will be sent to tag manager when this feed is processed.', 'gravityformsgoogleanalytics' ),
					'required' => true,
				);
			} else {
				$trigger_setting = array(
					'name'     => $prefix . 'trigger',
					'type'     => 'select_custom',
					'label'    => esc_html__( 'Tag Manager Trigger', 'gravityformsgoogleanalytics' ),
					'choices'  => $trigger_choices,
					'tooltip'  => '<h6>' . esc_html__( 'Tag Manager Trigger', 'gravityformsgoogleanalytics' ) . '</h6>' . esc_html__( 'Set the trigger that will be sent to tag manager when this feed is processed.', 'gravityformsgoogleanalytics' ),
					'required' => true,
				);
			}

			return array(
				$trigger_setting,
				array(
					'name'                => $prefix . 'parameters',
					'label'               => esc_html__( 'Parameters', 'gravityformsgoogleanalytics' ),
					'type'                => 'generic_map',
					'tooltip'             => '<h6>' . esc_html__( 'Submission Parameters', 'gravityformsgoogleanalytics' ) . '</h6>' . esc_html__( 'Set the parameters that will be sent to Google Analytics for this feed.', 'gravityformsgoogleanalytics' ),
					'limit'               => 25,
					'key_field'           => array(
						'title'        => esc_html__( 'Parameter Name', 'gravityformsgoogleanalytics' ),
						'allow_custom' => true,
						'placeholder'  => esc_html__( 'Enter a parameter name', 'gravityformsgoogleanalytics' ),
						'choices'      => $variable_choices,
					),
					'value_field'         => array(
						'title'        => esc_html__( 'Parameter Value', 'gravityformsgoogleanalytics' ),
						'allow_custom' => true,
						'placeholder'  => esc_html__( 'Enter a value', 'gravityformsgoogleanalytics' ),
					),
				),
			);
		}
	}

	/**
	 * Callback for validation the custom parameters.
	 *
	 * @since 2.0
	 *
	 * @param object $field      The field being validated.
	 * @param array  $parameters The parameters being validated.
	 *
	 * @return void
	 */
	public function custom_parameter_validation_callback( $field, $parameters ) {

		foreach ( $parameters as $parameter ) {
			$key = 'gf_custom' === $parameter['key'] ? $parameter['custom_key'] : $parameter['key'];

			if ( 'gf_custom' === $parameter['value'] ) {
				$value = $parameter['custom_value'];
			} else {
				$value = $parameter['value'];
			}

			$field_error = $this->validate_parameter( $key, $value );

			if ( $field_error ) {
				$this->addon->set_field_error( $field, $field_error );
			}
		}

	}

	/**
	 * Parameter validation
	 *
	 * @since 2.0
	 *
	 * @param string $key   The parameter key.
	 * @param string $value The parameter value.
	 *
	 * @return string The validation error.
	 */
	public function validate_parameter( $key, $value ) {
		if ( ! is_string( $key ) || ! is_string( $value ) ) {
			return esc_html__( 'Parameter names and values must be strings.', 'gravityformsgoogleanalytics' );
		}

		if ( mb_strlen( $key ) > 40 ) {
			return esc_html__( 'Parameter names must be 40 characters or less.', 'gravityformsgoogleanalytics' );
		}

		if ( mb_strlen( $value ) > 100 ) {
			return esc_html__( 'Parameter values must be 100 characters or less.', 'gravityformsgoogleanalytics' );
		}

		if ( ! preg_match( '/^(?!_)[\w]*(?<!_)$/u', $key ) ) {
			return esc_html__( 'Parameter names cannot begin or end with an underscore, and must contain only letters, numbers, and underscores.', 'gravityformsgoogleanalytics' );
		}
	}

	/**
	 * Get tag manager trigger choices.
	 *
	 * @since 2.0
	 *
	 * @param array $options The add-on's options.
	 *
	 * @return array Array of trigger choices.
	 */
	private function get_tag_manager_trigger_choices( $options ) {
		if ( rgar( $options, 'is_manual' ) === true ) {
			return array();
		}
		$trigger_choices = \GFCache::get( 'tag_manager_trigger_choices' );
		if ( $trigger_choices ) {
			return $trigger_choices;
		}

		$ga4_account = rgar( $options, 'ga4_account' );
		$api_path    = rgar( $ga4_account, 'gtm_api_path' );
		$workspace   = rgar( $ga4_account, 'gtm_workspace_id' );
		$triggers    = rgar( $this->addon->get_tag_manager_triggers( array(), $api_path, $workspace ), 'trigger' );

		if ( is_wp_error( $triggers ) || empty( $triggers ) || ! is_array( $triggers ) ) {
			return false;
		}

		$trigger_choices = array();
		foreach ( $triggers as $trigger ) {

			$trigger_event_name = $this->get_trigger_event_name( $trigger );
			$trigger_choices[] = array(
				'label' => $trigger['name'],
				'value' => $trigger_event_name,
			);
		}
		\GFCache::set( 'tag_manager_trigger_choices', $trigger_choices );
		return $trigger_choices;
	}

	/**
	 * Get the trigger event name.
	 *
	 * @since 2.1
	 *
	 * @param array $trigger The trigger.
	 *
	 * @return string The trigger event name.
	 */
	private function get_trigger_event_name( $trigger ) {
		if ( $trigger['type'] !== 'customEvent' ) {
			return $trigger['name'];
		}

		// There's no convenient api method to get this value, but it should always be in the same place for custom events.
		$event_name = rgars( $trigger, 'customEventFilter/0/parameter/1/value' );

		if ( $event_name ) {
			return $event_name;
		} else {
			return $trigger['name'];
		}
	}

	/**
	 * Get tag manager variable choices.
	 *
	 * @since 2.0
	 *
	 * @param array $options The add-on's options.
	 *
	 * @return array Array of variable choices.
	 */
	private function get_tag_manager_variable_choices( $options ) {
		if ( rgar( $options, 'is_manual' ) === true ) {
			return array();
		}

		$variable_choices = \GFCache::get( 'tag_manager_variable_choices' );
		if ( $variable_choices ) {
			return $variable_choices;
		}

		$ga4_account = rgar( $options, 'ga4_account' );
		$api_path    = rgar( $ga4_account, 'gtm_api_path' );
		$workspace   = rgar( $ga4_account, 'gtm_workspace_id' );

		$variables      = rgar( $this->addon->get_tag_manager_variables( array(), $api_path, $workspace ), 'variable' );
		$variable_choices = array();

		if ( rgempty( $variables ) ) {
			return $variable_choices;
		}

		foreach ( $variables as $variable ) {
			if ( rgar( $variable, 'type' ) !== 'v' ) {
				continue;
			}
			$variable_choices[] = array(
				'label' => $variable['name'],
				'value' => $this->get_variable_value( $variable ),
			);
		}
		\GFCache::set( 'tag_manager_variable_choices', $variable_choices );
		return $variable_choices;
	}

	/**
	 * Get the value of the tag manager variable
	 *
	 * @since 2.0
	 *
	 * @param array $variable The variable from which to retrieve the value.
	 *
	 * @return string Returns the variable's value or a blank string if the variable isn't found.
	 */
	public function get_variable_value( $variable ) {
		foreach ( $variable['parameter'] as $parameter ) {
			if ( rgar( $parameter, 'key' ) === 'name' ) {
				return rgar( $parameter, 'value' );
			}
		}

		return '';
	}

	/**
	 * Configures the columns for the feed page.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		$columns = array( 'feedName' => esc_html__( 'Name', 'gravityformsgoogleanalytics' ) );

		if ( $this->addon->get_options( '', 'mode' ) == 'gtm' ) {
			$columns['submission_trigger'] = esc_html__( 'Trigger', 'gravityformsgoogleanalytics' );
		}

		return $columns;
	}

	/**
	 * Add pagination form settings to Gravity Forms.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form     The form.
	 *
	 * @return array Updated form settings
	 */
	public function pagination_form_settings( $form ) {
		if ( rgget( 'settingstype' ) !== 'form' ) {
			return array();
		}

		if ( isset( $form['pagination'] ) ) {
			return array(
				array(
					'title'  => esc_html__( 'Form Settings', 'gravityformsgoogleanalytics' ),
					'fields' => array(
						array(
							'name'    => 'google_analytics_pagination',
							'label'   => esc_html__( 'Pagination Tracking', 'gravityformsgoogleanalytics' ),
							'type'    => 'checkbox',
							'choices' => array(
								array(
									'label' => esc_html__( 'Enable pagination tracking', 'gravityformsgoogleanalytics' ),
									'name'  => 'google_analytics_pagination',
								),
							),
						),
					),
				),
				array(
					'title'      => esc_html__( 'Event Parameters', 'gravityformsgoogleanalytics' ),
					'fields'     => $this->get_ga4_event_fields( $form, 'pagination' ),
					'dependency' => array(
						'live'   => true,
						'fields' => array(
							array(
								'field'  => 'google_analytics_pagination',
								'values' => array( '1' ),
							),
						),
					),
				),
			);
		} else {
			return array(
				array(
					'title'  => esc_html__( 'Form Settings', 'gravityformsgoogleanalytics' ),
					'fields' => array(
						array(
							'label' => esc_html__( 'Pagination Tracking', 'gravityformsgoogleanalytics' ),
							'type'  => 'html',
							'html'  => '<p>' . esc_html__( 'Add a Page field to your form to begin tracking pagination events.', 'gravityformsgoogleanalytics' ) . '</p>',
						),
					),
				),
			);
		}
	}

}
