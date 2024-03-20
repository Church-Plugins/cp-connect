<?php

namespace CP_Connect\ChMS;

use CP_Connect\Admin\Settings;
use WP_Error;

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
		add_action( 'init', [ $this, 'includes' ], 5 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/** Actions ***************************************************/

		/**
	 * Register rest routes.
	 *
	 * @since  1.1.0
	 */
	public function register_rest_routes() {
		$integrations = \CP_Connect\Integrations\_Init::get_instance();

		$chms = $this->get_active_chms_class();

		if ( ! $chms ) {
			return;
		}

		foreach( $integrations->get_integrations() as $integration ) {
			register_rest_route(
				'cp-connect/v1',
				"$chms->rest_namespace/$integration->type/pull",
				[
					'methods'  => 'POST',
					'callback' => function() use ( $integration ) {
						do_action( "cp_connect_pull_$integration->type", $integration );
	
						return rest_ensure_response( [ 'success' => true ] );
					},
					'permission_callback' => function() {
						return current_user_can( 'manage_options' );
					}
				]
			);

			register_rest_route(
				'cp-connect/v1',
				"$chms->rest_namespace/pull",
				[
					'methods'  => 'POST',
					'callback' => function() use ( $integrations ) {
						$integrations->pull_content();
	
						return rest_ensure_response( [ 'success' => true ] );
					},
					'permission_callback' => function() {
						return current_user_can( 'manage_options' );
					}
				]
			);

			register_rest_route(
				'cp-connect/v1',
				"$chms->rest_namespace/check-connection",
				[
					'methods'  => 'GET',
					'callback' => function() use ( $integrations, $chms ) {
						// $integrations->pull_content();

						$data = $chms->check_connection();

						if ( ! $data ) {
							return rest_ensure_response( [ 'connected' => false, 'message' => __( 'No connection data found', 'cp-connect' ) ] );
						}
	
						return rest_ensure_response(
							[
								'connected' => 'success' === $data['status'],
								'message'   => $data['message'],
							]
						);
					},
					'permission_callback' => function() {
						return true;
					}
				]
			);
		}

		register_rest_route(
			'cp-connect/v1',
			"$chms->rest_namespace/authenticate",
			[
				'methods'  => 'POST',
				'callback' => function( $request ) use ( $chms ) {
					try {
						$authorized = $chms->check_auth( $request->get_param( 'data' ) );
						return rest_ensure_response( [ 'authorized' => $authorized ] );
					} catch ( \Exception $e ) {
						return new \WP_Error( 'authentication_failed', $e->getMessage(), [ 'status' => 401 ] );
					}
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args' => $chms->get_auth_api_args()
			]
		);
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	public function includes() {
		$this->get_active_chms_class(); // Trigger the active ChMS class to load
	}

	/**
	 * Get the active ChMS class
	 *
	 * @return \CP_Connect\ChMS\ChMS | false
	 */
	public function get_active_chms_class() {
		$active_chms = $this->get_active_chms();

		switch( $active_chms ) {
			case 'mp':
				return MinistryPlatform::get_instance();
			case 'pco' :
				return PCO::get_instance();
			case 'ccb' :
				return ChurchCommunityBuilder::get_instance();
		}

		return false;
	}

	/**
	 * Get the active ChMS
	 *
	 * @return string
	 */
	public function get_active_chms() {
		/**
		 * Filter the active ChMS
		 *
		 * @param string The active ChMS.
		 * @return string
		 */
		return apply_filters( 'cp_connect_active_chms', Settings::get( 'chms' ) );
	}

}
