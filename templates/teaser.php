<?php
/**
 * Teaser shown on a single Class page to visitors who have not bought it.
 *
 * Override by copying to: yourtheme/creative-hub/teaser.php
 *
 * @package CreativeHub
 */

defined( 'ABSPATH' ) || exit;

get_header();

$class_id = get_queried_object_id();
$heading  = ch_get_setting( 'locked_heading', __( 'This class is waiting for you', 'creative-hub' ) );
$message  = ch_get_setting( 'locked_message', '' );
?>

<div class="ch-scope ch-teaser">
	<div class="ch-teaser__inner">

		<?php if ( has_post_thumbnail( $class_id ) ) : ?>
			<div class="ch-teaser__media">
				<?php echo get_the_post_thumbnail( $class_id, 'large' ); ?>
			</div>
		<?php endif; ?>

		<p class="ch-teaser__eyebrow">
			<?php
			$terms = get_the_terms( $class_id, 'ch_class_type' );
			echo esc_html( ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : __( 'Class', 'creative-hub' ) );
			?>
		</p>

		<h1 class="ch-teaser__title"><?php echo esc_html( get_the_title( $class_id ) ); ?></h1>

		<?php if ( has_excerpt( $class_id ) ) : ?>
			<p class="ch-teaser__excerpt"><?php echo esc_html( get_the_excerpt( $class_id ) ); ?></p>
		<?php endif; ?>

		<div class="ch-teaser__lock">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $message ) : ?>
				<p><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>

		<?php echo do_shortcode( '[ch_class_products class_id="' . absint( $class_id ) . '"]' ); ?>

		<?php if ( ! is_user_logged_in() ) : ?>
			<?php
			$ch_login_url = wc_get_page_permalink( 'myaccount' );
			if ( ! $ch_login_url ) {
				$ch_login_url = home_url( '/my-account/' );
			}
			$ch_login_url = add_query_arg( 'redirect', rawurlencode( get_permalink( $class_id ) ), $ch_login_url );
			?>
			<p class="ch-teaser__login">
				<?php esc_html_e( 'Already bought this?', 'creative-hub' ); ?>
				<a class="ch-link" href="<?php echo esc_url( $ch_login_url ); ?>">
					<?php esc_html_e( 'Log into your account to unlock it.', 'creative-hub' ); ?>
				</a>
			</p>
		<?php endif; ?>

	</div>
</div>

<?php
get_footer();
