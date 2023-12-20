<?php

namespace CP_Connect\Admin;

/**
 * Plugin settings
 *
 */
class Settings {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \CP_Connect\Settings
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Settings ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get a value from the options table
	 *
	 * @param $key
	 * @param $default
	 * @param $group
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get( $key, $default = '', $group = 'cpc_main_options' ) {
		$options = get_option( $group, [] );

		if ( isset( $options[ $key ] ) ) {
			$value = $options[ $key ];
		} else {
			$value = $default;
		}

		return apply_filters( 'cpc_settings_get', $value, $key, $group );
	}

	public static function set( $key, $value, $group = 'cpc_main_options' ) {
		$options = get_option( $group, [] );

		$options[ $key ] = $value;

		update_option( $group, $options );
	}

	/**
	 * Get advanced options
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_advanced( $key, $default = '' ) {
		return self::get( $key, $default, 'cpc_advanced_options' );
	}

	/**
	 * Class constructor. Add admin hooks and actions
	 *
	 */
	protected function __construct() {
		add_action( 'cmb2_admin_init', [ $this, 'register_main_options_metabox' ] );
		add_action( 'cmb2_save_options_page_fields', 'flush_rewrite_rules' );
	}

	public function register_main_options_metabox() {

		/**
		 * Registers main options page menu item and form.
		 */
		$args = array(
			'id'           => 'cpc_main_options_page',
			'title'        => 'CP Connect',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpc_main_options',
			'tab_group'    => 'cpc_main_options',
			'tab_title'    => 'ChMS',
			'parent_slug'  => 'options-general.php',
			'display_cb'   => [ $this, 'options_display_with_tabs'],
		);

		$main_options = new_cmb2_box( $args );

		/**
		 * Options fields ids only need
		 * to be unique within this box.
		 * Prefix is not needed.
		 */
		$main_options->add_field( array(
			'name'    => __( 'Select your ChMS', 'cp-connect' ),
			'id'      => 'chms',
			'type'    => 'select',
			'options' => [
				''    => '-- Select --',
				'ccb' => 'Church Community Builder',
				'pco' => 'Planning Center Online',
				'mp'  => 'Ministry Platform',
			],
		) );

		do_action( 'cpc_main_options_metabox', $main_options );

		$main_options->add_field( array(
			'name'    => __( 'Pull Now', 'cp-connect' ),
			'id'      => 'pull_now',
			'type'    => 'checkbox',
			'desc'    => __( 'Check this box to pull data from your ChMS now.', 'cp-connect' ),
		) );

		do_action( 'cpc_main_options_tabs' );

		$this->license_fields();

	}

	protected function license_fields() {
		$license = new \ChurchPlugins\Setup\Admin\License( 'cpc_license', 438, CP_CONNECT_STORE_URL, CP_CONNECT_PLUGIN_FILE, get_admin_url( null, 'admin.php?page=cpc_license' ) );

		/**
		 * Registers settings page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpc_options_page',
			'title'        => 'CP Connect Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpc_license',
			'parent_slug'  => 'cpc_main_options',
			'tab_group'    => 'cpc_main_options',
			'tab_title'    => 'License',
			'display_cb'   => [ $this, 'options_display_with_tabs' ]
		);

		$options = new_cmb2_box( $args );
		$license->license_field( $options );
	}



	/**
	 * A CMB2 options-page display callback override which adds tab navigation among
	 * CMB2 options pages which share this same display callback.
	 *
	 * @param \CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
	 */
	public function options_display_with_tabs( $cmb_options ) {
		$tabs = $this->options_page_tabs( $cmb_options );
		?>
		<div class="wrap cmb2-options-page option-<?php echo $cmb_options->option_key; ?>">
			<?php if ( get_admin_page_title() ) : ?>
				<h2><?php echo wp_kses_post( get_admin_page_title() ); ?></h2>
			<?php endif; ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $option_key => $tab_title ) : ?>
					<a class="nav-tab<?php if ( isset( $_GET['page'] ) && $option_key === $_GET['page'] ) : ?> nav-tab-active<?php endif; ?>"
					   href="<?php menu_page_url( $option_key ); ?>"><?php echo wp_kses_post( $tab_title ); ?></a>
				<?php endforeach; ?>
			</h2>
			<form class="cmb-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST"
				  id="<?php echo $cmb_options->cmb->cmb_id; ?>" enctype="multipart/form-data"
				  encoding="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr( $cmb_options->option_key ); ?>">
				<?php $cmb_options->options_page_metabox(); ?>
				<?php submit_button( esc_attr( $cmb_options->cmb->prop( 'save_button' ) ), 'primary', 'submit-cmb' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Gets navigation tabs array for CMB2 options pages which share the given
	 * display_cb param.
	 *
	 * @param \CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
	 *
	 * @return array Array of tab information.
	 */
	public function options_page_tabs( $cmb_options ) {
		$tab_group = $cmb_options->cmb->prop( 'tab_group' );
		$tabs      = array();

		foreach ( \CMB2_Boxes::get_all() as $cmb_id => $cmb ) {
			if ( $tab_group === $cmb->prop( 'tab_group' ) ) {
				$tabs[ $cmb->options_page_keys()[0] ] = $cmb->prop( 'tab_title' )
					? $cmb->prop( 'tab_title' )
					: $cmb->prop( 'title' );
			}
		}

		return $tabs;
	}


}
