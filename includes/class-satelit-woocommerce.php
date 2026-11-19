<?php
/**
 * WooCommerce: a "Prices in other shops" product tab and the price in other
 * currencies at today's BNM rate. Hooks in only when WooCommerce is active.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration.
 */
class Satelit_WooCommerce {

	const META_Q    = '_satelit_md_query';
	const META_ITEM = '_satelit_md_item';

	/**
	 * Hooks in after all plugins are loaded.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_hook' ) );
	}

	/**
	 * Adds the hooks when WooCommerce is there.
	 */
	public static function maybe_hook() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tab' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'fx' ), 20, 2 );
	}

	/**
	 * Side box on the product screen.
	 */
	public static function meta_box() {
		add_meta_box( 'satelit_md', __( 'Satelit: prices in other shops', 'satelit-moldova-data' ), array( __CLASS__, 'meta_box_html' ), 'product', 'side' );
	}

	/**
	 * Box fields.
	 *
	 * @param WP_Post $post Product post.
	 */
	public static function meta_box_html( $post ) {
		wp_nonce_field( 'satelit_md_product', 'satelit_md_product_nonce' );
		?>
		<p>
			<label for="satelit_md_query"><b><?php esc_html_e( 'Search words', 'satelit-moldova-data' ); ?></b></label>
			<input type="text" class="widefat" id="satelit_md_query" name="satelit_md_query" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_Q, true ) ); ?>" placeholder="lapte 2.5% 1 l">
		</p>
		<p>
			<label for="satelit_md_item"><b><?php esc_html_e( 'or Satelit item id', 'satelit-moldova-data' ); ?></b></label>
			<input type="number" min="1" class="widefat" id="satelit_md_item" name="satelit_md_item" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ITEM, true ) ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'The item id shows every shop’s offer for that exact product; the search words show a comparison. Empty: no tab.', 'satelit-moldova-data' ); ?></p>
		<?php
	}

	/**
	 * Saves the box.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		unset( $post );
		if ( ! isset( $_POST['satelit_md_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['satelit_md_product_nonce'] ) ), 'satelit_md_product' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$q    = isset( $_POST['satelit_md_query'] ) ? sanitize_text_field( wp_unslash( $_POST['satelit_md_query'] ) ) : '';
		$item = isset( $_POST['satelit_md_item'] ) ? absint( $_POST['satelit_md_item'] ) : 0;
		update_post_meta( $post_id, self::META_Q, $q );
		update_post_meta( $post_id, self::META_ITEM, $item ? $item : '' );
	}

	/**
	 * Adds the tab when the product has a search term or an item id.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		global $product;
		$id = $product && is_object( $product ) ? $product->get_id() : get_the_ID();
		if ( ! $id || ( '' === (string) get_post_meta( $id, self::META_Q, true ) && ! get_post_meta( $id, self::META_ITEM, true ) ) ) {
			return $tabs;
		}
		$tabs['satelit_prices'] = array(
			'title'    => __( 'Prices in other shops', 'satelit-moldova-data' ),
			'priority' => 35,
			'callback' => array( __CLASS__, 'tab_html' ),
		);
		return $tabs;
	}

	/**
	 * Tab content.
	 */
	public static function tab_html() {
		$id   = get_the_ID();
		$item = absint( get_post_meta( $id, self::META_ITEM, true ) );
		$html = $item
			? Satelit_Features::render( 'product', array( 'id' => (string) $item ) )
			: Satelit_Features::render(
				'prices',
				array(
					'q'     => (string) get_post_meta( $id, self::META_Q, true ),
					'limit' => '8',
				)
			);
		if ( '' === $html ) {
			$html = '<p>' . esc_html__( 'No offers to show right now.', 'satelit-moldova-data' ) . '</p>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Satelit_Render.
	}

	/**
	 * "≈ 12.34 EUR · 13.45 USD" under the price on single product pages.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function fx( $html, $product ) {
		$s = Satelit_Api::settings();
		if ( empty( $s['woo_fx'] ) || is_admin() || ! function_exists( 'is_product' ) || ! is_product() || '' === Satelit_Api::key() ) {
			return $html;
		}
		$price = (float) wc_get_price_to_display( $product );
		if ( $price <= 0 ) {
			return $html;
		}
		$line = self::fx_line( $price, get_woocommerce_currency(), $s['woo_fx_codes'] );
		return '' === $line ? $html : $html . $line;
	}

	/**
	 * The converted line for a price, or ''.
	 *
	 * @param float  $price    Price in the shop currency.
	 * @param string $currency Shop currency code.
	 * @param string $codes    Target codes, comma separated.
	 * @return string
	 */
	public static function fx_line( $price, $currency, $codes ) {
		$map = Satelit_Render::rates_map();
		if ( is_wp_error( $map ) || ! $map ) {
			return '';
		}
		$map['MDL'] = 1.0;
		if ( ! isset( $map[ $currency ] ) ) {
			return '';
		}
		$mdl   = $price * $map[ $currency ];
		$parts = array();
		foreach ( explode( ',', strtoupper( $codes ) ) as $c ) {
			$c = trim( $c );
			if ( '' === $c || $c === $currency || ! isset( $map[ $c ] ) ) {
				continue;
			}
			$parts[] = number_format_i18n( $mdl / $map[ $c ], 2 ) . ' ' . $c;
		}
		if ( ! $parts ) {
			return '';
		}
		wp_enqueue_style( 'satelit-md' );
		$rates = Satelit_Api::get( '/rates', array(), 'rates' );
		$attr  = ! is_wp_error( $rates ) && ! empty( $rates['meta']['attribution'] ) ? $rates['meta']['attribution'] : 'BNM';
		return '<span class="satelit-fx" title="' . esc_attr( $attr ) . '">≈ ' . esc_html( implode( ' · ', $parts ) ) . ' <small class="satelit-muted">' . esc_html( $attr ) . '</small></span>';
	}
}
