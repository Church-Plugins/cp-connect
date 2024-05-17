<?php

namespace CP_Connect\Integrations;

use CP_Connect\Exception;

class TEC extends Integration {

	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'init', [ $this, 'custom_taxonomies' ] );
	}

	/**
	 * Register custom taxonomies
	 */
	public function custom_taxonomies() {
		register_taxonomy(
			'cpc_event_designation',
			'tribe_events',
			[
				'labels'             => $this->get_labels( 'Designation', 'Designations' ),
				'public'             => true,
				'show_in_rest'       => true,
				'show_in_quick_edit' => true,
				'show_admin_column'  => true,
				'hierarchical'       => true,
			]
		);
	}

	/**
	 * Get labes based on single and plural form
	 *
	 * @param string $singular
	 * @param string $plural
	 */
	public function get_labels( $singular, $plural ) {
		$labels = [
			'name'               => '%2$s',
			'singular_name'      => '%1$s',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New %1$s',
			'edit_item'          => 'Edit %1$s',
			'new_item'           => 'New %1$s',
			'all_items'          => 'All %2$s',
			'view_item'          => 'View %1$s',
			'search_items'       => 'Search %2$s',
			'not_found'          => 'No %2$s found',
			'not_found_in_trash' => 'No %2$s found in Trash',
			'parent_item_colon'  => '%1$s parent:',
			'menu_name'          => '%2$s',
		];

		return array_map( function( $label ) use ( $singular, $plural ) {
			return sprintf( $label, $singular, $plural );
		}, $labels );
	}

	public $id = 'tec';

	public $type = 'events';

	public $label = 'Events';

	public function update_item( $item ) {
		
		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}

		unset( $item['chms_id'] );

		// Organizer does not ignore duplicates by default, so we are handling that
		if ( isset( $item['Organizer'] ) ) {
			$item['Organizer']['OrganizerID'] = \Tribe__Events__Organizer::instance()->create( $item['Organizer'], 'publish', true );
			
			if ( is_wp_error( $item['Organizer']['OrganizerID'] ) ) {
				unset( $item['Organizer'] );
				error_log( $item['Organizer']['OrganizerID']->get_error_message() );
			}
		}
		
		$id = tribe_create_event( $item );

		if ( ! $id ) {
			throw new Exception( 'Event could not be created' );
		}

		// TEC categories
		$categories = [];
		foreach( $item['event_category'] as $category ) {
			if ( ! $term = term_exists( $category, 'tribe_events_cat' ) ) {
				$term = wp_insert_term( $category, 'tribe_events_cat' );
			}

			if ( ! is_wp_error( $term ) ) {
				$categories[] = $term['term_id'];
			}
		}

		wp_set_post_terms( $id, $categories, 'tribe_events_cat' );

		$designations = [];

		if ( isset( $item['cpc_event_designation'] ) && taxonomy_exists( 'cpc_event_designation' ) ) {
			$names = explode( ',', $item['cpc_event_designation'] );

			foreach ( $names as $term_name ) {
				if ( ! $term = term_exists( $term_name, 'cpc_event_designation' ) ) {
					$term = wp_insert_term( $term_name, 'cpc_event_designation' );
				}

				if ( ! is_wp_error( $term ) ) {
					$designations[] = $term['term_id'];
				}
			}
		}

		wp_set_post_terms( $id, $designations, 'cpc_event_designation' );
		
		return $id;
	}
	
}