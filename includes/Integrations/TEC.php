<?php

namespace CP_Connect\Integrations;

use CP_Connect\Exception;

class TEC extends Integration {

	public $id = 'tec';

	public $type = 'events';

	public $label = 'Events';

	/**
	 * Pull content for TEC. A parent reflector.
	 *
	 * @return void
	 * @author costmo
	 */
	public function pull_content() {
		return parent::pull_content();
	}

	public function update_item( $item ) {

		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}

		unset( $item['chms_id'] );

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
		
		// import the image and set as the thumbnail
		if ( ! empty( $item['thumbnail_url'] ) && get_post_meta( $id, '_thumbnail_url', true ) !== $item['thumbnail_url'] ) {
			$thumb_id = media_sideload_image( $item['thumbnail_url'], $id, $item['post_title'] . ' Thumbnail', 'id' );
			
			if ( ! is_wp_error( $thumb_id ) ) {
				set_post_thumbnail( $id, $thumb_id );
				update_post_meta( $id, '_thumbnail_url', $item['thumbnail_url'] );
			}
		}
		
		do_action( 'cp_tec_update_item_after', $item, $id );

		return $id;
	}

}