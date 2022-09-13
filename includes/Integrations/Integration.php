<?php
namespace CP_Connect\Integrations;

use CP_Connect\Exception;

abstract class Integration {

	/**
	 * @var self
	 */
	protected static $_instance;

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
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	protected function __construct() {
		add_action( _Init::$_cron_hook, [ $this, 'pull_content' ] );
	}

	/**
	 * Pull the content from the ChMS which should hook in through the filter.
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function pull_content() {
		$items = apply_filters( 'cp_connect_pull_' . $this->type, false );

		// error_log( "CONTENT PULL RECEIVED: " . $this->type );
		// error_log( var_export( $items, true ) );

		// break early if something went wrong or nothing hooked in
		if ( empty( $items ) || !is_array( $items ) ) {
			return;
		}

		$item_store = $this->get_store();

		foreach( $items as $item ) {
			$store = $item_store[ $item['chms_id'] ] ?? false;

			// $item['foo'] = md5( time() );

			// check if any of the provided values have changed
			if ( $this->create_store_key( $item ) !== $store ) {

				try {
					$id = $this->update_item( $item );
					update_post_meta( $id, '_chms_id', $item['chms_id'] );
				} catch( Exception $e ) {
					error_log( $e );
				}

			}

			unset( $item_store[ $item['chms_id'] ] );
		}

		foreach( $item_store as $chms_id => $hash ) {
			$this->remove_item( $chms_id );
		}

		$this->update_store( $items );
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