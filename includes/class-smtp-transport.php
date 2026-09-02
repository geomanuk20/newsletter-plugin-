<?php
/**
 * Standalone SMTP Transport Helper for Native PHP and WordPress
 */

class ADNL_SMTP_Transport {

	/**
	 * Send an HTML email directly via SMTP socket.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $html_body
	 * @param array  $config
	 * @return array array( 'success' => bool, 'message' => string )
	 */
	public static function send( $to, $subject, $html_body, $config = array() ) {
		$host       = trim( $config['host'] ?? '' );
		$port       = intval( $config['port'] ?? 587 );
		$encryption = strtolower( trim( $config['encryption'] ?? 'tls' ) );
		$username   = trim( $config['username'] ?? '' );
		$password   = trim( $config['password'] ?? '' );
		$from_email = trim( $config['from_email'] ?? 'newsletter@example.com' );
		$from_name  = trim( $config['from_name'] ?? 'Daily Newsletter' );
		$auth       = ! empty( $config['auth'] );

		// If Gmail, strip any spaces from 16-character app password and ensure host
		if ( strpos( $username, '@gmail.com' ) !== false || strpos( $host, 'gmail.com' ) !== false ) {
			$password = str_replace( ' ', '', $password );
			if ( empty( $host ) || strpos( $host, 'mailgun' ) !== false ) {
				$host = 'smtp.gmail.com';
			}
			if ( empty( $from_email ) || strpos( $from_email, 'example.com' ) !== false || strpos( $from_email, 'globalnews.com' ) !== false ) {
				$from_email = $username;
			}
		}

		if ( empty( $host ) ) {
			return array(
				'success' => false,
				'message' => 'SMTP Host is not configured. Please fill in your SMTP settings under the "SMTP & Delivery" tab.',
			);
		}

		$remote_target = ( 'ssl' === $encryption ) ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
		$timeout       = 15;
		$context       = stream_context_create( array(
			'ssl' => array(
				'verify_peer'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true,
				'SNI_enabled'       => true,
				'peer_name'         => $host,
			),
		) );

		$socket = @stream_socket_client( $remote_target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context );

		if ( ! $socket ) {
			$hint = ( 587 === $port ) ? ' (Note: Hostinger blocks outgoing port 587. Please use Port 465 with SSL in the SMTP tab).' : '';
			return array(
				'success' => false,
				'message' => sprintf( 'Could not connect to SMTP server %s:%d (%s - %s)%s', $host, $port, $errno, $errstr, $hint ),
			);
		}

		stream_set_timeout( $socket, $timeout );

		// 1. Read greeting
		$response = self::read_response( $socket );
		if ( substr( $response, 0, 3 ) !== '220' ) {
			fclose( $socket );
			return array( 'success' => false, 'message' => 'Invalid SMTP greeting: ' . $response );
		}

		// 2. Send EHLO
		$client_host = ! empty( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : ( ! empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
		if ( 'localhost' === $client_host && function_exists( 'get_home_url' ) ) {
			$parsed_host = parse_url( get_home_url(), PHP_URL_HOST );
			if ( ! empty( $parsed_host ) ) {
				$client_host = $parsed_host;
			}
		}
		fwrite( $socket, "EHLO {$client_host}\r\n" );
		$response = self::read_response( $socket );

		// 3. STARTTLS if required
		if ( 'tls' === $encryption ) {
			fwrite( $socket, "STARTTLS\r\n" );
			$response = self::read_response( $socket );
			if ( substr( $response, 0, 3 ) !== '220' ) {
				fclose( $socket );
				return array( 'success' => false, 'message' => 'STARTTLS failed: ' . $response );
			}

			// Secure connection with TLS
			$crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
			if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT' ) ) {
				$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
			}
			if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
				$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
			}

			$crypto_ok = stream_socket_enable_crypto( $socket, true, $crypto_method );
			if ( ! $crypto_ok ) {
				fclose( $socket );
				return array( 'success' => false, 'message' => 'TLS handshake failed with ' . $host );
			}

			// Resend EHLO after TLS handshake
			fwrite( $socket, "EHLO {$client_host}\r\n" );
			$response = self::read_response( $socket );
		}

		// 4. Authenticate
		if ( $auth && ! empty( $username ) ) {
			fwrite( $socket, "AUTH LOGIN\r\n" );
			$response = self::read_response( $socket );
			if ( substr( $response, 0, 3 ) !== '334' ) {
				fclose( $socket );
				return array( 'success' => false, 'message' => 'AUTH LOGIN not accepted: ' . $response );
			}

			fwrite( $socket, base64_encode( $username ) . "\r\n" );
			$response = self::read_response( $socket );
			if ( substr( $response, 0, 3 ) !== '334' ) {
				fclose( $socket );
				return array( 'success' => false, 'message' => 'Username rejected: ' . $response );
			}

			fwrite( $socket, base64_encode( $password ) . "\r\n" );
			$response = self::read_response( $socket );
			if ( substr( $response, 0, 3 ) !== '235' ) {
				fclose( $socket );
				return array( 'success' => false, 'message' => 'SMTP Authentication failed (Check username / app password): ' . $response );
			}
		}

		// 5. MAIL FROM
		fwrite( $socket, "MAIL FROM:<{$from_email}>\r\n" );
		$response = self::read_response( $socket );
		if ( substr( $response, 0, 3 ) !== '250' ) {
			fclose( $socket );
			return array( 'success' => false, 'message' => 'MAIL FROM error: ' . $response );
		}

		// 6. RCPT TO
		fwrite( $socket, "RCPT TO:<{$to}>\r\n" );
		$response = self::read_response( $socket );
		if ( substr( $response, 0, 3 ) !== '250' && substr( $response, 0, 3 ) !== '251' ) {
			fclose( $socket );
			return array( 'success' => false, 'message' => 'RCPT TO error: ' . $response );
		}

		// 7. DATA
		fwrite( $socket, "DATA\r\n" );
		$response = self::read_response( $socket );
		if ( substr( $response, 0, 3 ) !== '354' ) {
			fclose( $socket );
			return array( 'success' => false, 'message' => 'DATA command error: ' . $response );
		}

		// 8. Headers & Body
		$headers = array(
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . sprintf( '=?UTF-8?B?%s?= <%s>', base64_encode( $from_name ), $from_email ),
			'To: <' . $to . '>',
			'Subject: ' . sprintf( '=?UTF-8?B?%s?=', base64_encode( $subject ) ),
			'Date: ' . date( 'r' ),
			'X-Mailer: Auto Daily Newsletter WordPress Plugin',
		);

		// Normalize line endings to RFC standard CRLF and apply dot-stuffing
		$normalized_body = str_replace( array( "\r\n", "\r" ), "\n", $html_body );
		$normalized_body = str_replace( "\n", "\r\n", $normalized_body );
		$normalized_body = preg_replace( '/^\./m', '..', $normalized_body );

		$payload = implode( "\r\n", $headers ) . "\r\n\r\n" . $normalized_body . "\r\n.\r\n";
		fwrite( $socket, $payload );

		$response = self::read_response( $socket );
		fwrite( $socket, "QUIT\r\n" );
		fclose( $socket );

		if ( substr( $response, 0, 3 ) !== '250' ) {
			return array( 'success' => false, 'message' => 'Failed to deliver message: ' . $response );
		}

		return array(
			'success' => true,
			'message' => 'Message accepted by SMTP server: ' . trim( $response ),
		);
	}

	private static function read_response( $socket ) {
		$response = '';
		while ( ! feof( $socket ) ) {
			$line = fgets( $socket, 512 );
			if ( false === $line ) break;
			$response .= $line;
			if ( isset( $line[3] ) && ' ' === $line[3] ) {
				break;
			}
		}
		return trim( $response );
	}
}
