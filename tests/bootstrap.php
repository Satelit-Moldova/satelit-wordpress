<?php
/**
 * Offline test bootstrap: the few WordPress functions the plugin uses, in memory.
 * HTTP goes to a queue of canned answers (tests/fixtures), never the network,
 * unless SATELIT_LIVE=1 (then wp_remote_get really calls the API).
 *
 * @package SatelitMoldovaData
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'SATELIT_MD_VERSION', '1.0.0' );

$GLOBALS['t_options']    = array();
$GLOBALS['t_transients'] = array();
$GLOBALS['t_http']       = array(); // Queue of responses (array or WP_Error).
$GLOBALS['t_calls']      = array(); // URLs requested.
$GLOBALS['t_admin']      = false;
$GLOBALS['t_enqueued']   = array();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $v ) {
	return $v instanceof WP_Error;
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['t_options'] ) ? $GLOBALS['t_options'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['t_options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['t_options'][ $k ] );
	return true;
}
function get_transient( $k ) {
	if ( ! isset( $GLOBALS['t_transients'][ $k ] ) ) {
		return false;
	}
	list( $v, $exp ) = $GLOBALS['t_transients'][ $k ];
	return $exp && $exp < time() ? false : $v;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['t_transients'][ $k ] = array( $v, $ttl ? time() + $ttl : 0 );
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['t_transients'][ $k ] );
	return true;
}
function t_expire_fresh() {
	foreach ( $GLOBALS['t_transients'] as $k => $v ) {
		if ( preg_match( '/^satelit_\d+_/', $k ) ) {
			unset( $GLOBALS['t_transients'][ $k ] );
		}
	}
}
function apply_filters( $tag, $v ) {
	return $v;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function get_locale() {
	return 'ro_RO';
}
function get_bloginfo( $k ) {
	return '6.8';
}
function add_query_arg( $args, $url ) {
	$pairs = array();
	foreach ( $args as $k => $v ) {
		$pairs[] = $k . '=' . $v;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
}
function t_fixture( $name, $status = 200 ) {
	return array(
		'status'  => $status,
		'body'    => file_get_contents( __DIR__ . '/fixtures/' . $name . '.json' ),
		'headers' => array( 'x-credits-remaining' => '299000', 'x-credits-limit' => '300000' ),
	);
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['t_calls'][] = $url;
	if ( getenv( 'SATELIT_LIVE' ) ) {
		$ctx  = stream_context_create(
			array(
				'http' => array(
					'timeout'       => $args['timeout'],
					'ignore_errors' => true,
					'header'        => 'Authorization: ' . $args['headers']['Authorization'] . "\r\nAccept: application/json\r\nUser-Agent: " . $args['user-agent'] . "\r\n",
				),
			)
		);
		$body = @file_get_contents( $url, false, $ctx );
		if ( false === $body ) {
			return new WP_Error( 'http_request_failed', 'network' );
		}
		$status  = 0;
		$headers = array();
		foreach ( $http_response_header as $h ) {
			if ( preg_match( '#^HTTP/\S+ (\d+)#', $h, $m ) ) {
				$status = (int) $m[1];
			} elseif ( false !== strpos( $h, ':' ) ) {
				list( $hk, $hv ) = explode( ':', $h, 2 );
				$headers[ strtolower( trim( $hk ) ) ] = trim( $hv );
			}
		}
		return array( 'status' => $status, 'body' => $body, 'headers' => $headers );
	}
	if ( ! $GLOBALS['t_http'] ) {
		return new WP_Error( 'http_request_failed', 'no canned answer for ' . $url );
	}
	return array_shift( $GLOBALS['t_http'] );
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['status'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function wp_remote_retrieve_header( $r, $h ) {
	return isset( $r['headers'][ $h ] ) ? $r['headers'][ $h ] : '';
}
function __( $s, $d = null ) {
	return $s;
}
function _n( $a, $b, $n, $d = null ) {
	return 1 === $n ? $a : $b;
}
function esc_html__( $s, $d = null ) {
	return esc_html( $s );
}
function esc_attr__( $s, $d = null ) {
	return esc_attr( $s );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return preg_match( '#^https?://#', (string) $s ) ? htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ) : '';
}
function wp_kses( $s, $allowed ) {
	return $s;
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function absint( $v ) {
	return abs( (int) $v );
}
function number_format_i18n( $n, $d = 0 ) {
	return number_format( (float) $n, $d, ',', ' ' );
}
function date_i18n( $f, $ts ) {
	return gmdate( $f, $ts );
}
function wp_json_encode( $v ) {
	return json_encode( $v );
}
function selected( $a, $b, $echo = true ) {
	return (string) $a === (string) $b ? ' selected="selected"' : '';
}
function wp_list_pluck( $list, $f ) {
	return array_map(
		function ( $x ) use ( $f ) {
			return $x[ $f ];
		},
		$list
	);
}
function shortcode_atts( $pairs, $atts, $sc = '' ) {
	$out = array();
	foreach ( $pairs as $k => $v ) {
		$out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v;
	}
	return $out;
}
function current_user_can( $c ) {
	return $GLOBALS['t_admin'];
}
function wp_enqueue_style( $h ) {
	$GLOBALS['t_enqueued'][] = $h;
}
function wp_enqueue_script( $h ) {
	$GLOBALS['t_enqueued'][] = $h;
}
function admin_url( $p = '' ) {
	return 'https://example.test/wp-admin/' . $p;
}
function is_admin() {
	return false;
}

class T_Wpdb {
	public $options = 'wp_options';
	public $queries = array();
	public function esc_like( $s ) {
		return addcslashes( $s, '_%\\' );
	}
	public function prepare( $q ) {
		return $q;
	}
	public function query( $q ) {
		$this->queries[] = $q;
		foreach ( array_keys( $GLOBALS['t_transients'] ) as $k ) {
			if ( 0 === strpos( $k, 'satelit_' ) ) {
				unset( $GLOBALS['t_transients'][ $k ] );
			}
		}
		return 1;
	}
}
$GLOBALS['wpdb'] = new T_Wpdb();

require __DIR__ . '/../includes/class-satelit-api.php';
require __DIR__ . '/../includes/class-satelit-features.php';
require __DIR__ . '/../includes/class-satelit-render.php';
require __DIR__ . '/../includes/class-satelit-woocommerce.php';
