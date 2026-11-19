<?php
/**
 * Server-side rendered blocks (satelit/rates, …), no build step.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block registration.
 */
class Satelit_Blocks {

	/**
	 * Hooks in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Registers the editor script and one block per feature.
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		// WP 5.3+ has wp-block-editor and wp-server-side-render; 5.0-5.2 only wp-editor.
		$deps = array( 'wp-blocks', 'wp-element', 'wp-components' );
		if ( wp_script_is( 'wp-block-editor', 'registered' ) && wp_script_is( 'wp-server-side-render', 'registered' ) ) {
			$deps[] = 'wp-block-editor';
			$deps[] = 'wp-server-side-render';
		} else {
			$deps[] = 'wp-editor';
		}
		wp_register_script(
			'satelit-md-blocks',
			SATELIT_MD_URL . 'assets/js/blocks.js',
			$deps,
			SATELIT_MD_VERSION,
			true
		);

		$labels = Satelit_Features::labels();
		$icons  = Satelit_Features::icons();
		$defs   = array();
		foreach ( Satelit_Features::atts() as $feature => $atts ) {
			$attributes = array();
			foreach ( $atts as $name => $default ) {
				$attributes[ $name ] = array(
					'type'    => 'string',
					'default' => $default,
				);
			}
			$defs[ $feature ] = array(
				'title' => $labels[ $feature ],
				'icon'  => $icons[ $feature ],
				'atts'  => $atts,
			);
			register_block_type(
				'satelit/' . $feature,
				array(
					'editor_script'   => 'satelit-md-blocks',
					'editor_style'    => 'satelit-md',
					'attributes'      => $attributes,
					'render_callback' => function ( $attrs ) use ( $feature ) {
						return Satelit_Features::render( $feature, $attrs );
					},
				)
			);
		}
		wp_localize_script(
			'satelit-md-blocks',
			'satelitMdBlocks',
			array(
				'blocks' => $defs,
				'fields' => Satelit_Features::field_labels(),
				'panel'  => __( 'Settings', 'satelit-moldova-data' ),
			)
		);
	}
}
