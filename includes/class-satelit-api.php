<?php
/**
 * The one place that talks to the Satelit API.
 *
 * Every call: wp_remote_get, 8 s timeout, a transient for the fresh copy and a
 * longer-lived transient for the stale copy that is served when the API fails.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Satelit API client.
 */
class Satelit_Api {

	const DEFAULT_BASE = 'https://api.satelit.md/v1';
	const TIMEOUT      = 8;
	const STALE_TTL    = WEEK_IN_SECONDS;
	const BACKOFF_TTL  = 300;
	const OPT_KEY      = 'satelit_md_api_key';
	const OPT_SETTINGS = 'satelit_md_settings';
	const OPT_ERROR    = 'satelit_md_last_error';
	const OPT_CREDITS  = 'satelit_md_credits';
	const OPT_CACHE    = 'satelit_md_cache_ver';
	const DOWN         = 'satelit_md_down';

	/**
	 * Default cache lifetimes in minutes, per product.
	 *
	 * @var array
	 */
	public static $ttl_defaults = array(
		'rates'  => 180,
		'fuel'   => 120,
		'prices' => 180,
		'news'   => 15,
		'cinema' => 120,
		'movie'  => 1440,
		'law'    => 360,
		'joke'   => 360,
	);

	/**
	 * Lowest allowed lifetimes in minutes (protects the credits).
	 *
	 * @var array
	 */
	public static $ttl_min = array(
		'rates'  => 60,
		'fuel'   => 60,
		'prices' => 60,
		'news'   => 10,
		'cinema' => 60,
		'movie'  => 1440,
		'law'    => 60,
		'joke'   => 60,
	);

	/**
	 * API base URL. Override with the SATELIT_MD_API_BASE constant or the filter.
	 *
	 * @return string
	 */
	public static function base() {
		$base = defined( 'SATELIT_MD_API_BASE' ) ? SATELIT_MD_API_BASE : self::DEFAULT_BASE;
		return untrailingslashit( apply_filters( 'satelit_md_api_base', $base ) );
	}

	/**
	 * The stored key ('' when none). Never printed anywhere.
	 *
	 * @return string
	 */
	public static function key() {
		return (string) get_option( self::OPT_KEY, '' );
	}

	/**
	 * Plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPT_SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$ttl   = isset( $saved['ttl'] ) && is_array( $saved['ttl'] ) ? $saved['ttl'] : array();
		return array(
			'lang'         => isset( $saved['lang'] ) ? $saved['lang'] : 'auto',
			'ttl'          => array_merge( self::$ttl_defaults, $ttl ),
			'woo_fx'       => ! empty( $saved['woo_fx'] ),
			'woo_fx_codes' => isset( $saved['woo_fx_codes'] ) ? $saved['woo_fx_codes'] : 'EUR,USD',
		);
	}

	/**
	 * API language: the setting, or the site locale when set to auto.
	 *
	 * @return string One of ro, ru, en, uk.
	 */
	public static function lang() {
		$lang = self::settings()['lang'];
		if ( in_array( $lang, array( 'ro', 'ru', 'en', 'uk' ), true ) ) {
			return $lang;
		}
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$short  = strtolower( substr( (string) $locale, 0, 2 ) );
		return in_array( $short, array( 'ru', 'en', 'uk' ), true ) ? $short : 'ro';
	}

	/**
	 * Cache lifetime in seconds for a product group.
	 *
	 * @param string $group Product group (rates, fuel, ...).
	 * @return int
	 */
	public static function ttl( $group ) {
		$ttl = self::settings()['ttl'];
		$min = isset( self::$ttl_min[ $group ] ) ? self::$ttl_min[ $group ] : 10;
		$val = isset( $ttl[ $group ] ) ? (int) $ttl[ $group ] : 60;
		return max( $min, $val ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Cached GET. Returns the envelope array (data + meta) or a WP_Error.
	 *
	 * @param string $path  Route, e.g. '/rates'.
	 * @param array  $args  Query arguments.
	 * @param string $group Product group for the TTL.
	 * @return array|WP_Error
	 */
	public static function get( $path, $args = array(), $group = 'rates' ) {
		if ( '' === self::key() ) {
			return new WP_Error( 'satelit_no_key', __( 'No Satelit API key is set.', 'satelit-moldova-data' ) );
		}
		$args['lang'] = self::lang();
		ksort( $args );
		$id    = md5( $path . '?' . http_build_query( $args ) );
		$ver   = (int) get_option( self::OPT_CACHE, 1 );
		$fresh = 'satelit_' . $ver . '_' . $id;
		$stale = 'satelit_s' . $ver . '_' . $id;

		$hit = get_transient( $fresh );
		if ( is_array( $hit ) ) {
			// A remembered "not found / invalid" answer, so a wrong id costs one call, not one per view.
			if ( isset( $hit['__error'] ) ) {
				return new WP_Error( $hit['__error'], $hit['message'], array( 'status' => $hit['status'] ) );
			}
			return $hit;
		}

		// While the API is down or the key is refused, do not call it again on every view.
		$down = get_transient( self::DOWN );
		if ( is_array( $down ) ) {
			$res = new WP_Error( $down['code'], $down['message'], array( 'status' => $down['status'] ) );
		} else {
			$res = self::request( $path, $args );
			if ( ! is_wp_error( $res ) ) {
				set_transient( $fresh, $res, self::ttl( $group ) );
				set_transient( $stale, $res, self::STALE_TTL );
				return $res;
			}
			self::backoff( $res, $fresh );
		}

		// The API failed: serve the last good copy and do not retry for a few minutes.
		$old = get_transient( $stale );
		if ( is_array( $old ) ) {
			$old['meta']['stale'] = true;
			set_transient( $fresh, $old, self::BACKOFF_TTL );
			return $old;
		}
		return $res;
	}

	/**
	 * After a failed call: pause all calls (API down, rate limit, key refused)
	 * or remember a per-route "not found / invalid" answer.
	 *
	 * @param WP_Error $err   Error from request().
	 * @param string   $fresh Transient name of this route.
	 */
	private static function backoff( $err, $fresh ) {
		$data   = $err->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
		$entry  = array(
			'code'    => $err->get_error_code(),
			'message' => $err->get_error_message(),
			'status'  => $status,
		);
		if ( 404 === $status || 422 === $status ) {
			set_transient( $fresh, array( '__error' => $entry['code'] ) + $entry, 10 * MINUTE_IN_SECONDS );
			return;
		}
		if ( in_array( $status, array( 401, 402, 403 ), true ) ) {
			$ttl = 5 * MINUTE_IN_SECONDS;
		} elseif ( 429 === $status && ! empty( $data['retry_after'] ) ) {
			$ttl = min( 5 * MINUTE_IN_SECONDS, max( 10, (int) $data['retry_after'] ) );
		} else {
			$ttl = 2 * MINUTE_IN_SECONDS;
		}
		set_transient( self::DOWN, $entry, $ttl );
	}

	/**
	 * Uncached GET (used by "Test connection" and by get()).
	 *
	 * @param string $path Route.
	 * @param array  $args Query arguments.
	 * @param string $key  Key to use instead of the stored one.
	 * @return array|WP_Error
	 */
	public static function request( $path, $args = array(), $key = null ) {
		$key = null === $key ? self::key() : $key;
		$url = self::base() . $path;
		if ( $args ) {
			$url = add_query_arg( array_map( 'rawurlencode', $args ), $url );
		}
		$resp = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 2,
				'user-agent'  => 'satelit-moldova-data/' . SATELIT_MD_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				'headers'     => array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			$err = new WP_Error( 'satelit_unreachable', $resp->get_error_message(), array( 'status' => 0 ) );
			self::note_error( $path, $err );
			return $err;
		}

		$status = (int) wp_remote_retrieve_response_code( $resp );
		$body   = json_decode( wp_remote_retrieve_body( $resp ), true );

		$remaining = wp_remote_retrieve_header( $resp, 'x-credits-remaining' );
		if ( '' !== $remaining && null !== $remaining ) {
			update_option(
				self::OPT_CREDITS,
				array(
					'remaining' => (int) $remaining,
					'limit'     => (int) wp_remote_retrieve_header( $resp, 'x-credits-limit' ),
					'time'      => time(),
				),
				false
			);
		}

		if ( 200 === $status && is_array( $body ) && array_key_exists( 'data', $body ) ) {
			if ( ! isset( $body['meta'] ) || ! is_array( $body['meta'] ) ) {
				$body['meta'] = array();
			}
			if ( false !== get_option( self::OPT_ERROR ) ) {
				delete_option( self::OPT_ERROR );
			}
			return $body;
		}

		$code = isset( $body['error']['code'] ) ? (string) $body['error']['code'] : 'http_' . $status;
		$msg  = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'Unexpected answer from the API.', 'satelit-moldova-data' );
		$err  = new WP_Error(
			'satelit_' . $code,
			$msg,
			array(
				'status'      => $status,
				'retry_after' => (int) wp_remote_retrieve_header( $resp, 'retry-after' ),
			)
		);
		self::note_error( $path, $err );
		return $err;
	}

	/**
	 * True for errors an admin must fix (key, plan, account).
	 *
	 * @param WP_Error $err Error.
	 * @return bool
	 */
	public static function is_key_error( $err ) {
		$data   = $err->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;
		return in_array( $status, array( 401, 402, 403 ), true );
	}

	/**
	 * Remembers the last error for the admin notice and logs one short line.
	 *
	 * @param string   $path Route.
	 * @param WP_Error $err  Error.
	 */
	private static function note_error( $path, $err ) {
		$data = $err->get_error_data();
		update_option(
			self::OPT_ERROR,
			array(
				'path'    => $path,
				'code'    => $err->get_error_code(),
				'status'  => isset( $data['status'] ) ? (int) $data['status'] : 0,
				'message' => wp_strip_all_tags( $err->get_error_message() ),
				'time'    => time(),
			),
			true
		);
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- one short line, debug log only.
			error_log( 'Satelit: GET ' . $path . ' failed: ' . $err->get_error_code() );
		}
	}

	/**
	 * Drops every cached answer: bumps the cache version (works with object
	 * caches) and deletes the plugin's transients from the options table.
	 */
	public static function clear_cache() {
		global $wpdb;
		update_option( self::OPT_CACHE, (int) get_option( self::OPT_CACHE, 1 ) + 1, true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk delete of own transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_satelit_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_satelit_' ) . '%'
			)
		);
	}
}
