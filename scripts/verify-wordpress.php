<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Questo script va eseguito con wp eval-file.\n" );
}

$errors = array();
if ( ! defined( 'EDD_VERSION' ) || '3.7.0' !== EDD_VERSION ) {
	$errors[] = 'EDD 3.7.0 non attivo.';
}
if ( ! defined( 'EDD_TSPAY_VERSION' ) ) {
	$errors[] = 'Plugin TS Pay non attivo.';
}

$gateways = edd_get_payment_gateways();
if ( empty( $gateways['tspay'] ) ) {
	$errors[] = 'Gateway TS Pay non registrato.';
}

$orders = edd_get_orders( array( 'number' => 20, 'gateway' => 'tspay', 'type' => 'sale' ) );
$summary = array();
foreach ( $orders as $order ) {
	$summary[] = array(
		'id'             => $order->id,
		'status'         => $order->status,
		'gateway'        => $order->gateway,
		'total'          => $order->total,
		'currency'       => $order->currency,
		'transaction_id' => $order->get_transaction_id(),
		'order_key'      => edd_get_order_meta( $order->id, '_edd_tspay_order_key', true ),
		'charge_state'   => edd_get_order_meta( $order->id, '_edd_tspay_charge_state', true ),
	);
}

WP_CLI::line( wp_json_encode( array( 'orders' => $summary ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
if ( $errors ) {
	WP_CLI::error( implode( ' ', $errors ) );
}
WP_CLI::success( 'Registrazione gateway e ordini TS Pay verificati.' );

