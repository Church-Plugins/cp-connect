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
						// $this->render_group_mapping_tab();
						echo "Rock RMS Options content";
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
				// $this->initialize_group_mapping_options();
				break;
		}
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
	 * Content displayed on the API config tab
	 */
	function api_config_callback() {
		?>
			<!-- Add the icon to the page -->
			<div id="icon-themes" class="icon32"></div>
			<h2>Rock RMS Plugin Options</h2>
			<p class="description">Here you can set the parameters to authenticate to and use the Rock RMS API</p>
			<p>The following parameters are required to authenticate to the API using oAuth and then execute API calls to Rock RMS.</p>
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
	 * Handles pulling groups from Ministry Platform
	 *
	 * @param \CP_Connect\Integrations\CP_Groups $integration
	 */
	public function pull_groups( $integration ) {

		$params = [
			'IsSystem'		=> 'false',
			'IsActive'		=> 'true',
			'IsPublic'		=> 'true'
		];

		$group_data = $this->remote_rest_request( 'api/Groups', 'GET', $params );
		if( empty( $group_data ) || !is_array( $group_data ) ) {
			return false;
		} else {
			_C::log( $group_data );
		}

	}

	/**
	 * Handles pulling groups from Ministry Platform
	 *
	 * @param \CP_Connect\Integrations\CP_Groups $integration
	 */
	public function pull_events( $integration ) {

		// TODO: This
		_C::log( "PULL EVENTS" );

	}

	private function remote_rest_request( $endpoint_path, $method = 'GET', $params = [] ) {

		$endpoint_path = preg_replace( "/^\//", "", $endpoint_path );
		$config = get_option( 'rockrms_api_config' );
		$endpoint = $this->get_option_value( 'RRMS_API_ENDPOINT', $config );
		$api_key = $this->get_option_value( 'RRMS_API_REST_KEY', $config );

		if( empty( $endpoint ) || empty( $api_key ) ) {
			return;
		}

		$endpoint = trailingslashit( $endpoint ) . $endpoint_path;
		if( !empty( $params ) ) {
			$endpoint .= '?' . http_build_query( $params );
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