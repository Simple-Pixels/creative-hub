<?php
/**
 * [ch_class_files] — a Download button for a Class page that opens a popup
 * listing every file for that class, each with its own download button plus a
 * "Download all" (zip).
 *
 * The files live on the Studio Session product(s) linked to the class, in the
 * ACF repeater `downloadable_files` with a file sub field `class_file`.
 *
 * File URLs are never printed into the page. Every link points at
 * /?ch_class_file=CLASS&f=ATTACHMENT (or /?ch_class_zip=CLASS), which checks that
 * the visitor has access to the class before sending anything.
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

class CH_Class_Files {

	/** @var array<int,array> Modals to print in the footer, keyed by class ID. */
	protected static $modals = array();

	public static function init() {
		add_shortcode( 'ch_class_files', array( __CLASS__, 'render' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_early' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_download' ), 1 );
	}

	/* --------------------------------------------------------------------- */
	/* Assets                                                                 */
	/* --------------------------------------------------------------------- */

	public static function register() {
		wp_register_style( 'creative-hub-class-files', CH_URL . 'assets/css/creative-hub-class-files.css', array(), CH_VERSION );
		wp_register_script( 'creative-hub-class-files', CH_URL . 'assets/js/creative-hub-class-files.js', array(), CH_VERSION, true );
	}

	protected static function enqueue() {
		CH_Assets::enqueue_front();
		if ( ! wp_style_is( 'creative-hub-class-files', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'creative-hub-class-files' );
		wp_enqueue_script( 'creative-hub-class-files' );
	}

	/**
	 * The shortcode normally lives in an Elementor single template for Classes (so it
	 * isn't in the class's own content). Load the small stylesheet in the head on every
	 * class page, and wherever the shortcode is found in a page's content.
	 */
	public static function enqueue_early() {
		if ( is_singular( 'ch_class' ) ) {
			self::enqueue();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$found = has_shortcode( (string) $post->post_content, 'ch_class_files' );
		if ( ! $found ) {
			$elementor = get_post_meta( $post->ID, '_elementor_data', true );
			$found     = is_string( $elementor ) && false !== strpos( $elementor, 'ch_class_files' );
		}
		if ( $found ) {
			self::enqueue();
		}
	}

	/* --------------------------------------------------------------------- */
	/* Finding the files                                                      */
	/* --------------------------------------------------------------------- */

	/**
	 * Turn whatever ACF / post meta holds for a file field into an attachment ID.
	 *
	 * @param mixed $value Attachment ID, ACF file array, or URL.
	 * @return int
	 */
	protected static function to_attachment_id( $value ) {
		if ( is_array( $value ) ) {
			$value = isset( $value['ID'] ) ? $value['ID'] : ( isset( $value['id'] ) ? $value['id'] : 0 );
		}
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
			return absint( attachment_url_to_postid( $value ) );
		}
		return 0;
	}

	/**
	 * Attachment IDs in a product's `downloadable_files` repeater, in order.
	 *
	 * Reads through ACF when it is active, and falls back to the raw post meta ACF
	 * writes, so the files still work if ACF is ever deactivated.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	protected static function attachment_ids_for_product( $product_id ) {
		/**
		 * Filter the ACF repeater field name that holds a product's files.
		 *
		 * @param string $name Default "downloadable_files".
		 */
		$repeater = (string) apply_filters( 'ch_class_files_repeater', 'downloadable_files' );
		/**
		 * Filter the file sub field name inside that repeater.
		 *
		 * @param string $name Default "class_file".
		 */
		$sub = (string) apply_filters( 'ch_class_files_subfield', 'class_file' );

		$values = array();

		if ( function_exists( 'get_field' ) ) {
			// Unformatted, so the saved attachment ID comes back whatever the field's return format is.
			$rows = get_field( $repeater, $product_id, false );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( is_array( $row ) && isset( $row[ $sub ] ) ) {
						$values[] = $row[ $sub ];
					}
				}
			}
		}

		if ( ! $values ) {
			$count = (int) get_post_meta( $product_id, $repeater, true );
			for ( $i = 0; $i < $count; $i++ ) {
				$value = get_post_meta( $product_id, $repeater . '_' . $i . '_' . $sub, true );
				if ( '' !== $value && null !== $value ) {
					$values[] = $value;
				}
			}
		}

		$ids = array();
		foreach ( $values as $value ) {
			$id = self::to_attachment_id( $value );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Describe one attachment for the popup and the download endpoints.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Keys: id, name, filename, ext, size, path (local file or ''), url.
	 */
	protected static function describe( $attachment_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return null;
		}

		$path  = (string) get_attached_file( $attachment_id );
		$local = '';
		if ( $path && is_file( $path ) && is_readable( $path ) ) {
			// Only ever serve files that live inside the uploads folder.
			$uploads = wp_upload_dir();
			$base    = realpath( $uploads['basedir'] );
			$real    = realpath( $path );
			if ( $base && $real && 0 === strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
				$local = $real;
			}
		}

		// Files moved to a CDN / bucket (e.g. WP Offload Media) may not be on this server.
		$url = (string) wp_get_attachment_url( $attachment_id );
		if ( '' === $local && '' === $url ) {
			return null;
		}

		$source   = '' !== $local ? $local : ( $path ? $path : wp_parse_url( $url, PHP_URL_PATH ) );
		$filename = wp_basename( (string) $source );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		$title = trim( wp_strip_all_tags( get_the_title( $attachment_id ) ) );
		if ( '' === $title ) {
			$title = pathinfo( $filename, PATHINFO_FILENAME );
		}

		$size = null;
		if ( '' !== $local ) {
			$size = (int) filesize( $local );
		} else {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) {
				$size = (int) $meta['filesize'];
			}
		}

		return array(
			'id'       => (int) $attachment_id,
			'name'     => $title,
			'filename' => $filename,
			'ext'      => $ext,
			'size'     => $size,
			'path'     => $local,
			'url'      => $url,
		);
	}

	/**
	 * Every file for a class: the files of each Studio Session product linked to it.
	 *
	 * @param int $class_id Class post ID.
	 * @return array[] Entries from describe().
	 */
	public static function get_files( $class_id ) {
		$seen  = array();
		$files = array();

		foreach ( ch_get_class_product_ids( $class_id ) as $product_id ) {
			foreach ( self::attachment_ids_for_product( $product_id ) as $attachment_id ) {
				if ( isset( $seen[ $attachment_id ] ) ) {
					continue;
				}
				$seen[ $attachment_id ] = true;

				$file = self::describe( $attachment_id );
				if ( $file ) {
					$files[] = $file;
				}
			}
		}

		/**
		 * Filter the files offered for a class.
		 *
		 * @param array[] $files    File descriptions (id, name, filename, ext, size, path, url).
		 * @param int     $class_id Class post ID.
		 */
		return (array) apply_filters( 'ch_class_files', $files, $class_id );
	}

	protected static function file_url( $class_id, $attachment_id ) {
		return add_query_arg(
			array(
				'ch_class_file' => absint( $class_id ),
				'f'             => absint( $attachment_id ),
			),
			home_url( '/' )
		);
	}

	protected static function zip_url( $class_id ) {
		return add_query_arg( 'ch_class_zip', absint( $class_id ), home_url( '/' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Shortcode + popup                                                      */
	/* --------------------------------------------------------------------- */

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'class_id' => 0,
				'label'    => __( 'Download', 'creative-hub' ),
			),
			$atts,
			'ch_class_files'
		);

		$class_id = absint( $atts['class_id'] );
		if ( ! $class_id ) {
			$class_id = (int) get_the_ID();
		}

		$is_editor = current_user_can( 'edit_posts' );

		if ( ! $class_id || 'ch_class' !== get_post_type( $class_id ) ) {
			return $is_editor
				? '<p class="ch-scope ch-empty">' . esc_html__( '[ch_class_files] This shortcode belongs on a Class page (or use class_id="123"). Only editors see this message.', 'creative-hub' ) . '</p>'
				: '';
		}

		// Only people who can open the class see its files.
		if ( ! ch_user_can_access_class( $class_id ) ) {
			return '';
		}

		$files = self::get_files( $class_id );
		if ( ! $files ) {
			return $is_editor
				? '<p class="ch-scope ch-empty">' . esc_html__( '[ch_class_files] No files found. Add them to the linked Studio Session product under "downloadable_files". Only editors see this message.', 'creative-hub' ) . '</p>'
				: '';
		}

		self::enqueue();

		if ( ! isset( self::$modals[ $class_id ] ) ) {
			self::$modals[ $class_id ] = $files;
			add_action( 'wp_footer', array( __CLASS__, 'print_modals' ), 5 );
		}

		$modal_id = 'ch-cfm-' . $class_id;

		ob_start();
		?>
		<div class="ch-scope ch-cf">
			<button type="button" class="ch-btn ch-cf__open" data-ch-cfm-open="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<span><?php echo esc_html( $atts['label'] ); ?></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function print_modals() {
		$can_zip = class_exists( 'ZipArchive' );

		foreach ( self::$modals as $class_id => $files ) {
			$modal_id = 'ch-cfm-' . $class_id;
			$count    = count( $files );
			$total    = 0;
			foreach ( $files as $file ) {
				$total += (int) $file['size'];
			}
			?>
			<div class="ch-scope ch-cfm" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
				<div class="ch-cfm__backdrop" data-ch-cfm-close></div>
				<div class="ch-cfm__panel">
					<button type="button" class="ch-cfm__close" data-ch-cfm-close aria-label="<?php esc_attr_e( 'Close', 'creative-hub' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
					</button>

					<h2 class="ch-cfm__title" id="<?php echo esc_attr( $modal_id ); ?>-title"><?php esc_html_e( 'Your class files', 'creative-hub' ); ?></h2>
					<p class="ch-cfm__sub"><?php echo esc_html( get_the_title( $class_id ) ); ?></p>

					<?php if ( $count > 1 && $can_zip ) : ?>
						<a class="ch-btn ch-cfm__all" href="<?php echo esc_url( self::zip_url( $class_id ) ); ?>" data-ch-cfm-zip data-busy="<?php esc_attr_e( 'Preparing your zip…', 'creative-hub' ); ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 3v12m0 0l-4.5-4.5M12 15l4.5-4.5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<span data-ch-cfm-zip-label>
								<?php
								echo esc_html(
									$total
										? sprintf( /* translators: 1: number of files, 2: combined size */ __( 'Download all (%1$d files, %2$s)', 'creative-hub' ), $count, size_format( $total ) )
										: sprintf( /* translators: %d: number of files */ __( 'Download all (%d files)', 'creative-hub' ), $count )
								);
								?>
							</span>
						</a>
					<?php endif; ?>

					<ul class="ch-cfm__list">
						<?php foreach ( $files as $file ) : ?>
							<?php
							$meta = array();
							if ( $file['ext'] ) {
								$meta[] = strtoupper( $file['ext'] );
							}
							if ( $file['size'] ) {
								$meta[] = size_format( $file['size'] );
							}
							?>
							<li class="ch-cfm__item">
								<span class="ch-cfm__ext" aria-hidden="true"><?php echo esc_html( $file['ext'] ? strtoupper( substr( $file['ext'], 0, 4 ) ) : __( 'FILE', 'creative-hub' ) ); ?></span>
								<span class="ch-cfm__meta">
									<span class="ch-cfm__name"><?php echo esc_html( $file['name'] ); ?></span>
									<?php if ( $meta ) : ?>
										<span class="ch-cfm__size"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span>
									<?php endif; ?>
								</span>
								<a class="ch-btn ch-cfm__dl" href="<?php echo esc_url( self::file_url( $class_id, $file['id'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: file name */ __( 'Download %s', 'creative-hub' ), $file['name'] ) ); ?>">
									<?php esc_html_e( 'Download', 'creative-hub' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php
		}

		self::$modals = array(); // Print each modal once.
	}

	/* --------------------------------------------------------------------- */
	/* Downloads                                                              */
	/* --------------------------------------------------------------------- */

	protected static function deny( $message, $status ) {
		wp_die( esc_html( $message ), '', array( 'response' => $status, 'back_link' => true ) );
	}

	public static function maybe_download() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only; access is checked against the logged-in user below.
		$single = isset( $_GET['ch_class_file'] );
		$zip    = isset( $_GET['ch_class_zip'] );
		if ( ! $single && ! $zip ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		$class_id = absint( wp_unslash( $single ? $_GET['ch_class_file'] : $_GET['ch_class_zip'] ) );
		$file_id  = $single && isset( $_GET['f'] ) ? absint( wp_unslash( $_GET['f'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$class = $class_id ? get_post( $class_id ) : null;
		if ( ! $class || 'ch_class' !== $class->post_type || ( 'publish' !== $class->post_status && ! current_user_can( 'edit_post', $class_id ) ) ) {
			self::deny( __( 'That class could not be found.', 'creative-hub' ), 404 );
		}

		// Not logged in: log in, then come straight back.
		if ( ! is_user_logged_in() ) {
			$back    = $single
				? add_query_arg(
					array(
						'ch_class_file' => $class_id,
						'f'             => $file_id,
					),
					home_url( '/' )
				)
				: self::zip_url( $class_id );
			$account = wc_get_page_permalink( 'myaccount' );
			$account = $account ? $account : home_url( '/my-account/' );
			wp_safe_redirect( add_query_arg( 'redirect', rawurlencode( $back ), $account ) );
			exit;
		}

		if ( ! ch_user_can_access_class( $class_id ) ) {
			self::deny( __( 'These files are included with the class once it has been purchased.', 'creative-hub' ), 403 );
		}

		$files = self::get_files( $class_id );
		if ( ! $files ) {
			self::deny( __( 'Sorry, there are no files available for this class right now.', 'creative-hub' ), 404 );
		}

		if ( $zip ) {
			self::send_zip( $class, $files );
		}

		// Only files that belong to this class can be requested.
		foreach ( $files as $file ) {
			if ( $file['id'] === $file_id ) {
				self::send_file( $class_id, $file );
			}
		}

		self::deny( __( 'That file could not be found.', 'creative-hub' ), 404 );
	}

	/**
	 * @param int   $class_id Class post ID.
	 * @param array $file     Entry from describe().
	 */
	protected static function send_file( $class_id, $file ) {
		$user_id = get_current_user_id();

		if ( '' === $file['path'] ) {
			// Not on this server (offloaded to a bucket / CDN): hand over to its URL.
			do_action( 'ch_class_file_downloaded', $user_id, $class_id, $file['id'] );
			wp_redirect( $file['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the attachment's own URL, possibly on a CDN host.
			exit;
		}

		self::stream( $file['path'], $file['filename'] );

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		/**
		 * Fires after a class file has been sent.
		 *
		 * @param int $user_id       User ID.
		 * @param int $class_id      Class post ID.
		 * @param int $attachment_id Attachment that was downloaded.
		 */
		do_action( 'ch_class_file_downloaded', $user_id, $class_id, $file['id'] );
		exit;
	}

	/**
	 * @param WP_Post $class Class post.
	 * @param array[] $files Files to include.
	 */
	protected static function send_zip( $class, $files ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::deny( __( 'Zip downloads aren\'t available on this server. Please download the files one at a time.', 'creative-hub' ), 501 );
		}

		// A big zip can take a while to build.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$tmp_files = array();
		$cleanup   = function () use ( &$tmp_files ) {
			foreach ( $tmp_files as $tmp ) {
				if ( $tmp && file_exists( $tmp ) ) {
					@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		};
		register_shutdown_function( $cleanup );

		$archive_path = wp_tempnam( 'ch-class-files' );
		$tmp_files[]  = $archive_path;

		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			self::deny( __( 'Sorry, we couldn\'t prepare your zip. Please try again.', 'creative-hub' ), 500 );
		}

		$used = array();
		foreach ( $files as $file ) {
			$source = $file['path'];

			if ( '' === $source ) {
				// Offloaded file: fetch a temporary copy to put in the zip.
				if ( ! function_exists( 'download_url' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$source = download_url( $file['url'], 300 );
				if ( is_wp_error( $source ) ) {
					$zip->close();
					self::deny( __( 'Sorry, we couldn\'t prepare your zip. Please try again.', 'creative-hub' ), 500 );
				}
				$tmp_files[] = $source;
			}

			// Unique name inside the zip, in case two files share a filename.
			$name = sanitize_file_name( $file['filename'] );
			$base = pathinfo( $name, PATHINFO_FILENAME );
			$ext  = pathinfo( $name, PATHINFO_EXTENSION );
			$n    = 2;
			while ( isset( $used[ strtolower( $name ) ] ) ) {
				$name = $base . '-' . $n . ( $ext ? '.' . $ext : '' );
				++$n;
			}
			$used[ strtolower( $name ) ] = true;

			$zip->addFile( $source, $name );
		}

		if ( ! $zip->close() || ! file_exists( $archive_path ) || ! filesize( $archive_path ) ) {
			self::deny( __( 'Sorry, we couldn\'t prepare your zip. Please try again.', 'creative-hub' ), 500 );
		}

		$user_id  = get_current_user_id();
		$zip_name = sanitize_file_name( wp_strip_all_tags( get_the_title( $class ) ) );
		$zip_name = '' !== $zip_name ? $zip_name : 'class-files';
		self::stream( $archive_path, $zip_name . '.zip', 'application/zip' );

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		/**
		 * Fires after the "Download all" zip for a class has been sent.
		 *
		 * @param int $user_id  User ID.
		 * @param int $class_id Class post ID.
		 */
		do_action( 'ch_class_zip_downloaded', $user_id, $class->ID );
		exit;
	}

	/**
	 * Send a local file to the browser as a download.
	 *
	 * @param string $path Absolute path.
	 * @param string $name File name to offer.
	 * @param string $type MIME type; worked out from the name when empty.
	 */
	protected static function stream( $path, $name, $type = '' ) {
		if ( '' === $type ) {
			$check = wp_check_filetype( $name );
			$type  = $check['type'] ? $check['type'] : 'application/octet-stream';
		}

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}
}
