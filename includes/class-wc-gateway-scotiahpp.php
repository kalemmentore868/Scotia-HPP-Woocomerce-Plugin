<?php
if (!defined('ABSPATH')) exit;

class WC_Gateway_ScotiaHPP extends WC_Payment_Gateway {

    public $id;
    public $method_title;
    public $method_description;
    public $has_fields;
    public $supports;
    public $title;
    public $description;
    public $enabled;
    public $sandbox_mode;
    public $test_store_id;
    public $test_shared_secret;
    public $prod_store_id;
    public $prod_shared_secret;


    public function __construct() {
    $this->id = 'scotiahpp_by_hexakode';
    $this->method_title = __( 'ScotiaBank HPP', 'scotia-hpp-woo' );
    $this->method_description = __( 'Accept credit/debit card payments via ScotiaBank HPP.', 'scotia-hpp-woo' );
    $this->has_fields = false;
    $this->supports = [ 'products', 'subscriptions', 'default', 'virtual' ];

    $this->init_form_fields();
    $this->init_settings();

    $this->title               = $this->get_option( 'title' );
    $this->description         = $this->get_option( 'description' );
    $this->enabled             = $this->get_option( 'enabled' );
    $this->sandbox_mode        = $this->get_option( 'sandbox_mode' ) === 'yes';
    $this->test_store_id       = $this->get_option( 'test_store_id' );
    $this->test_shared_secret  = $this->get_option( 'test_shared_secret' );
    $this->prod_store_id       = $this->get_option( 'prod_store_id' );
    $this->prod_shared_secret  = $this->get_option( 'prod_shared_secret' );

    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'handle_scotiahpp_redirect' ], 10, 1 );

}

   public function init_form_fields() {
    $this->form_fields = [
        'enabled' => [
            'title' => __( 'Enable', 'scotia-hpp-woo' ),
            'type' => 'checkbox',
            'label' => __( 'Enable ScotiaBank HPP', 'scotia-hpp-woo' ),
            'default' => 'no',
        ],
        'title' => [
            'title' => __( 'Title', 'scotia-hpp-woo' ),
            'type' => 'text',
            'default' => 'ScotiaBank HPP',
        ],
        'description' => [
            'title' => __( 'Description', 'scotia-hpp-woo' ),
            'type' => 'textarea',
            'default' => 'Pay securely using ScotiaBank Hosted Payment Page.',
        ],
        'sandbox_mode' => [
            'title' => __( 'Sandbox Mode', 'scotia-hpp-woo' ),
            'type' => 'checkbox',
            'label' => __( 'Enable test mode', 'scotia-hpp-woo' ),
            'default' => 'yes',
        ],
        'test_store_id' => [
            'title' => __( 'Test Store ID', 'scotia-hpp-woo' ),
            'type' => 'text',
        ],
        'test_shared_secret' => [
            'title' => __( 'Test Shared Secret', 'scotia-hpp-woo' ),
            'type' => 'text',
        ],
        'prod_store_id' => [
            'title' => __( 'Production Store ID', 'scotia-hpp-woo' ),
            'type' => 'text',
        ],
        'prod_shared_secret' => [
            'title' => __( 'Production Shared Secret', 'scotia-hpp-woo' ),
            'type' => 'text',
        ],
    ];
}


    public function is_available() {
        return 'yes' === $this->enabled;
    }

        private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'hexakode' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

    private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'hexa-payments-woo' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'hexa-payments-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'hexa-payments-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'hexa-payments-woo' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

    private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}


  public function process_payment($order_id) {
    

    $order = wc_get_order($order_id);
    if (! $order) {
    
        wc_add_notice(__('Invalid order. Please try again.', 'scotia-hpp-woo'), 'error');
        return ['result' => 'failure'];
    }

    $sandbox_mode = $this->get_option('sandbox_mode') === 'yes';
    $currency = $order->get_currency();

    if ($sandbox_mode && strtoupper($currency) !== 'USD') {
        wc_add_notice(__('In sandbox mode, only USD is allowed for ScotiaBank HPP.', 'scotia-hpp-woo'), 'error');
        
        return ['result' => 'failure'];
    }

    if (! $sandbox_mode && strtoupper($currency) !== 'TTD') {
        wc_add_notice(__('In live mode, only TTD is allowed for ScotiaBank HPP.', 'scotia-hpp-woo'), 'error');
        
        return ['result' => 'failure'];
    }

    $redirect_url = add_query_arg('scotiahpp_redirect', $order_id, home_url());

   

    return [
        'result' => 'success',
        'redirect' => $redirect_url,
    ];
}

    private function create_scotiahpp_url($order) {
        
    $sandbox_mode = $this->get_option('sandbox_mode') === 'yes';

    

    $store_id = $sandbox_mode ? $this->get_option('test_store_id') : $this->get_option('prod_store_id');
    $shared_secret = $sandbox_mode ? $this->get_option('test_shared_secret') : $this->get_option('prod_shared_secret');

    if (empty($store_id) || empty($shared_secret)) {
        
        return false;
    }

    $charge_total = number_format($order->get_total(), 2, '.', '');
    $currency = $sandbox_mode ? '840' : '780';
    $order_id = $order->get_id();
    $language = 'en_GB';
    $checkout_option = 'combinedpage';
    $hash_algorithm = 'HMACSHA256';
    $txn_type = 'sale';
    $timezone = 'America/Sao_Paulo';

    $txndatetime = date('Y:m:d-H:i:s');
    $separator = '|';

    $response_success_url = add_query_arg(
    [
        'payment_status' => 'scotia_hpp_callback',
        'order_id'       => $order_id,
        'status'         => 'success'
    ],
    $this->get_return_url( $order )
);

$unique_oid = $order_id . '-' . time();

$order->update_meta_data('_scotiahpp_oid', $unique_oid);
$order->save();

$response_fail_url = add_query_arg(
    [
        'payment_status' => 'scotia_hpp_callback',
        'order_id'       => $order_id,
        'status'         => 'failure'
    ],
    $this->get_return_url( $order )
);

    if ($sandbox_mode) {
        $string_to_hash = implode($separator, [
            $charge_total,
            $checkout_option,
            $currency,
            $hash_algorithm,
            $language,
            $unique_oid,
            $response_fail_url,
            $response_success_url,
            $store_id,
            $timezone,
            $txndatetime,
            $txn_type
        ]);
    } else {
        $string_to_hash = implode($separator, [
            'true',
            $charge_total,
            $checkout_option,
            $currency,
            $hash_algorithm,
            $language,
            $unique_oid,
            $response_fail_url,
            $response_success_url,
            $store_id,
            '03',
            '01',
            $timezone,
            $txndatetime,
            $txn_type
        ]);
    }

 


    $hash = base64_encode(hash_hmac('sha256', $string_to_hash, $shared_secret, true));

    
    $action_url = $sandbox_mode
        ? 'https://test.ipg-online.com/connect/gateway/processing'
        : 'https://www2.ipg-online.com/connect/gateway/processing';

    $fields = [
        'storename' => $store_id,
        'timezone' => $timezone,
        'language' => $language,
        'txntype' => $txn_type,
        'chargetotal' => $charge_total,
        'currency' => $currency,
        'txndatetime' => $txndatetime,
        'hashExtended' => $hash,
        'hash_algorithm' => $hash_algorithm,
        'oid' => $unique_oid,
        'responseSuccessURL' => $response_success_url,
        'responseFailURL' => $response_fail_url,
        'checkoutoption' => $checkout_option
    ];

    if (!$sandbox_mode) {
        $fields['authenticateTransaction'] = 'true';
        $fields['threeDSTransType'] = '01';
        $fields['threeDSRequestorChallengeIndicator'] = '03';
    }

    $form_fields = '';
    foreach ($fields as $key => $value) {
        $form_fields .= sprintf('<input type="hidden" name="%s" value="%s" />', esc_attr($key), esc_attr($value));
    }

    
    return 'data:text/html,' . rawurlencode("
        <!DOCTYPE HTML>
        <html>
        <head><title>Redirecting...</title></head>
        <body>
        <form id='scotiaForm' method='post' action='{$action_url}'>
            {$form_fields}
        </form>
        <script>document.getElementById('scotiaForm').submit();</script>
        </body>
        </html>
    ");
}

public function generate_scotiahpp_form($order) {
    $html = $this->create_scotiahpp_url($order);
    return urldecode(str_replace('data:text/html,', '', $html));
}


public function handle_scotiahpp_redirect( $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        echo '<p>' . esc_html__( 'Order not found.', 'scotia-hpp-woo' ) . '</p>';
        return;
    }

    $status = sanitize_text_field( $_GET['status'] ?? '' );
    // $transaction_id = sanitize_text_field( $_GET['transaction_id'] ?? '' );

    // if ( $transaction_id ) {
    //     $order->update_meta_data( '_scotiahpp_transaction_id', $transaction_id );
    // }

    if ( $status === 'success' ) {
        if ( $order->get_status() !== 'completed' ) {
            // $order->payment_complete( $transaction_id );
            $order->update_status( 'completed', __( 'Payment completed via ScotiaBank HPP.', 'scotia-hpp-woo' ) );
            
            WC()->cart->empty_cart();
        }
        echo '<p>' . esc_html__( 'Payment successful. Your order is now complete.', 'scotia-hpp-woo' ) . '</p>';
    } elseif ( $status === 'failure' || $status === 'failed' ) {
        if ( $order->get_status() !== 'failed' ) {
            $order->update_status( 'failed', __( 'Payment failed via ScotiaBank HPP.', 'scotia-hpp-woo' ) );

        }
        echo '<p>' . esc_html__( 'Payment failed. Please try again or contact support.', 'scotia-hpp-woo' ) . '</p>';
    }

    $order->save();
}


}
