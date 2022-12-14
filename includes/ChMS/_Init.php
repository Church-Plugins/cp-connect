<?php

namespace CP_Connect\ChMS;

require_once( CP_CONNECT_PLUGIN_DIR . "/includes/ChMS/cli/PCO.php" );

/**
 * Setup integration initialization
 */
class _Init {

	/**
	 * @var _Init
	 */
	protected static $_instance;

	/**
	 * The string to use for the cron pull
	 *
	 * @var string
	 */
	public static $_cron_hook = 'cp_connect_chms_pull';

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

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	public function includes() {
		$active_chms = apply_filters( 'cp_connect_active_chms', 'mp' );
		
		switch( $active_chms ) {
			case 'mp':
				MinistryPlatform::get_instance();
				break;
			case 'pco' :
				$pco = PCO::get_instance();
				break;
		}
	}

	protected function actions() {
		add_action( 'init', [ $this, 'schedule_cron' ] );
		add_action( 'init', [ $this, 'includes' ] );
	}

	/** Actions ***************************************************/

	/**
	 * Schedule the cron to pull data from the ChMS
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function schedule_cron() {
		if ( wp_next_scheduled( self::$_cron_hook ) ) {
			return;
		}

		$args = apply_filters( 'cp_connect_chms_cron_args', [
			'timestamp' => time(),
			'recurrence' => 'hourly',
		] );

		wp_schedule_event( $args[ 'timestamp' ], $args['recurrence'], self::$_cron_hook );
	}

}
