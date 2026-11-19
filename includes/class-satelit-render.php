<?php
/**
 * HTML for every feature. All output is escaped here.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderers.
 */
class Satelit_Render {

	/**
	 * Fetches through the API class; null on failure (and remembers why).
	 *
	 * @param string $path  Route.
	 * @param array  $args  Query.
	 * @param string $group TTL group.
	 * @return array|WP_Error
	 */
	private static function fetch( $path, $args, $group ) {
		return Satelit_Api::get( $path, $args, $group );
	}

	/**
	 * What a failed block shows: nothing to visitors, one short line to admins.
	 *
	 * @param WP_Error $err Error.
	 * @return string
	 */
	private static function fail( $err ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		wp_enqueue_style( 'satelit-md' );
		if ( 'satelit_no_key' === $err->get_error_code() ) {
			/* translators: %s: link to the settings page. */
			$msg = sprintf( __( 'Satelit: add your API key in %s. Only administrators see this line.', 'satelit-moldova-data' ), '<a href="' . esc_url( admin_url( 'options-general.php?page=satelit' ) ) . '">' . esc_html__( 'Settings → Satelit', 'satelit-moldova-data' ) . '</a>' );
		} else {
			/* translators: %s: error message from the API. */
			$msg = esc_html( sprintf( __( 'Satelit: no data (%s). Only administrators see this line.', 'satelit-moldova-data' ), $err->get_error_message() ) );
		}
		return '<p class="satelit satelit-admin-note">' . wp_kses( $msg, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
	}

	/**
	 * Wraps a feature: optional heading, body, attribution and the Satelit link.
	 *
	 * @param string $feature Feature key (CSS class).
	 * @param string $title   Heading text ('' for none).
	 * @param string $body    Escaped inner HTML.
	 * @param array  $meta    Envelope meta.
	 * @param string $extra   Extra escaped footer HTML.
	 * @return string
	 */
	private static function wrap( $feature, $title, $body, $meta, $extra = '' ) {
		wp_enqueue_style( 'satelit-md' );
		$out = '<div class="satelit satelit-' . esc_attr( $feature ) . '">';
		if ( '' !== $title ) {
			$out .= '<div class="satelit-head">' . esc_html( $title ) . '</div>';
		}
		$out .= $body;
		$out .= '<div class="satelit-foot">';
		if ( ! empty( $meta['attribution'] ) ) {
			$out .= '<span class="satelit-attr">' . esc_html( $meta['attribution'] ) . '</span> ';
		}
		$out .= $extra;
		if ( ! empty( $meta['stale'] ) ) {
			$out .= '<span class="satelit-stale">' . esc_html__( 'saved copy', 'satelit-moldova-data' ) . '</span> ';
		}
		$out .= '<a class="satelit-brand" href="https://satelit.md" rel="noopener" target="_blank">' . esc_html__( 'Data: Satelit', 'satelit-moldova-data' ) . '</a>';
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * Money with two decimals in the site's number format.
	 *
	 * @param mixed $v   Value.
	 * @param int   $dec Decimals.
	 * @return string
	 */
	private static function num( $v, $dec = 2 ) {
		return null === $v || '' === $v ? '—' : number_format_i18n( (float) $v, $dec );
	}

	/**
	 * A date (and time) from an ISO string in the site's formats.
	 *
	 * @param string $iso  ISO 8601 date or date-time.
	 * @param bool   $time Add the time.
	 * @return string
	 */
	private static function date( $iso, $time = false ) {
		$ts = strtotime( (string) $iso );
		if ( ! $ts ) {
			return '';
		}
		$fmt = get_option( 'date_format' ) . ( $time ? ' ' . get_option( 'time_format' ) : '' );
		if ( ! $time ) {
			return date_i18n( $fmt, strtotime( substr( (string) $iso, 0, 10 ) . ' 12:00:00' ) );
		}
		return function_exists( 'wp_date' ) ? wp_date( $fmt, $ts ) : date_i18n( $fmt, $ts + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Codes list from "EUR, usd".
	 *
	 * @param string $codes Comma separated codes.
	 * @return array
	 */
	private static function codes( $codes ) {
		$out = array();
		foreach ( explode( ',', strtoupper( (string) $codes ) ) as $c ) {
			$c = preg_replace( '/[^A-Z]/', '', $c );
			if ( 3 === strlen( $c ) ) {
				$out[] = $c;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Bounded positive integer.
	 *
	 * @param mixed $v   Value.
	 * @param int   $def Default.
	 * @param int   $max Upper bound.
	 * @return int
	 */
	private static function limit( $v, $def, $max ) {
		$v = absint( $v );
		return $v < 1 ? $def : min( $v, $max );
	}

	/**
	 * Today's rates keyed by code: value of 1 unit in MDL.
	 *
	 * @return array|WP_Error
	 */
	public static function rates_map() {
		$res = self::fetch( '/rates', array(), 'rates' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$map = array();
		foreach ( (array) $res['data'] as $r ) {
			if ( ! empty( $r['code'] ) && ! empty( $r['value'] ) ) {
				$nom               = ! empty( $r['nominal'] ) ? (float) $r['nominal'] : 1.0;
				$map[ $r['code'] ] = (float) $r['value'] / $nom;
			}
		}
		return $map;
	}

	/**
	 * [satelit_rates]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_rates( $atts ) {
		$res = self::fetch( '/rates', array(), 'rates' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$by = array();
		foreach ( (array) $res['data'] as $r ) {
			$by[ $r['code'] ] = $r;
		}
		$rows = '';
		$day  = '';
		foreach ( self::codes( $atts['codes'] ) as $code ) {
			if ( ! isset( $by[ $code ] ) ) {
				continue;
			}
			$r   = $by[ $code ];
			$day = $r['date'];
			$chg = isset( $r['change_7d_pct'] ) ? (float) $r['change_7d_pct'] : null;
			if ( null === $chg || abs( $chg ) < 0.05 ) {
				$arrow = '<span class="satelit-flat" aria-hidden="true">•</span>';
				$cls   = 'satelit-flat';
			} elseif ( $chg > 0 ) {
				$arrow = '<span aria-hidden="true">▲</span>';
				$cls   = 'satelit-up';
			} else {
				$arrow = '<span aria-hidden="true">▼</span>';
				$cls   = 'satelit-down';
			}
			$nom   = (int) $r['nominal'] > 1 ? (int) $r['nominal'] . ' ' : '';
			$rows .= '<tr><th scope="row"><span class="satelit-code">' . esc_html( $nom . $code ) . '</span> <span class="satelit-muted">' . esc_html( $r['name'] ) . '</span></th>'
				. '<td class="satelit-num" data-satelit-code="' . esc_attr( $code ) . '">' . esc_html( self::num( $r['value'], 4 ) ) . '</td>'
				. '<td class="satelit-num ' . esc_attr( $cls ) . '">' . $arrow . ' ' . esc_html( null === $chg ? '—' : ( $chg > 0 ? '+' : '' ) . self::num( $chg, 1 ) . '%' ) . '</td></tr>';
		}
		if ( '' === $rows ) {
			return '';
		}
		$head  = '<table class="satelit-table"><thead><tr><th scope="col">' . esc_html__( 'Currency', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'MDL', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( '7 days', 'satelit-moldova-data' ) . '</th></tr></thead><tbody>';
		$title = sprintf(
			/* translators: %s: date. */
			__( 'BNM official rates, %s', 'satelit-moldova-data' ),
			self::date( $day )
		);
		return self::wrap( 'rates', $title, $head . $rows . '</tbody></table>', $res['meta'] );
	}

	/**
	 * [satelit_converter]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_converter( $atts ) {
		$res = self::fetch( '/rates', array(), 'rates' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$map  = self::rates_map();
		$keep = array( 'MDL' => 1.0 );
		foreach ( self::codes( $atts['codes'] ) as $c ) {
			if ( isset( $map[ $c ] ) ) {
				$keep[ $c ] = $map[ $c ];
			}
		}
		if ( count( $keep ) < 2 ) {
			return '';
		}
		$codes  = array_keys( $keep );
		$amount = is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : 100.0;
		$from   = $codes[1];
		$result = $amount * $keep[ $from ];

		$opts     = function ( $sel ) use ( $codes ) {
			$o = '';
			foreach ( $codes as $c ) {
				$o .= '<option value="' . esc_attr( $c ) . '"' . selected( $c, $sel, false ) . '>' . esc_html( $c ) . '</option>';
			}
			return $o;
		};
		static $n = 0;
		++$n;
		$uid  = 'satelit-conv-' . $n;
		$body = '<form class="satelit-conv" data-satelit-rates="' . esc_attr( wp_json_encode( $keep ) ) . '" onsubmit="return false">'
			. '<label class="satelit-field"><span>' . esc_html__( 'Amount', 'satelit-moldova-data' ) . '</span><input type="number" inputmode="decimal" min="0" step="any" name="amount" value="' . esc_attr( $amount ) . '" id="' . esc_attr( $uid ) . '"></label>'
			. '<label class="satelit-field"><span>' . esc_html__( 'From', 'satelit-moldova-data' ) . '</span><select name="from">' . $opts( $from ) . '</select></label>'
			. '<button type="button" class="satelit-swap" aria-label="' . esc_attr__( 'Swap currencies', 'satelit-moldova-data' ) . '">⇄</button>'
			. '<label class="satelit-field"><span>' . esc_html__( 'To', 'satelit-moldova-data' ) . '</span><select name="to">' . $opts( 'MDL' ) . '</select></label>'
			. '<output class="satelit-result" for="' . esc_attr( $uid ) . '" aria-live="polite">' . esc_html( self::num( $result ) . ' MDL' ) . '</output>'
			. '</form>';
		wp_enqueue_script( 'satelit-md-converter' );
		$day = isset( $res['data'][0]['date'] ) ? $res['data'][0]['date'] : '';
		/* translators: %s: date. */
		$title = sprintf( __( 'Currency converter, BNM rate of %s', 'satelit-moldova-data' ), self::date( $day ) );
		return self::wrap( 'converter', $title, $body, $res['meta'] );
	}

	/**
	 * [satelit_fuel]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_fuel( $atts ) {
		$res = self::fetch( '/fuel', array(), 'fuel' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$d     = $res['data'];
		$names = array();
		$nets  = array();
		foreach ( (array) $d['networks'] as $n ) {
			$names[ $n['key'] ] = $n['name'];
			if ( empty( $n['stale'] ) && ! empty( $n['prices']['a95'] ) ) {
				$nets[] = $n;
			}
		}
		usort(
			$nets,
			function ( $a, $b ) {
				return $a['prices']['a95'] <=> $b['prices']['a95'];
			}
		);
		$nets  = array_slice( $nets, 0, self::limit( $atts['limit'], 10, 100 ) );
		$fuels = array(
			'a95'    => __( 'A95', 'satelit-moldova-data' ),
			'diesel' => __( 'Diesel', 'satelit-moldova-data' ),
			'lpg'    => __( 'LPG', 'satelit-moldova-data' ),
		);
		$cheap = isset( $d['cheapest'] ) ? (array) $d['cheapest'] : array();

		$body = '<ul class="satelit-facts">';
		if ( ! empty( $d['cap'] ) ) {
			$cap = array();
			foreach ( $d['cap'] as $f => $v ) {
				$cap[] = ( isset( $fuels[ $f ] ) ? $fuels[ $f ] : $f ) . ' ' . self::num( $v );
			}
			$body .= '<li><b>' . esc_html__( 'ANRE cap', 'satelit-moldova-data' ) . '</b> ' . esc_html( implode( ' · ', $cap ) ) . ' ' . esc_html__( 'lei/l', 'satelit-moldova-data' ) . '</li>';
		}
		$low = array();
		foreach ( $fuels as $f => $label ) {
			if ( ! empty( $cheap[ $f ] ) && isset( $names[ $cheap[ $f ] ] ) ) {
				$min   = isset( $d['range'][ $f ]['min'] ) ? ' ' . self::num( $d['range'][ $f ]['min'] ) : '';
				$low[] = $label . ': ' . $names[ $cheap[ $f ] ] . $min;
			}
		}
		if ( $low ) {
			$body .= '<li><b>' . esc_html__( 'Cheapest', 'satelit-moldova-data' ) . '</b> ' . esc_html( implode( ' · ', $low ) ) . '</li>';
		}
		$body .= '</ul><table class="satelit-table"><thead><tr><th scope="col">' . esc_html__( 'Network', 'satelit-moldova-data' ) . '</th>';
		foreach ( $fuels as $label ) {
			$body .= '<th scope="col" class="satelit-num">' . esc_html( $label ) . '</th>';
		}
		$body .= '</tr></thead><tbody>';
		foreach ( $nets as $n ) {
			$body .= '<tr><th scope="row">' . esc_html( $n['name'] ) . '</th>';
			foreach ( array_keys( $fuels ) as $f ) {
				$v     = isset( $n['prices'][ $f ] ) ? $n['prices'][ $f ] : null;
				$best  = null !== $v && isset( $d['range'][ $f ]['min'] ) && (float) $v <= (float) $d['range'][ $f ]['min'];
				$body .= '<td class="satelit-num' . ( $best ? ' satelit-best' : '' ) . '">' . esc_html( self::num( $v ) ) . '</td>';
			}
			$body .= '</tr>';
		}
		$body .= '</tbody></table>';
		/* translators: %s: date. */
		$title = sprintf( __( 'Fuel prices, lei per litre, %s', 'satelit-moldova-data' ), self::date( $d['date'] ) );
		return self::wrap( 'fuel', $title, $body, $res['meta'], self::sources( $res['meta'] ) );
	}

	/**
	 * Short "Sources: …" line when the data has at most three named sources.
	 *
	 * @param array $meta Envelope meta.
	 * @return string
	 */
	private static function sources( $meta ) {
		if ( empty( $meta['sources'] ) || count( $meta['sources'] ) > 3 || ! empty( $meta['attribution'] ) ) {
			return '';
		}
		$links = array();
		foreach ( $meta['sources'] as $s ) {
			if ( empty( $s['name'] ) || 'Satelit' === $s['name'] ) {
				continue;
			}
			$links[] = empty( $s['url'] ) ? esc_html( $s['name'] ) : '<a href="' . esc_url( $s['url'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $s['name'] ) . '</a>';
		}
		return $links ? '<span class="satelit-attr">' . esc_html__( 'Source:', 'satelit-moldova-data' ) . ' ' . implode( ', ', $links ) . '</span> ' : '';
	}

	/**
	 * [satelit_prices]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_prices( $atts ) {
		$q = trim( wp_strip_all_tags( (string) $atts['q'] ) );
		if ( strlen( $q ) < 2 ) {
			return '';
		}
		$q   = function_exists( 'mb_substr' ) ? mb_substr( $q, 0, 100 ) : substr( $q, 0, 100 );
		$res = self::fetch( '/prices/search', array( 'q' => $q ), 'prices' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$items = array_slice( (array) $res['data'], 0, self::limit( $atts['limit'], 5, 20 ) );
		if ( ! $items ) {
			return self::wrap( 'prices', '', '<p class="satelit-empty">' . esc_html__( 'No products found.', 'satelit-moldova-data' ) . '</p>', $res['meta'] );
		}
		$body = '<table class="satelit-table"><thead><tr><th scope="col">' . esc_html__( 'Product', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'From', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'Median', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'Shops', 'satelit-moldova-data' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $it ) {
			$body .= '<tr><th scope="row">' . esc_html( $it['name'] ) . '</th>'
				. '<td class="satelit-num satelit-best">' . esc_html( self::num( $it['min_price'] ) ) . '</td>'
				. '<td class="satelit-num">' . esc_html( self::num( $it['median_price'] ) ) . '</td>'
				. '<td class="satelit-num">' . esc_html( (int) $it['stores'] ) . '</td></tr>';
		}
		$body .= '</tbody></table>';
		/* translators: %s: search words. */
		$title = sprintf( __( 'Prices in Moldovan shops, lei: “%s”', 'satelit-moldova-data' ), $q );
		return self::wrap( 'prices', $title, $body, $res['meta'] );
	}

	/**
	 * [satelit_product id="…"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_product( $atts ) {
		$id = absint( $atts['id'] );
		if ( ! $id ) {
			return '';
		}
		$res = self::fetch( '/prices/items/' . $id, array(), 'prices' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$it     = $res['data'];
		$offers = array_slice( isset( $it['offers'] ) ? (array) $it['offers'] : array(), 0, self::limit( $atts['limit'], 10, 50 ) );
		$body   = '<ul class="satelit-facts"><li><b>' . esc_html__( 'From', 'satelit-moldova-data' ) . '</b> ' . esc_html( self::num( $it['min_price'] ) ) . ' ' . esc_html__( 'lei', 'satelit-moldova-data' )
			. ' · <b>' . esc_html__( 'Median', 'satelit-moldova-data' ) . '</b> ' . esc_html( self::num( $it['median_price'] ) )
			/* translators: %d: number of shops. */
			. ' · ' . esc_html( sprintf( _n( '%d shop', '%d shops', (int) $it['stores'], 'satelit-moldova-data' ), (int) $it['stores'] ) ) . '</li></ul>';
		$body .= '<table class="satelit-table"><thead><tr><th scope="col">' . esc_html__( 'Shop', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'Price', 'satelit-moldova-data' ) . '</th><th scope="col" class="satelit-num">' . esc_html__( 'Stock', 'satelit-moldova-data' ) . '</th></tr></thead><tbody>';
		foreach ( $offers as $o ) {
			$shop  = isset( $o['store']['name'] ) ? $o['store']['name'] : '';
			$cell  = empty( $o['url'] ) ? esc_html( $shop ) : '<a href="' . esc_url( $o['url'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $shop ) . '</a>';
			$old   = ! empty( $o['old_price'] ) && (float) $o['old_price'] > (float) $o['price'] ? ' <s class="satelit-muted">' . esc_html( self::num( $o['old_price'] ) ) . '</s>' : '';
			$body .= '<tr><th scope="row">' . $cell . '</th><td class="satelit-num">' . esc_html( self::num( $o['price'] ) ) . $old . '</td><td class="satelit-num">'
				. ( ! empty( $o['in_stock'] ) ? '<span class="satelit-up">' . esc_html__( 'yes', 'satelit-moldova-data' ) . '</span>' : '<span class="satelit-muted">' . esc_html__( 'no', 'satelit-moldova-data' ) . '</span>' ) . '</td></tr>';
		}
		$body .= '</tbody></table>';
		return self::wrap( 'product', $it['name'], $body, $res['meta'] );
	}

	/**
	 * [satelit_news]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_news( $atts ) {
		$args = array( 'per' => self::limit( $atts['limit'], 5, 20 ) );
		$cat  = sanitize_key( $atts['category'] );
		if ( '' !== $cat ) {
			$args['category'] = $cat;
		}
		$res = self::fetch( '/news', $args, 'news' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$body = '<ul class="satelit-cards">';
		foreach ( (array) $res['data'] as $n ) {
			$img = '';
			if ( ! empty( $n['image'] ) ) {
				$src = is_array( $n['image'] ) ? ( isset( $n['image']['url'] ) ? $n['image']['url'] : reset( $n['image'] ) ) : $n['image'];
				if ( is_string( $src ) && '' !== $src ) {
					$img = '<img class="satelit-thumb" src="' . esc_url( $src ) . '" alt="" loading="lazy">';
				}
			}
			$links = array();
			foreach ( array_slice( isset( $n['sources'] ) ? (array) $n['sources'] : array(), 0, 3 ) as $s ) {
				$links[] = '<a href="' . esc_url( $s['url'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $s['name'] ) . '</a>';
			}
			$body .= '<li class="satelit-card">' . $img . '<div class="satelit-card-body">'
				. '<div class="satelit-muted satelit-small">' . esc_html( isset( $n['category']['label'] ) ? $n['category']['label'] : '' ) . ' · ' . esc_html( self::date( $n['published_at'], true ) ) . '</div>'
				. '<div class="satelit-title">' . esc_html( $n['title'] ) . '</div>'
				. ( ! empty( $n['dek'] ) ? '<p>' . esc_html( $n['dek'] ) . '</p>' : '' )
				. ( $links ? '<div class="satelit-small">' . implode( ' · ', $links ) . '</div>' : '' )
				. '</div></li>';
		}
		$body .= '</ul>';
		return self::wrap( 'news', __( 'Latest news from Moldova', 'satelit-moldova-data' ), $body, $res['meta'] );
	}

	/**
	 * A poster URL of a picture set, or ''.
	 *
	 * @param array  $pics Pictures.
	 * @param string $size s, m or l.
	 * @return string
	 */
	private static function poster( $pics, $size ) {
		if ( ! empty( $pics['poster'][ $size ] ) ) {
			return $pics['poster'][ $size ];
		}
		return ! empty( $pics['poster']['s'] ) ? $pics['poster']['s'] : '';
	}

	/**
	 * [satelit_cinema city="Chișinău"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_cinema( $atts ) {
		$args = array( 'country' => 'MD' );
		$city = trim( sanitize_text_field( $atts['city'] ) );
		if ( '' !== $city ) {
			$args['city'] = $city;
		}
		$res = self::fetch( '/movies/cinemas/today', $args, 'cinema' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$d     = $res['data'];
		$limit = self::limit( $atts['limit'], 8, 50 );
		$body  = '';
		foreach ( isset( $d['cinemas'] ) ? (array) $d['cinemas'] : array() as $c ) {
			$films = array_slice( isset( $c['films'] ) ? (array) $c['films'] : array(), 0, $limit );
			if ( ! $films ) {
				continue;
			}
			$body .= '<section class="satelit-cinema-one"><div class="satelit-title">' . esc_html( $c['cinema']['name'] ) . '</div>';
			if ( ! empty( $c['cinema']['address'] ) ) {
				$body .= '<div class="satelit-muted satelit-small">' . esc_html( $c['cinema']['address'] ) . '</div>';
			}
			$body .= '<ul class="satelit-films">';
			foreach ( $films as $f ) {
				$src   = self::poster( isset( $f['pictures'] ) ? (array) $f['pictures'] : array(), 's' );
				$img   = $src ? '<img class="satelit-poster" src="' . esc_url( $src ) . '" alt="" loading="lazy" width="60" height="90">' : '<span class="satelit-poster satelit-noposter" aria-hidden="true"></span>';
				$times = '';
				foreach ( isset( $f['sessions'] ) ? (array) $f['sessions'] : array() as $s ) {
					$tip    = trim( implode( ' ', isset( $s['formats'] ) ? (array) $s['formats'] : array() ) . ' ' . ( isset( $s['language'] ) ? $s['language'] : '' ) . ( ! empty( $s['hall'] ) ? ', ' . $s['hall'] : '' ) );
					$label  = esc_html( $s['time'] );
					$cls    = 'satelit-time' . ( ! empty( $s['sold_out'] ) ? ' satelit-sold' : '' );
					$times .= empty( $s['booking_url'] )
						? '<span class="' . esc_attr( $cls ) . '" title="' . esc_attr( $tip ) . '">' . $label . '</span>'
						: '<a class="' . esc_attr( $cls ) . '" title="' . esc_attr( $tip ) . '" href="' . esc_url( $s['booking_url'] ) . '" rel="noopener nofollow" target="_blank">' . $label . '</a>';
				}
				$title = isset( $f['title'] ) ? $f['title'] : ( isset( $f['listed_title'] ) ? $f['listed_title'] : '' );
				$body .= '<li class="satelit-film">' . $img . '<div><div class="satelit-film-title">' . esc_html( $title ) . ( ! empty( $f['year'] ) ? ' <span class="satelit-muted">' . esc_html( $f['year'] ) . '</span>' : '' ) . '</div><div class="satelit-times">' . $times . '</div></div></li>';
			}
			$body .= '</ul></section>';
		}
		if ( '' === $body ) {
			$body = '<p class="satelit-empty">' . esc_html__( 'No sessions today.', 'satelit-moldova-data' ) . '</p>';
		}
		$day = isset( $d['day'] ) ? $d['day'] : '';
		/* translators: 1: city, 2: date. */
		$head = '' !== $city ? sprintf( __( 'In cinemas in %1$s, %2$s', 'satelit-moldova-data' ), $city, self::date( $day ) ) : sprintf( /* translators: %s: date. */ __( 'In cinemas today, %s', 'satelit-moldova-data' ), self::date( $day ) );
		return self::wrap( 'cinema', $head, $body, $res['meta'] );
	}

	/**
	 * [satelit_movie id="m603"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_movie( $atts ) {
		$id = strtolower( trim( (string) $atts['id'] ) );
		if ( ! preg_match( '/^[ms][0-9]{1,10}$/', $id ) ) {
			return '';
		}
		$res = self::fetch( '/movies/titles/' . $id, array(), 'movie' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$m    = $res['data'];
		$src  = self::poster( isset( $m['pictures'] ) ? (array) $m['pictures'] : array(), 'm' );
		$bits = array();
		if ( ! empty( $m['year'] ) ) {
			$bits[] = $m['year'];
		}
		if ( ! empty( $m['runtime_min'] ) ) {
			/* translators: %d: minutes. */
			$bits[] = sprintf( __( '%d min', 'satelit-moldova-data' ), (int) $m['runtime_min'] );
		}
		if ( ! empty( $m['genres'] ) ) {
			$bits[] = implode( ', ', (array) $m['genres'] );
		}
		if ( ! empty( $m['ratings']['community']['value'] ) ) {
			$bits[] = '★ ' . self::num( $m['ratings']['community']['value'], 1 );
		}
		$people = array();
		if ( ! empty( $m['people']['directors'] ) ) {
			$people[] = '<b>' . esc_html__( 'Director', 'satelit-moldova-data' ) . '</b> ' . esc_html( implode( ', ', wp_list_pluck( array_slice( $m['people']['directors'], 0, 3 ), 'name' ) ) );
		}
		if ( ! empty( $m['people']['cast'] ) ) {
			$people[] = '<b>' . esc_html__( 'Cast', 'satelit-moldova-data' ) . '</b> ' . esc_html( implode( ', ', wp_list_pluck( array_slice( $m['people']['cast'], 0, 5 ), 'name' ) ) );
		}
		$orig = ! empty( $m['original_title'] ) && $m['original_title'] !== $m['title'] ? '<div class="satelit-muted">' . esc_html( $m['original_title'] ) . '</div>' : '';
		$body = '<div class="satelit-movie-card">'
			. ( $src ? '<img class="satelit-poster-l" src="' . esc_url( $src ) . '" alt="' . esc_attr( $m['title'] ) . '" loading="lazy">' : '' )
			. '<div class="satelit-card-body"><div class="satelit-title satelit-big">' . esc_html( $m['title'] ) . '</div>' . $orig
			. '<div class="satelit-muted satelit-small">' . esc_html( implode( ' · ', $bits ) ) . '</div>'
			. ( ! empty( $m['tagline'] ) ? '<p><i>' . esc_html( $m['tagline'] ) . '</i></p>' : '' )
			. ( ! empty( $m['overview'] ) ? '<p>' . esc_html( $m['overview'] ) . '</p>' : '' )
			. ( $people ? '<p class="satelit-small">' . implode( '<br>', $people ) . '</p>' : '' )
			. '</div></div>';
		return self::wrap( 'movie', '', $body, $res['meta'] );
	}

	/**
	 * [satelit_law days="7"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render_law( $atts ) {
		$res = self::fetch( '/law/digests', array( 'days' => self::limit( $atts['days'], 7, 60 ) ), 'law' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$body = '<ul class="satelit-list">';
		foreach ( (array) $res['data'] as $dg ) {
			$body .= '<li><span class="satelit-title">' . esc_html( $dg['title'] ) . '</span> <span class="satelit-muted satelit-small">'
				/* translators: %d: number of acts. */
				. esc_html( sprintf( _n( '%d act', '%d acts', (int) $dg['acts'], 'satelit-moldova-data' ), (int) $dg['acts'] ) ) . '</span></li>';
		}
		$body .= '</ul>';
		return self::wrap( 'law', __( 'Monitorul Oficial digest', 'satelit-moldova-data' ), $body, $res['meta'], self::sources( $res['meta'] ) );
	}

	/**
	 * [satelit_joke]
	 *
	 * @param array $atts Attributes (none).
	 * @return string
	 */
	public static function render_joke( $atts ) {
		unset( $atts );
		$res = self::fetch( '/jokes/today', array(), 'joke' );
		if ( is_wp_error( $res ) ) {
			return self::fail( $res );
		}
		$j     = $res['data'];
		$src   = ! empty( $j['source']['name'] ) ? ( empty( $j['source']['url'] ) ? esc_html( $j['source']['name'] ) : '<a href="' . esc_url( $j['source']['url'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $j['source']['name'] ) . '</a>' ) : '';
		$body  = ( ! empty( $j['title'] ) ? '<div class="satelit-title">' . esc_html( $j['title'] ) . '</div>' : '' )
			. '<p class="satelit-joke-text">' . nl2br( esc_html( $j['text'] ) ) . '</p>';
		$extra = $src ? '<span class="satelit-attr">' . esc_html__( 'Source:', 'satelit-moldova-data' ) . ' ' . $src . '</span> ' : '';
		return self::wrap( 'joke', __( 'Joke of the day', 'satelit-moldova-data' ), $body, $res['meta'], $extra );
	}
}
