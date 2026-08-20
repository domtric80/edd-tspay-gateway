<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Questo script va eseguito con wp eval-file.\n" );
}

$settings = get_option( 'edd_settings', array() );
$settings['test_mode']                 = '1';
$settings['currency']                  = 'EUR';
$settings['gateways']                  = array( 'tspay' => '1' );
$settings['default_gateway']           = 'tspay';
$settings['tspay_test_api_url']        = 'http://tspay-mock:8080';
$settings['tspay_test_api_key']        = 'mock-api-key';
$settings['tspay_test_merchant_ref']   = 'MOCKMERCHANT';
$settings['tspay_webhook_secret']      = 'mock-webhook-secret';
$settings['tspay_source_types']        = 'card,paypal';
$settings['tspay_locale']              = 'it-IT';
update_option( 'edd_settings', $settings );

$existing = get_page_by_path( 'prodotto-demo-tspay', OBJECT, 'download' );
if ( ! $existing ) {
	$download_id = wp_insert_post(
		array(
			'post_type'    => 'download',
			'post_status'  => 'publish',
			'post_title'   => 'Prodotto demo TS Pay',
			'post_name'    => 'prodotto-demo-tspay',
			'post_content' => 'Prodotto digitale di prova per il gateway TS Pay.',
		)
	);
	update_post_meta( $download_id, 'edd_price', '9.90' );
	update_post_meta( $download_id, '_edd_product_type', 'default' );
} else {
	$download_id = $existing->ID;
}

WP_CLI::success( 'EDD e TS Pay mock configurati. Prodotto demo: ' . get_permalink( $download_id ) );

