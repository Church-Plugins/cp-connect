<?php
namespace CP_Connect\Integrations;

use CP_Connect\Exception;

abstract class Integration extends \WP_Background_Process {

	/**
	 * @var | Unique ID for this integration
	 */
	public $id;

	/**
	 * @var | The type of content (Events, Groups, Etc)
	 */
	public $type;

	/**
	 * @var | Label for this integration
	 */
	public $label;

	/**
	 * Set the Action
	 */
	public function __construct() {
		$this->action = 'pull_' . $this->type;

		parent::__construct();
	}

	/**
	 * Pull the content from the ChMS which should hook in through the filter.
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey 
	 */
	public function process( $items ) {

		$items = apply_filters( 'cp_connect_process_items', $items, $this );
		
		$item_store = $this->get_store();

		foreach( $items as $item ) {
			$store = $item_store[ $item['chms_id'] ] ?? false;

			// add a unique key to process a hard pull
			if ( apply_filters( 'cp_connect_process_hard_refresh', false, $items, $this ) ) {
				$item[ md5( time() ) ] = time();
			}
			
			// check if any of the provided values have changed
			if ( $this->create_store_key( $item ) !== $store ) {
				$this->push_to_queue( apply_filters( "cp_connect_{$this->type}_item", $item, $this ) );
			}

			unset( $item_store[ $item['chms_id'] ] );
		}

		foreach( $item_store as $chms_id => $hash ) {
			$this->remove_item( $chms_id );
		}

		$this->update_store( $items );
		
		$this->save()->dispatch();
	}

	/**
	 * The task for handling individual item updates
	 * 
	 * @param $item
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function task( $item ) {
		
		try {
			$id = $this->update_item( $item );
		} catch ( Exception $e ) {
			error_log( 'Could not import item: ' . json_encode( $item ) );
			error_log( $e );
			return false;
		}
		
		$this->maybe_sideload_thumb( $item, $id );
		$this->maybe_update_location( $item, $id );
		
		// Save ChMS ID
		if ( ! empty( $item['chms_id'] ) ) {
			update_post_meta( $id, '_chms_id', $item['chms_id'] );
		}
		
		do_action( 'cp_update_item_after', $item, $id, $this );
		do_action( 'cp_' . $this->id . '_update_item_after', $item, $id );
		return false;
	}
	
	protected function complete() {
		parent::complete();
	}

	/**
	 * Update the post with the associated data
	 *
	 * @param $item
	 *
	 * @throws Exception
	 * @return int | bool The post ID on success, FALSE on failure
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract function update_item( $item );

	/**
	 * Import item thumbnail
	 * 
	 * @param $item
	 * @param $id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_sideload_thumb( $item, $id ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		
		// import the image and set as the thumbnail
		if ( ! empty( $item['thumbnail_url'] ) && get_post_meta( $id, '_thumbnail_url', true ) !== $item['thumbnail_url'] ) {
			$thumb_id = media_sideload_image( $item['thumbnail_url'], $id, $item['post_title'] . ' Thumbnail', 'id' );

			if ( ! is_wp_error( $thumb_id ) ) {
				set_post_thumbnail( $id, $thumb_id );
				update_post_meta( $id, '_thumbnail_url', $item['thumbnail_url'] );
			}
		}
	}

	/**
	 * @param $item
	 * @param $id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_update_location( $item, $id ) {
		if ( ! taxonomy_exists( 'cp_location' ) ) {
			return;
		}
		
		$location = empty( $item['cp_location'] ) ? false : $item['cp_location'];
		wp_set_post_terms( $id, $location, 'cp_location' );
	}

	/**
	 * Remove all posts associated with this chms_id, there should only be one
	 *
	 * @param $chms_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function remove_item( $chms_id ) {
		$id = $this->get_chms_item_id( $chms_id );
		
		if ( $thumb = get_post_thumbnail_id( $id ) ) {
			wp_delete_attachment( $thumb, true );
		}
		
		wp_delete_post( $id, true );
	}

	/**
	 * Get the post associated with the provided item
	 *
	 * @param $chms_id
	 *
	 * @return string|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_chms_item_id( $chms_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_chms_id' AND meta_value = %s", $chms_id ) );
	}

	/**
	 * Get the stored hash values from the last pull
	 *
	 * @return false|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_store() {
		return get_option( 'cp_connect_store_' . $this->type, [] );
	}

	/**
	 * Update the store cache so we know what to update each time
	 *
	 * @param $items
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_store( $items ) {
		$store = [];

		foreach( $items as $item ) {
			$store[ $item['chms_id'] ] = $this->create_store_key( $item );
		}

		update_option( 'cp_connect_store_' . $this->type, $store, false );
	}

	/**
	 * Create the store key
	 *
	 * @param $item
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function create_store_key( $item ) {
		return md5( serialize( $item ) );
	}

}