<?php
/**
 * Subscriber Manager: Handles subscription CRUD, tokens, shortcode, and export.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADNL_Subscriber_Manager {

	/**
	 * Track whether popup widget has been rendered on the current request.
	 *
	 * @var bool
	 */
	public static $popup_rendered = false;

	public function __construct() {
		// AJAX endpoints for frontend subscription form
		add_action( 'wp_ajax_adnl_subscribe', array( $this, 'ajax_handle_subscription' ) );
		add_action( 'wp_ajax_nopriv_adnl_subscribe', array( $this, 'ajax_handle_subscription' ) );
	}

	/**
	 * Retrieve all active subscribers.
	 *
	 * @return array
	 */
	public function get_active_subscribers() {
		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id ASC" );
	}

	/**
	 * Add new subscriber.
	 *
	 * @param string $email
	 * @param string $name
	 * @param string $status
	 * @return int|WP_Error
	 */
	public function add_subscriber( $email, $name = '', $status = 'active' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'auto-daily-newsletter' ) );
		}

		$name  = sanitize_text_field( $name );
		$token = wp_generate_password( 40, false );

		// Ensure table exists (self-healing in case activation hook was skipped during plugin zip update)
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			require_once ADNL_PLUGIN_DIR . 'includes/class-activator.php';
			ADNL_Activator::create_tables();
		}

		// Check if already subscribed
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );

		if ( $existing ) {
			if ( 'active' === $existing->status ) {
				if ( ! headers_sent() ) {
					setcookie( 'adnl_subscribed', '1', time() + ( 365 * 86400 ), '/' );
				}
				return new WP_Error( 'already_subscribed', sprintf( __( '%s is already subscribed to our newsletter!', 'auto-daily-newsletter' ), $email ) );
			} else {
				// Reactivate unsubscribed contact
				$wpdb->update(
					$table,
					array(
						'status'     => 'active',
						'token'      => $token,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $existing->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
				return $existing->id;
			}
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$inserted = $wpdb->insert(
			$table,
			array(
				'email'      => $email,
				'name'       => $name,
				'token'      => $token,
				'status'     => $status,
				'ip_address' => $ip,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// Auto-repair tables in case schema was missing, then retry once
			require_once ADNL_PLUGIN_DIR . 'includes/class-activator.php';
			ADNL_Activator::create_tables();

			$inserted = $wpdb->insert(
				$table,
				array(
					'email'      => $email,
					'name'       => $name,
					'token'      => $token,
					'status'     => $status,
					'ip_address' => $ip,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_error', __( 'Could not save subscription. Please try again.', 'auto-daily-newsletter' ) );
		}

		$subscriber_id = $wpdb->insert_id;

		return $subscriber_id;
	}

	/**
	 * Send welcome email with today's edition to newly subscribed reader.
	 *
	 * @param string $email
	 * @param string $name
	 * @param string $token
	 */
	public function send_welcome_email( $email, $name, $token ) {
		$post_collector   = new ADNL_Post_Collector();
		$template_builder = new ADNL_Template_Builder();
		$mailer           = new ADNL_Mailer();

		$posts = $post_collector->get_latest_news_posts();
		$site_name = get_bloginfo( 'name' );
		$subject = sprintf( __( 'Welcome to %s! Today\'s Daily Digest', 'auto-daily-newsletter' ), $site_name );

		$subscriber = (object) array(
			'email' => $email,
			'name'  => $name,
			'token' => $token,
		);

		$base_html         = $template_builder->build_digest_html( $posts );
		$personalized_html = $template_builder->personalize_html( $base_html, $subscriber );

		$mailer->send_single_email( $email, $subject, $personalized_html, $token );
	}

	/**
	 * Unsubscribe user via security token.
	 *
	 * @param string $token
	 * @return bool
	 */
	public function unsubscribe_by_token( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';

		$sanitized_token = sanitize_text_field( $token );
		if ( empty( $sanitized_token ) ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			array(
				'status'     => 'unsubscribed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'token' => $sanitized_token ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return ( false !== $updated && $updated > 0 );
	}

	/**
	 * Delete subscriber permanently.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete_subscriber( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => intval( $id ) ),
			array( '%d' )
		);

		return ( false !== $deleted );
	}

	/**
	 * AJAX endpoint to handle frontend subscribe form submissions.
	 */
	public function ajax_handle_subscription() {
		check_ajax_referer( 'adnl_subscribe_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'auto-daily-newsletter' ) ) );
		}

		$result = $this->add_subscriber( $email, $name, 'active' );

		if ( is_wp_error( $result ) ) {
			if ( 'already_subscribed' === $result->get_error_code() && ! headers_sent() ) {
				setcookie( 'adnl_subscribed', '1', time() + ( 365 * 86400 ), '/' );
			}
			wp_send_json_error( array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			) );
		}

		if ( ! headers_sent() ) {
			setcookie( 'adnl_subscribed', '1', time() + ( 365 * 86400 ), '/' );
		}

		wp_send_json_success( array(
			'message' => __( 'Thank you! You have successfully subscribed to our daily digest.', 'auto-daily-newsletter' ),
		) );
	}

	/**
	 * Shortcode callback: [daily_newsletter_form]
	 *
	 * @param array $atts
	 * @return string HTML output
	 */
	public function render_subscribe_form_shortcode( $atts ) {
		$site_name = get_bloginfo( 'name' );
		if ( empty( $site_name ) ) {
			$site_name = 'Our Daily Newsletter';
		}

		$custom_header_opt = get_option( 'adnl_header_title', '' );
		if ( ! empty( $custom_header_opt ) ) {
			$default_title = str_replace( '{site_name}', $site_name, $custom_header_opt );
		} else {
			$default_title = 'Subscribe to ' . $site_name;
		}

		if ( trim( $default_title ) === 'Subscribe to' ) {
			$default_title = 'Subscribe to ' . $site_name;
		}

		$atts = shortcode_atts(
			array(
				'title'       => $default_title,
				'subtitle'    => __( 'Get the top curated news delivered straight to your inbox every morning.', 'auto-daily-newsletter' ),
				'button_text' => __( 'Subscribe Now', 'auto-daily-newsletter' ),
				'show_name'   => 'no',
				'show_logo'   => 'yes',
			),
			$atts,
			'daily_newsletter_form'
		);

		ob_start();
		include ADNL_PLUGIN_DIR . 'templates/frontend-form.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode callback: [daily_newsletter_popup]
	 *
	 * @return string HTML output
	 */
	public function render_popup_shortcode() {
		if ( self::$popup_rendered ) {
			return '';
		}
		self::$popup_rendered = true;
		ob_start();
		include ADNL_PLUGIN_DIR . 'templates/popup-widget.php';
		return ob_get_clean();
	}

	/**
	 * Render bottom-left slide-in popup newsletter widget.
	 */
	public function render_popup_widget() {
		try {
			if ( self::$popup_rendered ) {
				return;
			}
			self::$popup_rendered = true;

			// Allow forcing popup preview on any device via ?popup=1, ?show_popup=1, or ?preview_popup=1
			$force_preview = isset( $_GET['preview_popup'] ) || isset( $_GET['show_popup'] ) || isset( $_GET['popup'] );

			$popup_enabled = get_option( 'adnl_popup_enabled', 1 );
			if ( false === $popup_enabled || '' === $popup_enabled ) {
				$popup_enabled = 1;
			}

			if ( ! $force_preview && '0' === strval( $popup_enabled ) && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) ) {
				return;
			}

			include ADNL_PLUGIN_DIR . 'templates/popup-widget.php';
		} catch ( \Throwable $e ) {
			// Fail silently so wp_footer never aborts or corrupts theme output
		}
	}

	/**
	 * Export subscribers to CSV.
	 */
	public static function export_subscribers_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'auto-daily-newsletter' ) );
		}

		check_admin_referer( 'adnl_export_subscribers' );

		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';
		$results = $wpdb->get_results( "SELECT id, email, name, status, created_at FROM {$table} ORDER BY id DESC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Email', 'Name', 'Status', 'Subscribed Date' ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				fputcsv( $output, $row );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Download sample CSV template for importing subscribers.
	 */
	public static function download_sample_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'auto-daily-newsletter' ) );
		}

		check_admin_referer( 'adnl_download_sample_csv' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-sample-template.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Email', 'Name', 'Status' ) );
		fputcsv( $output, array( 'alex@example.com', 'Alex Morgan', 'active' ) );
		fputcsv( $output, array( 'sarah@example.com', 'Sarah Connor', 'active' ) );
		fputcsv( $output, array( 'reader@news.org', 'Daily Reader', 'active' ) );

		fclose( $output );
		exit;
	}

	/**
	 * Import subscribers from a CSV file.
	 *
	 * @param string $file_path Absolute path to the uploaded CSV file.
	 * @param bool   $update_existing Whether to update existing subscriber names.
	 * @return array Import summary stats.
	 */
	public static function import_subscribers_from_csv( $file_path, $update_existing = false ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Uploaded CSV file could not be read.', 'auto-daily-newsletter' ),
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'adnl_subscribers';

		// Ensure table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			require_once ADNL_PLUGIN_DIR . 'includes/class-activator.php';
			ADNL_Activator::create_tables();
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to open CSV file.', 'auto-daily-newsletter' ),
			);
		}

		$imported = 0;
		$skipped  = 0;
		$invalid  = 0;
		$total    = 0;

		// Detect separator by reading first line
		$first_line = fgets( $handle );
		$delimiter  = ',';
		if ( false !== $first_line ) {
			if ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) {
				$delimiter = ';';
			} elseif ( substr_count( $first_line, "\t" ) > substr_count( $first_line, ',' ) ) {
				$delimiter = "\t";
			}
			rewind( $handle );
		}

		$header = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
		if ( empty( $header ) ) {
			fclose( $handle );
			return array(
				'success' => false,
				'message' => __( 'The uploaded file is empty.', 'auto-daily-newsletter' ),
			);
		}

		// Remove BOM from first column header if present
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/[\xEF\xBB\xBF]/', '', $header[0] );
		}

		$email_col  = null;
		$name_col   = null;
		$status_col = null;

		// Match header columns
		foreach ( $header as $idx => $col_name ) {
			$col_clean = strtolower( trim( (string) $col_name ) );
			if ( in_array( $col_clean, array( 'email', 'email address', 'e-mail', 'mail' ), true ) ) {
				$email_col = $idx;
			} elseif ( in_array( $col_clean, array( 'name', 'full name', 'first name', 'subscriber name' ), true ) ) {
				$name_col = $idx;
			} elseif ( in_array( $col_clean, array( 'status' ), true ) ) {
				$status_col = $idx;
			}
		}

		// If header did not contain 'email', check if row 1 itself is data or assume col 0 is email
		$first_row_is_data = false;
		if ( null === $email_col ) {
			if ( is_email( trim( $header[0] ) ) ) {
				$email_col = 0;
				$name_col  = isset( $header[1] ) ? 1 : null;
				$first_row_is_data = true;
			} else {
				$email_col = 0;
				$name_col  = isset( $header[1] ) ? 1 : null;
			}
		}

		$rows_to_process = array();
		if ( $first_row_is_data ) {
			$rows_to_process[] = $header;
		}

		while ( ( $data = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) !== false ) {
			if ( ! empty( $data ) && array_filter( $data ) ) {
				$rows_to_process[] = $data;
			}
		}
		fclose( $handle );

		foreach ( $rows_to_process as $row ) {
			$total++;
			$raw_email  = isset( $row[ $email_col ] ) ? trim( $row[ $email_col ] ) : '';
			$raw_name   = ( null !== $name_col && isset( $row[ $name_col ] ) ) ? trim( $row[ $name_col ] ) : '';
			$raw_status = ( null !== $status_col && isset( $row[ $status_col ] ) ) ? strtolower( trim( $row[ $status_col ] ) ) : 'active';

			$email = sanitize_email( $raw_email );
			if ( ! is_email( $email ) ) {
				$invalid++;
				continue;
			}

			$name   = sanitize_text_field( $raw_name );
			$status = in_array( $raw_status, array( 'active', 'unsubscribed' ), true ) ? $raw_status : 'active';

			// Check if email already exists
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, name FROM {$table} WHERE email = %s", $email ) );

			if ( $existing ) {
				if ( $update_existing ) {
					$wpdb->update(
						$table,
						array(
							'name'       => ! empty( $name ) ? $name : $existing->name,
							'status'     => $status,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $existing->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
					$imported++;
				} else {
					$skipped++;
				}
			} else {
				$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 40, false ) : md5( $email . uniqid( '', true ) );
				$inserted = $wpdb->insert(
					$table,
					array(
						'email'      => $email,
						'name'       => $name,
						'status'     => $status,
						'token'      => $token,
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( false !== $inserted ) {
					$imported++;
				} else {
					$invalid++;
				}
			}
		}

		return array(
			'success'  => true,
			'imported' => $imported,
			'skipped'  => $skipped,
			'invalid'  => $invalid,
			'total'    => $total,
		);
	}
}
