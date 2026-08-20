<?php
/**
 * Plugin Name: TS Pay for Easy Digital Downloads
 * Description: Gateway TS Pay con incasso e-commerce immediato, verifica server-to-server e webhook firmati per Easy Digital Downloads.
 * Version:     0.1.1
 * Author:      TS Pay EDD Integration
 * Text Domain: edd-tspay
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * EDD requires at least: 3.3
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'EDD_TSPAY_VERSION', '0.1.1' );
define( 'EDD_TSPAY_FILE', __FILE__ );
define( 'EDD_TSPAY_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Load the integration only after EDD has initialized its functions.
 */
function edd_tspay_bootstrap() {
	load_plugin_textdomain( 'edd-tspay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! defined( 'EDD_VERSION' ) || version_compare( EDD_VERSION, '3.3.0', '<' ) ) {
		add_action( 'admin_notices', 'edd_tspay_dependency_notice' );
		return;
	}

	require_once EDD_TSPAY_DIR . 'includes/class-edd-tspay-api.php';
	require_once EDD_TSPAY_DIR . 'includes/class-edd-tspay-order-manager.php';
	require_once EDD_TSPAY_DIR . 'includes/class-edd-tspay-gateway.php';
	require_once EDD_TSPAY_DIR . 'includes/class-edd-tspay-webhook.php';

	EDD_TSPay_Gateway::init();
	EDD_TSPay_Webhook::init();
}
add_action( 'plugins_loaded', 'edd_tspay_bootstrap', 20 );

/**
 * Display the dependency error without causing a fatal error on the storefront.
 */
function edd_tspay_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'TS Pay for Easy Digital Downloads richiede Easy Digital Downloads 3.3 o successivo.', 'edd-tspay' );
	echo '</p></div>';
}
