<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_ScotiaHPP_Blocks extends AbstractPaymentMethodType {
    protected $name = 'scotiahpp_by_hexakode';
    protected $gateway;

    public function initialize() {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->gateway = $gateways['scotiahpp_by_hexakode'] ?? null;
    }

    public function is_active() {
        return $this->gateway && $this->gateway->is_available();
    }

    public function needs_shipping_address() {
    return false;
}


    public function get_payment_method_script_handles() {
        wp_enqueue_script(
            'wc-scotiahpp-blocks-integration',
            plugins_url('block/scotiahpp-blockv2.js', __DIR__),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'],
            null,
            true
        );

        $settings = get_option('woocommerce_scotiahpp_by_hexakode_settings', []);
        wp_add_inline_script(
            'wc-scotiahpp-blocks-integration',
            'window.wc = window.wc || {}; window.wc.wcSettings = window.wc.wcSettings || {}; window.wc.wcSettings["scotiahpp_by_hexakode_data"] = ' . wp_json_encode([
                'title' => $settings['title'] ?? 'ScotiaBank HPP',
                'description' => $settings['description'] ?? 'Pay securely via ScotiaBank.',
                'ariaLabel' => $settings['title'] ?? 'ScotiaBank Payment',
            ]) . ';',
            'before'
        );

        return ['wc-scotiahpp-blocks-integration'];
    }

    public function get_payment_method_data() {
        $settings = get_option('woocommerce_scotiahpp_by_hexakode_settings', []);
        return [
            'title' => $settings['title'] ?? 'ScotiaBank HPP',
            'description' => $settings['description'] ?? '',
            'ariaLabel' => $settings['title'] ?? 'ScotiaBank Payment',
            'supports' => ['products', 'subscriptions', 'default', 'virtual'],
        ];
    }

    public function enqueue_payment_method_script() {
    wp_enqueue_script(
        'wc-scotiahpp-blocks-integration',
        plugins_url('block/scotiahpp-blockv2.js', __DIR__),
        ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'],
        null,
        true
    );
}
}
