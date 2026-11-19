<?php
/**
 * Classic widget: one widget, pick the feature (rates, converter, fuel, news, law, joke).
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Satelit classic widget.
 */
class Satelit_Widget extends WP_Widget {

	/**
	 * Features that fit a sidebar.
	 *
	 * @var array
	 */
	private static $fits = array( 'rates', 'converter', 'fuel', 'news', 'law', 'joke' );

	/**
	 * Fields the widget form offers.
	 *
	 * @var array
	 */
	private static $fields = array( 'codes', 'amount', 'limit', 'category', 'days' );

	/**
	 * Registers the widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'satelit_md',
			__( 'Satelit — Moldova Data', 'satelit-moldova-data' ),
			array(
				'description'                 => __( 'BNM rates, converter, fuel prices, news, Monitorul Oficial or the joke of the day.', 'satelit-moldova-data' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Front end.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Saved values.
	 */
	public function widget( $args, $instance ) {
		$feature = isset( $instance['feature'] ) && in_array( $instance['feature'], self::$fits, true ) ? $instance['feature'] : 'rates';
		$atts    = array();
		foreach ( self::$fields as $f ) {
			if ( isset( $instance[ $f ] ) && '' !== $instance[ $f ] ) {
				$atts[ $f ] = $instance[ $f ];
			}
		}
		$html = Satelit_Features::render( $feature, $atts );
		if ( '' === $html ) {
			return;
		}
		$title = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) : '';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sidebar markup comes from the theme; $html is escaped by Satelit_Render.
		echo $args['before_widget'];
		if ( '' !== $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		echo $html;
		echo $args['after_widget'];
		// phpcs:enable
	}

	/**
	 * Admin form.
	 *
	 * @param array $instance Saved values.
	 * @return string
	 */
	public function form( $instance ) {
		$labels  = Satelit_Features::labels();
		$flabels = Satelit_Features::field_labels();
		$feature = isset( $instance['feature'] ) ? $instance['feature'] : 'rates';
		$title   = isset( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'satelit-moldova-data' ); ?></label>
			<input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'feature' ) ); ?>"><?php esc_html_e( 'Show', 'satelit-moldova-data' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'feature' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'feature' ) ); ?>">
				<?php foreach ( self::$fits as $f ) : ?>
					<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $feature, $f ); ?>><?php echo esc_html( $labels[ $f ] ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php foreach ( self::$fields as $f ) : ?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( $f ) ); ?>"><?php echo esc_html( $flabels[ $f ] ); ?></label>
				<input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id( $f ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $f ) ); ?>" value="<?php echo esc_attr( isset( $instance[ $f ] ) ? $instance[ $f ] : '' ); ?>" placeholder="<?php esc_attr_e( 'default', 'satelit-moldova-data' ); ?>">
			</p>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Empty fields use the defaults. Fields a feature does not use are ignored.', 'satelit-moldova-data' ); ?></p>
		<?php
		return '';
	}

	/**
	 * Saves the form.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		unset( $old_instance );
		$out            = array();
		$out['title']   = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$out['feature'] = isset( $new_instance['feature'] ) && in_array( $new_instance['feature'], self::$fits, true ) ? $new_instance['feature'] : 'rates';
		foreach ( self::$fields as $f ) {
			$out[ $f ] = isset( $new_instance[ $f ] ) ? sanitize_text_field( $new_instance[ $f ] ) : '';
		}
		return $out;
	}
}
