<?php

namespace CP_Connect\Integrations;

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
	public static $_cron_hook = 'cp_connect_pull';

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
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
		CP_Groups::get_instance();
		TEC::get_instance();
	}

	/**
	 * Handle actions 
	 * 
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function actions() {
		add_action( 'init', [ $this, 'schedule_cron' ], 999 );
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
		if ( is_admin() && isset( $_REQUEST['cp-connect-pull'] ) ) {
			do_action( self::$_cron_hook );
		}

		if ( wp_next_scheduled( self::$_cron_hook ) ) {
			return;
		}

		$args = apply_filters( 'cp_connect_cron_args', [
			'timestamp' => time(),
			'recurrence' => 'hourly',
		] );
		
		wp_schedule_event( $args[ 'timestamp' ], $args['recurrence'], self::$_cron_hook );
	}	
}
