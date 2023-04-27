<?php

namespace CP_Connect\ChMS;

use ChurchCommunityBuilderAPI\CCBPress_Connection as CCB_API;

class ChurchCommunityBuilder extends ChMS {

	public $api = null;

	public function integrations() {

		add_action( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );
		add_action( 'cp_update_item_after', [ $this, 'load_group_image' ], 10, 3 );
		add_filter( 'cp_group_get_thumbnail', [ $this, 'get_group_image' ], 10, 2 );

		add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}


	public function api() {

		if( empty( $this->api ) ) {
			$this->api = $pco = new CCB_API();
		}

		return $this->api;
	}


	public function pull_groups( $integration ) {

		$args = [
			'query_string' => [
				'srv'                  => 'group_profiles',
				'include_participants' => 'false',
				'include_image_link'   => 'true',
				// 'per_page' => 25,
			],
			'refresh_cache' => 1,
		];

		$groups = $this->api()->get( $args ); //todo test this and return early if not what we want

		$groups = json_decode( json_encode( $groups->response->groups ), false );

		$formatted = [];

		foreach ( $groups->group as $group ) {

			// Skip inactive and general groups
			// @TODO ... this should be a filter so it isn't specific to ChristPres
			if ( ! $group->inactive ) { continue; }
			if ( "Church - General" == $group->department ) { continue; }

			// Only process if it's a connect group type
			if ( "Connect Group" !== $group->group_type ) { continue; }
			// Client may also want small groups but leaving out for now
			// if ( "Small Group" !== $group->group_type ) { continue; }

			$args = [
				'chms_id'          => $group->{'@attributes'}->id,
				'post_status'      => 'publish',
				'post_title'       => $group->name,
				'post_content'     => '',
				'tax_input'        => [],
				'group_category'   => [],
				'group_type'       => [],
				'group_life_stage' => [],
				'meta_input'       => [
					'leader'       => $group->main_leader->full_name,
					'leader_email' => $group->main_leader->email,
					'public_url'   => $this->api()->get_base_url( 'group_detail.php?group_id=' . esc_attr( $group->{'@attributes'}->id ) ),
				],
				'thumbnail_url'    => '',
			];

			if ( is_string( $group->description ) && ! empty( $group->description ) ) {
				$args['post_content'] = $group->description;
			}

			if ( 'string' === gettype( $group->image ) && ! empty( $group->image ) ) {
				$thumb_url = $group->image;
				$args['thumbnail_url'] = $thumb_url . '#.png';
			}

			$address_city = ( !empty( $group->addresses->address->city ) && 'string' == gettype( $group->addresses->address->city ) ) ? $group->addresses->address->city : '';
			$address_state = ( !empty( $group->addresses->address->state ) && 'string' == gettype( $group->addresses->address->state ) ) ? $group->addresses->address->state : '';
			$address_zip = ( !empty( $group->addresses->address->zip ) && 'string' == gettype( $group->addresses->address->zip ) ) ? $group->addresses->address->zip : '';

			if ( !empty( $address_city ) ) {
				$args['meta_input']['location'] = sprintf( "%s, %s %s", $address_city, $address_state, $address_zip );
			} else {
				$args['meta_input']['location'] = sprintf( "%s %s", $address_state, $address_zip );
			}

			if ( !empty( $group->meeting_time ) && 'string' == gettype( $group->meeting_time ) ) {
				$args['meta_input']['time_desc'] = date( 'g:ia', strtotime( $group->meeting_time ) );

				if ( ! empty( $group->meeting_day ) && 'string' == gettype( $group->meeting_day ) ) {
					$args['meta_input']['time_desc'] = $group->meeting_day . 's at ' . $args['meta_input']['time_desc'];
					$args['meta_input']['meeting_day'] = $group->meeting_day;
				}
			}

			$args['meta_input']['kid_friendly'] = ( ( 'true' == $group->childcare_provided ) && 'string' == gettype( $group->childcare_provided ) ) ? true : false;

			if ( ! empty( $group->campus ) ) {
				// if ( $location = $this->get_location_term( $group->campus ) ) {
					$args['cp_location'] = $group->campus;
				// }
			}

			if ( ! empty( $group->group_type ) ) {
				$args['group_type'][] = $group->group_type;
			}

			$additional_fields = array();
			if ( ! empty( (array) $group->user_defined_fields ) ) {
				if ( 'array' == gettype( $group->user_defined_fields->user_defined_field ) ) {
					$additional_fields = $group->user_defined_fields->user_defined_field;
				} elseif ( 'object' == gettype( $group->user_defined_fields->user_defined_field ) ) {
					$additional_fields[] = $group->user_defined_fields->user_defined_field;
				}
			}

			// @TODO this should be a filter so it's not specific to ChristPres
			foreach ( $additional_fields as $field ) {

				if ( 'Life Stage' == ( $field->label ) ) {
					$args['group_life_stage'][] = $field->selection;
				}

				if ( 'Gender' == ( $field->label ) ) {
					$args['group_category'][] = $field->selection;
				}

			}

			$formatted[] = $args;
		}

		$integration->process( $formatted );
	}


	public function load_group_image( $item, $post_id, $integration ) {

		if ( !empty( $item['thumbnail_url'] ) ) {
			$cached = $this->api()->cache_image( $item['thumbnail_url'], $item['chms_id'], 'group' );

			if ( $cached ) {
				$upload_dir = wp_upload_dir();
				$upload_dir_url = trailingslashit( $upload_dir['baseurl'] );
				$cached_url = $upload_dir_url . $this->api()->image_cache_dir . '/cache/group-' . $item['chms_id'] . '.jpg';

				update_post_meta( $post_id, '_thumbnail_url', $item['thumbnail_url'] );
				update_post_meta( $post_id, '_cached_thumbnail_url', $cached_url );
			}
		}

	}


	public function get_group_image( $value, $group ) {
		if ( $url = get_post_meta( $group->post->ID, '_cached_thumbnail_url', true ) ) {
			return $url;
		}

		return $value;
	}


	/**
	 * This function introduces a single plugin menu option into the WordPress 'Plugins'
	 * menu.
	 */

	function plugin_menu() {

		add_submenu_page( 'options-general.php',
			'Church Community Builder Integration',         // The title to be displayed in the browser window for this page.
			'CCB',                        // The text to be displayed for this menu item
			'administrator',                    // Which type of users can see this menu item
			'ccb_plugin_options', // The unique ID - that is, the slug - for this menu item
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
				<?php settings_fields( 'ccb_plugin_options' ); ?>
				<?php do_settings_sections( 'ccb_plugin_options' ); ?>
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<?php submit_button( 'Pull Now', 'secondary', 'cp-connect-pull', false ); ?>
				</p>
			</form>
		</div> <!-- /.wrap -->


		<?php
	} // end sandbox_plugin_display


	function initialize_plugin_options() {

		// If the options don't exist, add them
		if ( false == get_option( 'ccb_plugin_options' ) ) {
			add_option( 'ccb_plugin_options' );
		} // end if


		// First, we register a section. This is necessary since all future options must belong to one.
		add_settings_section(
			'ccb_settings_section',                           // ID used to identify this section and with which to register options
			'API Configuration Options',                                  // Title to be displayed on the administration page
			[ $this, 'ccb_section_callback' ],  // Callback used to render the description of the section
			'ccb_plugin_options'                              // Page on which to add this section of options
		);

		/**
		 * The API URL field
		 */

		add_settings_field(
			'api_prefix',
			'<strong>' . __('Your CCB Website', 'ccbpress-core') . '</strong>',
			array( $this, 'input_callback' ),
			'ccb_plugin_options',
			'ccb_settings_section',
			array(
				'field_id'  => 'api_prefix',
				'page_id'   => 'ccb_plugin_options',
				'size'      => 'medium',
				'label'     => __('The URL you use to access your Church Community Builder site.', 'ccbpress-core'),
				'before'    => '<code>https://</code>',
				'after'    => '<code>.ccbchurch.com</code>'
			)
		);

		/**
		 * The API username field
		 */

		add_settings_field(
			'api_user',
			'<strong>' . __( 'API Username', 'ccbpress-core' ) . '</strong>',
			array( $this, 'input_callback' ),
			'ccb_plugin_options',
			'ccb_settings_section',
			array(
				'field_id'  	=> 'api_user',
				'page_id'   	=> 'ccb_plugin_options',
				'size'      	=> 'medium',
				'autocomplete'	=> 'off',
				'label'			=> __( 'This is different from the login you use for Church Community Builder.', 'ccbpress-core' ),
			)
		);

		/**
		 * The API password field
		 */

		add_settings_field(
			'api_pass',
			'<strong>' . __( 'API Password', 'ccbpress-core' ) . '</strong>',
			array( $this, 'input_callback' ),
			'ccb_plugin_options',
			'ccb_settings_section',
			array(
				'field_id'  	=> 'api_pass',
				'page_id'   	=> 'ccb_plugin_options',
				'type'      	=> 'password',
				'size'      	=> 'medium',
				'autocomplete'	=> 'off',
			)
		);

		// Disabled for now, requires additional JS from ccbpress-core plugin
		// if ( $this->api()->is_connected() ) {

		// 	// First, we register a section. This is necessary since all future options must belong to one.
		// 	add_settings_section(
		// 		'ccb_settings_api_services_section',
		// 		__( 'API Services', 'ccbpress-core' ),
		// 		array( $this, 'api_services_section_callback' ),
		// 		'ccb_plugin_options'
		// 	);

		// 	add_settings_field(
		// 		'check_services_form',
		// 		'<strong>' . __('Check Your Services', 'ccbpress-core') . '</strong>',
		// 		array( $this, 'text_callback' ),
		// 		'ccb_plugin_options',
		// 		'ccb_settings_api_services_section',
		// 		array(
		// 			'header' => NULL,
		// 			'title' => NULL,
		// 			'content' => '<a class="button" id="ccbpress-ccb-service-check-button">Check Services Now</a><div id="ccbpress-ccb-service-check-results"></div>',
		// 		)
		// 	);

		// }

		// Finally, we register the fields with WordPress
		register_setting(
			'ccb_plugin_options',
			'ccb_plugin_options',
    		array( $this, 'sanitize_callback' )
		);


	}

    public function ccb_section_callback() {
        echo '<p>' . __('These are the settings for the API connection to Church Community Builder.', 'ccbpress-core') . '</p>';
	}

	public function sanitize_callback( $input ) {

	    //return $input;
	    // Define all of the variables that we'll be using
		$ccb_api_user = "";
		$ccb_api_pass = "";
		$ccb_api_prefix = "";
		$output = array();

		// Loop through each of the incoming options
		foreach ( $input as $key => $value ) {

			// Check to see if the current option has a value. If so, process it.
			if ( isset( $input[$key] ) ) {

				switch ( $key ) {

					case 'api_user':
						$ccb_api_user = $input[$key];
						break;

					case 'api_pass':
						$ccb_api_pass = $input[$key];
						break;

					case 'api_prefix':
						$ccb_api_prefix = $input[$key];
						break;

				}

				// Strip all HTML and PHP tags and properly handle quoted strings
				$output[$key] = strip_tags( stripslashes( $input[$key] ) );

			}

		}

		// Let's test the connection with our newly saved settings
	    $output['connection_test'] = (string) $this->api()->test_connection( $output['api_prefix'], $output['api_user'], $output['api_pass'] );

		// Return the array
		return $output;

	}

    /**
     * Text Input Field
     *
     * @package    CCBPress_Core
     * @since      1.0.0
     *
     * @param	array	$args	Arguments to pass to the function. (See below).
	 *
	 * string	$args[ 'field_id' ]
	 * string	$args[ 'page_id' ]
	 * string	$args[ 'label' ]
     *
     * @return	string	HTML to display the field.
     */
    public function input_callback( $args ) {

        // Set the defaults.
		$defaults = array(
			'field_id'		=> null,
			'page_id'		=> null,
			'label'      	=> null,
            'type'          => 'text',
			'size'          => 'regular',
            'before'        => null,
            'after'         => null,
			'autocomplete'	=> null,
		);

		// Parse the arguments.
		$args = wp_parse_args( $args, $defaults );

        // Get the saved values from WordPress.
    	$options = get_option( $args['page_id'] );


        // Start the output buffer.
        ob_start();
        ?>
        <?php echo $args['before']; ?>
        <input type="<?php echo esc_attr( $args['type'] ); ?>" id="<?php echo esc_attr( $args['field_id'] ); ?>" name="<?php echo esc_attr( $args['page_id'] ); ?>[<?php echo esc_attr( $args['field_id'] ); ?>]" value="<?php echo ( isset( $options[ $args['field_id'] ] ) ? $options[ $args['field_id'] ] : '' ); ?>" class="<?php esc_attr_e( $args['size'] ); ?>-text"<?php echo ( 'off' === $args['autocomplete'] ) ? ' autocomplete="off"' : ''; ?> />
        <?php echo $args['after']; ?>
        <?php if ( $args['label'] != '' ) : ?>
            <p class="description"><?php echo $args['label']; ?></p>
        <?php endif; ?>

        <?php
    	// Print the output
        echo ob_get_clean();

    }

   /**
    * Text
    *
    * @package    CCBPress_Core
    * @since      1.0.0
    *
    * @param	array	$args	Arguments to pass to the function. (See below).
    *
    * string	$args[ 'header_type' ]
    * string	$args[ 'title' ]
    * string	$args[ 'content' ]
    *
    * @return	string	HTML to display the field.
    */

   public function text_callback( $args ) {

   	// Set the defaults
   	$defaults = array(
   		'header'	=> 'h2',
   		'title'		=> NULL,
   		'content'	=> NULL,
   	);

   	// Parse the arguments
   	$args = wp_parse_args( $args, $defaults );

   	ob_start();
   	// Check that the title and header_type are not blank
   	if ( ! is_null( $args['title'] ) ) {
   		echo '<' . $args['header'] . '>' . $args['title'] . '</' . $args['header'] . '>';
       }

       // Check that the content is not blank
   	if ( ! is_null ( $args['content'] ) ) {
   		echo $args['content'];
       }

   	// Print the output
       echo ob_get_clean();

   } // text_callback()

	public function api_services_section_callback() {
        echo '<p>' . __('Use this tool to check if your API User has the appropriate API Services enabled in Church Community Builder.', 'ccbpress-core') . '</p>';
	}

	function get_option_value( $key, $options = false ) {

		if ( ! $options ) {
			$options = get_option( 'ccb_plugin_options' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';

	}

}
