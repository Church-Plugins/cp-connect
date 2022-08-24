<?php

namespace CP_Connect\Admin;

/**
 * Admin-only plugin initialization
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
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {
//		License::get_instance();
//		Settings::get_instance();
	}

	/**
	 * Admin init actions
	 *
	 * @return void
	 */
	protected function actions() {}

	/** Actions ***************************************************/


}
