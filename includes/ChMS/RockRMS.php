<?php

namespace CP_Connect\ChMS;

use \CP_Connect\Setup\Convenience as _C;

/**
 * RockRMS Integration provider
 *
 */
class RockRMS extends ChMS {

	public function integrations() {

		$this->load_connection_parameters( 'rockrms_api_config' );

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
			__( 'Rock RMS Integration', 'cp-connect' ),         // The title to be displayed in the browser window for this page.
			__( 'Rock RMS', 'cp-connect' ),                        // The text to be displayed for this menu item
			'administrator',                    // Which type of users can see this menu item
			'rockrms_plugin_options', // The unique ID - that is, the slug - for this menu item
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
				<a href='?page=rockrms_plugin_options&tab=connect' class='nav-tab <?php echo $tab == 'connect' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Connect', 'cp-connect' ) ?></a>
				<a href='?page=rockrms_plugin_options&tab=group-options' class='nav-tab <?php echo $tab == 'group-options' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Group Options', 'cp-connect' ) ?></a>
			</nav>
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
	 * Initialize the group mapping options in the admin
	 */
	function initialize_group_mapping_options() {
		/*** Group Field Mapping Settings ***/

		if( !empty( $_POST ) ) {
			// TODO: Save here
			// $this->save_custom_fields();
		}

		$group_mapping_option = 'rockrms_group_mapping';       // the option id
		$group_mapping_tab    = 'rockrms_group_mapping_tab';   // the id for the tab
		$group_mapping_group  = 'rockrms_group_mapping_group'; // the id for the settings group

		register_setting( $group_mapping_group, $group_mapping_option );

		add_settings_section(
			$group_mapping_option,
			'Rock RMS Field Mapping',
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
	 * Content displayed on the Group Mapping tab
	 */
	function render_group_mapping_tab() {

		settings_fields( 'rockrms_group_mapping_group' );
		do_settings_sections( 'rockrms_group_mapping_tab' );

		// TODO: This is not rendering properly for Rock RMS
		// $this->render_custom_mappings();

		$valid_fields = $this->valid_fields();

		$txt_fields = json_encode( $valid_fields );
		echo "<input type='hidden' name='rockrms_group_valid_fields' value='{$txt_fields}'>";
		wp_nonce_field( 'cp-connect-rrms-fields', '_cp_rrms_nonce' );
		submit_button();


	}

	/**
	 * Valid/known field filter
	 *
	 * @return array
	 * @author costmo
	 */
	protected function valid_fields() {

		// TODO: Add custom mappings here
		return array_merge( ['select'], $this->get_all_group_mapping_fields() );
	}

	/**
	 * Content displayed on the Group Mapping tab
	 */
	function group_mapping_callback() {
		$this->render_field_select( 'rockrms_group_mapping' );

		echo '<h3>Group Field Mapping</h3>';
		echo '<p>The following parameters are used to map Rock RMS groups to the CP Groups plugin</p>';
	}

	/**
	 * Render the API configuration tab
	 */
	function render_api_config_tab() {
		settings_fields( 'rockrms_api_config_group' );
		do_settings_sections( 'rockrms_api_config_tab' );
		?>
		<p class="submit">
			<?php submit_button( null, 'primary', 'submit', false ); ?>
			<?php submit_button( 'Pull Now', 'secondary', 'cp-connect-pull', false ); ?>
		</p>
		<?php
	}

		/**
	 * Get all tables to grab from the API
	 */
	function get_all_group_mapping_fields() {
		$fields = get_option( 'rockrms_group_mapping' );
		$fields = isset( $fields['fields'] ) ? $fields['fields'] : array();

		return array_merge( $this->get_default_group_mapping_fields(), $fields );
	}

	/**
	 * Render a interface to select additional fields to grab from the API
	 *
	 * @param string $option_id The option id
	 */
	function render_field_select( $option_id ) {
		$option = get_option( $option_id );

		$fields = isset( $option['fields'] ) ? $option['fields'] : array();
		$error = false;

		// $mp = new MP();

		// if( $mp->authenticate() ) {
		// 	$table = $mp->table( 'Groups' );

		// 	// makes a dummy request just to get any error messages from user specified fields
		// 	$table->select( implode( ',', $this->get_all_group_mapping_fields() ) )->top(1)->get();

		// 	$error = $table->errorMessage() ? json_decode( $table->errorMessage(), true ) : false;
		// }
		?>

		<div class="cp-connect-field-select" data-option-id="<?php echo esc_attr( $option_id ) ?>">
			<p>This is the current query being made to Rock RMS</p>
			<h4>SELECT</h4>
			<code>
				// SELECT CODE
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
					<h4>Rock RMS API Error</h4>
					<code><?php echo $error['Message'] ?></code>
				</div>
			<?php endif; ?>
		</div>
		<hr>
		<?php
	}

	/**
	 * Initialize the api config options in the admin
	 */
	function initialize_api_config_options() {
		/*** API Configuration Settings ***/

		$api_config_option = 'rockrms_api_config';
		$api_config_tab    = 'rockrms_api_config_tab';
		$api_config_group  = 'rockrms_api_config_group';

		/* register the setting group */
		register_setting( $api_config_group, $api_config_option );

		/* add the settings section */
		add_settings_section(
			$api_config_option,                    // ID used to identify this section and with which to register options
			__( 'API Configuration', 'cp-connect' ),                   // Title to be displayed on the administration page
			[ $this, 'api_config_callback' ],      // Callback used to render the description and fields for this section.
			$api_config_tab                        // Tab on which to add this section of options
		);

		/* Introduce the fields for the configuration information. */
		add_settings_field(
			'RRMS_API_ENDPOINT',                                    // ID used to identify the field throughout the theme
			__( 'API Endpoint', 'cp-connect' ),                                       // The label to the left of the option interface element
			[ $this, 'rrms_api_endpoint_callback' ],                // The name of the function responsible for rendering the option interface
			$api_config_tab,                                      // The tab on which this option will be displayed
			$api_config_option,                                   // The option name to which this field belongs
			[ 'ex: https://rockrms.mychurch.com' ] // The array of arguments to pass to the callback. In this case, just a description.
		);

		add_settings_field(
			'RRMS_API_REST_KEY',
			__( 'API Rest Key', 'cp-connect' ),
			[ $this, 'rrms_api_rest_key_callback' ],
			$api_config_tab,
			$api_config_option
		);

		/*** End API Configuration Settings ***/
	}

	/**
	 * The default group mapping fields to fetch from the Rock RMS API
	 */
	protected function get_default_group_mapping_fields() {

		return array(
			'Id',
			'Name',
			'GroupType',
			'LocationId', // Lookup
			'DisplayName',	// Lookup
			'Description',
			'Start_Date',
			'End_Date',
			'Postal_Code',
			'City',
			'State',
			'Meeting_Time',
			'Meeting_Day',
			'Meeting_Frequency',
		);
	}

	/**
	 * The default API to group mapping
	 */
	protected function get_default_group_mapping() {

		return array(
			'chms_id' => 'Id',
			'post_title' => 'Name',
			'post_content' => 'Description',
			'leader' => 'Display_Name',
			'start_date' => 'Start_Date',
			'end_date' => 'End_Date',
			'frequency' => 'Meeting_Frequency',
			'city' => 'City',
			'state_or_region' => 'State',
			'postal_code' => 'Postal_Code',
			'meeting_time' => 'Meeting_Time',
			'meeting_day' => 'Meeting_Day',
			'cp_location' => 'Location_Id',
			'group_type' => 'GroupType'
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
			'frequency' => 'Meeting Frequency',
			'location' => 'Location ID',
			'city' => 'City',
			'state_or_region' => 'State',
			'postal_code' => 'Postal Code',
			'meeting_time' => 'Meeting Time',
			'meeting_day' => 'Meeting Day',
			'cp_location' => 'Group Campus',
			'group_type' => 'Group Type'
		);
	}

	/**
	 * Content displayed on the API config tab
	 */
	function api_config_callback() {
		?>
			<!-- Add the icon to the page -->
			<div id="icon-themes" class="icon32"></div>
			<h2><?php _e( 'Rock RMS Plugin Options', 'cp-connect' ); ?></h2>
			<p class="description"><?php _e( 'Set the parameters to authenticate to and use the Rock RMS API below', 'cp-connect' ); ?></p>
			<p><?php _e( 'The following parameters are required to authenticate to the API and execute API calls to Rock RMS.', 'cp-connect' ); ?></p>
		<?php
	}

	/**
	 * Provides HTML for the Rock RMS API REST Key field
	 *
	 * @param Array $args
	 * @return void
	 * @author costmo
	 */
	function rrms_api_rest_key_callback( $args ) {
		$options = get_option( 'rockrms_api_config' );


		$opt = $this->get_option_value( 'RRMS_API_REST_KEY', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="password" id="RRMS_API_REST_KEY" name="rockrms_api_config[RRMS_API_REST_KEY]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="RRMS_API_REST_KEY"> ' . $args[0] . '</label>';

		echo $html;

	} // end rrms_api_endpoint_callback

	/**
	 * Provides HTML for the Rock RMS API endpoint field
	 *
	 * @param Array $args
	 * @return void
	 * @author costmo
	 */
	function rrms_api_endpoint_callback( $args ) {
		$options = get_option( 'rockrms_api_config' );


		$opt = $this->get_option_value( 'RRMS_API_ENDPOINT', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="RRMS_API_ENDPOINT" name="rockrms_api_config[RRMS_API_ENDPOINT]" value="' . $opt . '" size="60"/>';

		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="RRMS_API_ENDPOINT"> ' . $args[0] . '</label>';

		echo $html;

	} // end rrms_api_endpoint_callback

	/**
	 * Retrieve and normalize option values
	 *
	 * @param String $key
	 * @param boolean $options
	 * @return mixed
	 * @author costmo
	 */
	function get_option_value( $key, $options = false ) {

		if ( ! $options ) {
			$options = get_option( 'rockrms_api_config' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';
	}

	/**
	 * Handles pulling groups from Rock RMS
	 *
	 * @param \CP_Connect\Integrations\CP_Groups $integration
	 */
	public function pull_groups( $integration ) {

		// Get all groups, filtered by GroupType
		$group_type_data = $this->pull_group_types() ?? [];
		$group_type_ids = array_keys( $group_type_data );

		// TODO: Front-end override of these parameters
		$params = [
			'IsSystem eq false',
			'IsActive eq true',
			'IsPublic eq true',
			'IsSecurityRole eq false'
		];
		if( !empty( $group_type_ids ) ) {
			$extra_params = implode( ' or ', array_map(
				function( $id ) {
					return 'GroupTypeId eq ' . $id;
				},
				$group_type_ids
			) );
			if( !empty( $extra_params ) ) {
				$params[] = '(' . $extra_params . ')';
			}
		}

		$group_data = $this->remote_rest_request( 'api/Groups', 'GET', $params );
		if( empty( $group_data ) || !is_array( $group_data ) ) {
			return false;
		} else {
			// _C::log( "Groups" );
			// _C::log( $group_data );

			$fields = $this->get_all_group_mapping_fields();

			$group_mapping = get_option( 'rockrms_group_mapping' );
			$group_mapping = isset( $group_mapping['mapping'] ) ? $group_mapping['mapping'] : $this->get_default_group_mapping();

			$formatted = [];

			$custom_mappings = get_option( 'cp_group_custom_field_mapping', [] );
			$custom_mapping_data = array();

			$formatted = [];

			foreach( $group_data as $group ) {

				$group = (array)$group;

				$group['GroupType'] = $group_type_data[ $group['GroupTypeId'] ]['name'] ?? '';
				$group = $this->get_address_info( $group );

				$mapped_values = $this->get_mapped_values( $group, $group_mapping );

				$args = [
					'chms_id'          => '',
					'post_status'      => 'publish',
					'post_title'       => '',
					'post_content'     => '',
					'tax_input'        => [],
					'group_type'       => [],
					'meta_input'       => [],
					'break' 		   => 11
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

				// TODO: Get leader details
				// if( isset( $mapped_values['leader'] ) ) {
				// 	$args['meta_input']['leader'] = $mapped_values['leader'];
				// }

				// TODO: Pull this data
				// if( isset( $mapped_values['start_date'] ) ) {
				// 	$args['meta_input']['start_date'] = date( 'Y-m-d', strtotime( $mapped_values['start_date'] ) );
				// }

				// if( isset( $mapped_values['end_date'] ) ) {
				// 	$args['meta_input']['end_date'] = date( 'Y-m-d', strtotime( $mapped_values['end_date'] ) );
				// }
				// if( isset( $mapped_values['frequency'] ) ) {
				// 	$args['meta_input']['frequency'] = $mapped_values['frequency'];
				// }

				if ( isset( $mapped_values['city'] ) ) {
					$state_or_region = isset( $mapped_values['state_or_region'] ) ? $mapped_values['state_or_region'] : '';
					$postal_code = isset( $mapped_values['postal_code'] ) ? $mapped_values['postal_code'] : '';
					$args['meta_input']['location'] = sprintf( "%s, %s %s", $mapped_values['city'], $state_or_region, $postal_code );
				}

				// if( isset( $mapped_values['time_desc'] ) ) {
				// 	$args['meta_input']['time_desc'] = $mapped_values['time_desc'];
				// }

				// if( isset( $mapped_values['meeting_time'] ) ) {
				// 	$args['meta_input']['time_desc'] = date( 'g:ia', strtotime( $mapped_values[ 'meeting_time' ] ) );

				// 	if ( ! empty( $mapped_values['meeting_day'] ) ) {
				// 		$args['meta_input']['time_desc'] = $mapped_values['meeting_day'] . 's at ' . $args['meta_input']['time_desc'];
				// 		$args['meta_input']['meeting_day'] = $mapped_values['meeting_day'];
				// 	}
				// }

				// if( isset( $mapped_values['cp_location'] ) ) {
				// 	if( $location = $this->get_location_term( $mapped_values['cp_location'] ) ) {
				// 		$args['cp_location'] = $location;
				// 	}
				// }


				if( isset( $mapped_values['group_type'] ) ) {
					$args['group_type'][] = $mapped_values['group_type'];
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

	/**
	 * Make an API query to fill-in address information for this group
	 *
	 * @return array
	 */
	public function get_address_info( $group ) {

		$params = [
			'GroupId eq ' . $group['Id']
		];

		$return_value = [];

		$group_location = $this->remote_rest_request( 'api/GroupLocations', 'GET', $params );
		if( empty( $group_location ) || !is_array( $group_location ) || empty( $group_location[0] ) ) {
			return $group;
		}

		$params = [
			'Id eq ' . $group_location[0]->LocationId
		];

		$location_details = $this->remote_rest_request( 'api/Locations/', 'GET', $params );
		if( empty( $location_details ) || !is_array( $location_details ) || empty( $location_details[0] ) ) {
			return $group;
		}

		$group['City'] = $location_details[0]->City;
		$group['State'] = $location_details[0]->State;
		$group['Postal_Code'] = $location_details[0]->PostalCode;

		return $group;

	}

	/**
	 * Retrieve valid/important group type IDs from Rock RMS
	 *
	 * @return mixed	`false` on failure or empty results, otherwise array of group types with GroupType ID as key
	 *
	 */
	public function pull_group_types() {

		// TODO: Front-end override of these parameters
		$params = [
			'IsSystem eq false',
			'ShowInGroupList eq true',
			'ShowInNavigation eq true',
		];

		$return_value = [];

		$group_data = $this->remote_rest_request( 'api/GroupTypes', 'GET', $params );

		if( empty( $group_data ) || !is_array( $group_data ) ) {
			return false;
		} else {

			foreach( $group_data as $loop_data ) {
				if( !empty( $loop_data ) && is_object( $loop_data ) && !empty( $loop_data->Id ) ) {
					$return_value[ $loop_data->Id ] =
						[
							'id'			=> $loop_data->Id,
							'name'			=> $loop_data->Name,
							'description'	=> $loop_data->Description,
							'idkey'			=> $loop_data->IdKey
						];
				}
			}
		}

		return $return_value;
	}

	/**
	 * Handles pulling events from Rock RMS
	 *
	 * @param \CP_Connect\Integrations\CP_Groups $integration
	 */
	public function pull_events( $integration ) {

		// TODO: This
		_C::log( "TODO: PULL EVENTS FOR ROCK RMS" );

	}

	public function remote_rest_request( $endpoint_path, $method = 'GET', $params = [] ) {

		$endpoint_path = preg_replace( "/^\//", "", $endpoint_path );
		$config = get_option( 'rockrms_api_config' );
		$endpoint = $this->get_option_value( 'RRMS_API_ENDPOINT', $config );
		$api_key = $this->get_option_value( 'RRMS_API_REST_KEY', $config );

		if( empty( $endpoint ) || empty( $api_key ) ) {
			return;
		}

		$endpoint = trailingslashit( $endpoint ) . $endpoint_path;
		if( !empty( $params ) ) {
			$endpoint .= '?$filter=';// . http_build_query( $params );
			foreach( $params as $param ) {
				$endpoint .= preg_replace( "/\ /", "%20", $param ) . '%20and%20';
			}
			$endpoint = preg_replace( "/%20and%20$/", "", $endpoint );
		}

		// $endpoint .= '?filter=IsSystem%20eq%20false';

		_C::log( "ENDPOINT" );
		_C::log( $endpoint );

		// TODO: Different mechanism for 'POST'
		$response = wp_remote_get( $endpoint, [
			'timeout'	=> 30,
			'headers'	=> [
				'Authorization-Token' => $api_key
			]
		]);

		// Basic sanity check
		if( empty( $response ) || !is_array( $response ) ) {
			// TODO: Response is probably a WP_Error - bubble error messages to the user
			return false;
		}

		// Validate the response
		if( empty( $response['response'] ) || !is_array( $response['response'] ) || empty( $response['response']['code'] ) || (int)$response['response']['code'] !== 200 ) {
			return false;
		}

		$response_body = false;
		// Validate the body
		if( empty( $response['body'] ) || !is_string( $response['body'] ) ) {
			return false;
		} else {
			$response_body = @json_decode( $response['body'] );
			if( empty( $response_body ) || !is_array( $response_body ) ) {
				return false;
			}
		}

		return $response_body;
	}
}