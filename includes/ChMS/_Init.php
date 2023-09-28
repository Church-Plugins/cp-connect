<?php

namespace CP_Connect\ChMS;

require_once( CP_CONNECT_PLUGIN_DIR . "/includes/ChMS/cli/PCO.php" );
require_once( CP_CONNECT_PLUGIN_DIR . "/includes/ChMS/ccb-api/ccb-api.php" );

/**
 * Setup integration initialization
 */
class _Init {

	/**
	 * @var _Init
	 */
	protected static $_instance;

	/**
	 * Only make one instance of _Init
	 *
	 * @return _Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof _Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		$this->actions();
	}

	protected function actions() {
		add_action( 'init', [ $this, 'includes' ] );
	}

	/** Actions ***************************************************/

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	public function includes() {

		//  Grab the value from wp-config.php
		$active_chms = false;
		// Get the active ChMS from CP_CONNECT_ACTIVE_CHMS, if defined
		if( defined( 'CP_CONNECT_ACTIVE_CHMS' ) && !empty( CP_CONNECT_ACTIVE_CHMS ) ) {
			$active_chms = CP_CONNECT_ACTIVE_CHMS;
		} else {
			// Fallback to Ministry Platform
			$active_chms = apply_filters( 'cp_connect_active_chms', 'mp' );
		}


		switch( $active_chms ) {
			case 'mp':
				MinistryPlatform::get_instance();
				break;
			case 'rockrms':
				RockRMS::get_instance();
				break;
			case 'pco' :
				$pco = PCO::get_instance();
				break;
			case 'ccb' :
				$ccb = ChurchCommunityBuilder::get_instance();
				break;
			default :
				// Do nothing
				break;
		}
	}

}
