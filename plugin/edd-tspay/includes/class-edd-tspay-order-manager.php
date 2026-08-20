<?php

defined( 'ABSPATH' ) || exit;

/**
 * Maps signed TS Pay charge notifications onto EDD orders.
 */
class EDD_TSPay_Order_Manager {
	/**
	 * Find the EDD order linked to a TS Pay order key.
	 *
	 * @param string $order_key TS Pay order key.
	 * @return EDD\Orders\Order|false
	 */
	public static function find_by_order_key( $order_key ) {
		$orders = edd_get_orders(
			array(
				'number'     => 1,
				'type'       => 'sale',
				'meta_query' => array(
					array(
						'key'   => '_edd_tspay_order_key',
						'value' => sanitize_text_field( $order_key ),
					),
				),
			)
		);

		return ! empty( $orders ) ? reset( $orders ) : false;
	}

	/**
	 * Normalize the possible list wrappers used by TS Pay.
	 *
	 * @param array $response API response.
	 * @return array<int,array>
	 */
	public static function extract_charges( array $response ) {
		foreach ( array( 'charges', 'items', 'data', 'results' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				$response = $response[ $key ];
				break;
			}
		}

		if ( isset( $response['chargeKey'] ) || isset( $response['state'] ) ) {
			return array( $response );
		}

		return array_values( array_filter( $response, 'is_array' ) );
	}

	/**
	 * Select the most useful charge when multiple attempts exist.
	 *
	 * @param array<int,array> $charges Charge list.
	 * @return array|null
	 */
	public static function select_charge( array $charges ) {
		$priority = array(
			'active'   => 50,
			'pending'  => 40,
			'refunded' => 30,
			'failed'   => 20,
			'error'    => 10,
		);
		$selected = null;
		$score    = -1;

		foreach ( $charges as $charge ) {
			$state        = isset( $charge['state'] ) ? sanitize_key( $charge['state'] ) : '';
			$current_score = isset( $priority[ $state ] ) ? $priority[ $state ] : 0;
			if ( $current_score > $score ) {
				$selected = $charge;
				$score    = $current_score;
			}
		}

		return $selected;
	}

	/**
	 * Validate and apply a charge to an EDD order.
	 *
	 * @param EDD\Orders\Order $order  EDD order.
	 * @param array            $charge TS Pay charge payload.
	 * @return true|WP_Error
	 */
	public static function apply_charge( $order, array $charge ) {
		if ( ! $order || 'tspay' !== $order->gateway ) {
			return new WP_Error( 'tspay_wrong_gateway', __( 'L’ordine non appartiene al gateway TS Pay.', 'edd-tspay' ) );
		}

		$order_key = isset( $charge['orderKey'] ) ? sanitize_text_field( $charge['orderKey'] ) : '';
		$expected  = (string) edd_get_order_meta( $order->id, '_edd_tspay_order_key', true );
		if ( empty( $order_key ) || ! hash_equals( $expected, $order_key ) ) {
			return new WP_Error( 'tspay_order_key_mismatch', __( 'Il riferimento ordine TS Pay non coincide.', 'edd-tspay' ) );
		}

		if ( isset( $charge['currency'] ) && strtoupper( $charge['currency'] ) !== strtoupper( $order->currency ) ) {
			return new WP_Error( 'tspay_currency_mismatch', __( 'La valuta del pagamento TS Pay non coincide.', 'edd-tspay' ) );
		}

		if ( isset( $charge['amount'] ) && (int) $charge['amount'] !== (int) round( (float) $order->total * 100 ) ) {
			return new WP_Error( 'tspay_amount_mismatch', __( 'L’importo del pagamento TS Pay non coincide.', 'edd-tspay' ) );
		}

		$state      = isset( $charge['state'] ) ? sanitize_key( $charge['state'] ) : '';
		$charge_key = isset( $charge['chargeKey'] ) ? sanitize_text_field( $charge['chargeKey'] ) : '';
		$last_state = (string) edd_get_order_meta( $order->id, '_edd_tspay_charge_state', true );

		if ( $charge_key ) {
			edd_update_order_meta( $order->id, '_edd_tspay_charge_key', $charge_key );
			edd_set_payment_transaction_id( $order->id, $charge_key, (float) $order->total );
		}
		edd_update_order_meta( $order->id, '_edd_tspay_charge_state', $state );
		if ( isset( $charge['payMethod']['type'] ) ) {
			edd_update_order_meta( $order->id, '_edd_tspay_payment_method', sanitize_key( $charge['payMethod']['type'] ) );
		}

		if ( 'active' === $state && 'complete' !== $order->status ) {
			edd_update_payment_status( $order->id, 'complete' );
		} elseif ( in_array( $state, array( 'failed', 'error' ), true ) && ! in_array( $order->status, array( 'complete', 'refunded' ), true ) ) {
			edd_update_payment_status( $order->id, 'failed' );
		} elseif ( 'refunded' === $state && 'refunded' !== $order->status ) {
			edd_update_payment_status( $order->id, 'refunded' );
		}

		if ( $state && $state !== $last_state && function_exists( 'edd_insert_payment_note' ) ) {
			edd_insert_payment_note(
				$order->id,
				sprintf(
					/* translators: 1: TS Pay state, 2: charge key. */
					__( 'TS Pay: stato addebito “%1$s” (chargeKey: %2$s).', 'edd-tspay' ),
					$state,
					$charge_key ?: '—'
				)
			);
		}

		return true;
	}
}

