<?php

defined( 'ABSPATH' ) || exit;

/**
 * Signed TS Pay webhook endpoint.
 */
class EDD_TSPay_Webhook {
	/** Register hooks. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/** Register the public route; authentication is performed with HMAC. */
	public static function register_route() {
		register_rest_route(
			'edd-tspay/v1',
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Validate and process a TS Pay event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function receive( WP_REST_Request $request ) {
		$raw       = $request->get_body();
		$signature = trim( (string) $request->get_header( 'x-message-hash' ) );
		$secret    = trim( (string) edd_get_option( 'tspay_webhook_secret', '' ) );

		if ( ! $secret ) {
			return new WP_Error( 'tspay_webhook_not_configured', __( 'Webhook TS Pay non configurato.', 'edd-tspay' ), array( 'status' => 503 ) );
		}
		if ( ! self::valid_signature( $raw, $signature, $secret ) ) {
			return new WP_Error( 'tspay_invalid_signature', __( 'Firma webhook TS Pay non valida.', 'edd-tspay' ), array( 'status' => 401 ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'tspay_invalid_payload', __( 'Payload webhook TS Pay non valido.', 'edd-tspay' ), array( 'status' => 400 ) );
		}

		$entity = isset( $data['event']['entity'] ) ? sanitize_key( $data['event']['entity'] ) : '';
		if ( 'tspay_charge' !== $entity ) {
			return new WP_REST_Response( array( 'received' => true, 'ignored' => true ), 200 );
		}

		if ( ! self::merchant_matches( isset( $data['merchantRef'] ) ? $data['merchantRef'] : '' ) ) {
			return new WP_Error( 'tspay_wrong_merchant', __( 'merchantRef webhook inatteso.', 'edd-tspay' ), array( 'status' => 403 ) );
		}

		$charge = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();
		if ( empty( $charge['state'] ) && ! empty( $data['event']['state'] ) ) {
			$charge['state'] = $data['event']['state'];
		}
		$order_key = isset( $charge['orderKey'] ) ? sanitize_text_field( $charge['orderKey'] ) : '';
		$order     = $order_key ? EDD_TSPay_Order_Manager::find_by_order_key( $order_key ) : false;
		if ( ! $order ) {
			// A valid event can belong to a different store using the same merchant.
			return new WP_REST_Response( array( 'received' => true, 'ignored' => true ), 200 );
		}

		$result = EDD_TSPay_Order_Manager::apply_charge( $order, $charge );
		if ( is_wp_error( $result ) ) {
			edd_record_gateway_error( __( 'Webhook TS Pay rifiutato', 'edd-tspay' ), $result->get_error_message(), $order->id );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 422 ) );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/** @return bool */
	private static function valid_signature( $raw, $signature, $secret ) {
		if ( ! $signature ) {
			return false;
		}
		$signature = preg_replace( '/^sha256=/i', '', $signature );
		$hex       = hash_hmac( 'sha256', $raw, $secret );
		$base64    = base64_encode( hash_hmac( 'sha256', $raw, $secret, true ) );
		return hash_equals( $hex, strtolower( $signature ) ) || hash_equals( $base64, $signature );
	}

	/** @return bool */
	private static function merchant_matches( $merchant_ref ) {
		$merchant_ref = trim( (string) $merchant_ref );
		if ( ! $merchant_ref ) {
			return false;
		}
		$valid = array_filter(
			array(
				trim( (string) edd_get_option( 'tspay_test_merchant_ref', '' ) ),
				trim( (string) edd_get_option( 'tspay_live_merchant_ref', '' ) ),
			)
		);
		return empty( $valid ) || in_array( $merchant_ref, $valid, true );
	}
}

