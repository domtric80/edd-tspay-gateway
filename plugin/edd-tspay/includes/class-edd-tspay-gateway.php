<?php

defined( 'ABSPATH' ) || exit;

/**
 * EDD gateway registration, settings and redirect flow.
 */
class EDD_TSPay_Gateway {
	/** Register hooks. */
	public static function init() {
		add_filter( 'edd_payment_gateways', array( __CLASS__, 'register_gateway' ) );
		add_filter( 'edd_settings_sections_gateways', array( __CLASS__, 'register_section' ) );
		add_filter( 'edd_settings_gateways', array( __CLASS__, 'register_settings' ) );
		add_action( 'edd_tspay_cc_form', '__return_false' );
		add_action( 'edd_gateway_tspay', array( __CLASS__, 'process_purchase' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_return' ), 1 );
		add_action( 'admin_post_edd_tspay_test_connection', array( __CLASS__, 'test_connection' ) );
		add_action( 'edd_view_order_details_payment_meta_after', array( __CLASS__, 'render_order_meta' ) );
	}

	/** @param array $gateways Existing gateways. @return array */
	public static function register_gateway( $gateways ) {
		$gateways['tspay'] = array(
			'admin_label'    => __( 'TS Pay', 'edd-tspay' ),
			'checkout_label' => __( 'TS Pay', 'edd-tspay' ),
			'supports'       => array(),
		);
		return $gateways;
	}

	/** @param array $sections Gateway sections. @return array */
	public static function register_section( $sections ) {
		$sections['tspay'] = __( 'TS Pay', 'edd-tspay' );
		return $sections;
	}

	/** @param array $settings Gateway settings. @return array */
	public static function register_settings( $settings ) {
		$webhook_url = rest_url( 'edd-tspay/v1/webhook' );
		$test_url    = wp_nonce_url( admin_url( 'admin-post.php?action=edd_tspay_test_connection' ), 'edd_tspay_test_connection' );

		$settings['tspay'] = array(
			'tspay_intro' => array(
				'id'   => 'tspay_intro',
				'name' => __( 'Configurazione TS Pay', 'edd-tspay' ),
				'type' => 'descriptive_text',
				'desc' => sprintf(
					/* translators: 1: connection test URL, 2: webhook URL. */
					__( 'Il gateway usa Incasso e-commerce immediato (LinkToPay). <a href="%1$s">Verifica la connessione attiva</a>.<br><strong>Webhook:</strong> <code>%2$s</code> — registra l’evento <code>tspay_charge.*</code> e inserisci qui sotto il secret restituito da TS Pay.', 'edd-tspay' ),
					esc_url( $test_url ),
					esc_html( $webhook_url )
				),
			),
			'tspay_test_api_url' => array(
				'id'   => 'tspay_test_api_url',
				'name' => __( 'URL API test', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'large',
				'std'  => 'https://api-staging.tspay.app',
			),
			'tspay_test_api_key' => array(
				'id'   => 'tspay_test_api_key',
				'name' => __( 'API key test', 'edd-tspay' ),
				'type' => 'password',
				'size' => 'large',
			),
			'tspay_test_merchant_ref' => array(
				'id'   => 'tspay_test_merchant_ref',
				'name' => __( 'Codice titolare test (merchantRef)', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'regular',
			),
			'tspay_live_api_url' => array(
				'id'   => 'tspay_live_api_url',
				'name' => __( 'URL API produzione', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'large',
				'std'  => 'https://api.tspay.app',
			),
			'tspay_live_api_key' => array(
				'id'   => 'tspay_live_api_key',
				'name' => __( 'API key produzione', 'edd-tspay' ),
				'type' => 'password',
				'size' => 'large',
			),
			'tspay_live_merchant_ref' => array(
				'id'   => 'tspay_live_merchant_ref',
				'name' => __( 'Codice titolare produzione (merchantRef)', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'regular',
			),
			'tspay_webhook_secret' => array(
				'id'   => 'tspay_webhook_secret',
				'name' => __( 'Webhook secret', 'edd-tspay' ),
				'desc' => __( 'Shared secret usato per verificare X-Message-Hash (HMAC-SHA256).', 'edd-tspay' ),
				'type' => 'password',
				'size' => 'large',
			),
			'tspay_source_types' => array(
				'id'   => 'tspay_source_types',
				'name' => __( 'Metodi TS Pay', 'edd-tspay' ),
				'desc' => __( 'Valori separati da virgola: card, paypal, sepa_debit, pis_charge.', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'large',
				'std'  => 'card,paypal',
			),
			'tspay_locale' => array(
				'id'   => 'tspay_locale',
				'name' => __( 'Lingua pagina di pagamento', 'edd-tspay' ),
				'type' => 'select',
				'options' => array( 'it-IT' => 'Italiano', 'en-EN' => 'English' ),
				'std'  => 'it-IT',
			),
			'tspay_confirmation_note' => array(
				'id'   => 'tspay_confirmation_note',
				'name' => __( 'Nota prima della conferma', 'edd-tspay' ),
				'type' => 'text',
				'size' => 'large',
			),
			'tspay_api_timeout' => array(
				'id'   => 'tspay_api_timeout',
				'name' => __( 'Timeout API (secondi)', 'edd-tspay' ),
				'type' => 'number',
				'size' => 'small',
				'std'  => 20,
			),
		);

		return $settings;
	}

	/**
	 * Create the pending EDD order and redirect to TS Pay.
	 *
	 * @param array $purchase_data EDD purchase data.
	 */
	public static function process_purchase( $purchase_data ) {
		if ( empty( $purchase_data['gateway_nonce'] ) || ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
			wp_die( esc_html__( 'Verifica di sicurezza non riuscita.', 'edd-tspay' ), 403 );
		}

		if ( 'EUR' !== strtoupper( edd_get_currency() ) ) {
			edd_set_error( 'tspay_currency', __( 'TS Pay accetta attualmente solo pagamenti in EUR.', 'edd-tspay' ) );
			edd_send_back_to_checkout( '?payment-mode=tspay' );
		}

		$payment_data = array(
			'price'        => $purchase_data['price'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'downloads'    => $purchase_data['downloads'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => 'tspay',
			'status'       => 'pending',
		);
		if ( ! empty( $purchase_data['date'] ) ) {
			$payment_data['date'] = $purchase_data['date'];
		}

		$order_id = edd_build_order( $payment_data );
		if ( ! $order_id ) {
			edd_record_gateway_error( __( 'Errore TS Pay', 'edd-tspay' ), __( 'Impossibile creare l’ordine EDD prima del reindirizzamento.', 'edd-tspay' ) );
			edd_set_error( 'tspay_order', __( 'Impossibile creare l’ordine. Riprova.', 'edd-tspay' ) );
			edd_send_back_to_checkout( '?payment-mode=tspay' );
		}

		$token        = wp_generate_password( 40, false, false );
		$token_hash   = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
		$return_url   = add_query_arg( array( 'edd-tspay-action' => 'return', 'payment-id' => $order_id, 'token' => $token ), home_url( '/' ) );
		$cancel_url   = add_query_arg( array( 'edd-tspay-action' => 'cancel', 'payment-id' => $order_id, 'token' => $token ), home_url( '/' ) );
		$source_types = self::source_types();

		$payload = array(
			'externalRef'      => 'edd-order-' . $order_id,
			'metadata'         => array( 'edd_order_id' => (string) $order_id ),
			'template'         => array(
				'title'      => sanitize_text_field( get_bloginfo( 'name' ) ),
				'desc'       => __( 'Acquisto digitale', 'edd-tspay' ),
				'paymentRef' => sprintf( __( 'Ordine #%d', 'edd-tspay' ), $order_id ),
			),
			'currency'         => 'EUR',
			'amount'           => (int) round( (float) $purchase_data['price'] * 100 ),
			'maxPaymentsNumber'=> 1,
			'langLocale'       => edd_get_option( 'tspay_locale', 'it-IT' ),
			'sourceTypes'      => $source_types,
			'callbackUrl'      => esc_url_raw( $return_url ),
			'cancelUrl'        => esc_url_raw( $cancel_url ),
			'contactRequest'   => false,
		);

		$note = trim( (string) edd_get_option( 'tspay_confirmation_note', '' ) );
		if ( $note ) {
			$payload['confirmationPageNote'] = sanitize_text_field( $note );
		}

		$response = ( new EDD_TSPay_API() )->create_link_to_pay( $payload );
		if ( is_wp_error( $response ) || empty( $response['orderKey'] ) || empty( $response['url'] ) || ! self::valid_http_url( $response['url'] ) ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : __( 'Risposta TS Pay incompleta.', 'edd-tspay' );
			edd_update_payment_status( $order_id, 'failed' );
			edd_record_gateway_error( __( 'Errore TS Pay', 'edd-tspay' ), $message, $order_id );
			edd_set_error( 'tspay_api', __( 'Non è stato possibile inizializzare il pagamento TS Pay. Riprova o scegli un altro metodo.', 'edd-tspay' ) );
			edd_send_back_to_checkout( '?payment-mode=tspay' );
		}

		edd_update_order_meta( $order_id, '_edd_tspay_order_key', sanitize_text_field( $response['orderKey'] ) );
		edd_update_order_meta( $order_id, '_edd_tspay_payment_url', esc_url_raw( $response['url'] ) );
		edd_update_order_meta( $order_id, '_edd_tspay_return_token_hash', $token_hash );
		EDD()->session->set( 'edd_resume_payment', $order_id );

		self::safe_external_redirect( $response['url'] );
	}

	/** Handle TS Pay browser return or cancellation. */
	public static function handle_return() {
		if ( empty( $_GET['edd-tspay-action'] ) ) {
			return;
		}

		$action   = sanitize_key( wp_unslash( $_GET['edd-tspay-action'] ) );
		$order_id = isset( $_GET['payment-id'] ) ? absint( $_GET['payment-id'] ) : 0;
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$order    = $order_id ? edd_get_order( $order_id ) : false;
		$expected = $order_id ? (string) edd_get_order_meta( $order_id, '_edd_tspay_return_token_hash', true ) : '';
		$actual   = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );

		if ( ! $order || 'tspay' !== $order->gateway || ! $expected || ! hash_equals( $expected, $actual ) ) {
			wp_die( esc_html__( 'Ritorno TS Pay non valido.', 'edd-tspay' ), esc_html__( 'Pagamento non verificato', 'edd-tspay' ), array( 'response' => 403 ) );
		}

		if ( 'cancel' === $action ) {
			if ( 'pending' === $order->status ) {
				edd_update_payment_status( $order_id, 'abandoned' );
			}
			EDD()->session->set( 'edd_resume_payment', null );
			edd_redirect( self::failure_url( $order_id ) );
		}

		if ( 'return' !== $action ) {
			return;
		}

		$order_key = (string) edd_get_order_meta( $order_id, '_edd_tspay_order_key', true );
		$response  = ( new EDD_TSPay_API() )->get_charges_for_order( $order_key );
		$charge_state = '';
		if ( ! is_wp_error( $response ) ) {
			$charge = EDD_TSPay_Order_Manager::select_charge( EDD_TSPay_Order_Manager::extract_charges( $response ) );
			if ( $charge ) {
				$charge_state = isset( $charge['state'] ) ? sanitize_key( $charge['state'] ) : '';
				$result = EDD_TSPay_Order_Manager::apply_charge( $order, $charge );
				if ( is_wp_error( $result ) ) {
					edd_record_gateway_error( __( 'Verifica TS Pay fallita', 'edd-tspay' ), $result->get_error_message(), $order_id );
				}
			}
		} else {
			edd_record_gateway_error( __( 'Verifica TS Pay non disponibile', 'edd-tspay' ), $response->get_error_message(), $order_id );
		}

		if ( in_array( $charge_state, array( 'failed', 'error' ), true ) ) {
			EDD()->session->set( 'edd_resume_payment', null );
			edd_redirect( self::failure_url( $order_id ) );
		}

		edd_empty_cart();
		EDD()->session->set( 'edd_resume_payment', null );
		edd_redirect( edd_get_receipt_page_uri( $order_id ) );
	}

	/** @return array<int,string> */
	private static function source_types() {
		$allowed = array( 'card', 'paypal', 'sepa_debit', 'pis_charge' );
		$values  = array_map( 'trim', explode( ',', strtolower( (string) edd_get_option( 'tspay_source_types', 'card,paypal' ) ) ) );
		$values  = array_values( array_intersect( $values, $allowed ) );
		return $values ?: array( 'card' );
	}

	/** @param string $url Trusted URL returned by the authenticated API. */
	private static function safe_external_redirect( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) use ( $host ) {
				if ( $host ) {
					$hosts[] = $host;
				}
				return array_unique( $hosts );
			}
		);
		edd_redirect( $url, 303 );
	}

	/** @return bool */
	private static function valid_http_url( $url ) {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && ! empty( $parts['host'] ) && ! empty( $parts['scheme'] ) && in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true );
	}

	/** @return string */
	private static function failure_url( $order_id ) {
		$failure_page = absint( edd_get_option( 'failure_page', 0 ) );
		$failure_url  = $failure_page ? get_permalink( $failure_page ) : home_url( '/' );
		return add_query_arg( 'payment-id', absint( $order_id ), $failure_url );
	}

	/** Perform a read-only authentication test from wp-admin. */
	public static function test_connection() {
		if ( ! current_user_can( 'manage_shop_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato.', 'edd-tspay' ), 403 );
		}
		check_admin_referer( 'edd_tspay_test_connection' );
		$response = ( new EDD_TSPay_API() )->authenticate();
		if ( is_wp_error( $response ) ) {
			wp_die(
				esc_html( $response->get_error_message() ),
				esc_html__( 'Connessione TS Pay non riuscita', 'edd-tspay' ),
				array( 'response' => 502, 'back_link' => true )
			);
		}
		wp_die(
			esc_html__( 'Connessione TS Pay verificata: API key attiva.', 'edd-tspay' ),
			esc_html__( 'Connessione TS Pay', 'edd-tspay' ),
			array( 'response' => 200, 'back_link' => true )
		);
	}

	/** @param int $order_id EDD order ID. */
	public static function render_order_meta( $order_id ) {
		$order = edd_get_order( $order_id );
		if ( ! $order || 'tspay' !== $order->gateway ) {
			return;
		}
		$fields = array(
			__( 'TS Pay orderKey', 'edd-tspay' ) => edd_get_order_meta( $order_id, '_edd_tspay_order_key', true ),
			__( 'TS Pay chargeKey', 'edd-tspay' ) => edd_get_order_meta( $order_id, '_edd_tspay_charge_key', true ),
			__( 'Stato TS Pay', 'edd-tspay' ) => edd_get_order_meta( $order_id, '_edd_tspay_charge_state', true ),
			__( 'Metodo TS Pay', 'edd-tspay' ) => edd_get_order_meta( $order_id, '_edd_tspay_payment_method', true ),
		);
		foreach ( $fields as $label => $value ) {
			if ( $value ) {
				printf( '<p><strong>%1$s:</strong><br>%2$s</p>', esc_html( $label ), esc_html( $value ) );
			}
		}
	}
}
