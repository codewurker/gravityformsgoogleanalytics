( function ( GF_Google_Analytics_Admin, $ ) {
	function display_error( message ) {
		$( '.gforms_note_error' ).remove();
		$( '#gform-page-loader-mask' ).hide();
		$( '#gform-settings' ).before( '<div class="alert gforms_note_error" role="alert">' + message + '</div>' );
		$( '.alert' ).get(0).scrollIntoView();
	}
	function hide_error() {
		$( '.gforms_note_error' ).remove();
	}

	jQuery( document ).ready( function() {
		// Main form wrapper.
		let $form = $( '#gform-settings' );

		// Handle disconnects from the admin.
		let $disconnect = $( '.gfga-disconnect' );
		if ( $disconnect.length > 0 ) {
			$disconnect.on( 'click', function( e ) {
				e.preventDefault();
				$disconnect.html( google_analytics_admin_strings.disconnect );

				var url_params = wpAjax.unserialize( e.target.href );
				var nonce_value = url_params.nonce;

				// Perform Ajax request.
				$.post(
					ajaxurl,
					{
						action: 'disconnect_account',
						nonce: nonce_value,
					},
					function( response ) {
						window.location.href = window.location.href;
					}
				);
			} );
		}

		// Disable submit button and only reenable if required fields are filled out. Necessary to work with new loader.
		let $action = $form.find( 'input[name="gfgaaction"]');
		if ( $action.length !== 0 && $action.val() !== 'manual' ) {
			let $submit_button = $form.find( ':submit' );
			$submit_button.prop( 'disabled', true );
		}
		$( '#gform-settings-section-google-analytics-settings' ).on( 'change', ( 'input, select' ), function( event ) {
			let property = $form.find( 'select[name="gaproperty"]' ).find(':selected').val();
			let measurement_id = $( '#ga-data-streams select :selected' ).val();

			let gtm_container = $form.find( 'select[name="gtmproperty"]' ).find( ':selected' ).val();
			if ( ! gtm_container ) {
				gtm_container = $form.find( '#container' ).val();
			}
			let workspace = $form.find( 'select[name="gaworkspace"]' ).val();
			if ( ! workspace ) {
				workspace = $form.find( '#workspace' ).val();
			}

			let $submit_button = $form.find( ':submit' );
			if ( 'ga' === $action.val() ) {
				if ( property && measurement_id ) {
					$submit_button.prop( 'disabled', false );
				} else {
					$submit_button.prop( 'disabled', true );
				}
			}
			if ( 'gtm' === $action.val() ) {
				if ( gtm_container && workspace ) {
					$submit_button.prop( 'disabled', false );
				} else {
					$submit_button.prop( 'disabled', true );
				}
			}
		})

		// Handle form submission on connect screen
		$form.on( 'submit', function( e ) {

			// Hide error message if there is one being displayed
			hide_error();

			// Determining if we're just connecting for the first time.
			if ( $form.find( 'input[value="google_analytics_setup"]' ).length === 1 ) {
				// is_postback
				e.preventDefault();

				// Set l18n.
				var $save_button = $form.find( '#gform-settings-save' );
				var $mode = $form.find( '[name="_gform_setting_mode"]:checked').val();
				$save_button.prop( 'value', google_analytics_admin_strings.redirect ).prop( 'disabled', 'disabled' );

				// Get nonce.
				var nonce_value = $form.find( 'input[name="gfganonce"]').val();

				// Perform Ajax request.
				$.post(
					ajaxurl,
					{
						action: 'redirect_to_api',
						nonce: nonce_value,
						mode: $mode,
					},
					function( response ) {
						if ( ! response.data.errors ) {
							window.location.href = response.data.redirect;
						}
					},
					'json'
				);

				return;
			}

			// Determine if we're executing in the correct form
			let $action = $form.find( 'input[name="gfgaaction"]');
			if( $action.length === 0 ) {
				return;
			}

			if ( 'ga' === $action.val() ) {
				e.preventDefault();

				// Getting selected items from the account/property and data stream drop downs.
				let $selected_property = $form.find( 'select[name="gaproperty"]' ).find( ':selected' );
				let $selected_data_stream = $form.find( '#ga-data-streams select :selected' )

				$.post(
					ajaxurl,
					{
						action: 'save_google_analytics_data',
						account_id: $selected_property.data( 'account-id' ),
						account_name: $selected_property.data('account-name'),
						property_id: $selected_property.val(),
						property_name: $selected_property.data('property-name'),
						measurement_id: $selected_data_stream.val(),
						data_stream_name: $selected_data_stream.data( 'data-stream-name' ),
						data_stream_id: $selected_data_stream.data( 'data-stream-id' ),
						nonce: $form.find( 'input[name="gfganonce"]' ).val(),
						token: $form.find('input[name="gfga_token"]' ).val(),
						refresh: $form.find('input[name="gfga_refresh"]' ).val(),
					},
					function( response ) {
						if ( response.success ) {
							window.location.href = response['data'];
						} else {
							display_error( response.data[0].message );
						}
					}
				);
			}
			if ( 'gtm' === $action.val() ) {
				// We're in Google Tag Manager mode
				e.preventDefault();

				// Get Google Analytics data
				let $selected_property = $form.find( 'select[name="gaproperty"]' ).find( ':selected' );
				let $selected_data_stream = $form.find( '#ga-data-streams select :selected' )

				// Get GTM Data
				let $selected_gtm_account = $form.find( 'select[name="gtmproperty"] :selected' );
				let $selected_gtm_container = $form.find( 'select[name="gacontainer"] :selected' );
				let $gtm_workspace = $form.find( 'select[name="gaworkspace"] :selected' );
				let $gtm_auto_create = $form.find( 'input[name="_gform_setting_gtm_auto_create"]' );

				$.post(
					ajaxurl,
					{
						action             : 'save_google_tag_manager_data',
						account_id         : $selected_property.data( 'account-id' ),
						account_name       : $selected_property.data( 'account-name' ),
						property_id        : $selected_property.val(),
						property_name      : $selected_property.data( 'property-name' ),
						measurement_id     : $selected_data_stream.val(),
						data_stream_name   : $selected_data_stream.data( 'data-stream-name' ),
						gtm_account_id     : $selected_gtm_account.data( 'account-id' ),
						gtm_account_name   : $selected_gtm_account.val(),
						gtm_api_path       : $selected_gtm_container.data( 'path' ),
						gtm_container      : $selected_gtm_container.val(),
						gtm_auto_create    : $gtm_auto_create.val(),
						gtm_workspace_id   : $gtm_workspace.val(),
						gtm_workspace_name : $gtm_workspace.text(),
						nonce              : $form.find( 'input[name="gfganonce"]' ).val(),
						token              : $form.find( 'input[name="gfga_token"]' ).val(),
						refresh            : $form.find( 'input[name="gfga_refresh"]' ).val(),
					},
					function( response ) {
						if ( false === response.success ) {
							display_error( response.data[0].message );
						} else {
							window.location.href = response.data;
						}
					}
				);
			}
			if ( 'manual' === $action.val() ) {
				// We're in manual configuration mode
				e.preventDefault();

				$.post(
					ajaxurl,
					{
						action           : 'save_manual_configuration_data',
						mode             : $form.find( '#ga_connection_type' ).val(),
						measurement_id   : $form.find( '#ga_measurement_id' ).val(),
						gmp_api_secret   : $form.find( '#gmp_api_secret' ).val(),
						gtm_container    : $form.find( '#gtm_container_id' ).val(),
						gtm_workspace_id : $form.find( '#gtm_workspace_id' ).val(),
						nonce            : $form.find( 'input[name="gfganonce"]' ).val(),
					},
					function( response ) {
						if ( false === response.success ) {
							display_error( response.data[0].message );
						} else {
							window.location.href = response.data;
						}
					}
				);
			}
		} );

		// Get data streams for selected Analytics account/property.
		$( '#gaproperty' ).on( 'change', function( e ) {

			// Hide error message if there is one being displayed
			hide_error();

			let $option = $( this ).find( ':selected' );
			let nonce = $( 'body' ).find( 'input[name="gfganonce"]' ).val();
			let token = $form.find('input[name="gfga_token"]' ).val();

			$( '#ga-data-streams' ).html( '<br /><img src="' + google_analytics_admin_strings.spinner + '" />' );
			$.post(
				ajaxurl,
				{
					action: 'get_ga4_data_streams',
					account: $option.data( 'account-id' ),
					property: $option.val(),
					nonce: nonce,
					token: token,
				},
				function( response ) {
					if ( response.success ) {
						$( '#ga-data-streams' ).html( response['data'] );
					} else {
						$( '#ga-data-streams' ).html( '' );
						display_error( response.data[0].message );
					}
				}
			);
		} );

		// Get containers for selected GTM account.
		$( '#gtmproperty' ).on( 'change', function( e ) {

			// Hide error message if there is one being displayed
			hide_error();

			const $option = $( this ).find( ':selected' );
			const accountId = $option.data( 'accountId' );
			const path = $option.data( 'path' );
			const token = $option.data( 'token' );
			const nonce = $( 'body' ).find( 'input[name="gfganonce"]' ).val();
			$( '#gtm-containers' ).html( '<br /><img src="' + google_analytics_admin_strings.spinner + '" />' );
			$( '#gtm-workspaces' ).html( '' );
			$.post(
				ajaxurl,
				{
					action: 'get_gtm_containers',
					accountId: accountId,
					path: path,
					nonce: nonce,
					token: token,
				},
				function( response ) {
					if( response.success ) {
						$( '#gtm-containers' ).html( response.body );
					} else {
						display_error( response.data[0].message );
						$( '#gtm-containers' ).html( '' );
					}
				}
			);
		} );

		// Get views for selected UA account.
		$( document ).on( 'change', '#gacontainer', function( e ) {

			// Hide error message if there is one being displayed
			hide_error();

			const $option = $( this ).find( ':selected' );
			const path = $option.data( 'path' );
			const token = $option.data( 'token' );
			const nonce = $( 'body' ).find( 'input[name="gfganonce"]' ).val();
			$( '#gtm-workspaces' ).html( '<br /><img src="' + google_analytics_admin_strings.spinner + '" />' );
			$.post(
				ajaxurl,
				{
					action: 'get_gtm_workspaces',
					path: path,
					nonce: nonce,
					token: token,
				},
				function( response ) {
					if( response.success ) {
						$( '#gtm-workspaces' ).html( response.body );
					} else {
						display_error( response.data[0].message );
						$( '#gform_setting_workspace' ).show();
						$( '#gtm-workspaces' ).html( '' );
					}
				}
			);
		} );
	} );
}( window.GF_Google_Analytics_Admin = window.GF_Google_Analytics_Admin || {}, jQuery ) );
