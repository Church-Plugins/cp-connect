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

	/**
	 * Render the custom field mappings
	 *
	 * @return void
	 * @author costmo
	 */
	protected function render_custom_mappings() {

		$custom_fields = get_option( 'cp_group_custom_field_mapping', [] );

		$html = "";
		if( !empty( $custom_fields ) && is_array( $custom_fields ) ) {

			foreach( $custom_fields as $key => $value ) {

				$list = "<table class='form-table' role='presentation'><tbody><tr><td><select name='cp_connect_field_mapping_targets[]'>";
				foreach( array_keys( $custom_fields ) as $field ) {
					$selected = $field === $key ? 'selected' : '';
					$disabled = $field === 'select' ? 'disabled' : '';
					$list .= "<option value='{$field}' {$selected} {$disabled}> {$field} </option>";
				}
				$list .=  "</select><span class='dashicons dashicons-dismiss'></span></td></tr></tbody></table>";
				$html .=
					"<div class='cp-connect-field-mapping-item-container'>
						<input type='text' name='cp_connect_field_mapping_names[]' value='{$value}' placeholder='Field Name' />
						{$list}
					</div>";
			}
		}

		$return =<<<EOT
		<div class="cp-connect-custom-mappings">
			{$html}
			<div class="cp-connect-custom-mappings__last_row">
				<i class="dashicons dashicons-plus-alt cp-connect-add-field-mapping"></i>
			</div>
		</div>
		EOT;


		echo $return;
	}

	/**
	 * The callback for displaying all group mapping fields
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	function field_mapping_callback( $args ) {

		$opt = get_option( $args['option'] );

		$opt = isset( $opt['mapping'] ) ? $opt['mapping'] : array();

		if( ! $opt || ! isset( $opt[ $args['key'] ] ) ) {
			$opt = $args['default_value'];
		}
		else {
			$opt = $opt[ $args['key'] ];
		}

		$options = implode( '', array_map( function( $val ) use ( $opt ) {
			$selected_att = $opt === $val ? 'selected' : '';
			$disabled_att = $val === 'select' ? 'disabled' : '';

			return sprintf( '<option %s %s>%s</option>', $selected_att, $disabled_att, esc_html( $val ) );
		}, $args['valid_fields'] ) );

		$field_name = $args['option'] . '[mapping]' . '[' . $args['key'] . ']';

		$html = sprintf( '<select name="%s" value="%s">%s</select>', esc_attr( $field_name ), esc_attr( $opt ), $options );

		$html .= '<label for="title"> ' . $args['description'] . '</label>';

		echo $html;
	}

	/**
	 * Gets an object with data and a mapping array, and returns the object values associated with the mapping keys
	 *
	 * @param array $data The data to map
	 * @param array $mapping The mapping array
	 */
	function get_mapped_values( $data, $mapping ) {
		$mapped_values = array();

		foreach( $mapping as $key => $value ) {
			if( isset( $data[ $value ] ) ) {
				$mapped_values[ $key ] = $data[ $value ];
			}
		}

		return $mapped_values;
	}
}