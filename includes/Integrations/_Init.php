<?php

namespace CP_Connect\Integrations;

use CP_Connect\Admin\Settings;

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
	 * @var array
	 */
	protected static $_integrations = [];

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
		$integrations = [ 'tec' => '\CP_Connect\Integrations\TEC', 'cp_groups' => '\CP_Connect\Integrations\CP_Groups' ];

		foreach( $integrations as $key => $integration ) {
			if ( ! class_exists( $integration ) ) {
				continue;
			}

			self::$_integrations[ $key ] = new $integration;
		}
	}

	/**
	 * Return integrations
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_integrations() {
		return self::$_integrations;
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
		add_action( self::$_cron_hook, [ $this, 'pull_content' ] );
	}

	/** Actions ***************************************************/

	/**
	 * trigger the contant pull
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function pull_content() {
		foreach( self::$_integrations as $integration ) {
			do_action( 'cp_connect_pull_' . $integration->type, $integration );
		}
	}

	/**
	 * Schedule the cron to pull data from the ChMS
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function schedule_cron() {
		if ( is_admin() && Settings::get( 'pull_now' ) ) {
			Settings::set( 'pull_now', '' );
//			add_filter( 'cp_connect_process_hard_refresh', '__return_true' );
//			do_action( self::$_cron_hook );
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
