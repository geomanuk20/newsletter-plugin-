<?php
/**
 * Frontend Bottom-Left Slide-in Popup Subscription Widget
 * 
 * Variables available:
 * @var bool   $popup_enabled
 * @var string $popup_title
 * @var string $popup_message
 * @var string $popup_button
 * @var int    $popup_delay
 * @var string $popup_image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$force_preview = isset( $_GET['preview_popup'] ) || isset( $_GET['show_popup'] ) || isset( $_GET['popup'] );

// 1. If subscriber cookie is present, NEVER output the popup HTML
if ( ! $force_preview && isset( $_COOKIE['adnl_subscribed'] ) && '1' === strval( $_COOKIE['adnl_subscribed'] ) ) {
	return;
}

// 2. If logged in, check if current user's email is already an active subscriber
if ( ! $force_preview && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	if ( $current_user && ! empty( $current_user->user_email ) ) {
		global $wpdb;
		$sub_tbl = $wpdb->prefix . 'adnl_subscribers';
		$is_sub  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$sub_tbl} WHERE email = %s AND status = 'active' LIMIT 1", $current_user->user_email ) );
		if ( $is_sub ) {
			if ( ! headers_sent() ) {
				setcookie( 'adnl_subscribed', '1', time() + ( 365 * 86400 ), '/' );
			}
			return;
		}
	}
}

$popup_enabled = get_option( 'adnl_popup_enabled', 1 );
if ( false === $popup_enabled || '' === $popup_enabled ) {
	$popup_enabled = 1;
}
if ( ! $force_preview && '0' === strval( $popup_enabled ) && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) ) {
	return;
}

$popup_title       = get_option( 'adnl_popup_title', 'HI THERE!' );
$raw_popup_message = get_option( 'adnl_popup_message', 'Subscribe to our newsletter for daily news & updates delivered straight to your inbox.' );
$popup_message     = html_entity_decode( $raw_popup_message, ENT_QUOTES, 'UTF-8' );
$popup_button      = get_option( 'adnl_popup_button', 'SUBSCRIBE' );
$popup_btn_color   = get_option( 'adnl_popup_btn_color', '#f43f5e' );
$popup_placeholder = get_option( 'adnl_popup_placeholder', 'Email' );
$popup_show_logo   = get_option( 'adnl_popup_show_logo', 1 );
$popup_delay       = max( 1, intval( get_option( 'adnl_popup_delay', 2 ) ) );
$site_name         = get_bloginfo( 'name' );
if ( empty( $site_name ) ) {
	$site_name = 'Daily News';
}
$site_logo         = get_option( 'adnl_site_logo', '' );
$popup_logo_h      = intval( get_option( 'adnl_popup_logo_height', 55 ) );
$popup_position    = get_option( 'adnl_popup_position', 'bottom-left' );
if ( empty( $popup_position ) ) {
	$popup_position = 'bottom-left';
}
$popup_frequency   = intval( get_option( 'adnl_popup_frequency', 30 ) );
?>

<style id="adnl-popup-critical-css">
.adnl-slidein-popup {
	display: none;
	position: fixed !important;
	z-index: 99999999 !important;
	max-width: 410px !important;
	width: calc(100% - 48px) !important;
	margin: 0 !important;
	padding: 0 !important;
	opacity: 0;
	visibility: hidden;
	pointer-events: none;
	transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease, visibility 0.3s ease;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
	line-height: 1.4 !important;
	box-sizing: border-box !important;
}
.adnl-slidein-popup * {
	box-sizing: border-box !important;
}
.adnl-slidein-popup.adnl-pos-bottom-left {
	bottom: 24px !important;
	left: 24px !important;
	right: auto !important;
	top: auto !important;
	transform: translateY(40px) !important;
}
.adnl-slidein-popup.adnl-pos-bottom-right {
	bottom: 24px !important;
	right: 24px !important;
	left: auto !important;
	top: auto !important;
	transform: translateY(40px) !important;
}
.adnl-slidein-popup.adnl-pos-top-left {
	top: 24px !important;
	left: 24px !important;
	right: auto !important;
	bottom: auto !important;
	transform: translateY(-40px) !important;
}
.adnl-slidein-popup.adnl-pos-top-right {
	top: 24px !important;
	right: 24px !important;
	left: auto !important;
	bottom: auto !important;
	transform: translateY(-40px) !important;
}
.adnl-slidein-popup.adnl-pos-center {
	top: 50% !important;
	left: 50% !important;
	right: auto !important;
	bottom: auto !important;
	transform: translate(-50%, -46%) scale(0.96) !important;
}
.adnl-slidein-popup.adnl-popup-visible {
	display: block !important;
	opacity: 1 !important;
	visibility: visible !important;
	pointer-events: auto !important;
}
.adnl-slidein-popup.adnl-pos-bottom-left.adnl-popup-visible,
.adnl-slidein-popup.adnl-pos-bottom-right.adnl-popup-visible,
.adnl-slidein-popup.adnl-pos-top-left.adnl-popup-visible,
.adnl-slidein-popup.adnl-pos-top-right.adnl-popup-visible {
	transform: translateY(0) !important;
}
.adnl-slidein-popup.adnl-pos-center.adnl-popup-visible {
	transform: translate(-50%, -50%) scale(1) !important;
}
.adnl-slidein-popup .adnl-popup-card {
	background: #ffffff !important;
	border-radius: 14px !important;
	box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(15, 23, 42, 0.08) !important;
	overflow: hidden !important;
	position: relative !important;
	width: 100% !important;
	max-width: 410px !important;
	margin: 0 auto !important;
	padding: 0 !important;
	border: none !important;
	text-align: center !important;
}
.adnl-slidein-popup .adnl-popup-close {
	position: absolute !important;
	top: 12px !important;
	right: 12px !important;
	z-index: 99 !important;
	width: 28px !important;
	height: 28px !important;
	margin: 0 !important;
	padding: 0 !important;
	border-radius: 4px !important;
	background: #fb7185 !important;
	color: #ffffff !important;
	border: none !important;
	font-size: 20px !important;
	line-height: 1 !important;
	font-weight: 700 !important;
	cursor: pointer !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
}
.adnl-slidein-popup .adnl-popup-close:hover {
	background: #f43f5e !important;
}
.adnl-slidein-popup .adnl-popup-input {
	width: 100% !important;
	height: 46px !important;
	padding: 10px 16px !important;
	border: 1px solid #cbd5e1 !important;
	border-radius: 6px !important;
	font-size: 14px !important;
	background: #ffffff !important;
	color: #0f172a !important;
	box-sizing: border-box !important;
	outline: none !important;
}
.adnl-slidein-popup .adnl-popup-input:focus {
	border-color: #2563eb !important;
	box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
}
.adnl-slidein-popup .adnl-popup-submit {
	cursor: pointer !important;
	border: none !important;
	border-radius: 6px !important;
	color: #ffffff !important;
	font-size: 14px !important;
	font-weight: 700 !important;
	letter-spacing: 1px !important;
	box-sizing: border-box !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	transition: filter 0.2s ease !important;
}
.adnl-slidein-popup .adnl-popup-submit:hover {
	filter: brightness(1.08) !important;
}
.adnl-slidein-popup .adnl-response-message {
	padding: 8px 12px !important;
	border-radius: 6px !important;
	font-size: 13px !important;
	font-weight: 600 !important;
	box-sizing: border-box !important;
}
.adnl-slidein-popup .adnl-response-success {
	background: #f0fdf4 !important;
	color: #166534 !important;
	border: 1px solid #bbf7d0 !important;
}
.adnl-slidein-popup .adnl-response-error {
	background: #fef2f2 !important;
	color: #991b1b !important;
	border: 1px solid #fecaca !important;
}
.adnl-slidein-popup .adnl-response-warning {
	background: #fffbeb !important;
	color: #92400e !important;
	border: 1px solid #fcd34d !important;
}
@media (max-width: 480px) {
	.adnl-slidein-popup {
		width: calc(100% - 24px) !important;
		left: 12px !important;
		right: 12px !important;
		bottom: 16px !important;
		top: auto !important;
		max-width: 100% !important;
		transform: translateY(40px) !important;
	}
	.adnl-slidein-popup.adnl-popup-visible {
		transform: translateY(0) !important;
	}
}
</style>

<!-- Slide-in Newsletter Popup Card (Configurable Position, Centered Logo) -->
<div id="adnl-slidein-popup" class="adnl-slidein-popup adnl-pos-<?php echo esc_attr( $popup_position ); ?>" style="position: fixed; display: none; z-index: 99999999;" data-delay="<?php echo esc_attr( $popup_delay ); ?>" data-frequency="<?php echo esc_attr( $popup_frequency ); ?>">
	
	<div class="adnl-popup-card" style="max-width: 410px; width: 100%; margin: 0 auto; box-sizing: border-box;">
		<!-- Close Button -->
		<button type="button" class="adnl-popup-close" aria-label="<?php esc_attr_e( 'Close', 'auto-daily-newsletter' ); ?>">&times;</button>

		<div class="adnl-popup-body" style="padding: 34px 28px 28px 28px; text-align: center; box-sizing: border-box; width: 100%;">
			<!-- Centered Company Logo -->
			<?php if ( $popup_show_logo ) : ?>
				<div class="adnl-popup-logo" style="margin: 0 auto 16px auto; display: flex; justify-content: center; align-items: center; text-align: center; width: 100%;">
					<?php if ( ! empty( $site_logo ) ) : ?>
						<img src="<?php echo esc_url( $site_logo ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="<?php echo esc_attr( $popup_logo_h ); ?>" style="height: <?php echo esc_attr( $popup_logo_h ); ?>px; max-height: <?php echo esc_attr( $popup_logo_h ); ?>px; max-width: 280px; width: auto; display: block; margin: 0 auto; border: none; box-shadow: none;" />
					<?php else : ?>
						<?php 
							$first_char = function_exists( 'mb_substr' ) ? mb_substr( $site_name, 0, 1 ) : substr( $site_name, 0, 1 );
							$brand_initial = ! empty( $site_name ) ? strtoupper( $first_char ) : 'N'; 
						?>
						<div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin: 0 auto;">
							<span style="background: #e11d48; color: #ffffff; font-size: 14px; font-weight: 900; padding: 4px 7px; border-radius: 3px; font-family: sans-serif; line-height: 1;"><?php echo esc_html( $brand_initial ); ?></span>
							<span style="font-size: 20px; font-weight: 800; color: #004b87; font-family: sans-serif; letter-spacing: -0.5px; line-height: 1;">
								<?php echo esc_html( $site_name ); ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<h3 class="adnl-popup-title" style="text-align: center; margin: 0 0 8px 0; font-size: 22px; font-weight: 900; letter-spacing: 0.5px; color: #0f172a; line-height: 1.25; width: 100%;">
				<?php echo esc_html( $popup_title ); ?>
			</h3>
			<p class="adnl-popup-desc" style="text-align: center; margin: 0 auto 18px auto; max-width: 320px; font-size: 14px; line-height: 1.5; color: #475569; width: 100%;">
				<?php echo esc_html( $popup_message ); ?>
			</p>

			<form class="adnl-subscribe-form adnl-popup-form" method="post" style="width: 100%; margin: 0; padding: 0; box-sizing: border-box; display: block;">
				<div class="adnl-popup-input-wrap" style="margin: 0 0 12px 0; width: 100%; box-sizing: border-box; display: block;">
					<input type="email" name="adnl_email" class="adnl-popup-input" placeholder="<?php echo esc_attr( $popup_placeholder ); ?>" required style="text-align: left; width: 100%; height: 46px; margin: 0; box-sizing: border-box; display: block;" />
				</div>
				<button type="submit" class="adnl-submit-btn adnl-popup-submit" style="width: 100%; height: 46px; margin: 0; box-sizing: border-box; display: flex; align-items: center; justify-content: center; background-color: <?php echo esc_attr( $popup_btn_color ); ?>; padding: 0 20px; font-size: 14px; font-weight: 700; letter-spacing: 1px;">
					<span class="adnl-btn-text"><?php echo esc_html( $popup_button ); ?></span>
					<span class="adnl-spinner" style="display:none;"></span>
				</button>
				<div class="adnl-response-message" style="display:none; margin-top: 10px; text-align: center; width: 100%;"></div>
			</form>
		</div>
	</div>

</div>

<script id="adnl-popup-inline-fallback">
(function() {
	if (window.adnlPopupFallbackInit) return;
	window.adnlPopupFallbackInit = true;

	function activatePopup() {
		var p = document.getElementById('adnl-slidein-popup');
		if (!p) return;
		if (p.parentNode !== document.body) document.body.appendChild(p);

		// If user already subscribed, NEVER show popup (physically remove it)
		var url = window.location.href;
		var force = url.indexOf('preview_popup=1') !== -1 || url.indexOf('show_popup=1') !== -1;
		var isSub = false;
		try {
			isSub = (
				localStorage.getItem('adnl_user_subscribed') === '1' ||
				localStorage.getItem('adnl_subscribed') === '1' ||
				sessionStorage.getItem('adnl_user_subscribed') === '1' ||
				sessionStorage.getItem('adnl_subscribed') === '1' ||
				document.cookie.indexOf('adnl_subscribed=1') !== -1
			);
		} catch(e){}

		if (isSub && !force) {
			p.style.display = 'none';
			if (p.parentNode) p.parentNode.removeChild(p);
			return;
		}

		// Frequency check
		var freq = parseInt(p.getAttribute('data-frequency'), 10);
		if (isNaN(freq) || freq < 0) freq = 30;
		if (freq > 0 && !force) {
			try {
				if (document.cookie.indexOf('adnl_popup_dismissed=1') !== -1) return;
				var d = localStorage.getItem('adnl_popup_dismissed_time') || sessionStorage.getItem('adnl_popup_dismissed_time');
				if (d && (Date.now() - parseInt(d, 10) < freq * 60 * 1000)) {
					return;
				}
			} catch(e){}
		}

		var delaySec = parseInt(p.getAttribute('data-delay'), 10) || 2;
		setTimeout(function() {
			p.style.display = 'block';
			setTimeout(function() {
				p.classList.add('adnl-popup-visible');
			}, 50);
		}, delaySec * 1000);

		// Close handler
		var c = p.querySelector('.adnl-popup-close');
		if (c) {
			c.onclick = function(e) {
				e.preventDefault();
				p.classList.remove('adnl-popup-visible');
				try {
					var now = Date.now().toString();
					localStorage.setItem('adnl_popup_dismissed_time', now);
					sessionStorage.setItem('adnl_popup_dismissed_time', now);
					var freqMin = parseInt(p.getAttribute('data-frequency'), 10) || 30;
					if (freqMin > 0) {
						document.cookie = "adnl_popup_dismissed=1; path=/; max-age=" + (freqMin * 60);
					}
				} catch(err){}
				setTimeout(function() { p.style.display = 'none'; }, 450);
			};
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', activatePopup);
	} else {
		activatePopup();
	}
})();
</script>
