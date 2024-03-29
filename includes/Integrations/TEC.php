<?php

namespace CP_Connect\Integrations;

use CP_Connect\Exception;

class TEC extends Integration {

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
		
		return $id;
	}
	
}