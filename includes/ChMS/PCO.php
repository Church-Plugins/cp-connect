<?php

namespace CP_Connect\ChMS;

use PlanningCenterAPI\PlanningCenterAPI as PlanningCenterAPI;

/**
 * TEMPORARY - DO NOT KEEP THE PCO_CLI CLASS BEYOND TESTING
 */
// Make the `cp` command available to WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'pco', '\CP_Connect\ChMS\PCO_CLI' );
}

class PCO_CLI {


	public $api = null;

	public function __construct() {
	}

	public function test_it( $args, $assoc_args ) {

		$pco = PCO::get_instance();
		$pco->pull_events();
		// $pco->setup_taxonomies( true );

	}
}


class PCO extends ChMS {

	public function integrations() {

		if( true === $this->load_connection_parameters() ) {
			add_filter( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
			add_filter( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );
		}

		$this->setup_taxonomies( false );
		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );

		// $this->pull_events();
		// $this->pull_groups();
	}

	/**
	 * Pull tags for an event instance
	 *
	 * @param int $event_instance_id
	 * @return array
	 * @author costmo
	 */
	public function pull_event_tags( $event_instance_id ) {

		$raw =
			$this->api()
				->module('calendar')
				->table('event_instances')
				->id( $event_instance_id )
				->associations('tags')
				->get();
		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		echo "RAW TAG DATA:\n";
		echo var_export( $raw, true ) . "\n----------\n";
		echo "END TAG EVENT DATA:\n";
	}

	/**
	 * Setup and register taxonopmies for incoming data
	 *
	 * Set true to import remote data
	 *
	 * @param boolean $add_data
	 * @return void
	 * @author costmo
	 */
	public function setup_taxonomies( $add_data = false ) {

		// Do not query the remote source on every page load
		// TODO: This needs to be more dynamic, but less heavy
		$taxonomies = [
			'Campus' 			=> false,
			'Ministry Group' 	=> false,
			'Event Type' 		=> false,
			'Frequency' 		=> false,
			'Ministry Leader' 	=> false
		];

		if( $add_data ) {
			$raw_groups =
				$this->api()
					->module('calendar')
					->table('tag_groups')
					->get();
			if( !empty( $this->api()->errorMessage() ) ) {
				error_log( var_export( $this->api()->errorMessage(), true ) );
				return [];
			}

			$taxonomies = [];
			foreach( $raw_groups['data'] as $group ) {
				$group_name = $group['attributes']['name'];
				$group_id = $group['id'];
				$taxonomies[ $group_name ] = $group_id;
			}
		}

		$output = [];

		// foreach( $raw_groups['data'] as $group ) {
		foreach( $taxonomies as $group_name => $group_id ) {

			if( !array_key_exists( $group_name, $output ) ) {
				$output[ $group_name ] = [];
			}

			if( $add_data && !empty( $group_id ) ) {
				$raw_tags =
					$this->api()
						->module('calendar')
						->table('tag_groups')
						->id( $group_id )
						->includes('tags')
						->get();

				foreach( $raw_tags['included'] as $tag_data ) {

					if( !in_array( $tag_data['attributes']['name'], $output[ $group_name ] ) ) {
						$output[ $group_name ][] = $tag_data['attributes']['name'];
					}
				}
			}
		}

		foreach( $output as $tax_name => $tax_data ) {

			$labels = [
				'name'              => ucwords( $tax_name ),
				'singular_name'     => ucwords( $tax_name ),
				'search_items'      => 'Search ' . ucwords( $tax_name ),
				'all_items'         => 'All ' . ucwords( $tax_name ),
				'parent_item'       => 'Parent ' . ucwords( $tax_name ),
				'parent_item_colon' => 'Parent ' . ucwords( $tax_name ),
				'edit_item'         => 'Edit ' . ucwords( $tax_name ),
				'update_item'       => 'Update ' . ucwords( $tax_name ),
				'add_new_item'      => 'Add New ' . ucwords( $tax_name ),
				'new_item_name'     => 'New ' . ucwords( $tax_name ),
				'menu_name'         => ucwords( $tax_name ),
				'not_found'         => 'No ' . ucwords( $tax_name ) . " found",
				'no_terms'          => 'No ' . ucwords( $tax_name )
			];

			$args   = [
				'public'            => true,
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_tag_cloud'    => true,
				'show_admin_column' => false,
			];

			register_taxonomy( $tax_name, 'tribe_events', $args );

			if( $add_data && !empty( $tax_data ) ) {
				foreach( $tax_data as $term ) {
					wp_insert_term( $term, $tax_name );
				}
			}
		}
	}

	/**
	 * Get details about a specific event
	 *
	 * @param int $event_id
	 * @return array
	 * @author costmo
	 */
	public function pull_event( $event_id ) {

		$raw =
			$this->api()
				->module('calendar')
				->table('events')
				->id( $event_id )
				->get();
		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		echo "RAW EVENT DATA:\n";
		echo var_export( $raw, true ) . "\n----------\n";
		echo "END RAW EVENT DATA:\n";
	}

	/**
	 * Pull all future published/public events from the ChMS
	 *
	 * @param array $events
	 * @return array
	 * @author costmo
	 */
	public function pull_events( $events = [] ) {

		// Pull event instances (top-level event entries by upcoming event date)
		$raw =
			$this->api()
				->module('calendar')
				->table('event_instances')
				->includes('event')
				->filter('future,approved')
				->order('starts_at')
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		$formatted = [];

		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		// TODO: 1. Download tag_groups and add as taxonomies
		// TODO: 2. Populate tags into the new taxonomies

		foreach ( $items as $event_instance ) {

			echo var_export( $event_instance, true ) . "\n";

			// echo var_export( $event_instance, true ) . "\n----------\n";
			$event_id = $event_instance['relationships']['event']['data']['id'] ?? 0;
			if( empty( $event_id ) ) {
				continue;
			}

			echo "\nPULL FOR: " . $event_id . "\n";
			$event = $this->pull_event( $event_id );
			$tags = $this->pull_event_tags( $event_instance['id'] );

			exit();

			$start_date = strtotime( $event_instance['attributes']['starts_at'] );
			$end_date   = strtotime( $event_instance['attributes']['ends_at'] );

			$args = [
				'chms_id'               => $event_id,
				'post_status'           => 'publish',
				'post_title'            => $event['data']['attributes']['name'],
				'post_content'          => $event['data']['attributes']['description'],
				'post_excerpt'          => $event['data']['attributes']['summary'],
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
				'FeaturedImage'         => $event['data']['attributes']['image_url'] ?? '',
			];

			if ( ! empty( $event['data']['attributes']['image_url'] ) ) {
				$args['thumbnail_url'] = $event['data']['attributes']['image_url'];
			}

			if ( ! empty( $event_instance['attributes']['location'] ) ) {
				if ( $location = $this->get_location_term( $event_instance['attributes']['location'] ) ) {
					$args['tax_input']['cp_location'] = $location;
				}
			}

			// PCO TODO: Campus, Ministry Group, Event Type, Frequency and Ministry Leader are tag types, but there does
			//   not appear to be a way to differentiaite between the Type of each tag value from this end
			if ( ! empty( $tags['data'] ) && is_array( $tags['data'] ) ) {
				$tags = [];
				foreach( $tags['data'] as $tag_data ) {
					if( !empty( $tag_data ) && !empty( $tag_data['attributes'] ) && !empty( $tag_data['attributes']['name'] ) ) {
						$tags[] = $tag_data['attributes']['name'];
					}
				}
				// TODO: Save tags for the entry
			}

// 			if ( ! empty( $event['Event_Type'] ) ) {
// 				$args['event_category'][] = $event['Event_Type'];
// 			}

// 			if ( ! empty( $event['Program_Name'] ) ) {
// 				$args['event_category'][] = $event['Program_Name'];
// 			}

// 			if ( ! empty( $event['First_Name'] ) ) {
// 				$args['Organizer'] = [
// 					'Organizer' => $event['First_Name'] . ' ' . $event['Last_Name'],
// 					'Email'     => $event['Email_Address'],
// //					'Website'   => $event[''],
// //					'Phone'     => $event[''],
// 				];
// 			}

// 			if ( ! empty( $event['Location_Name'] ) ) {
// 				$args['Venue'] = [
// 					'Venue'    => $event['Location_Name'],
// //					'Country'  => $event[''],
// 					'Address'  => $event['Address_Line_1'],
// 					'City'     => $event['City'],
// 					'State'    => $event['State/Region'],
// //					'Province' => $event[''],
// 					'Zip'      => $event['Postal_Code'],
// //					'Phone'    => $event[''],
// 				];
// 			}

// 			$formatted[] = $args;
		}

		echo "ITEM COUNT: " . count( $items ) . "\n";

		return $formatted;

	}

	public function pull_groups( $groups = [] ) {

		// Pull groups here
		$items =
			$this->api()
				->module('groups')
				->table('groups')
				->get();

		error_log( var_export( $items, true ) );

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
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

	/**
	 * Singleton instance of the third-party API client
	 *
	 * @return PlanningCenterAPI\PlanningCenterAPI
	 * @author costmo
	 */
	public function api() {

		if( empty( $this->api ) ) {
			$this->api = $pco = new PlanningCenterAPI();
		}

		return $this->api;
	}

}