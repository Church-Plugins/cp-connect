<?php

namespace CP_Connect\ChMS;

use PlanningCenterAPI\PlanningCenterAPI as PlanningCenterAPI;

class PCO extends ChMS {

	public function integrations() {

		if( true === $this->load_connection_parameters() ) {
			add_filter( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
			add_filter( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );
		}

		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );

		// $this->pull_events();
		$this->pull_groups();
	}

	public function pull_events( $events = [] ) {

		// Pull events here
		$pco = new PlanningCenterAPI();

		$items =
			$pco->module('calendar')
				->table('events')
				->get();

		error_log( var_export( $items, true ) );

		if( !empty( $pco->errorMessage() ) ) {
			error_log( var_export( $pco->errorMessage(), true ) );
		}


		$formatted = [];
		return $formatted;
	}

	public function pull_groups( $groups = [] ) {

		// Pull groups here
		$pco = new PlanningCenterAPI();

		$items =
			$pco->module('groups')
				->table('groups')
				->get();

		error_log( var_export( $items, true ) );

		if( !empty( $pco->errorMessage() ) ) {
			error_log( var_export( $pco->errorMessage(), true ) );
		}


		$formatted = [];
		return $formatted;
	}

	/**
	 * This function introduces a single plugin menu option into the WordPress 'Plugins'
	 * menu.
	 */

	function plugin_menu() {

		add_submenu_page( 'options-general.php',
			'Planning Center Online Integration',         // The title to be displayed in the browser window for this page.
			'PCO',                        // The text to be displayed for this menu item
			'administrator',                    // Which type of users can see this menu item
			'pco_plugin_options', // The unique ID - that is, the slug - for this menu item
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
			<h2>Planning Center Online Plugin Options</h2>
			<p class="description">Here you can set the parameters to authenticate to and use the Planning Center Online
				API</p>
			<!-- Make a call to the WordPress function for rendering errors when settings are saved. -->
			<?php settings_errors(); ?>

			<!-- Create the form that will be used to render our options -->
			<form method="post" action="options.php">
				<?php settings_fields( 'pco_plugin_options' ); ?>
				<?php do_settings_sections( 'pco_plugin_options' ); ?>
				<?php submit_button(); ?>
			</form>
		</div> <!-- /.wrap -->


		<?php
	} // end sandbox_plugin_display


	function initialize_plugin_options() {

		// If the options don't exist, add them
		if ( false == get_option( 'pco_plugin_options' ) ) {
			add_option( 'pco_plugin_options' );
		} // end if


		// First, we register a section. This is necessary since all future options must belong to one.
		add_settings_section(
			'pco_settings_section',                           // ID used to identify this section and with which to register options
			'API Configuration Options',                                  // Title to be displayed on the administration page
			[ $this, 'general_options_callback' ],  // Callback used to render the description of the section
			'pco_plugin_options'                              // Page on which to add this section of options
		);

		// Next, we will introduce the fields for the configuration information.
		add_settings_field(
			'PCO_APPLICATION_ID',                                  // ID used to identify the field throughout the theme
			'Application ID',                                     // The label to the left of the option interface element
			[ $this, 'pco_app_id_callback' ],        // The name of the function responsible for rendering the option interface
			'pco_plugin_options',                 // The page on which this option will be displayed
			'pco_settings_section',               // The name of the section to which this field belongs
			[                                                   // The array of arguments to pass to the callback. In this case, just a description.
			    'The <strong>Application ID</strong> for a Personal Access Token'
			]
		);

		add_settings_field(
			'PCO_SECRET',                                  // ID used to identify the field throughout the theme
			'Application Secret',                                     // The label to the left of the option interface element
			[ $this, 'pco_app_secret_callback' ],        // The name of the function responsible for rendering the option interface
			'pco_plugin_options',                 // The page on which this option will be displayed
			'pco_settings_section',               // The name of the section to which this field belongs
			[                                                   // The array of arguments to pass to the callback. In this case, just a description.
			    'The <strong>Secret</strong> for your Personal Access Token'
			]
		);

		// Finally, we register the fields with WordPress
		register_setting(
			'pco_plugin_options',
			'pco_plugin_options'
		);


	} // end ministry_platform_initialize_plugin_options

	function general_options_callback() {
		echo '<p>The following parameters are required to authenticate to the API and then execute API calls to Planning Center Online.</p><p>You can get your authentication credentials by <a target="_blank" href="https://api.planningcenteronline.com/oauth/applications">clicking here</a> and scrolling down to "Personal Access Tokens"</p>';
	}


	function get_option_value( $key, $options = false ) {

		if ( ! $options ) {
			$options = get_option( 'pco_plugin_options' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';

	}

	function pco_app_id_callback( $args ) {

		$options = get_option( 'pco_plugin_options' );
		$opt = $this->get_option_value( 'PCO_APPLICATION_ID', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="PCO_APPLICATION_ID" name="pco_plugin_options[PCO_APPLICATION_ID]" value="' . $opt . '" size="60"/>';
		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="PCO_APPLICATION_ID"> ' . $args[0] . '</label>';

		echo $html;

	} // end pco_app_id_callback

	function pco_app_secret_callback( $args ) {

		$options = get_option( 'pco_plugin_options' );
		$opt = $this->get_option_value( 'PCO_SECRET', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="password" id="PCO_SECRET" name="pco_plugin_options[PCO_SECRET]" value="' . $opt . '" size="60"/>';
		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="PCO_SECRET"> ' . $args[0] . '</label>';

		echo $html;

	} // end pco_app_secret_callback

	/**
	 * Get oAuth and API connection parameters from the database
	 *
	 * @param string $option_slug			Ignored
	 * @return bool
	 */
	function load_connection_parameters( $option_slug = 'pco_plugin_options' ) {
		return parent::load_connection_parameters( 'pco_plugin_options' );
	}

	/**
	 * Get parameters for this connection
	 *
	 * @param string $option_slug
	 * @return array
	 * @author costmo
	 */
	function get_connection_parameters( $option_slug = 'pco_plugin_options' ) {
		return parent::get_connection_parameters( $option_slug );
	}

}