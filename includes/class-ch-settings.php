<?php
/**
 * Settings screen at Classes → Settings, split into tabs.
 *
 * All tabs save into the single `ch_settings` option. Each tab only overwrites
 * its own keys, so saving one tab never wipes another.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Settings {

	const OPTION = 'ch_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
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

	public static function enqueue( $hook ) {
		if ( 'ch_class_page_ch-settings' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'creative-hub-admin', CH_URL . 'assets/js/admin-settings.js', array( 'jquery' ), CH_VERSION, true );
		wp_localize_script(
			'creative-hub-admin',
			'chAdmin',
			array(
				'title'  => __( 'Choose the curated-kit image', 'creative-hub' ),
				'button' => __( 'Use this image', 'creative-hub' ),
			)
		);
	}

	/**
	 * @return array<string,string> Tab slug => label.
	 */
	protected static function tabs() {
		return array(
			'general'      => __( 'General', 'creative-hub' ),
			'curated-kits' => __( 'Curated kits', 'creative-hub' ),
			'freebies'     => __( 'Freebies', 'creative-hub' ),
		);
	}

	protected static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'general';
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = (array) get_option( self::OPTION, array() );
		$tab   = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : 'general';

		if ( 'curated-kits' === $tab ) {
			$id  = absint( $input['curated_kit_image_id'] ?? 0 );
			$url = esc_url_raw( trim( (string) ( $input['curated_kit_image_url'] ?? '' ) ) );

			// A picked media-library image wins; a hand-typed URL that no longer
			// matches it means the picked ID is stale.
			if ( $id ) {
				$attachment_url = wp_get_attachment_url( $id );
				if ( ! $attachment_url ) {
					$id = 0;
				} elseif ( '' === $url ) {
					$url = esc_url_raw( $attachment_url );
				} elseif ( set_url_scheme( $attachment_url, 'https' ) !== set_url_scheme( $url, 'https' ) ) {
					$id = 0;
				}
			}

			$out['curated_kit_image_id']  = $id;
			$out['curated_kit_image_url'] = $url;
			$out['curated_kit_image_alt'] = sanitize_text_field( $input['curated_kit_image_alt'] ?? '' );

			return $out;
		}

		if ( 'freebies' === $tab ) {
			$out['freebie_email']   = empty( $input['freebie_email'] ) ? 0 : 1;
			$out['freebie_heading'] = sanitize_text_field( $input['freebie_heading'] ?? '' );
			$out['freebie_intro']   = sanitize_textarea_field( $input['freebie_intro'] ?? '' );

			return $out;
		}

		$valid_statuses = array_map(
			function ( $s ) {
				return preg_replace( '/^wc-/', '', $s );
			},
			array_keys( wc_get_order_statuses() )
		);

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
		$tab = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Creative Hub Settings', 'creative-hub' ); ?></h1>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'creative-hub' ); ?>">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'ch_class', 'page' => 'ch-settings', 'tab' => $slug ), admin_url( 'edit.php' ) ) ); ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'curated-kits' === $tab ) {
				self::render_curated_kits();
			} elseif ( 'freebies' === $tab ) {
				self::render_freebies();
			} else {
				self::render_general();
			}
			?>
		</div>
		<?php
	}

	protected static function render_freebies() {
		$email   = (int) ch_get_setting( 'freebie_email', 0 );
		$heading = CH_Freebies::heading();
		$intro   = CH_Freebies::intro();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ch_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_tab]" value="freebies">

			<p>
				<?php esc_html_e( 'The [ch_freebies] shortcode lists your freebies. Visitors who aren\'t logged in see a sign-up / log-in popup before the download starts.', 'creative-hub' ); ?>
				<?php
				printf(
					/* translators: %s: post type slug */
					esc_html__( 'It reads the "%s" post type and downloads each freebie\'s featured image.', 'creative-hub' ),
					esc_html( CH_Freebies::post_type() )
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ch-fb-heading"><?php esc_html_e( 'Popup heading', 'creative-hub' ); ?></label></th>
					<td><input type="text" class="large-text" id="ch-fb-heading" name="<?php echo esc_attr( self::OPTION ); ?>[freebie_heading]" value="<?php echo esc_attr( $heading ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ch-fb-intro"><?php esc_html_e( 'Popup message', 'creative-hub' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="ch-fb-intro" name="<?php echo esc_attr( self::OPTION ); ?>[freebie_intro]"><?php echo esc_textarea( $intro ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email the freebie', 'creative-hub' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[freebie_email]" value="1" <?php checked( $email, 1 ); ?>>
							<?php esc_html_e( 'Also email each freebie to the customer when they download it', 'creative-hub' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Sent in your WooCommerce email style with the file attached (files over 8MB are not attached). Each person gets at most one email per freebie per hour. Off by default.', 'creative-hub' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected static function render_curated_kits() {
		$kit = ch_get_curated_kit_image();
		$s   = (array) get_option( self::OPTION, array() );

		$id  = absint( $s['curated_kit_image_id'] ?? 0 );
		$url = array_key_exists( 'curated_kit_image_url', $s ) ? (string) $s['curated_kit_image_url'] : ch_default_kit_image_url();
		$alt = (string) ( $s['curated_kit_image_alt'] ?? '' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ch_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_tab]" value="curated-kits">

			<p><?php esc_html_e( 'Products in the "curated-kits" collection show this image as the last picture in their [ch_gallery], and it appears on the order confirmation screen when a curated kit is bought.', 'creative-hub' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Kit image', 'creative-hub' ); ?></th>
					<td>
						<div style="margin-bottom:12px;">
							<img id="ch-kit-preview" src="<?php echo $kit ? esc_url( $kit['src'] ) : ''; ?>" alt="" style="max-width:320px;max-height:240px;height:auto;border:1px solid #c3c4c7;background:#fff;padding:4px;<?php echo $kit ? '' : 'display:none;'; ?>">
						</div>
						<input type="hidden" id="ch-kit-id" name="<?php echo esc_attr( self::OPTION ); ?>[curated_kit_image_id]" value="<?php echo esc_attr( $id ); ?>">
						<p>
							<button type="button" class="button" id="ch-kit-select"><?php esc_html_e( 'Choose from media library', 'creative-hub' ); ?></button>
							<button type="button" class="button-link button-link-delete" id="ch-kit-remove" style="margin-left:10px;"><?php esc_html_e( 'Remove image', 'creative-hub' ); ?></button>
						</p>
						<p>
							<label for="ch-kit-url" class="screen-reader-text"><?php esc_html_e( 'Kit image URL', 'creative-hub' ); ?></label>
							<input type="url" class="large-text" id="ch-kit-url" name="<?php echo esc_attr( self::OPTION ); ?>[curated_kit_image_url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://">
						</p>
						<p class="description"><?php esc_html_e( 'Pick a file from the media library, or paste an image URL. Removing the image switches the curated-kit picture off everywhere.', 'creative-hub' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ch-kit-alt"><?php esc_html_e( 'Alt text', 'creative-hub' ); ?></label></th>
					<td>
						<input type="text" class="large-text" id="ch-kit-alt" name="<?php echo esc_attr( self::OPTION ); ?>[curated_kit_image_alt]" value="<?php echo esc_attr( $alt ); ?>">
						<p class="description"><?php esc_html_e( 'Describes the image for screen readers. Leave blank to use the media library alt text.', 'creative-hub' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	protected static function render_general() {
		$s = get_option( self::OPTION, array() );
		$s = wp_parse_args(
			$s,
			array(
				'access_statuses' => array( 'processing', 'completed' ),
				'auto_gate'       => 1,
				'admin_preview'   => 1,
				'buy_button_text' => 'Get this class',
				'locked_heading'  => 'This studio session is waiting for you',
				'locked_message'  => '',
				'hub_page_id'     => 0,
				'access_page_id'  => 0,
			)
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ch_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[_tab]" value="general">
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
								'show_option_none'  => __( '— Use the My Account dashboard only —', 'creative-hub' ),
								'option_none_value' => 0,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Optional standalone page holding the [creative_hub] shortcode. Leave blank to use the My Account dashboard.', 'creative-hub' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Shortcodes', 'creative-hub' ); ?></h2>
		<p><code>[creative_hub]</code> — <?php esc_html_e( 'the full hub (my classes, retreats and downloads).', 'creative-hub' ); ?></p>
		<p><code>[ch_my_classes]</code> — <?php esc_html_e( 'just the grid of the visitor\'s classes.', 'creative-hub' ); ?></p>
		<p><code>[ch_my_retreats]</code> — <?php esc_html_e( 'just the visitor\'s retreat bookings, with payment status.', 'creative-hub' ); ?></p>
		<p><code>[ch_my_downloads]</code> — <?php esc_html_e( 'just the download list, grouped by class.', 'creative-hub' ); ?></p>
		<p><code>[ch_freebies]</code> — <?php esc_html_e( 'the freebies grid; logged-out visitors get a sign-up / log-in popup before downloading.', 'creative-hub' ); ?></p>
		<p><code>[ch_gallery]</code> — <?php esc_html_e( 'image gallery for the current product or class (add product_id="123" to target one). Curated kits get the kit image added at the end.', 'creative-hub' ); ?></p>
		<p><code>[ch_class_products]</code> — <?php esc_html_e( 'buy options for the current class (use on the teaser).', 'creative-hub' ); ?></p>
		<p><code>[ch_access_notice]</code> — <?php esc_html_e( 'the "you don\'t have access" notice for the Access page (buy links + log-in link).', 'creative-hub' ); ?></p>
		<p><code>[ch_locked]…[/ch_locked]</code> / <code>[ch_teaser]…[/ch_teaser]</code> — <?php esc_html_e( 'show content only to buyers / only to non-buyers.', 'creative-hub' ); ?></p>
		<?php
	}
}
