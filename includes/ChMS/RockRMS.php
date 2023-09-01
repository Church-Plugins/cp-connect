<?php

namespace CP_Connect\ChMS;

/**
 * RockRMS Integration provider
 *
 */
class RockRMS extends ChMS {

	public function integrations() {
		// $this->mpLoadConnectionParameters();

		// add_action( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
		// add_action( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );

		// add_action( 'admin_init', [ $this, 'initialize_plugin_options' ] );

		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}

	/**
	 * This function introduces a single plugin menu option into the WordPress 'Plugins'
	 * menu.
	 */
	function plugin_menu() {

		add_submenu_page( 'options-general.php',
			__( 'Rock RMS Integration', 'cp-connect' ),         // The title to be displayed in the browser window for this page.
			__( 'Rock RMS', 'cp-connect' ),                        // The text to be displayed for this menu item
			'administrator',                    // Which type of users can see this menu item
			'rockrms_plugin_options', // The unique ID - that is, the slug - for this menu item
			[ $this, 'plugin_display' ]  // The name of the function to call when rendering the page for this menu
		);
	}

	/**
	 * Displays the options page
	 */
	function plugin_display() {

		$default_tab = 'connect';
		$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
		?>

		<div class='wrap'>
			<nav class='nav-tab-wrapper'>
				<a href='?page=ministry_platform_plugin_options&tab=connect' class='nav-tab <?php echo $tab == 'connect' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Connect', 'cp-connect' ) ?></a>
				<a href='?page=ministry_platform_plugin_options&tab=group-options' class='nav-tab <?php echo $tab == 'group-options' ? 'nav-tab-active' : ''; ?>'><?php esc_html_e( 'Group Options', 'cp-connect' ) ?></a>
			</nav>
			<form method="post" action=<?php echo esc_url( add_query_arg( 'tab', $tab, admin_url( 'options.php' ) ) ) ?>>
				<?php switch ( $tab ) {
					case 'connect':
						// $this->render_api_config_tab();
						echo "Rock RMS Connect content";
						break;
					case 'group-options':
						// $this->render_group_mapping_tab();
						echo "Rock RMS Options content";
						break;
				} ?>
			</form>
		</div>
		<?php
	}

	// __( 'Your system does not meet the requirements for Church Plugins - Staff', 'cp-connect' ) );

}