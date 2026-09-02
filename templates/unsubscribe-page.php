<?php
/**
 * Standalone Unsubscribe Confirmation Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name     = get_bloginfo( 'name' );
$site_url      = home_url();
$raw_title     = get_option( 'adnl_header_title', '' );
$header_title  = ! empty( $raw_title ) ? str_replace( '{site_name}', $site_name, $raw_title ) : ( ! empty( $site_name ) ? $site_name : 'Daily Newsletter' );
$site_logo     = get_option( 'adnl_site_logo', '' );
$logo_h        = intval( get_option( 'adnl_logo_height', 60 ) );
$primary_color = get_option( 'adnl_primary_color', '#2563eb' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php printf( esc_html__( 'Unsubscribed - %s', 'auto-daily-newsletter' ), esc_html( $header_title ) ); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			background: #f8fafc;
			color: #0f172a;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			margin: 0;
			padding: 20px;
			box-sizing: border-box;
		}
		.card {
			background: #ffffff;
			max-width: 480px;
			width: 100%;
			border-radius: 16px;
			padding: 40px 36px;
			text-align: center;
			box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
			border: 1px solid #e2e8f0;
		}
		.icon-circle {
			width: 64px;
			height: 64px;
			border-radius: 50%;
			background: #fef2f2;
			color: #ef4444;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-size: 28px;
			margin: 0 auto 20px auto;
		}
		h1 {
			font-size: 24px;
			font-weight: 700;
			margin: 0 0 12px 0;
			color: #0f172a;
		}
		p {
			font-size: 15px;
			line-height: 1.6;
			color: #64748b;
			margin: 0 0 24px 0;
		}
		.btn {
			display: inline-block;
			background: <?php echo esc_attr( $primary_color ); ?>;
			color: #ffffff;
			text-decoration: none;
			padding: 12px 28px;
			border-radius: 8px;
			font-weight: 600;
			font-size: 14px;
			transition: opacity 0.2s;
		}
		.btn:hover {
			opacity: 0.9;
		}
	</style>
</head>
<body>
	<div class="card">
		<!-- Publication Logo -->
		<?php if ( ! empty( $site_logo ) ) : ?>
			<div style="margin-bottom: 20px;">
				<img src="<?php echo esc_url( $site_logo ); ?>" alt="<?php echo esc_attr( $header_title ); ?>" style="max-height: <?php echo esc_attr( max( 42, min( 70, $logo_h ) ) ); ?>px; max-width: 260px; width: auto; height: auto; display: block; margin: 0 auto;" />
			</div>
		<?php endif; ?>

		<div class="icon-circle">✓</div>
		<h1><?php esc_html_e( 'Unsubscribed Successfully', 'auto-daily-newsletter' ); ?></h1>
		<p><?php printf( esc_html__( 'You have been removed from the daily newsletter list for %s. You will no longer receive daily news digest emails.', 'auto-daily-newsletter' ), '<strong style="color: #0f172a;">' . esc_html( $header_title ) . '</strong>' ); ?></p>
		<a href="<?php echo esc_url( $site_url ); ?>" class="btn">
			<?php esc_html_e( 'Return to Website', 'auto-daily-newsletter' ); ?>
		</a>
	</div>
	<script>
		try {
			localStorage.removeItem('adnl_user_subscribed');
			document.cookie = "adnl_subscribed=; path=/; max-age=0; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
		} catch(e) {}
	</script>
</body>
</html>
