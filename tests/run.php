<?php
/**
 * Offline tests, no network, no WordPress: php tests/run.php
 * Live smoke test (spends a few credits): SATELIT_API_KEY=… php tests/run.php --live
 *
 * @package SatelitMoldovaData
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

$t_pass = 0;
$t_fail = 0;

function check( $name, $cond ) {
	global $t_pass, $t_fail;
	if ( $cond ) {
		++$t_pass;
		echo "ok   $name\n";
	} else {
		++$t_fail;
		echo "FAIL $name\n";
	}
}

function reset_state( $key = 'sat_live_test' ) {
	$GLOBALS['t_options']    = array( 'satelit_md_api_key' => $key, 'date_format' => 'd.m.Y', 'time_format' => 'H:i', 'gmt_offset' => 2 );
	$GLOBALS['t_transients'] = array();
	$GLOBALS['t_http']       = array();
	$GLOBALS['t_calls']      = array();
	$GLOBALS['t_admin']      = false;
}

if ( in_array( '--live', $argv, true ) ) {
	$key = getenv( 'SATELIT_API_KEY' );
	if ( ! $key ) {
		echo "skip live: SATELIT_API_KEY is not set\n";
		exit( 0 );
	}
	putenv( 'SATELIT_LIVE=1' );
	reset_state( $key );
	$res = Satelit_Api::get( '/rates', array(), 'rates' );
	check( 'live /rates answers', ! is_wp_error( $res ) && ! empty( $res['data'] ) );
	$html = Satelit_Features::render( 'rates', array( 'codes' => 'EUR' ) );
	check( 'live rates table has EUR', false !== strpos( $html, '>EUR<' ) );
	$fuel = Satelit_Features::render( 'fuel', array() );
	check( 'live fuel has the ANRE cap', false !== strpos( $fuel, 'ANRE' ) );
	echo "\n$t_pass passed, $t_fail failed\n";
	exit( $t_fail ? 1 : 0 );
}

// Rates: table, arrows, attribution, cache.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'rates' );
$html = Satelit_Features::render( 'rates', array() );
check( 'rates shows EUR 20,1411', false !== strpos( $html, '20,1411' ) );
check( 'rates: USD +1,5% is up', (bool) preg_match( '/satelit-up">[^<]*<span aria-hidden="true">▲<\/span> \+1,5%/u', $html ) );
check( 'rates: EUR -0,1% is down', false !== strpos( $html, 'satelit-down' ) );
check( 'rates carries the BNM attribution', false !== strpos( $html, 'Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0' ) );
check( 'rates carries the Satelit link', false !== strpos( $html, 'href="https://satelit.md"' ) );
check( 'request has lang=ro', false !== strpos( $GLOBALS['t_calls'][0], 'lang=ro' ) );
$html2 = Satelit_Features::render( 'rates', array( 'codes' => 'usd' ) );
check( 'second render comes from the cache (1 HTTP call)', 1 === count( $GLOBALS['t_calls'] ) );
check( 'codes filter keeps USD only', false !== strpos( $html2, '>USD<' ) && false === strpos( $html2, '>EUR<' ) );
check( 'credits header remembered', 299000 === $GLOBALS['t_options']['satelit_md_credits']['remaining'] );

// Stale copy when the API fails.
t_expire_fresh();
$GLOBALS['t_http'][] = new WP_Error( 'http_request_failed', 'cURL error 28: timeout' );
$html = Satelit_Features::render( 'rates', array() );
check( 'API down: stale copy served', false !== strpos( $html, '20,1411' ) && false !== strpos( $html, 'saved copy' ) );
check( 'API down: error remembered', 'satelit_unreachable' === $GLOBALS['t_options']['satelit_md_last_error']['code'] );
$before = count( $GLOBALS['t_calls'] );
Satelit_Features::render( 'rates', array() );
check( 'API down: backoff, no retry on the next view', count( $GLOBALS['t_calls'] ) === $before );

// API down, nothing cached: nothing for visitors, one line for admins.
reset_state();
$GLOBALS['t_http'][] = new WP_Error( 'http_request_failed', 'Could not resolve host' );
check( 'API down, no cache: visitor sees nothing', '' === Satelit_Features::render( 'fuel', array() ) );
Satelit_Features::render( 'news', array() );
Satelit_Features::render( 'joke', array() );
check( 'API down: one call, then every route pauses (no 8 s wait per block)', 1 === count( $GLOBALS['t_calls'] ) );
check( 'API down: pause lasts 2 minutes', isset( $GLOBALS['t_transients']['satelit_md_down'] ) && $GLOBALS['t_transients']['satelit_md_down'][1] - time() <= 120 );

// 404: remembered per route, the rest keeps working.
reset_state();
$GLOBALS['t_http'][] = array( 'status' => 404, 'body' => '{"error":{"code":"not_found","message":"Not found."}}', 'headers' => array() );
check( '404: nothing rendered', '' === Satelit_Features::render( 'product', array( 'id' => '999999999' ) ) );
check( '404: remembered, no second call', '' === Satelit_Features::render( 'product', array( 'id' => '999999999' ) ) && 1 === count( $GLOBALS['t_calls'] ) );
check( '404: does not pause other routes', ! isset( $GLOBALS['t_transients']['satelit_md_down'] ) );

// 429 rate_limited: pause for Retry-After.
reset_state();
$GLOBALS['t_http'][] = array( 'status' => 429, 'body' => '{"error":{"code":"rate_limited","message":"Slow down."}}', 'headers' => array( 'retry-after' => '30' ) );
Satelit_Features::render( 'rates', array() );
check( '429: pause follows Retry-After', isset( $GLOBALS['t_transients']['satelit_md_down'] ) && $GLOBALS['t_transients']['satelit_md_down'][1] - time() <= 30 );

// Bad key.
reset_state( 'sat_live_bad' );
$GLOBALS['t_http'][] = t_fixture( 'error-401', 401 );
check( 'bad key: visitor sees nothing', '' === Satelit_Features::render( 'rates', array() ) );
check( 'bad key: 401 remembered for the admin notice', 401 === $GLOBALS['t_options']['satelit_md_last_error']['status'] );
$GLOBALS['t_admin']  = true;
$note = Satelit_Features::render( 'joke', array() );
check( 'bad key: no new call while the key is refused', 1 === count( $GLOBALS['t_calls'] ) );
check( 'bad key: admin sees a short note', false !== strpos( $note, 'satelit-admin-note' ) && false !== strpos( $note, 'not valid' ) );
check( 'bad key never printed', false === strpos( $note, 'sat_live_bad' ) );

// No key: no HTTP at all.
reset_state( '' );
check( 'no key: nothing rendered', '' === Satelit_Features::render( 'rates', array() ) );
check( 'no key: no HTTP call', 0 === count( $GLOBALS['t_calls'] ) );

// Converter.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'rates' );
$html = Satelit_Features::render( 'converter', array( 'codes' => 'EUR,USD', 'amount' => '10' ) );
check( 'converter embeds rates', false !== strpos( $html, 'data-satelit-rates="{&quot;MDL&quot;:1' ) && false !== strpos( $html, '&quot;EUR&quot;:20.1411' ) );
check( 'converter server-side result 10 EUR = 201,41 MDL', false !== strpos( $html, '201,41 MDL' ) );
check( 'converter enqueues its script', in_array( 'satelit-md-converter', $GLOBALS['t_enqueued'], true ) );

// Fuel.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'fuel' );
$html = Satelit_Features::render( 'fuel', array( 'limit' => '3' ) );
check( 'fuel: ANRE cap 28,08', false !== strpos( $html, 'ANRE cap' ) && false !== strpos( $html, '28,08' ) );
check( 'fuel: cheapest network named', false !== strpos( $html, 'A95: TLX 27,90' ) );
check( 'fuel: limit 3 rows, cheapest first', 3 === substr_count( $html, '<tr><th scope="row">' ) && strpos( $html, '>TLX<' ) < strpos( $html, '>Vento<' ) );
check( 'fuel: ANRE source link', false !== strpos( $html, 'href="https://anre.md"' ) );

// Prices and product.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'prices-search' );
$html = Satelit_Features::render( 'prices', array( 'q' => 'lapte', 'limit' => '2' ) );
check( 'prices: first product and min price', false !== strpos( $html, 'Lapte Casuta Mea 2.5% 1 l' ) && false !== strpos( $html, '15,99' ) );
check( 'prices: limit 2', 2 === substr_count( $html, '<tr><th scope="row">' ) );
check( 'prices: q sent encoded', false !== strpos( $GLOBALS['t_calls'][0], 'q=lapte' ) );
check( 'prices: too short q, no call', '' === Satelit_Features::render( 'prices', array( 'q' => 'l' ) ) && 1 === count( $GLOBALS['t_calls'] ) );
$GLOBALS['t_http'][] = t_fixture( 'product-217' );
$html = Satelit_Features::render( 'product', array( 'id' => '217' ) );
check( 'product: shop names with offers', false !== strpos( $html, 'MegaAlina' ) && false !== strpos( $html, '16,99' ) && false !== strpos( $html, 'Linella' ) );
check( 'product: bad id, no call', '' === Satelit_Features::render( 'product', array( 'id' => 'x' ) ) );

// News, law, joke.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'news' );
$html = Satelit_Features::render( 'news', array( 'limit' => '3', 'category' => 'Economy' ) );
check( 'news: title and source link', false !== strpos( $html, 'Guvernul a aprobat bugetul pe 2027' ) && false !== strpos( $html, 'href="https://tv8.md/' ) );
check( 'news: category and per sent', false !== strpos( $GLOBALS['t_calls'][0], 'category=economy' ) && false !== strpos( $GLOBALS['t_calls'][0], 'per=3' ) );
$GLOBALS['t_http'][] = t_fixture( 'law-digests' );
$html = Satelit_Features::render( 'law', array() );
check( 'law: digest with acts count', false !== strpos( $html, 'Noutăți legislative: 19 noiembrie 2026' ) && false !== strpos( $html, '28 acts' ) );
$GLOBALS['t_http'][] = t_fixture( 'joke' );
$html = Satelit_Features::render( 'joke', array() );
check( 'joke: text with line breaks and source', false !== strpos( $html, '<br />' ) && false !== strpos( $html, 'Râzi cu lacrimi' ) );

// Cinema and movie.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'cinemas-today' );
$html = Satelit_Features::render( 'cinema', array( 'city' => 'Chișinău' ) );
check( 'cinema: cinema, film and a session time', false !== strpos( $html, 'Cineplex Mall' ) && false !== strpos( $html, 'Badanta' ) && false !== strpos( $html, '>10:10<' ) );
check( 'cinema: poster from api.satelit.md', false !== strpos( $html, 'src="https://api.satelit.md/v1/movies/img/s/' ) );
check( 'cinema: city sent', false !== strpos( $GLOBALS['t_calls'][0], 'city=Chi%C8%99in%C4%83u' ) );
$GLOBALS['t_http'][] = t_fixture( 'movie-m603' );
$html = Satelit_Features::render( 'movie', array( 'id' => 'm603' ) );
check( 'movie: card with title, director, cast', false !== strpos( $html, 'Matrix' ) && false !== strpos( $html, 'Lana Wachowski' ) && false !== strpos( $html, 'Keanu Reeves' ) );
check( 'movie: bad id, no call', '' === Satelit_Features::render( 'movie', array( 'id' => '../me' ) ) && 2 === count( $GLOBALS['t_calls'] ) );

// Movie TTL never below 24 h, news never below 10 min.
$GLOBALS['t_options']['satelit_md_settings'] = array( 'ttl' => array( 'movie' => 5, 'news' => 1 ) );
check( 'ttl floors protect credits', DAY_IN_SECONDS === Satelit_Api::ttl( 'movie' ) && 600 === Satelit_Api::ttl( 'news' ) );

// WooCommerce line.
reset_state();
$GLOBALS['t_http'][] = t_fixture( 'rates' );
$line = Satelit_WooCommerce::fx_line( 100, 'MDL', 'EUR,USD' );
check( 'woo fx: 100 MDL = 4,96 EUR · 5,44 USD', false !== strpos( $line, '4,96 EUR · 5,44 USD' ) );
check( 'woo fx: BNM attribution', false !== strpos( $line, 'bnm.md' ) );

// Clear cache.
Satelit_Api::clear_cache();
check( 'clear cache bumps version and empties transients', 2 === get_option( 'satelit_md_cache_ver' ) && ! $GLOBALS['t_transients'] );

echo "\n$t_pass passed, $t_fail failed\n";
exit( $t_fail ? 1 : 0 );
