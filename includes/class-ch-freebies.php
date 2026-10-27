<?php
/**
 * [ch_freebies] — downloadable freebies behind a sign-up / log-in popup.
 *
 * Lists every published `freebie` post that has a featured image. The featured
 * image's original file is the download. Visitors who aren't logged in get a
 * popup to create a (WooCommerce customer) account or log in; the download starts
 * as soon as they're in. Optionally the freebie is also emailed to them.
 *
 * The original file is never printed into the page: the button points at
 * /?ch_freebie=ID, which checks the visitor is logged in and then streams the file.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Freebies {

	const NONCE_ACTION = 'ch_freebie';

	/** @var bool */
	protected static $modal_needed = false;

	public static function init() {
		add_shortcode( 'ch_freebies', array( __CLASS__, 'render' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_early' ), 20 );

		add_action( 'template_redirect', array( __CLASS__, 'maybe_download' ), 1 );

		foreach ( array( 'state', 'register', 'login' ) as $action ) {
			add_action( 'wp_ajax_nopriv_ch_freebie_' . $action, array( __CLASS__, 'ajax_' . $action ) );
			add_action( 'wp_ajax_ch_freebie_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Settings helpers                                                       */
	/* --------------------------------------------------------------------- */

	public static function post_type() {
		/**
		 * Filter the post type that holds the freebies.
		 *
		 * @param string $post_type Default "freebie".
		 */
		return sanitize_key( apply_filters( 'ch_freebie_post_type', 'freebie' ) );
	}

	public static function heading() {
		$heading = trim( (string) ch_get_setting( 'freebie_heading', '' ) );
		return '' !== $heading ? $heading : __( 'Sign up to download your freebie', 'creative-hub' );
	}

	public static function intro() {
		$intro = trim( (string) ch_get_setting( 'freebie_intro', '' ) );
		return '' !== $intro ? $intro : __( 'Create a free account to download. Everything you buy is saved in your Creative Hub too, so you never have to dig through emails.', 'creative-hub' );
	}

	/* --------------------------------------------------------------------- */
	/* Assets                                                                 */
	/* --------------------------------------------------------------------- */

	public static function register() {
		wp_register_style( 'creative-hub-freebies', CH_URL . 'assets/css/creative-hub-freebies.css', array(), CH_VERSION );
		wp_register_script( 'creative-hub-freebies', CH_URL . 'assets/js/creative-hub-freebies.js', array(), CH_VERSION, true );
		wp_localize_script(
			'creative-hub-freebies',
			'chFreebies',
			array( 'ajax' => admin_url( 'admin-ajax.php' ) )
		);
	}

	/**
	 * The shortcode usually sits in an Elementor widget, so also look in the
	 * page's Elementor data. Loading the styles in the head avoids a flash of
	 * unstyled content.
	 */
	public static function enqueue_early() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$found = has_shortcode( (string) $post->post_content, 'ch_freebies' );
		if ( ! $found ) {
			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			$found     = is_string( $elementor ) && false !== strpos( $elementor, 'ch_freebies' );
		}

		if ( $found ) {
			self::enqueue();
		}
	}

	protected static function enqueue() {
		CH_Assets::enqueue_front();
		if ( ! wp_style_is( 'creative-hub-freebies', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'creative-hub-freebies' );
		wp_enqueue_script( 'creative-hub-freebies' );
	}

	/* --------------------------------------------------------------------- */
	/* Files                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * The file a freebie downloads: its featured image's original upload.
	 *
	 * @param int $post_id Freebie post ID.
	 * @return array{0:int,1:string}|null Attachment ID and absolute path.
	 */
	protected static function file_for( $post_id ) {
		/**
		 * Filter the attachment a freebie downloads.
		 *
		 * @param int $attachment_id Defaults to the featured image.
		 * @param int $post_id       Freebie post ID.
		 */
		$attachment_id = (int) apply_filters( 'ch_freebie_attachment_id', get_post_thumbnail_id( $post_id ), $post_id );
		if ( ! $attachment_id ) {
			return null;
		}

		$path = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : false;
		if ( ! $path || ! is_readable( $path ) ) {
			$path = get_attached_file( $attachment_id );
		}
		if ( ! $path || ! is_readable( $path ) || ! is_file( $path ) ) {
			return null;
		}

		// Only ever serve files that live inside the uploads folder.
		$uploads = wp_upload_dir();
		$base    = realpath( $uploads['basedir'] );
		$real    = realpath( $path );
		if ( ! $base || ! $real || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}

		return array( $attachment_id, $real );
	}

	public static function download_url( $post_id ) {
		return add_query_arg( 'ch_freebie', absint( $post_id ), home_url( '/' ) );
	}

	/**
	 * Preview <img> for a freebie, without the original file in its srcset.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $title         Fallback alt text.
	 * @param bool   $eager         Load immediately rather than lazily.
	 * @return string
	 */
	protected static function preview_image( $attachment_id, $title, $eager ) {
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$alt = $title;
		}

		$hide_originals = function ( $sources ) use ( $attachment_id ) {
			$hidden = array_filter( array( wp_get_attachment_url( $attachment_id ), function_exists( 'wp_get_original_image_url' ) ? wp_get_original_image_url( $attachment_id ) : '' ) );
			foreach ( (array) $sources as $width => $source ) {
				if ( in_array( $source['url'], $hidden, true ) ) {
					unset( $sources[ $width ] );
				}
			}
			return $sources;
		};

		/**
		 * Filter the image size used for the preview. Keep it smaller than the original,
		 * which is what the Download button delivers.
		 *
		 * @param string $size Default "medium_large" (768px wide).
		 */
		$size = apply_filters( 'ch_freebie_preview_size', 'medium_large' );

		add_filter( 'wp_calculate_image_srcset', $hide_originals );
		$html = wp_get_attachment_image(
			$attachment_id,
			$size,
			false,
			array(
				'class'    => 'ch-freebies__img',
				'alt'      => $alt,
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
		remove_filter( 'wp_calculate_image_srcset', $hide_originals );

		return $html;
	}

	/* --------------------------------------------------------------------- */
	/* Shortcode                                                              */
	/* --------------------------------------------------------------------- */

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns' => 4,
				'count'   => -1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'ids'     => '',
			),
			$atts,
			'ch_freebies'
		);

		$columns = max( 1, min( 6, absint( $atts['columns'] ) ) );
		$orderby = in_array( $atts['orderby'], array( 'date', 'title', 'menu_order', 'modified', 'rand' ), true ) ? $atts['orderby'] : 'date';
		$order   = 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC';
		$count   = (int) $atts['count'];

		$args = array(
			'post_type'      => self::post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => 0 === $count ? -1 : $count,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only posts that have a featured image.
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		);

		$ids = array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) );
		if ( $ids ) {
			$args['post__in'] = $ids;
			if ( 'date' === $orderby && 'DESC' === $order ) {
				$args['orderby'] = 'post__in';
			}
		}

		if ( ! post_type_exists( $args['post_type'] ) ) {
			return current_user_can( 'edit_posts' )
				? '<p class="ch-scope ch-empty">' . esc_html( sprintf( /* translators: %s: post type slug */ __( '[ch_freebies] The "%s" post type isn\'t registered on this site. Only editors see this message.', 'creative-hub' ), $args['post_type'] ) ) . '</p>'
				: '';
		}

		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return current_user_can( 'edit_posts' )
				? '<p class="ch-scope ch-empty">' . esc_html__( '[ch_freebies] No published freebies with a featured image were found. Only editors see this message.', 'creative-hub' ) . '</p>'
				: '';
		}

		self::enqueue();

		$logged_in = is_user_logged_in();
		if ( ! $logged_in ) {
			self::$modal_needed = true;
			add_action( 'wp_footer', array( __CLASS__, 'print_modal' ), 5 );
		}

		$style = sprintf(
			'--ch-fb-cols:%1$d;--ch-fb-cols-md:%2$d;--ch-fb-cols-sm:%3$d;',
			$columns,
			min( $columns, 3 ),
			min( $columns, 2 )
		);

		$n = 0;
		ob_start();
		?>
		<div class="ch-scope ch-freebies" style="<?php echo esc_attr( $style ); ?>" data-ch-freebies data-logged-in="<?php echo $logged_in ? '1' : '0'; ?>">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id       = get_the_ID();
				$attachment_id = (int) get_post_thumbnail_id( $post_id );
				$title         = get_the_title( $post_id );
				if ( ! $attachment_id ) {
					continue;
				}
				?>
				<article class="ch-freebies__item">
					<figure class="ch-freebies__figure">
						<?php echo self::preview_image( $attachment_id, $title, $n < 4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <img>. ?>
					</figure>
					<a class="ch-btn ch-freebies__dl" href="<?php echo esc_url( self::download_url( $post_id ) ); ?>" rel="nofollow" data-ch-freebie-dl aria-label="<?php echo esc_attr( sprintf( /* translators: %s: freebie title */ __( 'Download %s', 'creative-hub' ), $title ) ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<span><?php esc_html_e( 'Download', 'creative-hub' ); ?></span>
					</a>
				</article>
				<?php
				++$n;
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- */
	/* Popup                                                                  */
	/* --------------------------------------------------------------------- */

	public static function print_modal() {
		if ( ! self::$modal_needed || is_user_logged_in() ) {
			return;
		}
		self::$modal_needed = false; // Print once.

		$lost_url = wp_lostpassword_url();
		$privacy  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		$i18n = array(
			'working'   => __( 'One moment…', 'creative-hub' ),
			'badEmail'  => __( 'Please enter a valid email address.', 'creative-hub' ),
			'shortPass' => __( 'Your password needs at least 8 characters.', 'creative-hub' ),
			'noLogin'   => __( 'Please enter your email and password.', 'creative-hub' ),
			'network'   => __( 'Something went wrong. Please check your connection and try again.', 'creative-hub' ),
			'exists'    => __( 'You already have an account with that email. Log in below to download.', 'creative-hub' ),
		);
		?>
		<div class="ch-scope ch-fbm" id="ch-fbm" role="dialog" aria-modal="true" aria-labelledby="ch-fbm-title" aria-describedby="ch-fbm-intro" data-ch-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>">
			<div class="ch-fbm__backdrop" data-ch-fbm-close></div>
			<div class="ch-fbm__panel">
				<button type="button" class="ch-fbm__close" data-ch-fbm-close aria-label="<?php esc_attr_e( 'Close', 'creative-hub' ); ?>">
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
				</button>

				<div class="ch-fbm__view" data-ch-fbm-view="form">
					<h2 class="ch-fbm__title" id="ch-fbm-title"><?php echo esc_html( self::heading() ); ?></h2>
					<p class="ch-fbm__intro" id="ch-fbm-intro"><?php echo esc_html( self::intro() ); ?></p>

					<div class="ch-fbm__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Account', 'creative-hub' ); ?>">
						<button type="button" class="ch-fbm__tab" role="tab" id="ch-fbm-tab-register" aria-controls="ch-fbm-register" aria-selected="true" data-ch-fbm-tab="register"><?php esc_html_e( 'Create account', 'creative-hub' ); ?></button>
						<button type="button" class="ch-fbm__tab" role="tab" id="ch-fbm-tab-login" aria-controls="ch-fbm-login" aria-selected="false" tabindex="-1" data-ch-fbm-tab="login"><?php esc_html_e( 'Log in', 'creative-hub' ); ?></button>
					</div>

					<form class="ch-fbm__form" id="ch-fbm-register" role="tabpanel" aria-labelledby="ch-fbm-tab-register" novalidate data-ch-fbm-form="register">
						<p class="ch-fbm__field">
							<label for="ch-fbm-reg-email"><?php esc_html_e( 'Email address', 'creative-hub' ); ?></label>
							<input type="email" id="ch-fbm-reg-email" name="email" autocomplete="email" required>
						</p>
						<p class="ch-fbm__field">
							<label for="ch-fbm-reg-pass"><?php esc_html_e( 'Choose a password', 'creative-hub' ); ?></label>
							<input type="password" id="ch-fbm-reg-pass" name="password" autocomplete="new-password" minlength="8" required aria-describedby="ch-fbm-reg-hint">
							<span class="ch-fbm__hint" id="ch-fbm-reg-hint"><?php esc_html_e( 'At least 8 characters', 'creative-hub' ); ?></span>
						</p>
						<p class="ch-fbm__hp" aria-hidden="true">
							<label><?php esc_html_e( 'Leave this field empty', 'creative-hub' ); ?>
								<input type="text" name="website" tabindex="-1" autocomplete="off">
							</label>
						</p>
						<p class="ch-fbm__error" role="alert" data-ch-fbm-error></p>
						<button type="submit" class="ch-btn ch-fbm__submit"><?php esc_html_e( 'Create account & download', 'creative-hub' ); ?></button>
						<p class="ch-fbm__fine">
							<?php esc_html_e( 'We use your email to create your account.', 'creative-hub' ); ?>
							<?php if ( $privacy ) : ?>
								<a href="<?php echo esc_url( $privacy ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy policy', 'creative-hub' ); ?></a>
							<?php endif; ?>
						</p>
					</form>

					<form class="ch-fbm__form" id="ch-fbm-login" role="tabpanel" aria-labelledby="ch-fbm-tab-login" novalidate hidden data-ch-fbm-form="login">
						<p class="ch-fbm__field">
							<label for="ch-fbm-log-user"><?php esc_html_e( 'Email address or username', 'creative-hub' ); ?></label>
							<input type="text" id="ch-fbm-log-user" name="log" autocomplete="username" required>
						</p>
						<p class="ch-fbm__field">
							<label for="ch-fbm-log-pass"><?php esc_html_e( 'Password', 'creative-hub' ); ?></label>
							<input type="password" id="ch-fbm-log-pass" name="password" autocomplete="current-password" required>
						</p>
						<p class="ch-fbm__error" role="alert" data-ch-fbm-error></p>
						<button type="submit" class="ch-btn ch-fbm__submit"><?php esc_html_e( 'Log in & download', 'creative-hub' ); ?></button>
						<p class="ch-fbm__fine"><a href="<?php echo esc_url( $lost_url ); ?>"><?php esc_html_e( 'Lost your password?', 'creative-hub' ); ?></a></p>
					</form>
				</div>

				<div class="ch-fbm__view" data-ch-fbm-view="done" hidden>
					<h2 class="ch-fbm__title"><?php esc_html_e( 'Your download is starting', 'creative-hub' ); ?></h2>
					<p class="ch-fbm__intro">
						<?php esc_html_e( 'Thanks for joining us. If nothing happens,', 'creative-hub' ); ?>
						<a href="#" data-ch-fbm-link><?php esc_html_e( 'download it here', 'creative-hub' ); ?></a>.
					</p>
					<button type="button" class="ch-btn ch-fbm__submit" data-ch-fbm-close><?php esc_html_e( 'Done', 'creative-hub' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- */
	/* AJAX                                                                   */
	/* --------------------------------------------------------------------- */

	protected static function fail( $message, $code = 'error', $status = 400 ) {
		wp_send_json_error(
			array(
				'message' => $message,
				'code'    => $code,
			),
			$status
		);
	}

	protected static function verify_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::fail( __( 'That took a little too long. Please try again.', 'creative-hub' ), 'nonce', 403 );
		}
	}

	protected static function throttle_key( $bucket ) {
		$ip = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		return 'ch_fb_' . $bucket . '_' . md5( (string) $ip );
	}

	protected static function throttled( $bucket, $max ) {
		return (int) get_transient( self::throttle_key( $bucket ) ) >= $max;
	}

	protected static function hit( $bucket ) {
		$key = self::throttle_key( $bucket );
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	}

	protected static function too_many() {
		self::fail( __( 'Too many attempts. Please wait a few minutes and try again.', 'creative-hub' ), 'throttled', 429 );
	}

	/**
	 * Is the visitor logged in, plus a fresh nonce. Fetched when the popup opens so
	 * a nonce baked into a cached page can never be stale.
	 */
	public static function ajax_state() {
		nocache_headers();
		wp_send_json_success(
			array(
				'loggedIn' => is_user_logged_in(),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	public static function ajax_register() {
		nocache_headers();
		self::verify_nonce();

		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'loggedIn' => true ) );
		}

		// Honeypot: real visitors never see or fill this.
		if ( ! empty( $_POST['website'] ) ) {
			self::fail( __( 'Something went wrong. Please try again.', 'creative-hub' ) );
		}

		if ( self::throttled( 'register', 8 ) ) {
			self::too_many();
		}
		self::hit( 'register' );

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords must not be altered.

		if ( ! is_email( $email ) ) {
			self::fail( __( 'Please enter a valid email address.', 'creative-hub' ), 'email' );
		}
		if ( strlen( $password ) < 8 ) {
			self::fail( __( 'Your password needs at least 8 characters.', 'creative-hub' ), 'password' );
		}

		$username = function_exists( 'wc_create_new_customer_username' ) ? wc_create_new_customer_username( $email ) : '';
		$user_id  = wc_create_new_customer( $email, $username, $password );

		if ( is_wp_error( $user_id ) ) {
			if ( 'registration-error-email-exists' === $user_id->get_error_code() ) {
				self::fail( __( 'You already have an account with that email. Log in below to download.', 'creative-hub' ), 'exists' );
			}
			self::fail( wp_strip_all_tags( $user_id->get_error_message() ) );
		}

		wc_set_customer_auth_cookie( $user_id );
		wp_send_json_success( array( 'loggedIn' => true ) );
	}

	public static function ajax_login() {
		nocache_headers();
		self::verify_nonce();

		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'loggedIn' => true ) );
		}

		if ( self::throttled( 'login', 10 ) ) {
			self::too_many();
		}

		$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords must not be altered.

		if ( '' === $login || '' === $password ) {
			self::fail( __( 'Please enter your email and password.', 'creative-hub' ), 'login' );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::hit( 'login' );
			// One message for every failure, so it can't be used to find out which emails have accounts.
			self::fail( __( 'That email or password isn\'t right. Please check them and try again.', 'creative-hub' ), 'login', 401 );
		}

		delete_transient( self::throttle_key( 'login' ) );
		wp_send_json_success( array( 'loggedIn' => true ) );
	}

	/* --------------------------------------------------------------------- */
	/* Download                                                               */
	/* --------------------------------------------------------------------- */

	public static function maybe_download() {
		if ( ! isset( $_GET['ch_freebie'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; access is checked by login below.
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$post_id = absint( wp_unslash( $_GET['ch_freebie'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || self::post_type() !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_die( esc_html__( 'That freebie could not be found.', 'creative-hub' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		// Not logged in: send them to log in, then straight back here.
		if ( ! is_user_logged_in() ) {
			$account = wc_get_page_permalink( 'myaccount' );
			$account = $account ? $account : home_url( '/my-account/' );
			wp_safe_redirect( add_query_arg( 'redirect', rawurlencode( self::download_url( $post_id ) ), $account ) );
			exit;
		}

		$file = self::file_for( $post_id );
		if ( ! $file ) {
			wp_die( esc_html__( 'Sorry, that file isn\'t available right now.', 'creative-hub' ), '', array( 'response' => 404, 'back_link' => true ) );
		}

		list( $attachment_id, $path ) = $file;
		$user_id                      = get_current_user_id();

		$type = wp_check_filetype( $path );
		$name = wp_basename( $path );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile

		// The visitor already has the whole file; finish the response before doing anything slower.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		/**
		 * Fires after a freebie has been sent to a logged-in visitor.
		 *
		 * @param int $user_id       User ID.
		 * @param int $post_id       Freebie post ID.
		 * @param int $attachment_id Attachment that was downloaded.
		 */
		do_action( 'ch_freebie_downloaded', $user_id, $post_id, $attachment_id );
		self::maybe_email( $user_id, $post, $path );

		exit;
	}

	/**
	 * Email the freebie to the visitor, if Classes → Settings → Freebies says to.
	 *
	 * @param int     $user_id User ID.
	 * @param WP_Post $post    Freebie post.
	 * @param string  $path    Absolute path of the file.
	 */
	protected static function maybe_email( $user_id, $post, $path ) {
		if ( ! ch_get_setting( 'freebie_email', 0 ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		/** Filter the largest file (in bytes) that is attached to the email. Default 8MB. */
		$max_bytes = (int) apply_filters( 'ch_freebie_email_max_bytes', 8 * MB_IN_BYTES );
		if ( filesize( $path ) > $max_bytes ) {
			return;
		}

		// Once an hour per person per freebie, however many times they click.
		$key = 'ch_fb_mail_' . $user_id . '_' . $post->ID;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, HOUR_IN_SECONDS );

		$title = get_the_title( $post );
		$name  = $user->first_name ? $user->first_name : __( 'there', 'creative-hub' );

		/* translators: %s: freebie title */
		$subject = sprintf( __( 'Your freebie: %s', 'creative-hub' ), $title );
		$body    = '<p>' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hi %s,', 'creative-hub' ), $name ) ) . '</p>'
			. '<p>' . esc_html( sprintf( /* translators: %s: freebie title */ __( 'Thanks for downloading "%s". We\'ve attached it here too, so it\'s always in your inbox when you need it.', 'creative-hub' ), $title ) ) . '</p>'
			. '<p><a href="' . esc_url( ch_get_hub_url() ) . '">' . esc_html__( 'Visit your Creative Hub', 'creative-hub' ) . '</a></p>';

		$mailer = function_exists( 'WC' ) && WC()->mailer() ? WC()->mailer() : null;
		if ( $mailer ) {
			$mailer->send( $user->user_email, $subject, $mailer->wrap_message( $title, $body ), "Content-Type: text/html\r\n", array( $path ) );
		} else {
			wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), array( $path ) );
		}
	}
}
