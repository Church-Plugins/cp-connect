<?php
namespace CP_Connect\ChMS;

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
		if ( isset( $_REQUEST['cp-connect-pull'] ) || get_option( 'cp_connect_pulling' ) ) {
			delete_option( 'cp_connect_pulling' );
			add_action('admin_notices', [ $this, 'general_admin_notice' ] );
		}
		
		if ( isset( $_POST['cp-connect-pull'] ) ) {
			update_option( 'cp_connect_pulling', true );
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
	 * Load connection parameters from the database
	 *
	 * Returns true if the connection is configured, false otherwise
	 *
	 * @param string $option_slug
	 * @return bool
	 *
	 */
	function load_connection_parameters( $option_slug = '' ) {

		// If no options available then just return - it hasn't been setup yet
		$options = get_option( $option_slug ?? md5( time() ), false );
		if( empty( $option_slug ) || !is_string( $option_slug ) || false === $options ) {
			return false;
		}
		// Sanity check the response
		if( empty( $options ) || !is_array( $options ) ) {
			return false;
		}

		// If there are no stored values, we still consider it unconfigured/empty
		$value_length = 0;
		foreach ( $options as $option => $value ) {
			$envString = $option . '=' . $value;
			$value_length += strlen( trim( $value ) );
			putenv( $envString );
		}

		return ($value_length > 0) ? true : false;
	}

	/**
	 * Get parameters for this connection
	 *
	 * @param string $option_slug
	 * @return array
	 * @author costmo
	 */
	function get_connection_parameters( $option_slug = '' ) {

		// If no options available then just return - it hasn't been setup yet
		$options = get_option( $option_slug ?? md5( time() ), false );
		if( empty( $option_slug ) || !is_string( $option_slug ) || false === $options ) {
			return [];
		}
		// Sanity check the response and normalize a potentially invalid return
		if( empty( $options ) || !is_array( $options ) ) {
			return [];
		}

		return $options;
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
}