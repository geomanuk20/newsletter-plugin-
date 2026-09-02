<?php
/**
 * Frontend Subscribe Form Shortcode Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_name    = isset( $atts['show_name'] ) && 'yes' === $atts['show_name'];
$site_name    = get_bloginfo( 'name' );
$site_logo    = get_option( 'adnl_site_logo', '' );
$brand_color  = get_option( 'adnl_primary_color', '#e11d48' );
$show_logo    = isset( $atts['show_logo'] ) ? ( 'yes' === $atts['show_logo'] || '1' === $atts['show_logo'] ) : ( ! empty( $site_logo ) );
?>
<div class="adnl-subscribe-widget">
	<div class="adnl-widget-inner">
		<?php if ( $show_logo && ! empty( $site_logo ) ) : ?>
			<div class="adnl-widget-logo" style="margin: 0 auto 14px auto; text-align: center; display: flex; justify-content: center; align-items: center;">
				<img src="<?php echo esc_url( $site_logo ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height: 55px; max-width: 240px; width: auto; height: auto; display: block; margin: 0 auto;" />
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $atts['title'] ) ) : ?>
			<h3 class="adnl-widget-title"><?php echo esc_html( $atts['title'] ); ?></h3>
		<?php endif; ?>

		<?php if ( ! empty( $atts['subtitle'] ) ) : ?>
			<p class="adnl-widget-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
		<?php endif; ?>

		<form class="adnl-subscribe-form" method="post">
			<div class="adnl-form-inputs">
				<?php if ( $show_name ) : ?>
					<div class="adnl-input-group">
						<input type="text" name="adnl_name" placeholder="<?php esc_attr_e( 'Your Name', 'auto-daily-newsletter' ); ?>" class="adnl-input" />
					</div>
				<?php endif; ?>
				<div class="adnl-input-group">
					<input type="email" name="adnl_email" placeholder="<?php esc_attr_e( 'Enter your email address...', 'auto-daily-newsletter' ); ?>" required class="adnl-input" />
				</div>
				<button type="submit" class="adnl-submit-btn" style="background-color: <?php echo esc_attr( $brand_color ); ?>;">
					<span class="adnl-btn-text"><?php echo esc_html( $atts['button_text'] ); ?></span>
					<span class="adnl-spinner" style="display:none;"></span>
				</button>
			</div>
			<div class="adnl-response-message" style="display:none;"></div>
		</form>
	</div>
</div>
