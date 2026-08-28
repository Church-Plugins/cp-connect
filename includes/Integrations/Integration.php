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

		// re-save the post to trigger slug calculation post location update
		wp_update_post( get_post( $id ) );

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
	 * Attachments are keyed by the image itself (see get_thumbnail_key()) rather than by the item, so
	 * items that share an image (e.g. every event in a series) share a single attachment instead of
	 * downloading a copy each. A new image is only downloaded when no attachment exists for its key.
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

		if ( empty( $item['thumbnail_url'] ) || get_post_meta( $id, '_thumbnail_url', true ) === $item['thumbnail_url'] ) {
			return;
		}

		$key      = $this->get_thumbnail_key( $item );
		$thumb_id = $this->get_existing_thumbnail( $key );

		// import the image and set as the thumbnail
		if ( ! $thumb_id ) {
			$thumb_id = $this->sideload_thumb( $item['thumbnail_url'], $id, $item['post_title'] . ' Thumbnail' );

			if ( is_wp_error( $thumb_id ) ) {
				error_log( sprintf( 'cp-connect: could not import thumbnail for %d (%s): %s', $id, $item['thumbnail_url'], $thumb_id->get_error_message() ) );
				return;
			}
		}

		$this->add_thumbnail_key( $thumb_id, $key );

		$previous_id = get_post_thumbnail_id( $id );

		set_post_thumbnail( $id, $thumb_id );
		update_post_meta( $id, '_thumbnail_url', $item['thumbnail_url'] );

		// the image changed, clean up the old one if nothing else is using it
		if ( $previous_id && (int) $previous_id !== (int) $thumb_id ) {
			$this->maybe_delete_attachment( $previous_id, $id );
		}
	}

	/**
	 * Get the key that identifies the image behind an item's thumbnail.
	 *
	 * ChMS integrations append a filename hint as the query string of the thumbnail url
	 * (e.g. `/files/{id}?mpevent-{slug}.jpeg`), so the url without its query string identifies the
	 * underlying image regardless of which item it was imported for. An integration can provide an
	 * explicit `thumbnail_key` instead.
	 *
	 * @param array $item
	 *
	 * @return string
	 * @since  1.2.0
	 */
	public function get_thumbnail_key( $item ) {
		$key = empty( $item['thumbnail_key'] ) ? strtok( $item['thumbnail_url'], '?' ) : $item['thumbnail_key'];

		return apply_filters( 'cp_connect_thumbnail_key', $key, $item, $this );
	}

	/**
	 * Find an attachment that was already imported for the provided thumbnail key.
	 *
	 * Falls back to the `_source_url` meta that WordPress stores on sideloaded images so that images
	 * imported before keys were recorded are reused instead of downloaded again.
	 *
	 * @param string $key
	 *
	 * @return int|false The attachment ID, or false if none exists.
	 * @since  1.2.0
	 */
	public function get_existing_thumbnail( $key ) {
		global $wpdb;

		if ( empty( $key ) ) {
			return false;
		}

		$thumb_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM $wpdb->posts p
			 INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
			 WHERE p.post_type = 'attachment' AND pm.meta_key = '_cp_connect_thumbnail_key' AND pm.meta_value = %s
			 ORDER BY p.ID DESC LIMIT 1",
			$key
		) );

		// legacy: match on the original source url, ignoring the filename hint in the query string
		if ( ! $thumb_id ) {
			$thumb_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT p.ID FROM $wpdb->posts p
				 INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
				 WHERE p.post_type = 'attachment' AND pm.meta_key = '_source_url' AND ( pm.meta_value = %s OR pm.meta_value LIKE %s )
				 ORDER BY p.ID DESC LIMIT 1",
				$key,
				$wpdb->esc_like( $key ) . '?%'
			) );

			if ( $thumb_id ) {
				$this->add_thumbnail_key( $thumb_id, $key );
			}
		}

		// the attachment exists but its file is gone, download it again
		if ( ! $thumb_id || ! $this->attachment_file_exists( $thumb_id ) ) {
			return false;
		}

		return (int) $thumb_id;
	}

	/**
	 * Download an image and create an attachment for it, unless an identical image was already imported.
	 *
	 * Some ChMS (Ministry Platform, for one) store a separate copy of the image for every event in a
	 * series, each with its own id and url, so the thumbnail key can't tell that they are the same
	 * picture. The downloaded file is hashed and, when an imported attachment with the same content
	 * exists, that attachment is reused and the download discarded.
	 *
	 * This mirrors media_sideload_image() with the hash check inserted between download and storage.
	 *
	 * @param string $url     The image url. The query string may carry a filename hint (see get_thumbnail_key()).
	 * @param int    $post_id The post to attach the image to.
	 * @param string $desc    The attachment description.
	 *
	 * @return int|\WP_Error The attachment ID.
	 * @since  1.2.0
	 */
	public function sideload_thumb( $url, $post_id, $desc = '' ) {
		$allowed_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' );
		$allowed_extensions = apply_filters( 'image_sideload_extensions', $allowed_extensions, $url );
		$allowed_extensions = array_map( 'preg_quote', $allowed_extensions );

		// Set variables for storage, fix file filename for query strings.
		preg_match( '/[^\?]+\.(' . implode( '|', $allowed_extensions ) . ')\b/i', $url, $matches );

		if ( ! $matches ) {
			return new \WP_Error( 'image_sideload_failed', __( 'Invalid image URL.' ) );
		}

		$file_array             = array();
		$file_array['name']     = wp_basename( $matches[0] );
		$file_array['tmp_name'] = download_url( $url );

		if ( is_wp_error( $file_array['tmp_name'] ) ) {
			return $file_array['tmp_name'];
		}

		$hash = md5_file( $file_array['tmp_name'] );

		if ( $hash && ( $thumb_id = $this->get_existing_thumbnail_by_hash( $hash ) ) ) {
			@unlink( $file_array['tmp_name'] );

			return $thumb_id;
		}

		$thumb_id = media_handle_sideload( $file_array, $post_id, $desc );

		if ( is_wp_error( $thumb_id ) ) {
			@unlink( $file_array['tmp_name'] );

			return $thumb_id;
		}

		add_post_meta( $thumb_id, '_source_url', $url );
		update_post_meta( $thumb_id, '_cp_connect_file_hash', $hash );

		return $thumb_id;
	}

	/**
	 * Find an imported attachment whose file content matches the provided hash.
	 *
	 * @param string $hash md5 of the file content.
	 *
	 * @return int|false The attachment ID, or false if none exists.
	 * @since  1.2.0
	 */
	public function get_existing_thumbnail_by_hash( $hash ) {
		global $wpdb;

		$this->backfill_file_hashes();

		$thumb_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM $wpdb->posts p
			 INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
			 WHERE p.post_type = 'attachment' AND pm.meta_key = '_cp_connect_file_hash' AND pm.meta_value = %s
			 ORDER BY p.ID DESC LIMIT 1",
			$hash
		) );

		if ( ! $thumb_id || ! $this->attachment_file_exists( $thumb_id ) ) {
			return false;
		}

		return (int) $thumb_id;
	}

	/**
	 * Record file hashes for thumbnails imported before hashes were stored, so that existing images are
	 * reused rather than imported again. Only attachments that are the thumbnail of an imported item are
	 * considered. Runs once per request and is a no-op once everything is hashed.
	 *
	 * @since  1.2.0
	 */
	protected function backfill_file_hashes() {
		global $wpdb;
		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		$ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID FROM $wpdb->posts p
			 INNER JOIN $wpdb->postmeta thumb ON thumb.meta_key = '_thumbnail_id' AND thumb.meta_value = p.ID
			 INNER JOIN $wpdb->postmeta chms ON chms.post_id = thumb.post_id AND chms.meta_key = '_chms_id'
			 LEFT JOIN $wpdb->postmeta hash ON hash.post_id = p.ID AND hash.meta_key = '_cp_connect_file_hash'
			 WHERE p.post_type = 'attachment' AND hash.meta_id IS NULL"
		);

		foreach ( $ids as $thumb_id ) {
			$file = get_attached_file( $thumb_id );
			$hash = ( $file && file_exists( $file ) ) ? md5_file( $file ) : '';

			// record a placeholder for missing files so they aren't scanned again
			update_post_meta( $thumb_id, '_cp_connect_file_hash', $hash ? $hash : 'missing' );
		}
	}

	/**
	 * Associate a thumbnail key with an attachment. An attachment can be shared by several keys when the
	 * ChMS hands out a different image id for the same picture.
	 *
	 * @param int    $thumb_id
	 * @param string $key
	 *
	 * @since  1.2.0
	 */
	public function add_thumbnail_key( $thumb_id, $key ) {
		if ( $key && ! in_array( $key, get_post_meta( $thumb_id, '_cp_connect_thumbnail_key' ), true ) ) {
			add_post_meta( $thumb_id, '_cp_connect_thumbnail_key', $key );
		}
	}

	/**
	 * Whether the attachment's file is still on disk.
	 *
	 * @param int $thumb_id
	 *
	 * @return bool
	 * @since  1.2.0
	 */
	protected function attachment_file_exists( $thumb_id ) {
		$file = get_attached_file( $thumb_id );

		return $file && file_exists( $file );
	}

	/**
	 * Delete an attachment unless another post is still using it as its thumbnail.
	 *
	 * @param int $thumb_id The attachment to delete.
	 * @param int $post_id  The post being updated/removed, ignored when checking for other usages.
	 *
	 * @return bool Whether the attachment was deleted.
	 * @since  1.2.0
	 */
	public function maybe_delete_attachment( $thumb_id, $post_id = 0 ) {
		global $wpdb;

		$in_use = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %s AND post_id != %d",
			$thumb_id,
			$post_id
		) );

		if ( $in_use ) {
			return false;
		}

		return (bool) wp_delete_attachment( $thumb_id, true );
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
		
		// the thumbnail may be shared with other items, only remove it if this is the last one using it
		if ( $thumb = get_post_thumbnail_id( $id ) ) {
			$this->maybe_delete_attachment( $thumb, $id );
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