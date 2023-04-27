<?php

namespace CP_Connect\ChMS;

use PlanningCenterAPI\PlanningCenterAPI as PlanningCenterAPI;

/**
 * Planning Center Online implementation
 *
 * @author costmo
 */
class PCO extends ChMS {

	/**
	 * Convenience reference to an external API connection
	 *
	 * @var PlanningCenterAPI
	 * @author costmo
	 */
	public $api = null;

	protected $events = [];

	protected $campuses = [];

	/**
	 * Load up, if possible
	 *
	 * @return void
	 * @author costmo
	 */
	public function integrations() {

		// If this integration is not configured, do not add our pull filters
		if( true === $this->load_connection_parameters() ) {
			$this->maybe_setup_taxonomies();
			add_action( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
			add_action( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );
		}

		$this->setup_taxonomies( false );
		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
		add_action( 'cp_tec_update_item_after', [ $this, 'event_taxonomies' ], 10, 2 );
	}

	public function event_taxonomies( $item, $id ) {
		$taxonomies = [ 'Ministry Group', 'Ministry Leader', 'Frequency', 'cp_ministry' ];
		foreach( $taxonomies as $tax ) {
			$tax_slug = \CP_Connect\ChMS\ChMS::string_to_slug( $tax );
			$categories = [];

			if( !empty( $item['tax_input'][$tax_slug] ) ) {
				$term_value = '';
				if( is_string( $item['tax_input'][$tax_slug] ) ) {

					$term_value = $item['tax_input'][$tax_slug];
					if( ! $term = term_exists( $term_value, $tax_slug ) ) {
						$term = wp_insert_term( $term_value, $tax_slug );
					}
					if( !is_wp_error( $term ) ) {
						$categories[] = $term['term_id'];
					}

				} else if( is_array( $item['tax_input'][$tax_slug] ) ) {

					foreach( $item['tax_input'][$tax_slug] as $term_value ) {

						if ( !$term = term_exists( $term_value, $tax_slug ) ) {
							$term = wp_insert_term( $term_value, $tax_slug );
						}
						if( !is_wp_error( $term ) ) {
							$categories[] = $term_value;
						}
					}

				}

				wp_set_post_terms( $id, $categories, $tax_slug );
			}
		}
	}

	/**
	 * Setup foundational taxonomy data from the remote source if we do not have any such data locally
	 *
	 * @return void
	 * @author costmo
	 */
	private function maybe_setup_taxonomies() {

		global $wpdb;

		// Search for the existence of a single taxonomy to determine if we've already done this
		$test_tax = 'ministry_leader';
		$have_data = false;


		// This function hits before the 'Init' hook, so `taxonomy_exists()` will
		//    always return false. Direct query required.
		$prepared = $wpdb->prepare(
			"SELECT		COUNT(*) as count
			 FROM		" . $wpdb->prefix . "term_taxonomy
			 WHERE		taxonomy = %s",
			 $test_tax
		);
		$results = $wpdb->get_results( $prepared );
		if( !empty( $results ) && is_array( $results ) ) {
			foreach( $results as $row ) {
				if( $row && is_object( $row ) && !empty( $row->count ) ) {
					$have_data = true;
				}
			}
		}

		// We have no data, populate it now
		if( !$have_data ) {
			$this->setup_taxonomies( true );
		}
		return;
	}

	/**
	 * Setup and register taxonomies for incoming data
	 *
	 *
	 * @param boolean $add_data		Set true to import remote data
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

			$tax_slug = ChMS::string_to_slug( $tax_name );
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
				'slug'				=> $tax_slug
			];

			register_taxonomy( $tax_slug, 'tribe_events', $args );

			if( $add_data && !empty( $tax_data ) ) {
				foreach( $tax_data as $term ) {
					wp_insert_term( $term, $tax_slug );
				}
			}
		}
	}

	/**
	 * Get the details of a specific campus/location
	 *
	 * Names do not always match exactly, so we also try `$campus_name . ' campus'` and `$campus_name . ' campuses'`
	 *
	 * @param string $campus_name			The campus name to find
	 * @return void
	 * @author costmo
	 */
	public function pull_campus_details( $campus_name ) {

		if ( empty( $this->campuses ) ) {
			// The PCO API seemingly ignores all input to get a specific record
			$this->campuses =
				$this->api()
				     ->module( 'people' )
				     ->table( 'campuses' )
				     ->get();
			if ( ! empty( $this->api()->errorMessage() ) ) {
				error_log( var_export( $this->api()->errorMessage(), true ) );

				return [];
			}
		}


		if( !empty( $this->campuses ) && is_array( $this->campuses ) && !empty( $this->campuses['data'] ) ) {
			foreach( $this->campuses['data'] as $index => $location_data ) {
				$normal_incoming = strtolower( $campus_name );
				$normal_loop = strtolower( $location_data['attributes']['name'] ?? '' );

				if( $normal_incoming == $normal_loop ||
					$normal_incoming . ' campus' == $normal_loop ||
					$normal_incoming . ' campuses' == $normal_loop ) {

						$return_value = $location_data['attributes'];
						$return_value['id'] = $location_data['id'];
						// If we found one, return it now - no need to keep iterating the loop
						return $return_value;
				}
			}
		}

		return [];
	}

	/**
	 * Pull tags for an event instance
	 *
	 * @param array $event
	 * @return array
	 * @author costmo
	 */
	public function pull_event_tags( $event ) {
		if ( empty( $event['relationships'] ) || empty( $event['relationships']['tags'] ) ) {
			return [];
		}

		if ( empty( $this->events['included'] ) ) {
			return [];
		}

		$tags = [];
		foreach( $this->events['included'] as $include ) {
			if ( $include['type'] != 'Tag' ) {
				continue;
			}

			$tags[ $include['id'] ] = $include;
		}

		$event_tags = [];
		foreach( $event['relationships']['tags']['data'] as $tag ) {
			if ( ! empty( $tags[ $tag['id'] ] ) ) {
				$event_tags[] = $tags[ $tag['id'] ];
			}
		}

		return $event_tags;
	}

	/**
	 * Get details about a specific event
	 *
	 * @param int $event_id
	 * @return array
	 * @author costmo
	 */
	public function pull_event( $event_id ) {

		if ( ! empty( $this->events['data'] ) ) {
			foreach( $this->events['data'] as $event ) {
				if ( $event['id'] == $event_id ) {
					return $event;
				}
			}

			return false;
		}

		$raw =
			$this->api()
				->module('calendar')
				->table('events')
				->id( $event_id )
				->includes('owner')
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		$output = $raw['data'] ?? $raw;

		// Normalize event owner details
		if( !empty( $raw['included'] ) && is_array( $raw['included'] ) ) {
			foreach( $raw['included'] as $included_data ) {
				if( !empty( $included_data['type'] ) && 'Person' === $included_data['type'] ) {
					$output['contact'] = $included_data['attributes'];
				}
			}
		}

		return $output;
	}

	/**
	 * Pull all future published/public events from the ChMS and massage the data for WP insert
	 *
	 * @param array $events
	 * @param bool $show_progress		true to show progress on the CLI
	 * @return array
	 * @author costmo
	 */
	public function pull_events( $integration, $show_progress = false ) {

		error_log( "PULL EVENTS STARTED " . date( 'Y-m-d H:i:s' ) );

		// Pull upcoming events
		$raw =
			$this->api()
				->module('calendar')
				->table('event_instances')
				->includes('event,event_times')
				->filter('future')
				->order('starts_at')
				->get();

		$this->events =
			$this->api()
				->module( 'calendar' )
				->table('events')
				->includes('tags' )
				->where('visible_in_church_center', '=', 'true' )
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		// Give massaged data somewhere to go - the return variable
		$formatted = [];

		// Collapse and normalize the response
		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		if( $show_progress ) {
			$progress = \WP_CLI\Utils\make_progress_bar( "Importing " . count( $this->events['data'] ) . " events", count( $this->events['data'] ) );
		}

		$counter = 0;
		// Iterate the received events for processing
		foreach ( $items as $event_instance ) {

			if( $show_progress ) {
				$progress->tick();
			}

			// Sanith check the event
			$event_id = $event_instance['relationships']['event']['data']['id'] ?? 0;
			if( empty( $event_id ) ) {
				continue;
			}

			// Pull top-level event details
			if ( ! $event = $this->pull_event( $event_id ) ) {
				continue;
			}

			$time_ids = wp_list_pluck( $event_instance['relationships']['event_times']['data'], 'id' );
			$start_date = $end_date = false;

			foreach( $raw['included'] as $include ) {
				if ( ! in_array( $include['id'], $time_ids ) ) {
					continue;
				}

				if ( empty( $include['attributes']['visible_on_kiosks'] ) ) {
					continue;
				}

				$start_date = new \DateTime( $include['attributes']['starts_at'] );
				$end_date   = new \DateTime( $include['attributes']['ends_at'] );
				break;
			}

			if ( ! $start_date || ! $end_date ) {
				continue;
			}

			$start_date->setTimezone( wp_timezone() );
			$end_date->setTimezone( wp_timezone() );

			// Begin stuffing the output
			$args = [
				'chms_id'        => $event_instance['id'],
				'post_status'    => 'publish',
				'post_title'     => $event['attributes']['name'] ?? '',
				'post_content'   => $event['attributes']['description'] ?? '',
				'post_excerpt'   => $event['attributes']['summary'] ?? '',
				'tax_input'      => [],
				'event_category' => [],
				'thumbnail_url'  => '',
				'meta_input'     => [
					'registration_url' => $event['attributes']['registration_url'] ?? '',
				],
				'EventStartDate'        => $start_date->format( 'Y-m-d' ),
				'EventEndDate'          => $end_date->format( 'Y-m-d' ),
//				'EventAllDay'           => $event[''],
				'EventStartHour'        => $start_date->format( 'G' ),
				'EventStartMinute'      => $start_date->format( 'i' ),
//				'EventStartMeridian'    => $event[''],
				'EventEndHour'          => $end_date->format( 'G' ),
				'EventEndMinute'        => $end_date->format( 'i' ),
//				'EventEndMeridian'      => $event[''],
//				'EventHideFromUpcoming' => $event[''],
//				'EventShowMapLink'      => $event[''],
//				'EventShowMap'          => $event[''],
//				'EventCost'             => $event[''],
//				'EventURL'              => $event[''],
//				'FeaturedImage'         => $event['attributes']['image_url'] ?? '',
			];

			// Fesatured image
			if ( ! empty( $event['attributes']['image_url'] ) ) {
				$url = explode( '?', $event['attributes']['image_url'], 2 );
				$args['thumbnail_url'] = $url[0] . '?tecevent-' . sanitize_title( $args['post_title'] ) . '.jpeg&' . $url[1];
			}

			// Generic location - a long string with an entire address
//			if ( ! empty( $event_instance['attributes']['location'] ) ) {
//				$args['tax_input']['cp_location'] = $event_instance['attributes']['location'];
//			}

			// Get the event's tags and pair them with appropriate taxonomies
			$tags = $this->pull_event_tags( $event );

			$tag_output = [];
			if ( ! empty( $tags ) && is_array( $tags ) ) {
				foreach( $tags as $tag ) {
					$tag_id = $tag['id'] ?? 0;
					$tag_value = $tag['attributes']['name'] ?? '';
					$tag_output[ $tag_value ] = $this->taxonomies_for_tag( $tag_value );
				}
			}

			$tax_output = [];
			// Segragate the "tags" that have non-tag data
			foreach( $tag_output as $tag_text => $included_taxonomies ) {

				// This "tag" is a campus/location
				if( in_array( 'campus', $included_taxonomies ) ) {

					$campus =  $this->pull_campus_details( $tag_text );

					// Map to Location/Venue for TEC
					if( !empty( $campus ) && is_array( $campus ) ) {
						$args['Venue'] = [
							'Venue'    => $tag_text,
							'Country'  => $campus['country'] ?? '',
							'Address'  => $campus['street'] ?? '',
							'City'     => $campus['city'] ?? '',
							'State'    => $campus['state'] ?? '',
		//					'Province' => $campus[''],
							'Zip'      => $campus['zip'],
		//					'Phone'    => $campus[''],
						];
					}

				} else if( in_array( 'event_type', $included_taxonomies ) ) {
					// This is a TEC Event Category
					$args['event_category'][] = $tag_text;
				} else if( in_array( 'ministry_group', $included_taxonomies ) ) {
					// This is a TEC Event Category
					$args['tax_input']['cp_ministry'] = $tag_text;
				} else {

					// This is something else - we're assuming that it's a taxonomy and that the taxonomy is already registered
					foreach( $included_taxonomies as $loop_tax ) {
						$args['tax_input'][ $loop_tax ] = $tag_text;
					}
				}
			}

			// Add event contact info
			if( !empty( $event['contact'] ) ) {

				// Normalize variables so they're easier to work with
				$contact_email = $event['contact']['contact_data']['email_addresses'][0]['address'] ?? '';
				$contact_phone = $event['contact']['contact_data']['phone_numbers'][0]['number'] ?? '';
				$first_name = $event['contact']['first_name'] ?? '';
				$last_name = $event['contact']['last_name'] ?? '';
				$use_name = $first_name . ' ' . $last_name;

				// Only include if the person is not "empty" (depending on the ChMS definition of "empty")
				if( !empty( $first_name ) && 'no owner' !== strtolower( $use_name ) ) {
					$args['Organizer'] = [
						'Organizer' => $use_name,
						'Email'     => $contact_email,
	//					'Website'   => $event[''],
						'Phone'     => $contact_phone,
					];
				}

			}

			// Add the data to our output
			$formatted[] = apply_filters( 'cp_connect_pco_event_args', $args );

//			$counter++;
//			if( $counter > 20 ) {
//				return $formatted;
//			}
		}
		if( $show_progress ) {
			$progress->finish();
		}

		error_log( "PULL EVENTS FINISHED " . date( 'Y-m-d H:i:s') );

		$integration->process( $formatted );
	}

	/**
	 * Pull details about a specific group from PCO
	 *
	 * @param int $group_id
	 * @return array
	 * @author costmo
	 */
	public function pull_group_details( $group, $included ) {
		$details = [];

		foreach ( $group['relationships'] as $relationship ) {
			foreach ( $included as $include ) {
				if ( $include['id'] == $relationship['data']['id'] ) {
					$details[] = $include;
				}
			}
		}

		return $details;
	}

	/**
	 * Get all groups from PCO
	 *
	 * @param int $event_id
	 * @return array
	 * @author costmo
	 */
	public function pull_groups( $integration ) {

		error_log( "PULL GROUPS STARTED" );

		// Pull groups here
		$raw =
			$this->api()
				->module('groups')
				->table('groups')
				->includes('location,group_type')
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
		}

		// Give massaged data somewhere to go - the return variable
		$formatted = [];

		// Collapse and normalize the response
		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		$formatted = [];

		$counter = 0;
		foreach( $items as $group ) {

			$start_date = strtotime( $group['attributes']['created_at'] ?? null );
			$end_date   = strtotime( $group['attributes']['archived_at'] ?? null );

			$args = [
				'chms_id'       => $group['id'],
				'post_status'   => 'publish',
				'post_title'    => $group['attributes']['name'] ?? '',
				'post_content'  => $group['attributes']['description'] ?? '',
				'tax_input'     => [],
				// 'group_category'   => [],
				'group_type'    => [],
				// 'group_life_stage' => [],
				'meta_input'    => [
					'leader'     => $group['attributes']['contact_email'] ?? '',
					'start_date' => date( 'Y-m-d', $start_date ),
					'end_date'   => ! empty( $end_date ) ? date( 'Y-m-d', $end_date ) : null,
					'public_url' => $group['attributes']['public_church_center_web_url'] ?? '',
				],
				'thumbnail_url' => $group['attributes']['header_image']['original'] ?? '',
			];

			if ( ! empty( $group['attributes']['schedule'] ) ) {
				$args['meta_input']['frequency'] = $group['attributes']['schedule'];
			}

			$item_details = $this->pull_group_details( $group, $raw['included'] );

			foreach( $item_details as $index => $item_data ) {

				$type = $item_data['type'] ?? '';

				if( 'GroupType' === $type ) {
					$args['group_type'][] = $item_data['attributes']['name'] ?? '';
				} else if( 'Location' === $type ) {
					// TODO: Maybe pull location information from $group['relationships']['location']['data']['id']
					if ( 'exact' == $item_data['attributes']['display_preference'] )  {
						$args['meta_input']['location'] = $item_data['attributes']['full_formatted_address'] ?? '';
					} else {
						$args['meta_input']['location'] = $item_data['attributes']['name'] ?? '';
					}
				}
			}

			// TODO: Look this up
			 if ( ! empty( $group['attributes']['schedule'] ) ) {
			 	$args['meta_input']['time_desc'] = $group['attributes']['schedule'];

			// 	if ( ! empty( $group['Meeting_Day'] ) ) {
			// 		$args['meta_input']['time_desc'] = $group['Meeting_Day'] . 's at ' . $args['meta_input']['time_desc'];
			// 		$args['meta_input']['meeting_day'] = $group['Meeting_Day'];
			// 	}
			 }

			// TODO: Look this up
			// if ( ! empty( $group['Congregation_ID'] ) ) {
			// 	if ( $location = $this->get_location_term( $group['Congregation_ID'] ) ) {
			// 		$args['tax_input']['cp_location'] = $location;
			// 	}
			// }

			// Not for PCO
			$args['group_category'] = []; // Is this different than group_type for PCO?

			// Not for PCO
			$args['group_life_stage'] = [];

			$formatted[] = $args;

			$counter++;
			// if( $counter > 5 ) {
			// 	return $formatted;
			// }
		}

		$integration->process( $formatted );
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
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<?php submit_button( 'Pull Now', 'secondary', 'cp-connect-pull', false ); ?>
				</p>
			</form>
		</div> <!-- /.wrap -->

		<?php
	} // end sandbox_plugin_display


	/**
	 * Setup WP admin settings fields
	 *
	 * @return void
	 * @author costmo
	 */
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

	/**
	 * Admin info string
	 *
	 * @return void
	 * @author costmo
	 */
	function general_options_callback() {
		echo '<p>The following parameters are required to authenticate to the API and then execute API calls to Planning Center Online.</p><p>You can get your authentication credentials by <a target="_blank" href="https://api.planningcenteronline.com/oauth/applications">clicking here</a> and scrolling down to "Personal Access Tokens"</p>';
	}


	/**
	 * Get the stored value of an option for admin
	 *
	 * @param string $key
	 * @param boolean $options
	 * @return void
	 * @author costmo
	 */
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

	/**
	 * Render Application ID admin input
	 *
	 * @param array $args
	 * @return void
	 * @author costmo
	 */
	function pco_app_id_callback( $args ) {

		$options = get_option( 'pco_plugin_options' );
		$opt = $this->get_option_value( 'PCO_APPLICATION_ID', $options );

		// Note the ID and the name attribute of the element match that of the ID in the call to add_settings_field
		$html = '<input type="text" id="PCO_APPLICATION_ID" name="pco_plugin_options[PCO_APPLICATION_ID]" value="' . $opt . '" size="60"/>';
		// Here, we will take the first argument of the array and add it to a label next to the checkbox
		$html .= '<label for="PCO_APPLICATION_ID"> ' . $args[0] . '</label>';

		echo $html;

	} // end pco_app_id_callback

	/**
	 * Render Application Secret admin input
	 *
	 * @param array $args
	 * @return void
	 * @author costmo
	 */
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

	/**
	 * Return the PCO-related taxonomies that hold the input value
	 *
	 * @param string $tag			The tag/term to find
	 * @return void
	 * @author costmo
	 */
	protected function taxonomies_for_tag( $tag ) {

		// TODO: This needs to be more dynamic
		$taxonomies = [
			'campus' 			=> false,
			'ministry_group' 	=> false,
			'event_type' 		=> false,
			'frequency' 		=> false,
			'ministry_leader' 	=> false
		];

		$in_tax = [];
		foreach( $taxonomies as $tax => $ignored ) {

			$tax = strtolower( $tax );
			$exists = term_exists( $tag, $tax );
			if( !empty( $exists ) ) {
				$in_tax[] = $tax;
			}

		}

		return $in_tax;
	}

}