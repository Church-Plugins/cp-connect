<?php

namespace CP_Connect\ChMS;

use MinistryPlatformAPI\MinistryPlatformTableAPI as MP;
use CP_Connect\Setup\Convenience as _C;

/**
 * Ministry Platform Integration provider
 *
 * TODO: Localize strings
 *
 */
class MinistryPlatform extends ChMS {

	public function integrations() {
		$this->mpLoadConnectionParameters();

		add_action( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
		add_action( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );

		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );

		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
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
	}

	/**
	 * Displays the options page
	 */
	function plugin_display() {

		$default_tab = 'connect';
		$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
		?>

		<div class='wrap'>
			<nav class='nav-tab-wrapper'>
				<a href='?page=ministry_platform_plugin_options&tab=connect' class='nav-tab <?php echo $tab == 'connect' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Connect', 'cp-connect' ) ?></a>
				<a href='?page=ministry_platform_plugin_options&tab=group-options' class='nav-tab <?php echo $tab == 'group-options' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Group Options', 'cp-connect' ) ?></a>
			</nav>
			<?php settings_errors(); ?>
			<form method="post" action=<?php echo esc_url( add_query_arg( 'tab', $tab, admin_url( 'options.php' ) ) ) ?>>
				<?php switch ( $tab ) {
					case 'connect':
						$this->render_api_config_tab();
						break;
					case 'group-options':
						$this->render_group_mapping_tab();
						break;
				} ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Conditionally initializes plugins options based on the current tab
	 */
	public function initialize_plugin_options() {
		$tabname = isset( $_GET['tab'] ) ? $_GET['tab'] : 'connect';

		switch ( $tabname ) {
			case 'connect':
				$this->initialize_api_config_options();
				break;
			case 'group-options':
				$this->initialize_group_mapping_options();
				break;
		}
	}

	/**
	 * Initialize the api config options in the admin
	 */
	function initialize_api_config_options() {
		/*** API Configuration Settings ***/

		$api_config_option = 'ministry_platform_api_config';
		$api_config_tab    = 'ministry_platform_api_config_tab';
		$api_config_group  = 'ministry_platform_api_config_group';

		/* register the setting group */
		register_setting( $api_config_group, $api_config_option );

		/* add the settings section */
		add_settings_section(
			$api_config_option,                    // ID used to identify this section and with which to register options
			'API Configuration',                   // Title to be displayed on the administration page
			[ $this, 'api_config_callback' ],      // Callback used to render the description and fields for this section.
			$api_config_tab                        // Tab on which to add this section of options
		);

		/* Introduce the fields for the configuration information. */
		add_settings_field(
			'MP_API_ENDPOINT',                                    // ID used to identify the field throughout the theme
			'API Endpoint',                                       // The label to the left of the option interface element
			[ $this, 'mp_api_endpoint_callback' ],                // The name of the function responsible for rendering the option interface
			$api_config_tab,                                      // The tab on which this option will be displayed
			$api_config_option,                                   // The option name to which this field belongs
			[ 'ex: https://my.mychurch.org/ministryplatformapi' ] // The array of arguments to pass to the callback. In this case, just a description.
		);

		add_settings_field(
			'MP_OAUTH_DISCOVERY_ENDPOINT',
			'Oauth Discovery Endpoint',
			[ $this, 'mp_oauth_discovery_callback' ],
			$api_config_tab,
			$api_config_option,
			[ 'ex: https://my.mychurch.org/ministryplatform/oauth' ]
		);

		add_settings_field(
			'MP_CLIENT_ID',
			'MP Client ID',
			[ $this, 'mp_client_id_callback' ],
			$api_config_tab,
			$api_config_option,
			[ 'The Client ID is defined in MP on the API Client page.' ]
		);

		add_settings_field(
			'MP_CLIENT_SECRET',
			'MP Client Secret',
			[ $this, 'mp_client_secret_callback' ],
			$api_config_tab,
			$api_config_option,
			[ 'The Client Secret is defined in MP on the API Client page.' ]
		);

		add_settings_field(
			'MP_API_SCOPE',
			'Scope',
			[ $this, 'mp_api_scope_callback' ],
			$api_config_tab,
			$api_config_option,
			[ 'Will usually be http://www.thinkministry.com/dataplatform/scopes/all' ]
		);

		/*** End API Configuration Settings ***/
	}

	protected function valid_fields() {
		$valid_fields = array( 'select' );

		// initialize the MP API wrapper
		$mp = new MP();

		// Authenticate to get access token required for API calls
		if( $mp->authenticate() ) {

			$fields = $this->get_all_group_mapping_fields();

			// get the list of fields from the Groups table
			$table = $mp->table( 'Groups' );

			// gets a group from API just to verify that all specified fields exist
			$group = $table->select( implode( ',', $fields ) )->top(1)->get();

			if( $group && count( $group ) > 0 ) {
				$group = $group[0];
			}

			// adds column names from group response to the available fields
			if( ! empty( $group ) ) {
				$valid_fields = array_merge( $valid_fields, array_keys( $group ) );
			}
		}

		return $valid_fields;
	}

	/**
	 * Save custom field selections
	 *
	 * @return void
	 * @author costmo
	 */
	protected function save_custom_fields() {

		// Sanity checks
		if( wp_doing_ajax() || !wp_verify_nonce( $_POST['_cp_mp_nonce'], 'cp-connect-mp-fields' ) ) {
			return;
		}
		if( empty( $_POST['option_page'] ) || 'ministry_platform_group_mapping_group' !== $_POST['option_page'] ) {
			return;
		}
		if(
			empty( $_POST['cp_connect_field_mapping_names'] ) || empty( $_POST['cp_connect_field_mapping_targets'] )  ||
			!is_array( $_POST['cp_connect_field_mapping_names'] ) || !is_array( $_POST['cp_connect_field_mapping_targets'] ) ||
			count( $_POST['cp_connect_field_mapping_names'] ) !== count( $_POST['cp_connect_field_mapping_targets'] )
		) {
			return;
		}

		$save_array = [];
		foreach( $_POST['cp_connect_field_mapping_targets'] as $index => $target ) {
			$save_array[ $target ] = $_POST['cp_connect_field_mapping_names'][ $index ];
		}
		update_option( 'cp_group_custom_field_mapping', $save_array );
	}

	/**
	 * Initialize the group mapping options in the admin
	 */
	function initialize_group_mapping_options() {
		/*** Group Field Mapping Settings ***/

		if( !empty( $_POST ) ) {
			$this->save_custom_fields();
		}

		$group_mapping_option = 'ministry_platform_group_mapping';       // the option id
		$group_mapping_tab    = 'ministry_platform_group_mapping_tab';   // the id for the tab
		$group_mapping_group  = 'ministry_platform_group_mapping_group'; // the id for the settings group

		register_setting( $group_mapping_group, $group_mapping_option );

		add_settings_section(
			$group_mapping_option,
			'Ministry Platform Field Mapping',
			[ $this, 'group_mapping_callback' ],
			$group_mapping_tab
		);


		$valid_fields = $this->valid_fields();
		$names = $this->get_group_field_names();

		$mapping = $this->get_default_group_mapping();

		foreach( $mapping as $key => $value ) {
			$name = isset( $names[ $key ] ) ? $names[ $key ] : '';

			add_settings_field(
				$key,
				$name,
				[ $this, 'field_mapping_callback' ],
				$group_mapping_tab,
				$group_mapping_option,
				array(
					'description' => '',
					'valid_fields' => $valid_fields,
					'key' => $key,
					'default_value' => $value,
					'option' => $group_mapping_option
				)
			);
		}

		/*** End Group Field Mapping Settings ***/
	}

	/**
	 * Content displayed on the API config tab
	 */
	function api_config_callback() {
		?>
			<!-- Add the icon to the page -->
			<div id="icon-themes" class="icon32"></div>
			<h2>Ministry Platform Plugin Options</h2>
			<p class="description">Here you can set the parameters to authenticate to and use the Ministry Platform
				API</p>
			<p>The following parameters are required to authenticate to the API using oAuth and then execute API calls to Ministry Platform.</p>
		<?php
	}

	/**
	 * Content displayed on the Group Mapping tab
	 */
	function group_mapping_callback() {
		$this->render_field_select( 'ministry_platform_group_mapping' );

		echo '<h3>Group Field Mapping</h3>';
		echo '<p>The following parameters are used to map Ministry Platform groups to the CP Groups plugin</p>';
	}


	/**
	 * Gets an object with data and a mapping array, and returns the object values associated with the mapping keys
	 *
	 * @param array $data The data to map
	 * @param array $mapping The mapping array
	 */
	function get_mapped_values( $data, $mapping ) {
		$mapped_values = array();

		foreach( $mapping as $key => $value ) {
			if( isset( $data[ $value ] ) ) {
				$mapped_values[ $key ] = $data[ $value ];
			}
		}

		return $mapped_values;
	}

	/**
	 * The default group mapping fields to fetch from the MP API
	 */
	protected function get_default_group_mapping_fields() {
		return array(
			'Group_ID',
			'Group_Name',
			'Group_Type_ID_Table.[Group_Type]',
			'Groups.Congregation_ID',
			'Primary_Contact_Table.[First_Name]',
			'Primary_Contact_Table.[Last_Name]',
			'Groups.Description',
			'Groups.Start_Date',
			'Groups.End_Date',
			'Life_Stage_ID_Table.[Life_Stage]',
			'Group_Focus_ID_Table.[Group_Focus]',
			'Offsite_Meeting_Address_Table.[Postal_Code]',
			'Offsite_Meeting_Address_Table.[Address_Line_1]',
			'Offsite_Meeting_Address_Table.[City]',
			'Offsite_Meeting_Address_Table.[State/Region]',
			'Meeting_Time',
			'Meeting_Day_ID_Table.[Meeting_Day]',
			'Meeting_Frequency_ID_Table.[Meeting_Frequency]',
			'dp_fileUniqueId as Image_ID',
			// 'Group_Gender_ID_Table.Group_Gender_Name',
			'Primary_Contact_Table.Display_Name'
		);
	}

	/**
	 * The default API to group mapping
	 */
	protected function get_default_group_mapping() {
		return array(
			'chms_id' => 'Group_ID',
			'post_title' => 'Group_Name',
			'post_content' => 'Description',
			'leader' => 'Display_Name',
			'start_date' => 'Start_Date',
			'end_date' => 'End_Date',
			'thumbnail_url' => 'Image_ID',
			'frequency' => 'Meeting_Frequency',
			'city' => 'City',
			'state_or_region' => 'State/Region',
			'postal_code' => 'Postal_Code',
			'meeting_time' => 'Meeting_Time',
			'meeting_day' => 'Meeting_Day',
			'cp_location' => 'Congregation_ID',
			'group_category' => 'Group_Focus',
			'group_type' => 'Group_Type',
			'group_life_stage' => 'Life_Stage',
			// 'gender' => 'Group_Gender_Name'
		);
	}

	/**
	 * Gets the names of the group fields
	 */
	protected function get_group_field_names() {
		return array(
			'chms_id' => 'Group ID',
			'post_title' => 'Group Name',
			'post_content' => 'Description',
			'leader' => 'Group Leader',
			'start_date' => 'Start Date',
			'end_date' => 'End Date',
			'thumbnail_url' => 'Image ID',
			'frequency' => 'Meeting Frequency',
			'location' => 'Congregation ID',
			'city' => 'City',
			'state_or_region' => 'State/Region',
			'postal_code' => 'Postal Code',
			'meeting_time' => 'Meeting Time',
			'meeting_day' => 'Meeting Day',
			'cp_location' => 'Group Campus',
			'group_category' => 'Group Focus',
			'group_type' => 'Group Type',
			'group_life_stage' => 'Life Stage',
			// 'gender' => 'Gender'
		);
	}

	/**
	 * Render a interface to select additional fields to grab from the API
	 *
	 * @param string $option_id The option id
	 */
	function render_field_select( $option_id ) {
		$option = get_option( $option_id );

		$fields = isset( $option['fields'] ) ? $option['fields'] : array();

		$mp = new MP();

		if( $mp->authenticate() ) {
			$table = $mp->table( 'Groups' );

			// makes a dummy request just to get any error messages from user specified fields
			$table->select( implode( ',', $this->get_all_group_mapping_fields() ) )->top(1)->get();

			$error = $table->errorMessage() ? json_decode( $table->errorMessage(), true ) : false;
		}
		?>

		<div class="cp-connect-field-select" data-option-id="<?php echo esc_attr( $option_id ) ?>">
			<p>This is the current query being made to Ministry Platform</p>
			<h4>SELECT</h4>
			<code>
				<?php echo implode( ',', $this->get_all_group_mapping_fields() ) ?>
			</code>
			<p>Specify additional fields to grab</p>
			<ul class="cp-connect-field-select__options" data-options="<?php echo htmlspecialchars( json_encode( array_values( $fields ) ), ENT_QUOTES, 'UTF-8' ) ?>"></ul>
			<div class="cp-connect-field-select__add">
				<input class="cp-connect-field-select__add-input" type="text" placeholder="Table_Name.Field_Name" />
				<button class="cp-connect-field-select__add-button button button-primary" type="button">Add</button>
			</div>
			<!-- displays an error message if one exists -->
			<?php if( $error ) : ?>
				<div>
					<h4>Ministry Platform API Error</h4>
					<code><?php echo $error['Message'] ?></code>
				</div>
			<?php endif; ?>
		</div>
		<hr>
		<?php
	}

	/**
	 * Get all tables to grab from the API
	 */
	function get_all_group_mapping_fields() {
		$fields = get_option( 'ministry_platform_group_mapping' );
		$fields = isset( $fields['fields'] ) ? $fields['fields'] : array();

		return array_merge( $this->get_default_group_mapping_fields(), $fields );
	}

	/**
	 * Render the API configuration tab
	 */
	function render_api_config_tab() {
		settings_fields( 'ministry_platform_api_config_group' );
		do_settings_sections( 'ministry_platform_api_config_tab' );
		?>
		<p class="submit">
			<?php submit_button( null, 'primary', 'submit', false ); ?>
			<?php submit_button( 'Pull Now', 'secondary', 'cp-connect-pull', false ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Group Mapping tab
	 */
	function render_group_mapping_tab() {
		settings_fields( 'ministry_platform_group_mapping_group' );
		do_settings_sections( 'ministry_platform_group_mapping_tab' );
		$this->render_custom_mappings();
		$valid_fields = $this->valid_fields();
		$txt_fields = json_encode( $valid_fields );
		echo "<input type='hidden' name='ministry_platform_group_valid_fields' value='{$txt_fields}'>";
		wp_nonce_field( 'cp-connect-mp-fields', '_cp_mp_nonce' );
		submit_button();
	}

	/**
	 * Render the custom field mappings
	 *
	 * @return void
	 * @author costmo
	 */
	protected function render_custom_mappings() {

		$custom_fields = get_option( 'cp_group_custom_field_mapping', [] );

		$html = "";
		if( !empty( $custom_fields ) && is_array( $custom_fields ) ) {

			foreach( $custom_fields as $key => $value ) {

				$list = "<table class='form-table' role='presentation'><tbody><tr><td><select name='cp_connect_field_mapping_targets[]'>";
				foreach( array_keys( $custom_fields ) as $field ) {
					$selected = $field === $key ? 'selected' : '';
					$disabled = $field === 'select' ? 'disabled' : '';
					$list .= "<option value='{$field}' {$selected} {$disabled}> {$field} </option>";
				}
				$list .=  "</select><span class='dashicons dashicons-dismiss'></span></td></tr></tbody></table>";
				$html .=
					"<div class='cp-connect-field-mapping-item-container'>
						<input type='text' name='cp_connect_field_mapping_names[]' value='{$value}' placeholder='Field Name' />
						{$list}
					</div>";
			}
		}

		$return =<<<EOT
		<div class="cp-connect-custom-mappings">
			{$html}
			<div class="cp-connect-custom-mappings__last_row">
				<i class="dashicons dashicons-plus-alt cp-connect-add-field-mapping"></i>
			</div>
		</div>
		EOT;


		echo $return;
	}

	function get_option_value( $key, $options = false ) {

		if ( ! $options ) {
			$options = get_option( 'ministry_platform_api_config' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';

	}

	/***** API Config Callbacks *****/

	function mp_api_endpoint_callback( $args ) {
		$options = get_option( 'ministry_platform_api_config' );


		$opt = $this->get_option_value( 'MP_API_ENDPOINT', $options );


		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_API_ENDPOINT" name="ministry_platform_api_config[MP_API_ENDPOINT]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_API_ENDPOINT"> ' . $args[0] . '</label>';

		echo $html;

	} // end mp_api_endpoint_callback

	function mp_oauth_discovery_callback( $args ) {
		$options = get_option( 'ministry_platform_api_config' );

		$opt = $this->get_option_value( 'MP_OAUTH_DISCOVERY_ENDPOINT', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_OAUTH_DISCOVERY_ENDPOINT" name="ministry_platform_api_config[MP_OAUTH_DISCOVERY_ENDPOINT]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_OAUTH_DISCOVERY_ENDPOINT"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_oauth_discovery_callback

	function mp_client_id_callback( $args ) {
		$options = get_option( 'ministry_platform_api_config' );

		$opt = $this->get_option_value( 'MP_CLIENT_ID', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_CLIENT_ID" name="ministry_platform_api_config[MP_CLIENT_ID]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_CLIENT_ID"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_client_id_callback

	function mp_client_secret_callback( $args ) {
		$options = get_option( 'ministry_platform_api_config' );

		$opt = $this->get_option_value( 'MP_CLIENT_SECRET', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_CLIENT_SECRET" name="ministry_platform_api_config[MP_CLIENT_SECRET]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_CLIENT_SECRET"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_client_secret_callback

	function mp_api_scope_callback( $args ) {
		$options = get_option( 'ministry_platform_api_config' );

		$opt = $this->get_option_value( 'MP_API_SCOPE', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="MP_API_SCOPE" name="ministry_platform_api_config[MP_API_SCOPE]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="MP_API_SCOPE"> ' . $args[0] . '</label>';

		echo $html;
	} // end mp_api_scope_callback

	/***** End API Config Callbacks *****/

	/**
	 * The callback for displaying all group mapping fields
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	function field_mapping_callback( $args ) {

		$opt = get_option( $args['option'] );

		$opt = isset( $opt['mapping'] ) ? $opt['mapping'] : array();

		if( ! $opt || ! isset( $opt[ $args['key'] ] ) ) {
			$opt = $args['default_value'];
		}
		else {
			$opt = $opt[ $args['key'] ];
		}

		$options = implode( '', array_map( function( $val ) use ( $opt ) {
			$selected_att = $opt === $val ? 'selected' : '';
			$disabled_att = $val === 'select' ? 'disabled' : '';

			return sprintf( '<option %s %s>%s</option>', $selected_att, $disabled_att, esc_html( $val ) );
		}, $args['valid_fields'] ) );

		$field_name = $args['option'] . '[mapping]' . '[' . $args['key'] . ']';

		$html = sprintf( '<select name="%s" value="%s">%s</select>', esc_attr( $field_name ), esc_attr( $opt ), $options );

		$html .= '<label for="title"> ' . $args['description'] . '</label>';

		echo $html;
	}


	/**
	 * Get oAuth and API connection parameters from the database
	 *
	 */
	function mpLoadConnectionParameters() {
		// If no options available then just return - it hasn't been setup yet
		if ( ! $options = get_option( 'ministry_platform_api_config', '' ) ) {
			return;
		}

		foreach ( $options as $option => $value ) {
			$envString = $option . '=' . $value;
			putenv( $envString );
		}
	}

	public function pull_events( $integration ) {

		// TODO: Update data

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
				'chms_id'          => $event['Event_ID'],
				'post_status'      => 'publish',
				'post_title'       => $event['Event_Title'],
				'post_content'     => $event['Description'] . '<br />' . $event['Meeting_Instructions'],
				// 'post_excerpt'     => $event['Description'],
				'tax_input'        => [],
				'event_category'   => [],
				'thumbnail_url'    => '',
				'meta_input'       => [
					'cp_registration_form'   => $event['Registration_Form'],
					'cp_registration_start'  => $event['Registration_Start'],
					'cp_registration_end'    => $event['Registration_End'],
					'cp_registration_active' => $event['Registration_Active'],
				],
				'EventStartDate'   => date( 'Y-m-d', $start_date ),
				'EventEndDate'     => date( 'Y-m-d', $end_date ),
				// 'EventAllDay'           => $event[''],
				'EventStartHour'   => date( 'G', $start_date ),
				'EventStartMinute' => date( 'i', $start_date ),
				// 'EventStartMeridian'    => $event[''],
				'EventEndHour'     => date( 'G', $end_date ),
				'EventEndMinute'   => date( 'i', $end_date ),
				// 'EventEndMeridian'      => $event[''],
				// 'EventHideFromUpcoming' => $event[''],
				// 'EventShowMapLink'      => $event[''],
				// 'EventShowMap'          => $event[''],
				// 'EventCost'             => $event[''],
				// 'EventURL'              => $event[''],
				// 'FeaturedImage'         => $event[''],
			];

			if ( ! empty( $event['Image_ID'] ) ) {
				$args['thumbnail_url'] = $this->get_option_value( 'MP_API_ENDPOINT' ) . '/files/' . $event['Image_ID'] . '?mpevent-' . sanitize_title( $args['post_title'] ) . '.jpeg';
			}

			if ( ! empty( $event['Congregation_ID'] ) ) {
				if ( $location = $this->get_location_term( $event['Congregation_ID'] ) ) {
					$args['cp_location'] = $location;
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
					// 'Website'   => $event[''],
					// 'Phone'     => $event[''],
				];
			}

			if ( ! empty( $event['Location_Name'] ) ) {
				$args['Venue'] = [
					'Venue'    => $event['Location_Name'],
					// 'Country'  => $event[''],
					'Address'  => $event['Address_Line_1'],
					'City'     => $event['City'],
					'State'    => $event['State/Region'],
					// 'Province' => $event[''],
					'Zip'      => $event['Postal_Code'],
					// 'Phone'    => $event[''],
				];
			}

			$formatted[] = $args;
		}

		$integration->process( $formatted );
	}

	public function pull_groups( $integration ) {

		// TODO: Update data

		$mp = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			return false;
		}

		$filter = apply_filters( 'cp_connect_chms_mp_groups_filter', "(Groups.End_Date >= getdate() OR Groups.End_Date IS NULL) AND Group_Gender_Id_Table.Group_Gender_Name IS NOT NULL" );

		$fields = $this->get_all_group_mapping_fields();

		$table = $mp->table( 'Groups' );
		$groups = $table
								->select( implode( ',', $fields ) )
								->filter( $filter )
								->top(10)
								->get();

		if( $table->errorMessage() ) {
			return false;
		}

		$group_mapping = get_option( 'ministry_platform_group_mapping' );
		$group_mapping = isset( $group_mapping['mapping'] ) ? $group_mapping['mapping'] : $this->get_default_group_mapping();

		$formatted = [];

		$custom_mappings = get_option( 'cp_group_custom_field_mapping', [] );
		$custom_mapping_data = array();

		foreach ( $groups as $group ) {
			$mapped_values = $this->get_mapped_values( $group, $group_mapping );

			$args = [
				'chms_id'          => '',
				'post_status'      => 'publish',
				'post_title'       => '',
				'post_content'     => '',
				'tax_input'        => [],
				'group_category'   => [],
				'group_type'       => [],
				'group_life_stage' => [],
				'meta_input'       => [],
				'thumbnail_url'    => '',
				'break' => 11,
			];

			if( isset( $mapped_values['chms_id'] ) ) {
				$args['chms_id']      = $mapped_values['chms_id'];
			}

			if( isset( $mapped_values['post_content'] ) ) {
				$args['post_content'] = $mapped_values['post_content'];
			}

			if( isset( $mapped_values['post_title'] ) ) {
				$args['post_title'] = $mapped_values['post_title'];
			}

			if( isset( $mapped_values['leader'] ) ) {
				$args['meta_input']['leader'] = $mapped_values['leader'];
			}

			if( isset( $mapped_values['start_date'] ) ) {
				$args['meta_input']['start_date'] = date( 'Y-m-d', strtotime( $mapped_values['start_date'] ) );
			}

			if( isset( $mapped_values['end_date'] ) ) {
				$args['meta_input']['end_date'] = date( 'Y-m-d', strtotime( $mapped_values['end_date'] ) );
			}

			if( isset( $mapped_values['thumbnail_url'] ) ) {
				$url = get_option( 'ministry_platform_api_config' );
				$url = isset( $url[ 'MP_API_ENDPOINT' ] ) ? $url[ 'MP_API_ENDPOINT' ] : '';
				$args['thumbnail_url'] = $url . '/files/' . $mapped_values['thumbnail_url'] . '?mpgroup-' . sanitize_title( $args['post_title'] ) . '.jpeg';
			}

			if( isset( $mapped_values['frequency'] ) ) {
				$args['meta_input']['frequency'] = $mapped_values['frequency'];
			}

			if ( isset( $mapped_values['city'] ) ) {
				$state_or_region = isset( $mapped_values['state_or_region'] ) ? $mapped_values['state_or_region'] : '';
				$postal_code = isset( $mapped_values['postal_code'] ) ? $mapped_values['postal_code'] : '';
				$args['meta_input']['location'] = sprintf( "%s, %s %s", $mapped_values['city'], $state_or_region, $postal_code );
			}

			if( isset( $mapped_values['time_desc'] ) ) {
				$args['meta_input']['time_desc'] = $mapped_values['time_desc'];
			}

			if( isset( $mapped_values['meeting_time'] ) ) {
				$args['meta_input']['time_desc'] = date( 'g:ia', strtotime( $mapped_values[ 'meeting_time' ] ) );

				if ( ! empty( $mapped_values['meeting_day'] ) ) {
					$args['meta_input']['time_desc'] = $mapped_values['meeting_day'] . 's at ' . $args['meta_input']['time_desc'];
					$args['meta_input']['meeting_day'] = $mapped_values['meeting_day'];
				}
			}

			if( isset( $mapped_values['cp_location'] ) ) {
				if( $location = $this->get_location_term( $mapped_values['cp_location'] ) ) {
					$args['cp_location'] = $location;
				}
			}

			if( isset( $mapped_values['group_category'] ) ) {
				$args['group_category'][] = $mapped_values['group_category'];
			}

			if( isset( $mapped_values['group_type'] ) ) {
				$args['group_type'][] = $mapped_values['group_type'];
			}

			if( isset( $mapped_values['group_life_stage'] ) ) {
				$args['group_life_stage'][] = $mapped_values['group_life_stage'];
			}

			if( isset( $mapped_values['gender'] ) ) {
				$args['gender'] = $mapped_values['gender'];
			}

			/** 
			 * Builds the custom data needed for getting available group options in metadata
			 */
			foreach( array_keys( $group ) as $key ) {
				if( ! isset( $custom_mappings[$key] ) ) {
					continue;
				}
				if( ! $group[$key] ) {
					continue;
				}

				if( ! ( isset( $custom_mapping_data[$key] ) && $custom_mapping_data[$key] ) ) {
					$custom_mapping_data[$key] = array(
						'field_name' => $key,
						'display_name' => $custom_mappings[$key],
						'slug' => 'cp_connect_' . sanitize_title( $custom_mappings[$key] ),
						'options' => array()
					);
				}
				
				// no duplicate options
				if( ! in_array( $group[$key], $custom_mapping_data[$key]['options'] ) ) {
					$option_slug = sanitize_title( $group[$key] );
					$custom_mapping_data[$key]['options'][$option_slug] = $group[$key];
				}
			}

			foreach( $custom_mappings as $field => $display_name ) {
				if( ! isset( $group[$field] ) || ! $group[$field] ) {
					continue;
				}

				$slug = 'cp_connect_' . sanitize_title( $display_name );
				$original_slug = $slug;
				$suffix = 1;

				while( isset( $args['meta_input'][$slug] ) ) {
					$slug = $original_slug . '-' . $suffix;
					$suffix += 1;
				}

				$args['meta_input'][$slug] = sanitize_title( $group[$field] );
			}

			$formatted[] = $args;
		}

		update_option( 'cp_group_custom_meta_mapping', $custom_mapping_data, 'no' );

		$integration->process( $formatted );
	}
}
