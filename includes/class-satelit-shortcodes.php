<?php
/**
 * Shortcodes: [satelit_rates], [satelit_converter], … one per feature.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode registration.
 */
class Satelit_Shortcodes {

	/**
	 * Hooks in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers satelit_<feature> for every feature.
	 */
	public static function register() {
		foreach ( array_keys( Satelit_Features::atts() ) as $feature ) {
			add_shortcode(
				'satelit_' . $feature,
				function ( $atts ) use ( $feature ) {
					return Satelit_Features::render( $feature, $atts );
				}
			);
		}
	}
}
