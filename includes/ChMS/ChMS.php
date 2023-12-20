<?php
namespace CP_Connect\ChMS;

use CP_Connect\Admin\Settings;

abstract class ChMS {

	/**
	 * @var self
	 */
	protected static $_instance;

	/**
	 * @var | Unique ID for this integration
	 */
	public $id;

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
		add_action( 'init', [ $this, 'integrations' ], 500 );
		add_action( 'cpc_main_options_metabox', [ $this, 'api_settings' ] );
		add_action( 'cmb2_save_options-page_fields_cpc_main_options_page', [ $this, 'maybe_add_connection_message' ] );

		if ( Settings::get( 'pull_now' ) ) {
			add_action('admin_notices', [ $this, 'general_admin_notice' ] );
		}
	}

	public function general_admin_notice() {
		echo '<div class="notice notice-success is-dismissible">
             <p>Processing pull request.</p>
         </div>';
	}

	/**
	 * Add the hooks for the supported integrations
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	abstract function integrations();

	/**
	 * Return the associated location id for the congregation id
	 *
	 * @param $congregation_id
	 *
	 * @return false|mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_location_term( $congregation_id ) {
		$map = $this->get_congregation_map();

		if ( isset( $map[ $congregation_id ] ) ) {
			return $map[ $congregation_id ];
		}

		return false;
	}

	/**
	 * A map of values to associate the ChMS congregation ID with the correct Location
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_congregation_map() {
		return apply_filters( 'cp_connect_congregation_map', [] );
	}

	/**
	 * Utility to turn an aribratray string into a useable slug
	 *
	 * @param string $string
	 * @return string
	 */
	public static function string_to_slug( $string ) {
		return str_replace( " ", "_", strtolower( $string ) );
	}

	/**
	 * Register the settings fields
	 *
	 * @since  1.0.4
	 *
	 * @param $cmb2 \CMB2 object
	 *
	 * @author Tanner Moushey, 11/30/23
	 */
	abstract function api_settings( $cmb2 );

	/**
	 * Check the connection to the ChMS
	 *
	 * @since  1.0.4
	 *
	 * @return null
	 */
	public function maybe_add_connection_message() {
		if ( ! $response = $this->check_connection() ) {
			return;
		}

		$response['type'] = 'success' === $response['status'] ? 'updated' : 'error';
		update_option( 'cp_settings_message', $response );
	}

	/**
	 * Check the connection to the ChMS
	 *
	 * @since  1.0.4
	 *
	 * @return bool | array
	 */
	public function check_connection() {
		return false;
	}

}