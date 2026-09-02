<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADNL_Deactivator {

	/**
	 * Run deactivation tasks: Clear scheduled cron events.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'adnl_daily_newsletter_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'adnl_daily_newsletter_cron' );
		}
		wp_clear_scheduled_hook( 'adnl_daily_newsletter_cron' );
	}
}
