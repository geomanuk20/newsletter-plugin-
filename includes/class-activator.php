<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADNL_Activator {

	/**
	 * Run activation tasks: Create tables, set default options, register cron.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();

		// Schedule cron job
		ADNL_Cron::reschedule();
	}

	/**
	 * Create subscribers and logs custom database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate   = $wpdb->get_charset_collate();
		$subscribers_table = $wpdb->prefix . 'adnl_subscribers';
		$logs_table        = $wpdb->prefix . 'adnl_logs';

		$sql_subscribers = "CREATE TABLE IF NOT EXISTS $subscribers_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL,
			name varchar(100) NOT NULL DEFAULT '',
			token varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			ip_address varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY token (token),
			KEY status (status)
		) $charset_collate;";

		$sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject varchar(255) NOT NULL,
			posts_count int(11) NOT NULL DEFAULT 0,
			recipients_count int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'success',
			message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_subscribers );
		dbDelta( $sql_logs );

		// Direct query fallback to ensure tables exist even if dbDelta fails
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$subscribers_table'" ) !== $subscribers_table ) {
			$wpdb->query( $sql_subscribers );
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$logs_table'" ) !== $logs_table ) {
			$wpdb->query( $sql_logs );
		}
	}

	/**
	 * Seed default options if not already configured.
	 */
	private static function set_default_options() {
		$defaults = array(
			// General settings
			'adnl_enabled'             => 1,
			'adnl_posts_count'         => 7, // 5-10
			'adnl_lookback_hours'      => 24,
			'adnl_fallback_behavior'   => 'latest', // 'latest' or 'skip'
			'adnl_post_types'          => array( 'post' ),
			'adnl_categories'          => array(),
			'adnl_schedule_time'       => '08:00', // 24-hr format HH:MM
			'adnl_email_subject'       => "[Daily Digest] Today's Top Stories - {date}",
			'adnl_preheader_text'      => "Here are today's top stories and news updates.",
			
			// Sender info
			'adnl_from_name'           => get_bloginfo( 'name' ),
			'adnl_from_email'          => get_bloginfo( 'admin_email' ),
			
			// SMTP settings (Default: Dedicated Custom SMTP Server)
			'adnl_mailer_type'         => 'smtp', // 'smtp' (default) or 'wp_mail'
			'adnl_smtp_host'           => '',
			'adnl_smtp_port'           => 587,
			'adnl_smtp_encryption'     => 'tls', // 'tls', 'ssl', 'none'
			'adnl_smtp_auth'           => 1,
			'adnl_smtp_user'           => '',
			'adnl_smtp_pass'           => '',

			// Batching settings
			'adnl_batch_size'          => 30, // batch size to prevent server timeout
			'adnl_batch_delay'         => 1,  // seconds between batches

			// Popup settings
			'adnl_popup_enabled'       => 1,
			'adnl_popup_show_logo'     => 1,
			'adnl_popup_title'         => 'HI THERE!',
			'adnl_popup_message'       => 'Subscribe to our newsletter for daily news & updates delivered straight to your inbox.',
			'adnl_popup_button'        => 'SUBSCRIBE',
			'adnl_popup_btn_color'     => '#f43f5e',
			'adnl_popup_placeholder'   => 'Email',
			'adnl_popup_delay'         => 2,
			'adnl_popup_frequency'     => 30, // minutes (half hour)
			'adnl_popup_logo_height'   => 55,
			'adnl_popup_position'      => 'bottom-left',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
}
