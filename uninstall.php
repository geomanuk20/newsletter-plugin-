<?php
/**
 * Fired when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adnl_subscribers" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adnl_logs" );

// Delete registered options
$options = array(
	'adnl_enabled',
	'adnl_posts_count',
	'adnl_lookback_hours',
	'adnl_fallback_behavior',
	'adnl_post_types',
	'adnl_categories',
	'adnl_schedule_time',
	'adnl_email_subject',
	'adnl_preheader_text',
	'adnl_primary_color',
	'adnl_from_name',
	'adnl_from_email',
	'adnl_mailer_type',
	'adnl_smtp_host',
	'adnl_smtp_port',
	'adnl_smtp_encryption',
	'adnl_smtp_auth',
	'adnl_smtp_user',
	'adnl_smtp_pass',
	'adnl_batch_size',
	'adnl_batch_delay',
	'adnl_last_run_timestamp',
	'adnl_last_dispatched_date',
	'adnl_last_dispatched_key',
);

foreach ( $options as $opt ) {
	delete_option( $opt );
}

// Clear any remaining scheduled cron
$timestamp = wp_next_scheduled( 'adnl_daily_newsletter_cron' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'adnl_daily_newsletter_cron' );
}
wp_clear_scheduled_hook( 'adnl_daily_newsletter_cron' );
