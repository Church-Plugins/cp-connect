<?php

namespace CP_Connect\ChMS;

use MinistryPlatformAPI\MinistryPlatformTableAPI as MP;

class MinistryPlatform extends ChMS {

	public function integrations() {
		$this->mpLoadConnectionParameters();
		
		add_filter( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
//		add_filter( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );

		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}

	public function pull_events( $events = [] ) {
		$mp      = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			return false;
		}

		$events = $mp->table( 'Events' )
		             ->select( "Event_ID, Event_Title, Events.Congregation_ID, Event_Type_ID_Table.[Event_Type], 
		             Congregation_ID_Table.[Congregation_Name], Events.Location_ID, Location_ID_Table.[Location_Name], 
		             Location_ID_Table_Address_ID_Table.[Address_Line_1], Location_ID_Table_Address_ID_Table.[Address_Line_2], 
		             Location_ID_Table_Address_ID_Table.[City], Location_ID_Table_Address_ID_Table.[State/Region], 
		             Location_ID_Table_Address_ID_Table.[Postal_Code], Meeting_Instructions, Events.Description, Events.Program_ID, 
		             Program_ID_Table.[Program_Name], Events.Primary_Contact, Primary_Contact_Table.[First_Name], 
		             Primary_Contact_Table.[Last_Name], Primary_Contact_Table.[Email_Address], Event_Start_Date, Event_End_Date, 
		             Visibility_Level_ID, Featured_On_Calendar, Events.Show_On_Web, Online_Registration_Product, Registration_Form, 
		             Registration_Start, Registration_End, Registration_Active, _Web_Approved, dp_fileUniqueId as Image_ID" )
		             ->filter( "Events.Show_On_Web = 'TRUE' AND Events._Web_Approved = 'TRUE' AND Events.Visibility_Level_ID = 4 AND Events.Event_End_Date >= getdate()" )
		             ->get();

		$formatted = [];

		foreach ( $events as $event ) {
			$start_date = strtotime( $event['Event_Start_Date'] );
			$end_date   = strtotime( $event['Event_End_Date'] );
			
			$args = [
				'chms_id'               => $event['Event_ID'],
				'post_status'           => 'publish',
				'post_title'            => $event['Event_Title'],
				'post_content'          => $event['Description'] . '<br />' . $event['Meeting_Instructions'],
				'post_excerpt'          => $event['Description'],
				'tax_input'             => [],
				'event_category'        => [],
				'thumbnail_url'         => '',
				'EventStartDate'        => date( 'Y-m-d', $start_date ),
				'EventEndDate'          => date( 'Y-m-d', $end_date ),
//				'EventAllDay'           => $event[''],
				'EventStartHour'        => date( 'G', $start_date ),
				'EventStartMinute'      => date( 'i', $start_date ),
//				'EventStartMeridian'    => $event[''],
				'EventEndHour'          => date( 'G', $end_date ),
				'EventEndMinute'        => date( 'i', $end_date ),
//				'EventEndMeridian'      => $event[''],
//				'EventHideFromUpcoming' => $event[''],
//				'EventShowMapLink'      => $event[''],
//				'EventShowMap'          => $event[''],
//				'EventCost'             => $event[''],
//				'EventURL'              => $event[''],
//				'FeaturedImage'         => $event[''],
			];
			
			if ( ! empty( $event['Image_ID'] ) ) {
				$args['thumbnail_url'] = $this->get_option_value( 'MP_API_ENDPOINT' ) . '/files/' . $event['Image_ID'] . '?ext=.jpeg';
			}
			
			if ( ! empty( $event['Congregation_ID'] ) ) {
				if ( $location = $this->get_location_term( $event['Congregation_ID'] ) ) {
					$args['tax_input']['cp_location'] = $location;
				}
			}
			
			if ( ! empty( $event['Event_Type'] ) ) {
				$args['event_category'][] = $event['Event_Type'];
			}

			if ( ! empty( $event['Program_Name'] ) ) {
				$args['event_category'][] = $event['Program_Name'];
			}

			if ( ! empty( $event['First_Name'] ) ) {
				$args['Organizer'] = [
					'Organizer' => $event['First_Name'] . ' ' . $event['Last_Name'],
					'Email'     => $event['Email_Address'],
//					'Website'   => $event[''],
//					'Phone'     => $event[''],
				];
			}
			
			if ( ! empty( $event['Location_Name'] ) ) {
				$args['Venue'] = [
					'Venue'    => $event['Location_Name'],
//					'Country'  => $event[''],
					'Address'  => $event['Address_Line_1'],
					'City'     => $event['City'],
					'State'    => $event['State/Region'],
//					'Province' => $event[''],
					'Zip'      => $event['Postal_Code'],
//					'Phone'    => $event[''],
				];
			}
			
			$formatted[] = $args;
		}

		return $formatted;

	}

	public function pull_groups( $groups = [] ) {
		$mp = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			return false;
		}

		$groups = $mp->table( 'Groups' )
		             ->select( "Group_ID, Group_Name, Group_Type_ID_Table.[Group_Type], Groups.Congregation_ID, Congregation_ID_Table.[Congregation_Name], 
	             Primary_Contact_Table.[First_Name] AS Contact_First_Name, Primary_Contact_Table.[Last_Name] AS Contact_Last_Name, Groups.Description, 
	             Groups.Start_Date, Groups.End_Date, Life_Stage_ID_Table.[Life_Stage], Group_Focus_ID_Table.[Group_Focus], Meeting_Time, Meeting_Day_ID_Table.[Meeting_Day], 
	             Meeting_Frequency_ID_Table.[Meeting_Frequency], dp_fileUniqueId as Image_ID" )
		             ->filter( "Groups.End_Date >= getdate()" )
		             ->get();

		return $groups;
	}


	/**
	 * This function introduces a single plugin menu option into the WordPress 'Plugins'
	 * menu.
	 */

	function plugin_menu() {

		add_submenu_page( 'options-general.php',
			'Ministry Platform Integration',         // The title to be displayed in the browser window for this page.
			'Ministry Platform',                        // The text to be displayed for this menu item
			'administrator',                    // Which type of users can see this menu item
			'ministry_platform_plugin_options', // The unique ID - that is, the slug - for this menu item
			[ $this, 'plugin_display' ]  // The name of the function to call when rendering the page for this menu
		);

	} // end sandbox_example_theme_menu

	/**
	 * Renders a simple page to display for the plugin menu defined above.
	 */
	function plugin_display() {
		?>
		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap">
			<!-- Add the icon to the page -->
			<div id="icon-themes" class="icon32"></div>
			<h2>Ministry Platform Plugin Options</h2>
			<p class="description">Here you can set the parameters to authenticate to and use the Ministry Platform
				API</p>
			<!-- Make a call to the WordPress function for rendering errors when settings are saved. -->
			<?php settings_errors(); ?>

			<!-- Create the form that will be used to render our options -->
			<form method="post" action="options.php">
				<?php settings_fields( 'ministry_platform_plugin_options' ); ?>
				<?php do_settings_sections( 'ministry_platform_plugin_options' ); ?>
				<?php submit_button(); ?>
			</form>
		</div> <!-- /.wrap -->


		<?php
	} // end sandbox_plugin_display


	function initialize_plugin_options() {

		// If the options don't exist, add them
		if ( false == get_option( 'ministry_platform_plugin_options' ) ) {
			add_option( 'ministry_platform_plugin_options' );
		} // end if


		// First, we register a section. This is necessary since all future options must belong to one.
		add_settings_section(
			'ministry_platform_settings_section',                           // ID used to identify this section and with which to register options
			'API Configuration Options',                                  // Title to be displayed on the administration page
			[ $this, 'general_options_callback' ],  // Callback used to render the description of the section
			'ministry_platform_plugin_options'                              // Page on which to add this section of options
		);

		// Next, we will introduce the fields for the configuration information.
		add_settings_field(
			'MP_API_ENDPOINT',                                  // ID used to identify the field throughout the theme
			'API Endpoint',                                     // The label to the left of the option interface element
			[ $this, 'mp_api_endpoint_callback' ],        // The name of the function responsible for rendering the option interface
			'ministry_platform_plugin_options',                 // The page on which this option will be displayed
			'ministry_platform_settings_section',               // The name of the section to which this field belongs
			[                                                   // The array of arguments to pass to the callback. In this case, just a description.
			                                                    'ex: https://my.mychurch.org/ministryplatformapi'
			]
		);

		add_settings_field(
			'MP_OAUTH_DISCOVERY_ENDPOINT',
			'Oauth Discovery Endpoint',
			[ $this, 'mp_oauth_discovery_callback' ],
			'ministry_platform_plugin_options',
			'ministry_platform_settings_section',
			[
				'ex: https://my.mychurch.org/ministryplatform/oauth'
			]
		);

		add_settings_field(
			'MP_CLIENT_ID',
			'MP Client ID',
			[ $this, 'mp_client_id_callback' ],
			'ministry_platform_plugin_options',
			'ministry_platform_settings_section',
			[
				'The Client ID is defined in MP on the API Client page.'
			]
		);

		add_settings_field(
			'MP_CLIENT_SECRET',
			'MP Client Secret',
			[ $this, 'mp_client_secret_callback' ],
			'ministry_platform_plugin_options',
			'ministry_platform_settings_section',
			[
				'The Client Secret is defined in MP on the API Client page.'
			]
		);

		add_settings_field(
			'MP_API_SCOPE',
			'Scope',
			[ $this, 'mp_api_scope_callback' ],
			'ministry_platform_plugin_options',
			'ministry_platform_settings_section',
			[
				'Will usually be http://www.thinkministry.com/dataplatform/scopes/all'
			]
		);

		// Finally, we register the fields with WordPress
		register_setting(
			'ministry_platform_plugin_options',
			'ministry_platform_plugin_options'
		);


	} // end ministry_platform_initialize_plugin_options

	function general_options_callback() {
		echo '<p>The following parameters are required to authenticate to the API using oAuth and then execute API calls to Ministry Platform.</p>';
	}


	function get_option_value( $key, $options = false ) {
		
		if ( ! $options ) {
			$options = get_option( 'ministry_platform_plugin_options' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';

	}

	function mp_api_endpoint_callback( $args ) {
		$options = get_option( 'ministry_platform_plugin_options' );


		$opt = $this->get_option_value( 'MP_API_ENDPOINT', $options );


		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_API_ENDPOINT" name="ministry_platform_plugin_options[MP_API_ENDPOINT]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_API_ENDPOINT"> ' . $args[0] . '</label>';

		echo $html;

	} // end mp_api_endpoint_callback

	function mp_oauth_discovery_callback( $args ) {
		$options = get_option( 'ministry_platform_plugin_options' );

		$opt = $this->get_option_value( 'MP_OAUTH_DISCOVERY_ENDPOINT', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_OAUTH_DISCOVERY_ENDPOINT" name="ministry_platform_plugin_options[MP_OAUTH_DISCOVERY_ENDPOINT]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_OAUTH_DISCOVERY_ENDPOINT"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_oauth_discovery_callback

	function mp_client_id_callback( $args ) {
		$options = get_option( 'ministry_platform_plugin_options' );

		$opt = $this->get_option_value( 'MP_CLIENT_ID', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_CLIENT_ID" name="ministry_platform_plugin_options[MP_CLIENT_ID]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_CLIENT_ID"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_client_id_callback

	function mp_client_secret_callback( $args ) {
		$options = get_option( 'ministry_platform_plugin_options' );

		$opt = $this->get_option_value( 'MP_CLIENT_SECRET', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_CLIENT_SECRET" name="ministry_platform_plugin_options[MP_CLIENT_SECRET]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_CLIENT_SECRET"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_client_secret_callback

	function mp_api_scope_callback( $args ) {
		$options = get_option( 'ministry_platform_plugin_options' );

		$opt = $this->get_option_value( 'MP_API_SCOPE', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_API_SCOPE" name="ministry_platform_plugin_options[MP_API_SCOPE]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_API_SCOPE"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_api_scope_callback


	/**
	 * Get oAuth and API connection parameters from the database
	 *
	 */
	function mpLoadConnectionParameters() {
		// If no options available then just return - it hasn't been setup yet
		if ( ! $options = get_option( 'ministry_platform_plugin_options', '' ) ) {
			return;
		}

		foreach ( $options as $option => $value ) {
			$envString = $option . '=' . $value;
			putenv( $envString );
		}
	}
	
}