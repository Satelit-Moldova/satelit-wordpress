<?php
/**
 * Settings → Satelit: key, language, cache lifetimes, test connection, clear cache.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page.
 */
class Satelit_Settings {

	const PAGE = 'satelit';

	/**
	 * Hooks in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'wp_ajax_satelit_md_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'admin_post_satelit_md_clear', array( __CLASS__, 'clear_cache' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Adds the page under Settings.
	 */
	public static function menu() {
		add_options_page(
			__( 'Satelit — Moldova Data', 'satelit-moldova-data' ),
			__( 'Satelit', 'satelit-moldova-data' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Registers the two options (the key is its own, not autoloaded option).
	 */
	public static function register() {
		register_setting(
			'satelit_md',
			Satelit_Api::OPT_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_key' ),
				'default'           => '',
			)
		);
		register_setting(
			'satelit_md',
			Satelit_Api::OPT_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Keeps the saved key when the field is left empty; removes it on request.
	 *
	 * @param string $value Submitted key.
	 * @return string
	 */
	public static function sanitize_key( $value ) {
		$old = (string) get_option( Satelit_Api::OPT_KEY, '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php checked the nonce before sanitizing.
		if ( ! empty( $_POST['satelit_md_remove_key'] ) ) {
			$value = '';
		} else {
			$value = trim( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ) );
			if ( '' === $value ) {
				return $old;
			}
		}
		if ( $value !== $old ) {
			delete_option( Satelit_Api::OPT_ERROR );
			delete_option( Satelit_Api::OPT_CREDITS );
			Satelit_Api::clear_cache();
		}
		return $value;
	}

	/**
	 * Cleans the settings array.
	 *
	 * @param array $value Submitted settings.
	 * @return array
	 */
	public static function sanitize_settings( $value ) {
		$value       = is_array( $value ) ? $value : array();
		$out         = array();
		$lang        = isset( $value['lang'] ) ? $value['lang'] : 'auto';
		$out['lang'] = in_array( $lang, array( 'auto', 'ro', 'ru', 'en', 'uk' ), true ) ? $lang : 'auto';
		$out['ttl']  = array();
		foreach ( Satelit_Api::$ttl_defaults as $group => $def ) {
			$v                    = isset( $value['ttl'][ $group ] ) ? absint( $value['ttl'][ $group ] ) : $def;
			$out['ttl'][ $group ] = max( Satelit_Api::$ttl_min[ $group ], min( $v, 10080 ) );
		}
		$out['woo_fx']       = ! empty( $value['woo_fx'] ) ? 1 : 0;
		$codes               = isset( $value['woo_fx_codes'] ) ? strtoupper( preg_replace( '/[^A-Za-z,]/', '', $value['woo_fx_codes'] ) ) : 'EUR,USD';
		$out['woo_fx_codes'] = '' === $codes ? 'EUR,USD' : $codes;
		return $out;
	}

	/**
	 * Admin script for the "Test connection" button (settings page only).
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_script( 'satelit-md-admin', SATELIT_MD_URL . 'assets/js/admin.js', array(), SATELIT_MD_VERSION, true );
		wp_localize_script(
			'satelit-md-admin',
			'satelitMdAdmin',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'satelit_md_test' ),
				'testing' => __( 'Checking…', 'satelit-moldova-data' ),
				'failed'  => __( 'The request failed.', 'satelit-moldova-data' ),
			)
		);
	}

	/**
	 * Admin-ajax: GET /v1/me with the saved key, uncached. Answers plan and credits only.
	 */
	public static function ajax_test() {
		check_ajax_referer( 'satelit_md_test', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'satelit-moldova-data' ) ), 403 );
		}
		if ( '' === Satelit_Api::key() ) {
			wp_send_json_error( array( 'message' => __( 'No Satelit API key is set.', 'satelit-moldova-data' ) ) );
		}
		$res = Satelit_Api::request( '/me' );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		delete_transient( Satelit_Api::DOWN );
		$d     = $res['data'];
		$limit = isset( $d['credits']['limit'] ) ? (int) $d['credits']['limit'] : 0;
		$used  = isset( $d['credits']['used'] ) ? (int) $d['credits']['used'] : 0;
		$plan  = ! empty( $d['plan'] ) ? $d['plan'] : __( 'none', 'satelit-moldova-data' );
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: plan name, 2: credits left, 3: credits in the period, 4: reset date. */
					__( 'Connected. Plan: %1$s. Credits left: %2$s of %3$s (renews %4$s).', 'satelit-moldova-data' ),
					$plan,
					number_format_i18n( max( 0, $limit - $used ) ),
					number_format_i18n( $limit ),
					isset( $d['credits']['reset'] ) ? mysql2date( get_option( 'date_format' ), substr( $d['credits']['reset'], 0, 10 ) ) : '—'
				),
				'plan'    => $plan,
				'left'    => max( 0, $limit - $used ),
			)
		);
	}

	/**
	 * Clears the cache (admin-post), then goes back to the page.
	 */
	public static function clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'satelit-moldova-data' ), 403 );
		}
		check_admin_referer( 'satelit_md_clear' );
		Satelit_Api::clear_cache();
		wp_safe_redirect( add_query_arg( 'satelit_cleared', '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Admin notices: key/plan errors everywhere in admin, the rest on our page.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_page = $screen && 'settings_page_' . self::PAGE === $screen->id;
		$err     = get_option( Satelit_Api::OPT_ERROR );
		if ( is_array( $err ) && ! empty( $err['status'] ) && in_array( (int) $err['status'], array( 401, 402, 403 ), true ) ) {
			printf(
				'<div class="notice notice-error"><p><b>%1$s</b> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'Satelit:', 'satelit-moldova-data' ),
				esc_html( $err['message'] . ' (' . $err['status'] . ')' ),
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Check the API key', 'satelit-moldova-data' )
			);
		} elseif ( $on_page && is_array( $err ) ) {
			printf(
				'<div class="notice notice-warning"><p><b>%1$s</b> %2$s</p></div>',
				esc_html__( 'Satelit:', 'satelit-moldova-data' ),
				esc_html(
					sprintf(
						/* translators: 1: route, 2: error message. */
						__( 'the last call to %1$s failed (%2$s). Pages show the saved copy when there is one.', 'satelit-moldova-data' ),
						$err['path'],
						$err['message']
					)
				)
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( $on_page && isset( $_GET['satelit_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Satelit cache cleared.', 'satelit-moldova-data' ) . '</p></div>';
		}
	}

	/**
	 * The page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = Satelit_Api::settings();
		$has_key = '' !== Satelit_Api::key();
		$credits = get_option( Satelit_Api::OPT_CREDITS );
		$groups  = array(
			'rates'  => __( 'Exchange rates and converter', 'satelit-moldova-data' ),
			'fuel'   => __( 'Fuel prices', 'satelit-moldova-data' ),
			'prices' => __( 'Shop prices and offers', 'satelit-moldova-data' ),
			'news'   => __( 'News', 'satelit-moldova-data' ),
			'cinema' => __( 'Cinema schedule', 'satelit-moldova-data' ),
			'movie'  => __( 'Film cards', 'satelit-moldova-data' ),
			'law'    => __( 'Monitorul Oficial', 'satelit-moldova-data' ),
			'joke'   => __( 'Joke of the day', 'satelit-moldova-data' ),
		);
		$langs   = array(
			'auto' => __( 'Site language', 'satelit-moldova-data' ),
			'ro'   => 'Română',
			'ru'   => 'Русский',
			'en'   => 'English',
			'uk'   => 'Українська',
		);
		$opt     = Satelit_Api::OPT_SETTINGS;
		?>
		<div class="wrap satelit-settings">
			<h1><?php esc_html_e( 'Satelit — Moldova Data', 'satelit-moldova-data' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'satelit_md' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="satelit_md_api_key"><?php esc_html_e( 'API key', 'satelit-moldova-data' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="satelit_md_api_key" name="<?php echo esc_attr( Satelit_Api::OPT_KEY ); ?>" value="" autocomplete="off" spellcheck="false" placeholder="<?php echo $has_key ? esc_attr__( 'A key is saved. Type a new one to replace it.', 'satelit-moldova-data' ) : 'sat_live_…'; ?>">
							<?php if ( $has_key ) : ?>
								<label><input type="checkbox" name="satelit_md_remove_key" value="1"> <?php esc_html_e( 'Remove the saved key', 'satelit-moldova-data' ); ?></label>
							<?php endif; ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to the Satelit console. */
									esc_html__( 'Create a key in the %s. It stays on this server and is never sent to visitors.', 'satelit-moldova-data' ),
									'<a href="https://developer.satelit.md/en/console/" target="_blank" rel="noopener">' . esc_html__( 'Satelit console', 'satelit-moldova-data' ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="satelit_md_lang"><?php esc_html_e( 'Data language', 'satelit-moldova-data' ); ?></label></th>
						<td>
							<select id="satelit_md_lang" name="<?php echo esc_attr( $opt ); ?>[lang]">
								<?php foreach ( $langs as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['lang'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache, minutes', 'satelit-moldova-data' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $groups as $g => $label ) : ?>
									<label class="satelit-ttl">
										<input type="number" class="small-text" min="<?php echo esc_attr( Satelit_Api::$ttl_min[ $g ] ); ?>" max="10080" name="<?php echo esc_attr( $opt ); ?>[ttl][<?php echo esc_attr( $g ); ?>]" value="<?php echo esc_attr( (int) $s['ttl'][ $g ] ); ?>">
										<?php echo esc_html( $label ); ?>
										<span class="description">
										<?php
										/* translators: %d: minutes. */
										echo esc_html( sprintf( __( '(at least %d)', 'satelit-moldova-data' ), Satelit_Api::$ttl_min[ $g ] ) );
										?>
										</span>
									</label><br>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WooCommerce', 'satelit-moldova-data' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[woo_fx]" value="1" <?php checked( $s['woo_fx'] ); ?>> <?php esc_html_e( 'Show the product price in other currencies at today’s BNM rate', 'satelit-moldova-data' ); ?></label>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[woo_fx_codes]" value="<?php echo esc_attr( $s['woo_fx_codes'] ); ?>" aria-label="<?php esc_attr_e( 'Currency codes, comma separated', 'satelit-moldova-data' ); ?>"></p>
							<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
								<p class="description"><?php esc_html_e( 'WooCommerce is not active: these options apply once it is.', 'satelit-moldova-data' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Connection', 'satelit-moldova-data' ); ?></h2>
			<p>
				<button type="button" class="button" id="satelit-md-test" <?php disabled( ! $has_key ); ?>><?php esc_html_e( 'Test connection', 'satelit-moldova-data' ); ?></button>
				<span id="satelit-md-test-result" aria-live="polite"></span>
			</p>
			<?php if ( is_array( $credits ) ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: credits left, 2: credits in the period, 3: date and time. */
							__( 'Last answer: %1$s of %2$s credits left (%3$s).', 'satelit-moldova-data' ),
							number_format_i18n( $credits['remaining'] ),
							number_format_i18n( $credits['limit'] ),
							date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $credits['time'] + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="satelit_md_clear">
				<?php wp_nonce_field( 'satelit_md_clear' ); ?>
				<?php submit_button( __( 'Clear cache', 'satelit-moldova-data' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Credits', 'satelit-moldova-data' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to the pricing page. */
					esc_html__( 'Each answer from the API costs credits from your plan: most routes 1-5, cinema schedules and film cards 40. Answers are cached for the minutes above and every visitor gets the cached copy, so a page costs credits only once per cache period. When the API cannot be reached, pages show the last saved copy. Plans: %s.', 'satelit-moldova-data' ),
					'<a href="https://developer.satelit.md/en/pricing/" target="_blank" rel="noopener">developer.satelit.md/en/pricing</a>'
				);
				?>
			</p>
			<p><?php esc_html_e( 'Shortcodes: [satelit_rates] [satelit_converter] [satelit_fuel] [satelit_prices q="lapte"] [satelit_product id="217"] [satelit_news] [satelit_cinema] [satelit_movie id="m603"] [satelit_law] [satelit_joke]. Every one is also a block (search "Satelit" in the block inserter).', 'satelit-moldova-data' ); ?></p>
		</div>
		<?php
	}
}
