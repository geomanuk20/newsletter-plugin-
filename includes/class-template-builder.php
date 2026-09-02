<?php
/**
 * Template Builder: Renders and personalizes the HTML email newsletter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADNL_Template_Builder {

	/**
	 * Build base HTML newsletter from posts data.
	 *
	 * @param array $posts Formatted posts array.
	 * @return string Rendered HTML content.
	 */
	public function build_digest_html( $posts, $custom_overrides = array() ) {
		$site_name      = get_bloginfo( 'name' );
		$site_url       = home_url();
		$site_logo      = isset( $custom_overrides['site_logo'] ) ? $custom_overrides['site_logo'] : get_option( 'adnl_site_logo', '' );
		$logo_height    = isset( $custom_overrides['logo_height'] ) ? intval( $custom_overrides['logo_height'] ) : intval( get_option( 'adnl_logo_height', 70 ) );
		$current_date   = wp_date( get_option( 'date_format', 'F j, Y' ) );
		$preheader_text = get_option( 'adnl_preheader_text', "Here are today's top stories and news updates." );
		$primary_color  = get_option( 'adnl_primary_color', '#2563eb' );
		
		$raw_title     = isset( $custom_overrides['header_title'] ) ? $custom_overrides['header_title'] : get_option( 'adnl_header_title', $site_name . ' Newsletter' );
		$header_title  = ! empty( $raw_title ) ? str_replace( '{site_name}', $site_name, $raw_title ) : $site_name . ' Newsletter';

		$raw_footer_text  = get_option( 'adnl_footer_text', 'You received this email because you subscribed to daily news updates on {site_name}.' );
		$footer_text      = ! empty( $raw_footer_text ) ? str_replace( '{site_name}', $site_name, $raw_footer_text ) : '';

		$current_year     = wp_date( 'Y' );
		$raw_copyright    = get_option( 'adnl_footer_copyright', '© {year} {site_name}. All rights reserved.' );
		$footer_copyright = str_replace( array( '{year}', '{site_name}' ), array( $current_year, $site_name ), $raw_copyright );
		$footer_bg_color  = get_option( 'adnl_footer_bg_color', '#f8fafc' );

		ob_start();
		include ADNL_PLUGIN_DIR . 'templates/email-digest.php';
		$html = ob_get_clean();

		return apply_filters( 'adnl_rendered_digest_html', $html, $posts );
	}

	/**
	 * Personalize template HTML for a specific subscriber.
	 *
	 * @param string $base_html
	 * @param object $subscriber
	 * @return string
	 */
	public function personalize_html( $base_html, $subscriber ) {
		$token           = ! empty( $subscriber->token ) ? $subscriber->token : '';
		$unsubscribe_url = add_query_arg(
			array(
				'adnl_action' => 'unsubscribe',
				'token'       => $token,
			),
			home_url( '/' )
		);

		$subscriber_name = ! empty( $subscriber->name ) ? $subscriber->name : __( 'Subscriber', 'auto-daily-newsletter' );
		$subscriber_email = ! empty( $subscriber->email ) ? $subscriber->email : '';

		$replacements = array(
			'{{UNSUBSCRIBE_URL}}' => esc_url( $unsubscribe_url ),
			'{{SUBSCRIBER_NAME}}' => esc_html( $subscriber_name ),
			'{{SUBSCRIBER_EMAIL}}'=> esc_html( $subscriber_email ),
			'{{SITE_NAME}}'       => esc_html( get_bloginfo( 'name' ) ),
			'{{SITE_URL}}'        => esc_url( home_url() ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $base_html );
	}
}
