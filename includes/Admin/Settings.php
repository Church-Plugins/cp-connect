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
	public static function get( $key, $default = '', $group = 'cpl_main_options' ) {
		$options = get_option( $group, [] );

		if ( isset( $options[ $key ] ) ) {
			$value = $options[ $key ];
		} else {
			$value = $default;
		}

		return apply_filters( 'cpl_settings_get', $value, $key, $group );
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
		return self::get( $key, $default, 'cpl_advanced_options' );
	}

	public static function get_item( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_item_options' );
	}

	public static function get_item_type( $key, $default = '' ) {
		return self::get( $key, $default, 'cpl_item_type_options' );
	}

	public static function get_staff( $key, $default = '' ) {
		return self::get( $key, $default, 'cp_connect_options' );
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

		$post_type = cp_connect()->setup->post_types->item_type_enabled() ? cp_connect()->setup->post_types->item_type->post_type : cp_connect()->setup->post_types->item->post_type;
		/**
		 * Registers main options page menu item and form.
		 */
		$args = array(
			'id'           => 'cpl_main_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'Main',
			'parent_slug'  => 'edit.php?post_type=' . $post_type,
			'display_cb'   => [ $this, 'options_display_with_tabs'],
		);

		$main_options = new_cmb2_box( $args );

		/**
		 * Options fields ids only need
		 * to be unique within this box.
		 * Prefix is not needed.
		 */
		$main_options->add_field( array(
			'name'    => __( 'Primary Color', 'cp-connect' ),
			'desc'    => __( 'The primary color to use in the templates.', 'cp-connect' ),
			'id'      => 'color_primary',
			'type'    => 'colorpicker',
			'default' => '#333333',
		) );

		$main_options->add_field( array(
			'name'         => __( 'Site Logo', 'cp-connect' ),
			'desc'         => sprintf( __( 'The logo to use for %s.', 'cp-connect' ), cp_connect()->setup->post_types->item->plural_label ),
			'id'           => 'logo',
			'type'         => 'file',
			// query_args are passed to wp.media's library query.
			'query_args'   => array(
				// Or only allow gif, jpg, or png images
				 'type' => array(
				     'image/gif',
				     'image/jpeg',
				     'image/png',
				 ),
			),
			'preview_size' => 'thumbnail', // Image size to use when previewing in the admin
		) );

		$main_options->add_field( array(
			'name'         => __( 'Default Thumbnail', 'cp-connect' ),
			'desc'         => sprintf( __( 'The default thumbnail image to use for %s.', 'cp-connect' ), cp_connect()->setup->post_types->item->plural_label ),
			'id'           => 'default_thumbnail',
			'type'         => 'file',
			// query_args are passed to wp.media's library query.
			'query_args'   => array(
				// Or only allow gif, jpg, or png images
				 'type' => array(
				     'image/gif',
				     'image/jpeg',
				     'image/png',
				 ),
			),
			'preview_size' => 'medium', // Image size to use when previewing in the admin
		) );

		$this->item_options();

		if ( cp_connect()->setup->post_types->item_type_enabled() ) {
			$this->item_type_options();
		}

		if ( cp_connect()->setup->post_types->speaker_enabled() ) {
			$this->speaker_options();
		}

		$this->advanced_options();

		/**
		 * Registers tertiary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_license_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_license',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'License',
			'display_cb'   => [ $this, 'options_display_with_tabs' ]
		);

		$tertiary_options = new_cmb2_box( $args );

		$tertiary_options->add_field( array(
			'name' => 'License Key',
			'id'   => 'license',
			'type' => 'text',
		) );
	}

	protected function item_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_item_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_item_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_connect()->setup->post_types->item->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-connect' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->item->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-connect' ),
			'id'      => 'plural_label',
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-connect' ),
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->item->plural_label,
		) );

	}

	protected function item_type_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_item_type_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_item_type_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_connect()->setup->post_types->item_type->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-connect' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->item_type->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-connect' ),
			'id'      => 'plural_label',
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-connect' ),
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->item_type->plural_label,
		) );

	}

	protected function speaker_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_speaker_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_speaker_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => cp_connect()->setup->post_types->speaker->plural_label,
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$options = new_cmb2_box( $args );

		$options->add_field( array(
			'name' => __( 'Labels' ),
			'id'   => 'labels',
			'type' => 'title',
		) );

		$options->add_field( array(
			'name'    => __( 'Singular Label', 'cp-connect' ),
			'id'      => 'singular_label',
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->speaker->single_label,
		) );

		$options->add_field( array(
			'name'    => __( 'Plural Label', 'cp-connect' ),
			'desc'    => __( 'Caution: changing this value will also adjust the url structure and may affect your SEO.', 'cp-connect' ),
			'id'      => 'plural_label',
			'type'    => 'text',
			'default' => cp_connect()->setup->post_types->speaker->plural_label,
		) );

	}

	protected function advanced_options() {
		/**
		 * Registers secondary options page, and set main item as parent.
		 */
		$args = array(
			'id'           => 'cpl_advanced_options_page',
			'title'        => 'Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => 'cpl_advanced_options',
			'parent_slug'  => 'cpl_main_options',
			'tab_group'    => 'cpl_main_options',
			'tab_title'    => 'Advanced',
			'display_cb'   => [ $this, 'options_display_with_tabs' ],
		);

		$advanced_options = new_cmb2_box( $args );

		$advanced_options->add_field( array(
			'name' => __( 'Modules' ),
			'id'   => 'modules_enabled',
			'type' => 'title',
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable' ) . ' ' . cp_connect()->setup->post_types->item_type->plural_label,
			'id'      => 'item_type_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Enable', 'cp-connect' ),
				0 => __( 'Disable', 'cp-connect' ),
			]
		) );

		$advanced_options->add_field( array(
			'name'    => __( 'Enable' ) . ' ' . cp_connect()->setup->post_types->speaker->plural_label,
			'id'      => 'speaker_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Enable', 'cp-connect' ),
				0 => __( 'Disable', 'cp-connect' ),
			]
		) );

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
