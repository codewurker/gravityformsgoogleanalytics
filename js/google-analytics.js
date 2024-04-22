( function () {

	/**
	 * Send events to Google Analytics. Use Ajax call so no duplicate entries are recorded.
	 *
	 * @since 1.3.0
	 *
	 * @param {number} entryId    The entry ID associated with this submission.
	 * @param {number} feedId     The feed ID associated with this event.
	 * @param {array}  parameters Event parameters to send to Google Analytics.
	 * @param {string} eventName  Event name to be sent to Google Analytics.
	 */
	this.send_unique_to_ga = async function ( entryId, feedId, parameters, eventName ) {
		let has_sent = await this.has_sent_feed( entryId, feedId );
		if ( ! has_sent ) {
			this.send_to_ga( parameters, eventName );
			this.mark_feed_as_sent( entryId, feedId );
		}
		this.maybe_trigger_feeds_sent();
	};

	/**
	 * Send events to Google Analytics. Use ajax call so no duplicate entries are recorded.
	 *
	 * @since 1.0.0

	 * @param {array}  parameters Event parameters to send to Google Analytics.
	 * @param {string} eventName  Event name to be sent to Google Analytics.
	 */
	this.send_to_ga = function( parameters, eventName ) {

		const eventTracker = gforms_google_analytics_frontend_strings.ua_tracker;

		// Check for gtab implementation
		if ( typeof window.parent.gtag != 'undefined' ) {
			window.parent.gtag( 'event', eventName, parameters );
		} else {
			// Check for GA from Monster Insights Plugin
			if ( typeof window.parent.ga == 'undefined' ) {
				if ( typeof window.parent.__gaTracker != 'undefined' ) {
					window.parent.ga = window.parent.__gaTracker;
				}
			}
			if ( typeof window.parent.ga != 'undefined' ) {

				let ga_send = 'send';
				// Try to get original UA code from third-party plugins or tag manager
				if ( eventTracker.length > 0 ) {
					ga_send = eventTracker + '.' + ga_send;
				}

				// Use that tracker
				window.parent.ga( ga_send, eventName, parameters );
			} else {
				console.error( 'Google Tag Manger script is not active. You may need to enable "Output the Google Analytics Script" setting on the Forms -> Settings -> Google Analytics page');
				return;
			}
		}

		// Logging if enabled.
		const eventData = { 'type': 'ga', 'eventName' : eventName, 'parameters' : parameters };
		this.consoleLog('Google Analytics event sent. Event data: ');
		this.consoleLog( JSON.stringify( eventData, null, 2 ) );

		// Triggering event_sent event.
		this.trigger_event( 'googleanalytics/event_sent', eventData );
		jQuery.post(
			gforms_google_analytics_frontend_strings.ajaxurl,
			{
				action: 'gf_ga_log_event_sent',
				parameters: parameters,
				eventName: eventName,
				connection: 'ga',
				nonce: gforms_google_analytics_frontend_strings.logging_nonce,
			}
		);
	}

	/**
	 * Send events to Google Tag Manager.
	 *
	 * @since 1.3.0
	 *
	 * @param {number} entryId      The entry ID associated with this submission.
	 * @param {number} feedId       The feed ID associated with this event.
	 * @param {array}  parameters   Event parameters to send to Google Analytics.
	 * @param {string} triggerName  Event type to be sent to Google Analytics.
	 */
	this.send_unique_to_gtm = async function( entryId, feedId, parameters, triggerName) {

		let has_sent = await this.has_sent_feed( entryId, feedId );
		if ( ! has_sent ) {
			this.send_to_gtm( parameters, triggerName );
			this.mark_feed_as_sent( entryId, feedId );
		} else {
			this.consoleLog( 'Event has already been sent. Aborting... Entry id: ' + entryId + '. Feed Id: ' + feedId );
		}
		this.maybe_trigger_feeds_sent();
	}

	/**
	 * Send events to Google Tag Manager.
	 *
	 * @since 1.0.0
	 *
	 * @param {array}  parameters   Event parameters to send to Google Analytics.
	 * @param {string} triggerName  Event name to be sent to Google Tag Manger.
	 */
	this.send_to_gtm = function( parameters, triggerName) {
		if ( typeof ( window.parent.dataLayer ) == 'undefined' ) {
			console.error( 'Google Tag Manger script is not active. You may need to enable "Output the Google Tag Manager Script" setting on the Forms -> Settings -> Google Analytics page' );
			return;
		}

		parameters['event'] = triggerName;
		window.parent.dataLayer.push( parameters );

		// Logging if enabled.
		const eventData = { 'type': 'gtm', 'triggerName' : triggerName, 'parameters' : parameters };
		this.consoleLog('Google Analytics event sent. Event data: ');
		this.consoleLog( JSON.stringify( eventData, null, 2 ) );

		// Triggering event_sent event.
		this.trigger_event( 'googleanalytics/event_sent', eventData );
		jQuery.post(
			gforms_google_analytics_frontend_strings.ajaxurl,
			{
				action: 'gf_ga_log_event_sent',
				parameters: parameters,
				triggerName: triggerName,
				connection: 'gtm',
				nonce: gforms_google_analytics_frontend_strings.logging_nonce,
			}
		);
	}

	/**
	 * Determines if event for this entry and feed has already been sent.
	 *
	 * @since 1.3.0
	 *
	 * @param {number} entryId Current entry id.
	 * @param {number} feedId  Current feed id.
	 *
	 * @returns {bool} Returns true if the event for this entry and feed has already been sent. Returns false if not.
	 */
	this.has_sent_feed = async function( entryId, feedId ) {
		let response = await jQuery.post( gforms_google_analytics_frontend_strings.ajaxurl, { action: 'get_entry_meta', entry_id: entryId, feed_id: feedId, nonce: gforms_google_analytics_frontend_strings.nonce }, 'json' );
		return response.data.event_sent;
	}

	/**
	 * Mark an event as sent via AJAX. Part of the system to prevent duplicate events from being sent.
	 *
	 * @since 1.3.0
	 *
	 * @param {number} entryId Current entry id.
	 * @param {number} feedId  Current feed id.
	 */
	this.mark_feed_as_sent = function( entryId, feedId ) {
		jQuery.post( gforms_google_analytics_frontend_strings.ajaxurl, { action: 'save_entry_meta', entry_id: entryId, feed_id: feedId, nonce: gforms_google_analytics_frontend_strings.nonce } );
	}

	/**
	 * Holds the number of feeds that have been sent.
	 *
	 * @since 1.3.0
	 *
	 * @type {number}
	 */
	this.feeds_sent = 0;

	/**
	 * Triggers 'googleanalytics/all_events_sent' after the last feed has been sent.
	 *
	 * @since 1.3.0
	 */
	this.maybe_trigger_feeds_sent = function() {

		this.feeds_sent++;
		this.consoleLog( 'Google Analytics event successfully sent: ' + this.feeds_sent + ' of ' + window['ga_feed_count'] );
		if ( this.feeds_sent >= window['ga_feed_count'] ) {

			this.consoleLog( 'All Google Analytics events have been sent.' );

			// All feeds have been sent. Trigger feeds sent event.
			this.trigger_event( 'googleanalytics/all_events_sent' );
			window['all_ga_events_sent'] = true;

			// Reset counters.
			this.feeds_sent = 0;
			window['ga_feed_count'] = 0;
		}
	}

	/**
	 * Triggers a Javascript event.
	 *
	 * @since 1.3.0
	 *
	 * @param {string} eventName Name of the event.
	 * @param {*}      eventData Data associated with this event.
	 */
	this.trigger_event = function( eventName, eventData ) {
		const event = new CustomEvent( eventName, { detail: eventData } );
		window.dispatchEvent( event );
	}

	/**
	 * Logs to the console if logging is enabled in settings page.
	 *
	 * @since 1.3.0
	 *
	 * @param {*} message The message to be logged.
	 */
	this.consoleLog = function( message ) {
		if ( gforms_google_analytics_frontend_strings.logging_enabled !== '1' ) {
			return;
		}
		console.log( message );
	}

	/**
	 * Initializes this object.
	 */
	this.init = function() {
		window.GF_Google_Analytics = this;

		// Trigger script loaded event
		this.trigger_event( 'googleanalytics/script_loaded' );
	}

	this.init();
}() );
