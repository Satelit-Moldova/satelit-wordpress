<?php
/**
 * One list of features: shortcodes, blocks and the widget all read it.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature registry.
 */
class Satelit_Features {

	/**
	 * Attribute defaults per feature (all strings, as in a shortcode).
	 *
	 * @return array
	 */
	public static function atts() {
		return array(
			'rates'     => array( 'codes' => 'EUR,USD,RON,RUB,UAH' ),
			'converter' => array(
				'codes'  => 'EUR,USD,RON,RUB,UAH,GBP',
				'amount' => '100',
			),
			'fuel'      => array( 'limit' => '10' ),
			'prices'    => array(
				'q'     => 'lapte',
				'limit' => '5',
			),
			'product'   => array(
				'id'    => '',
				'limit' => '10',
			),
			'news'      => array(
				'limit'    => '5',
				'category' => '',
			),
			'cinema'    => array(
				'city'  => 'Chișinău',
				'limit' => '8',
			),
			'movie'     => array( 'id' => 'm603' ),
			'law'       => array( 'days' => '7' ),
			'joke'      => array(),
		);
	}

	/**
	 * Human labels per feature (translated).
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'rates'     => __( 'Satelit: BNM exchange rates', 'satelit-moldova-data' ),
			'converter' => __( 'Satelit: MDL converter', 'satelit-moldova-data' ),
			'fuel'      => __( 'Satelit: fuel prices', 'satelit-moldova-data' ),
			'prices'    => __( 'Satelit: price comparison', 'satelit-moldova-data' ),
			'product'   => __( 'Satelit: product offers', 'satelit-moldova-data' ),
			'news'      => __( 'Satelit: latest news', 'satelit-moldova-data' ),
			'cinema'    => __( 'Satelit: cinemas today', 'satelit-moldova-data' ),
			'movie'     => __( 'Satelit: film card', 'satelit-moldova-data' ),
			'law'       => __( 'Satelit: Monitorul Oficial digest', 'satelit-moldova-data' ),
			'joke'      => __( 'Satelit: joke of the day', 'satelit-moldova-data' ),
		);
	}

	/**
	 * Labels of attribute fields (translated).
	 *
	 * @return array
	 */
	public static function field_labels() {
		return array(
			'codes'    => __( 'Currency codes, comma separated', 'satelit-moldova-data' ),
			'amount'   => __( 'Start amount', 'satelit-moldova-data' ),
			'limit'    => __( 'How many', 'satelit-moldova-data' ),
			'q'        => __( 'Search words', 'satelit-moldova-data' ),
			'id'       => __( 'Id', 'satelit-moldova-data' ),
			'category' => __( 'News category key (empty: all)', 'satelit-moldova-data' ),
			'city'     => __( 'City', 'satelit-moldova-data' ),
			'days'     => __( 'Days', 'satelit-moldova-data' ),
		);
	}

	/**
	 * Dashicon per feature (blocks).
	 *
	 * @return array
	 */
	public static function icons() {
		return array(
			'rates'     => 'chart-line',
			'converter' => 'calculator',
			'fuel'      => 'car',
			'prices'    => 'cart',
			'product'   => 'products',
			'news'      => 'megaphone',
			'cinema'    => 'tickets-alt',
			'movie'     => 'video-alt2',
			'law'       => 'book-alt',
			'joke'      => 'smiley',
		);
	}

	/**
	 * Renders a feature by name with raw attributes.
	 *
	 * @param string $feature Feature key.
	 * @param array  $atts    Attributes.
	 * @return string HTML.
	 */
	public static function render( $feature, $atts ) {
		$all = self::atts();
		if ( ! isset( $all[ $feature ] ) ) {
			return '';
		}
		$atts   = shortcode_atts( $all[ $feature ], is_array( $atts ) ? $atts : array(), 'satelit_' . $feature );
		$method = 'render_' . $feature;
		return Satelit_Render::$method( $atts );
	}
}
