<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Toffi_Source_Blocks extends AbstractPaymentMethodType {

	protected $name = 'toffi-source';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_toffi-source_settings', [] );
	}

	public function is_active() {
		$gateway_settings = get_option( 'woocommerce_toffi-source_settings', [] );
		return ( isset( $gateway_settings['enabled'] ) && 'yes' === $gateway_settings['enabled'] );
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'toffi-source-blocks',
			plugins_url( '../blocks/payment-method.js', __FILE__ ),
			array( 'wc-blocks-registry', 'wp-element', 'wp-i18n' ),
			filemtime( plugin_dir_path( __FILE__ ) . '../blocks/payment-method.js' ),
			true
		);

		$src     = get_option( 'woocommerce_toffi-source_settings', [] );
		$onramp  = get_option( 'woocommerce_toffi-onramp-source_settings', [] );
		wp_localize_script(
			'toffi-source-blocks',
			'wc_toffi_gateways_data',
			[
				'toffi-source' => [
					'title'           => $src['title']       ?? 'Toffi Source',
					'description'     => $src['description'] ?? 'Pay securely with Toffi Source.',
					'icon_url'        => plugins_url( '../assets/toffi_icon.png', __FILE__ ),
					'cards_icon_url'  => plugins_url( '../assets/toffi_cards.png', __FILE__ ),
				],
				'toffi-onramp-source' => [
					'title'           => $onramp['title']       ?? 'Credit Cards',
					'description'     => $onramp['description'] ?? 'Pay securely with Credit Cards.',
					'icon_url'        => plugins_url( '../assets/toffi_icon_onramp.png', __FILE__ ),
					'cards_icon_url'  => '',
				],
			]
		);

		return array( 'toffi-source-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'] ?? 'Toffi Source',
			'description' => $this->settings['description'] ?? 'Pay securely with Toffi Source.',
			'icon'        => plugins_url( '../assets/toffi_icon.png', __FILE__ ),
		);
	}
}

add_action(
	'woocommerce_blocks_payment_method_type_registration',
	function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
		$registry->register( new WC_Toffi_Source_Blocks() );
	}
);

// Register Onramp integration
final class WC_Toffi_Onramp_Blocks extends AbstractPaymentMethodType {
    protected $name = 'toffi-onramp-source';
    public function initialize() {
        $this->settings = get_option( 'woocommerce_toffi-onramp-source_settings', [] );
    }
    public function is_active() {
        return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }
    public function get_payment_method_script_handles() {
        return [ 'toffi-source-blocks' ];
    }
    public function get_payment_method_data() {
        return [
            'title'       => $this->settings['title']       ?? 'Credit Cards',
            'description' => $this->settings['description'] ?? 'Pay securely with Credit Cards.',
            'icon'        => plugins_url( '../assets/toffi_icon_onramp.png', __FILE__ ),
        ];
    }
}
add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
        $registry->register( new WC_Toffi_Onramp_Blocks() );
    }
);