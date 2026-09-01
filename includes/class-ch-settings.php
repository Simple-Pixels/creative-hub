<?php
/**
 * Settings screen at Classes → Settings.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Settings {

	const OPTION = 'ch_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=ch_class',
			__( 'Creative Hub Settings', 'creative-hub' ),
			__( 'Settings', 'creative-hub' ),
			'manage_woocommerce',
			'ch-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			'ch_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $input ) {
		$valid_statuses = array_keys( wc_get_order_statuses() ); // e.g. wc-processing
		$valid_statuses = array_map(
			function ( $s ) {
				return preg_replace( '/^wc-/', '', $s );
			},
			$valid_statuses
		);

		$out = array();

		$out['access_statuses'] = array();
		if ( ! empty( $input['access_statuses'] ) && is_array( $input['access_statuses'] ) ) {
			foreach ( $input['access_statuses'] as $status ) {
				if ( in_array( $status, $valid_statuses, true ) ) {
					$out['access_statuses'][] = $status;
				}
			}
		}
		if ( empty( $out['access_statuses'] ) ) {
			$out['access_statuses'] = array( 'processing', 'completed' );
		}

		$out['auto_gate']       = empty( $input['auto_gate'] ) ? 0 : 1;
		$out['admin_preview']   = empty( $input['admin_preview'] ) ? 0 : 1;
		$out['buy_button_text'] = sanitize_text_field( $input['buy_button_text'] ?? '' );
		$out['locked_heading']  = sanitize_text_field( $input['locked_heading'] ?? '' );
		$out['locked_message']  = sanitize_textarea_field( $input['locked_message'] ?? '' );
		$out['hub_page_id']     = absint( $input['hub_page_id'] ?? 0 );
		$out['access_page_id']  = absint( $input['access_page_id'] ?? 0 );

		// Product/class links may have changed elsewhere; refresh the index defensively.
		ch_rebuild_product_class_index();

		return $out;
	}

	public static function render() {
		$s = get_option( self::OPTION, array() );
		$s = wp_parse_args(
			$s,
			array(
				'access_statuses' => array( 'processing', 'completed' ),
				'auto_gate'       => 1,
				'admin_preview'   => 1,
				'buy_button_text' => 'Get this class',
				'locked_heading'  => 'This class is waiting for you',
				'locked_message'  => '',
				'hub_page_id'     => 0,
				'access_page_id'  => 0,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Creative Hub Settings', 'creative-hub' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ch_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Grant access on these order statuses', 'creative-hub' ); ?></th>
						<td>
							<?php
							foreach ( wc_get_order_statuses() as $key => $label ) {
								$slug    = preg_replace( '/^wc-/', '', $key );
								$checked = in_array( $slug, (array) $s['access_statuses'], true );
								printf(
									'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%s[access_statuses][]" value="%s" %s> %s</label>',
									esc_attr( self::OPTION ),
									esc_attr( $slug ),
									checked( $checked, true, false ),
									esc_html( $label )
								);
							}
							?>
							<p class="description"><?php esc_html_e( 'Keep "Processing" ticked so buyers get access as soon as payment clears — even before a kit is posted.', 'creative-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-lock class pages', 'creative-hub' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_gate]" value="1" <?php checked( $s['auto_gate'], 1 ); ?>>
								<?php esc_html_e( 'Block visitors who have not bought the class', 'creative-hub' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Turn this off only if you build the locked/unlocked states yourself with the [ch_locked] and [ch_teaser] shortcodes.', 'creative-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ch_access_page_id"><?php esc_html_e( 'Access page', 'creative-hub' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( self::OPTION ) . '[access_page_id]',
									'id'                => 'ch_access_page_id',
									'selected'          => $s['access_page_id'],
									'show_option_none'  => __( '— Use the built-in teaser instead —', 'creative-hub' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Visitors without access are redirected here. Put the [ch_access_notice] shortcode on this page. The class they tried to open is passed in the URL so the buy links are correct.', 'creative-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Staff preview', 'creative-hub' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[admin_preview]" value="1" <?php checked( $s['admin_preview'], 1 ); ?>>
								<?php esc_html_e( 'Let shop managers and editors view any class without buying it', 'creative-hub' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ch_buy_button_text"><?php esc_html_e( 'Buy button text', 'creative-hub' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ch_buy_button_text" name="<?php echo esc_attr( self::OPTION ); ?>[buy_button_text]" value="<?php echo esc_attr( $s['buy_button_text'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ch_locked_heading"><?php esc_html_e( 'Teaser heading', 'creative-hub' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ch_locked_heading" name="<?php echo esc_attr( self::OPTION ); ?>[locked_heading]" value="<?php echo esc_attr( $s['locked_heading'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ch_locked_message"><?php esc_html_e( 'Teaser message', 'creative-hub' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="ch_locked_message" name="<?php echo esc_attr( self::OPTION ); ?>[locked_message]"><?php echo esc_textarea( $s['locked_message'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="ch_hub_page_id"><?php esc_html_e( 'Creative Hub page', 'creative-hub' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( self::OPTION ) . '[hub_page_id]',
									'id'                => 'ch_hub_page_id',
									'selected'          => $s['hub_page_id'],
									'show_option_none'  => __( '— Use the My Account tab only —', 'creative-hub' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'The page where you placed the [creative_hub] shortcode (or built the hub with Elementor).', 'creative-hub' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Shortcodes', 'creative-hub' ); ?></h2>
			<p><code>[creative_hub]</code> — <?php esc_html_e( 'the full hub (my classes + my downloads).', 'creative-hub' ); ?></p>
			<p><code>[ch_my_classes]</code> — <?php esc_html_e( 'just the grid of the visitor\'s classes.', 'creative-hub' ); ?></p>
			<p><code>[ch_my_downloads]</code> — <?php esc_html_e( 'just the download list, grouped by class.', 'creative-hub' ); ?></p>
			<p><code>[ch_class_products]</code> — <?php esc_html_e( 'buy options for the current class (use on the teaser).', 'creative-hub' ); ?></p>
			<p><code>[ch_access_notice]</code> — <?php esc_html_e( 'the "you don\'t have access" notice for the Access page (buy links + log-in link).', 'creative-hub' ); ?></p>
			<p><code>[ch_locked]…[/ch_locked]</code> / <code>[ch_teaser]…[/ch_teaser]</code> — <?php esc_html_e( 'show content only to buyers / only to non-buyers.', 'creative-hub' ); ?></p>
		</div>
		<?php
	}
}
