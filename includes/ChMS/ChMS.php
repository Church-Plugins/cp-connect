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
		add_action( 'init', [ $this, 'integrations' ] );
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
}