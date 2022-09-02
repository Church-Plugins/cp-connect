<?php

namespace CP_Connect\Integrations;

use CP_Connect\Exception;

class CP_Groups extends Integration {
	
	public $id = 'cp_groups';
	
	public $type = 'groups';
	
	public $label = 'Groups';
	
	public function update_item( $item ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}
		
		unset( $item['chms_id'] );
		
		$item['post_type'] = 'cp_group';
		
		$id = wp_insert_post( $item );
		
		if ( ! $id ) {
			throw new Exception( 'Group could not be created' );
		}
		
		$taxonomies = [ 'group_category', 'group_type', 'group_life_stage' ];
		
		foreach( $taxonomies as $tax ) {
			$taxonomy = 'cp_' . $tax;
			$categories = [];

			foreach( $item[$tax] as $category ) {
				
				if ( ! $term = term_exists( $category, $taxonomy ) ) {
					$term = wp_insert_term( $category, $taxonomy );
				}
				
				if ( ! is_wp_error( $term ) ) {
					$categories[] = $term['term_id'];
				}
			}
	
			wp_set_post_terms( $id, $categories, $taxonomy );
		}
		
		// import the image and set as the thumbnail
		if ( ! empty( $item['thumbnail_url'] ) && get_post_meta( $id, '_thumbnail_url', true ) !== $item['thumbnail_url'] ) {
			$thumb_id = media_sideload_image( $item['thumbnail_url'], $id, $item['post_title'] . ' Thumbnail', 'id' );
	
			if ( ! is_wp_error( $thumb_id ) ) {
				set_post_thumbnail( $id, $thumb_id );
				update_post_meta( $id, '_thumbnail_url', $item['thumbnail_url'] );
			}
		}
		
		return $id;
	}

}