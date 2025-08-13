<?php
/**
 * Plugin Name: Toffi Source Gateway
 * Description: Accept custom credit card payments on your online store.
 * Author: Kagami
 * Author URI: Kagami.dev
 * Version: 3.1.9
 * Requires at least: 6.1
 * WC requires at least: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Toffi_Source_Gateway_Init {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'plugin_setup' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
	}

	public static function plugin_setup() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_action_links' ) );
		} else {
			add_action( 'admin_notices', array( __CLASS__, 'install_woocommerce_core_notice' ) );
		}
	}

	public static function load_plugin_textdomain() {
		load_plugin_textdomain(
			'wc-toffi-source-gateway',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	public static function add_gateway( $gateways ) {
		$gateways[] = 'WC_Toffi_Source_Gateway';
		$gateways[] = 'WC_Toffi_Onramp_Gateway';
		return $gateways;
	}

	public static function includes() {
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-toffi-source-gateway.php';
			require_once 'includes/class-wc-toffi-onramp-gateway.php';
			require_once 'includes/custom-endpoint.php';
			require_once 'includes/custom-endpoint-onramp.php';

			// Load blocks integration at the right time:
			add_action( 'woocommerce_blocks_loaded', function() {
				if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
					require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-toffi-source-blocks.php';
				}
			} );
		}
	}

	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=toffi-source' ) . '">' .
				__( 'Settings', 'wc-toffi-source-gateway' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}

	public static function install_woocommerce_core_notice() {
		echo '<div class="notice notice-error is-dismissible"><p>' .
			__( 'WooCommerce core plugin is required.', 'wc-toffi-source-gateway' ) .
			'</p></div>';
	}
}

WC_Toffi_Source_Gateway_Init::init();

// Block compatibility for both gateways
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\IntegrationRegistry' ) ) {
		$registry = \Automattic\WooCommerce\Blocks\Payments\Integrations\IntegrationRegistry::class;
		$registry::register_compatibility( 'toffi-source',       [ 'cart_checkout_blocks' => true ] );
		$registry::register_compatibility( 'toffi-onramp-source',[ 'cart_checkout_blocks' => true ] );
	}
} );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// Tell Blocks which gateways are allowed
add_filter( 'woocommerce_blocks_payment_gateway_type', function( $types ) {
	$types[] = 'toffi-source';
	$types[] = 'toffi-onramp-source';
	return $types;
} );