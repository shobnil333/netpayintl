<?php
/**
 * Plugin Name: NetpayIntl
 * Plugin URI: http://www.netpay-intl.com
 * Description: Process payment using NetpayIntl for woocommerce 2.x.
 * Version: 1.16
 * Author: Udi Azulay
 * Author URI: http://www.netpay-intl.com
 * License: GPL2
 */
 
// check that woocommerce is an active plugin before initializing payment gateway
if ( in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins' )) ) 
{
    add_action('plugins_loaded', 'NetpayIntl_init', 0);
    add_filter('woocommerce_payment_gateways', 'NetpayIntl_add_gateway' );
}

function NetpayIntl_add_gateway( $methods ) { $methods[] = 'WC_Gateway_NetpayIntl'; return $methods; }

function NetpayIntl_init() {
  class WC_Gateway_NetpayIntl extends WC_Payment_Gateway {

	  var $notify_url;

	  public function __construct() {

		  $this->id                = 'netpayintl';
		  $this->icon              = apply_filters( 'woocommerce_NetpayIntl_icon', plugin_dir_url('') . '/WC_Netpayintl/NetpayIntl_Logo.jpg' );
		  $this->has_fields        = false;
		  $this->order_button_text = __( 'Proceed to NetpayIntl', 'woocommerce' );
		  $this->service_directUrl = 'https://process.netpay-intl.com/member/remote_charge.asp';
		  $this->service_hppUrl    = 'https://uiservices.netpay-intl.com/hosted/';
		  $this->method_title      = __( 'NetpayIntl', 'woocommerce' );
		  $this->notify_url        = WC()->api_request_url( 'wc_gateway_NetpayIntl' );

		  // Load the settings.
		  $this->init_form_fields();
		  $this->init_settings();

		  // Define user set variables
		  $this->title 			    = $this->get_option( 'title' );
		  $this->description 		= $this->get_option( 'description' );
		  $this->merchantID 		= $this->get_option( 'merchantID' );
		  $this->securityKey    = $this->get_option( 'securityKey' );
		  $this->paymentaction  = $this->get_option( 'paymentaction', 'sale' );
		  $this->payFor         = $this->get_option( 'payFor', 'Cart Items');
		  $this->useCCStorage   = $this->get_option( 'useCCStorage') == 'yes';
		  $this->useHpp         = $this->get_option( 'useHpp' ) == 'yes' ? true : false;
		  $this->hppLanguage    = $this->get_option( 'hppLanguage');

      if(!$this->description) $this->description = ' ';
		  $this->supports       = $this->useHpp ? array() : array('default_credit_card_form');

		  // Actions
		  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		  add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		  add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_handler' ) );
		  add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'hpp_notify_handler' ) );
	  }

	  public function admin_options() {

		  ?>
		  <h3><?php _e( 'NetpayIntl standard', 'woocommerce' ); ?></h3>
		  <p><?php _e( 'NetpayIntl works by sending the user to NetpayIntl to enter their payment information.', 'woocommerce' ); ?></p>

		  <?php ?>

			  <table class="form-table">
			  <?php
				  // Generate the HTML For the settings form.
				  $this->generate_settings_html();
			  ?>
			  </table><!--/.form-table-->

		  <?php
	  }

	  function init_form_fields() {

		  $this->form_fields = array(
			  'enabled' => array(
				  'title'   => __( 'Enable/Disable', 'woocommerce' ),
				  'type'    => 'checkbox',
				  'label'   => __( 'Enable NetpayIntl standard', 'woocommerce' ),
				  'default' => 'yes'
			  ),
			  'title' => array(
				  'title'       => __( 'Title', 'woocommerce' ),
				  'type'        => 'text',
				  'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				  'default'     => __( 'NetpayIntl', 'woocommerce' ),
				  'desc_tip'    => true,
			  ),
			  'description' => array(
				  'title'       => __( 'Description', 'woocommerce' ),
				  'type'        => 'textarea',
				  'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
				  'default'     => __( 'Pay via NetpayIntl; you can pay with your credit card if you don\'t have a NetpayIntl account', 'woocommerce' )
			  ),
			  'merchantID' => array(
				  'title'       => __( 'NetpayIntl Merchant Number', 'woocommerce' ),
				  'type'        => 'number',
				  'description' => __( 'Your merchant account number at NetpayIntl.', 'woocommerce' ),
				  'default'     => '',
				  'desc_tip'    => true,
				  'placeholder' => '0000000'
			  ),
			  'securityKey' => array(
				  'title'       => __( 'NetpayIntl Security Key', 'woocommerce' ),
				  'type'        => 'text',
				  'description' => __( 'From the developer cener, create and copy your personal hash key', 'woocommerce' ),
				  'default'     => '',
				  'desc_tip'    => true,
				  'placeholder' => __( 'Optional', 'woocommerce' )
			  ),
			  'paymentaction' => array(
				  'title'       => __( 'NetpayIntl Action', 'woocommerce' ),
				  'type'        => 'select',
				  'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce' ),
				  'default'     => 'sale',
				  'desc_tip'    => true,
				  'options'     => array(
					  'sale'          => __( 'Capture', 'woocommerce' ),
					  'authorization' => __( 'Authorize', 'woocommerce' )
				  )
			  ),
			  'payFor' => array(
				  'title'       => __( 'payFor', 'woocommerce' ),
				  'type'        => 'text',
				  'label'       => __( 'PayFor text.', 'woocommerce' ),
				  'description' => __( 'Set title for the transaction', 'woocommerce' ),
				  'default'     => 'Cart Items'
			  ),
			  'useCCStorage' => array(
				  'title'       => __( 'useCCStorage', 'woocommerce' ),
				  'type'        => 'checkbox',
				  'label'       => __( 'Use Tokenized payment method storage.', 'woocommerce' ),
				  'description' => __( 'check this if you want to be able to use the same payment method latter', 'woocommerce' ),
				  'default'     => 'no'
			  ),
        'useHpp' => array(
				  'title'       => __( 'Process method', 'woocommerce' ),
				  'type'        => 'checkbox',
				  'label'       => __( 'Use PaymentPage Redirection (HPP vs SilentPost).', 'woocommerce' ),
				  'description' => __( 'select process method to use either direct or redirect (HPP)', 'woocommerce' ),
				  'default'     => 'yes'
			  ),
        'hppLanguage' => array(
				  'title'       => __( 'HPP Language', 'woocommerce' ),
				  'type'        => 'text',
				  'label'       => __( 'Select PaymentPage Language.', 'woocommerce' ),
				  'description' => __( 'enter a supported language code like en-us', 'woocommerce' ),
				  'default'     => ''
			  ),
		  );
	  }

    public function get_state( $cc, $state ) 
    {
		  if ( 'US' === $cc || 'CA' === $cc ) return $state;
		  $states = WC()->countries->get_states( $cc );
		  if ( isset( $states[ $state ] ) ) return $states[ $state ];
		  return $state;
	  }

	  function get_hpp_args( $order ) {
		  $order_id = $order->id;
      $send_total = number_format( $order->get_total(), 2, '.', '' );
		  $ret_args = array(
				  'merchantID'              => $this->merchantID,
				  'trans_currency'          => get_woocommerce_currency(),
          'trans_amount'            => $send_total,
				  'trans_type'              => $this->paymentaction == 'sale' ? 0 : 1,
				  'trans_refNum'            => ltrim( $order->get_order_number(), '#' ), /*serialize( array( $order_id, $order->order_key ) )*/ 
          'trans_storePm'           => $this->useCCStorage ? '1' : '0',
				  'url_redirect'            => add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ),
				  'url_notify'              => $this->notify_url,
				  'disp_payFor'             => $this->payFor,
				  'disp_lng'                => $this->hppLanguage,
				  'client_invoiceName'      => $order->billing_company,
				  'client_fullName'         => $order->billing_first_name . ' ' . $order->billing_last_name,
				  'client_billAddress1'     => $order->billing_address_1,
				  'client_billAddress2'     => $order->billing_address_2,
				  'client_billCity'         => $order->billing_city,
				  'client_billState'        => $this->get_state( $order->billing_country, $order->billing_state ),
				  'client_billZipcode'      => $order->billing_postcode,
				  'client_billCountry'      => $order->billing_country,
				  'client_email'            => $order->billing_email,
				  'client_phoneNum'         => $order->billing_phone,
    		  'signature'               => base64_encode(md5( $this->merchantID . $send_total . get_woocommerce_currency() . $this->securityKey, true))
		  );
		  return $ret_args;
	  }

    function get_direct_args( $order ) {
      $exp_date = $_POST[$this->id . '-card-expiry']; $exp_dates = NULL;
      
      if (strrpos($exp_date, '/')) $exp_dates = explode('/', $exp_date); 
      else if (strrpos($exp_date, '-')) $exp_dates = explode('=', $exp_date); 
      else $exp_dates = array( substr($exp_date, 0, 2), substr($exp_date, 2));

      $order_id = $order->id;
      $geo = new WC_Geolocation(); // Get WC_Geolocation instance object
      $send_total = number_format( $order->get_total(), 2, '.', '' );
		  $ret_args = array(
				  'CompanyNum'          => $this->merchantID,
				  'Currency'            => get_woocommerce_currency(),
          'Amount'              => $send_total,
				  'TransType'           => $this->paymentaction == 'sale' ? 0 : 1,
          'TypeCredit'          => '1',
				  'Order'               => ltrim( $order->get_order_number(), '#' ), /*serialize( array( $order_id, $order->order_key ) )*/ 
				  'InvoiceName'         => $order->billing_company,
				  'payFor'              => $this->payFor,
				  'Member'              => $order->billing_first_name . ' ' . $order->billing_last_name,
          'StoreCc'             => $this->useCCStorage ? '1' : '0',
				  'BillingAddress1'     => $order->billing_address_1,
				  'BillingAddress2'     => $order->billing_address_2,
				  'BillingCity'         => $order->billing_city,
				  'BillingState'        => $this->get_state( $order->billing_country, $order->billing_state ),
				  'BillingZipCode'      => $order->billing_postcode,
				  'BillingCountry'      => $order->billing_country,
				  'Email'               => $order->billing_email,
				  'PhoneNumber'         => $order->billing_phone,
				  'CardNum'             => str_replace(' ', '', $_POST[$this->id . '-card-number']),
				  'ExpMonth'            => trim($exp_dates[0]),
				  'ExpYear'             => trim($exp_dates[1]),
				  'CVV2'                => $_POST[$this->id . '-card-cvc'],
				  'ClientIP'		=> $geo->get_ip_address()
		  );
		  return $ret_args;
    }
  
	  function receipt_page( $order_id ) {
		  echo '<p>' . __( 'Thank you - your order is now pending payment. You should be automatically redirected to NetpayIntl to make payment.', 'woocommerce' ) . '</p>';
    
		  $order = new WC_Order( $order_id );
		  $post_adr = $this->service_hppUrl . '?';
		  $send_args = $this->get_hpp_args( $order );
		  $send_args_array = array();
		  foreach ( $send_args as $key => $value ) {
			  $send_args_array[] = '<input type="hidden" name="'.esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		  }
		  wc_enqueue_js( '
			  $.blockUI({
					  message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to NetpayIntl to make payment.', 'woocommerce' ) ) . '",
					  baseZ: 99999,
					  overlayCSS:
					  {
						  background: "#fff",
						  opacity: 0.6
					  },
					  css: {
						  padding:        "20px",
						  zindex:         "9999999",
						  textAlign:      "center",
						  color:          "#555",
						  border:         "3px solid #aaa",
						  backgroundColor:"#fff",
						  cursor:         "wait",
						  lineHeight:		"24px",
					  }
				  });
			  jQuery("#submit_payment_form").click();
		  ' );

		  echo 
        '<form action="' . esc_url( $post_adr ) . '" method="post" id="payment_form" target="_top">
				  ' . implode( '', $send_args_array ) . '
				  <!-- Button Fallback -->
				  <div class="payment_buttons">
					  <input type="submit" class="button alt" id="submit_payment_form" value="' . __( 'Pay via NetpayIntl', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
				  </div>
				  <script type="text/javascript">
					  jQuery(".payment_buttons").hide();
				  </script>
			  </form>';

	  }

    private function setProcessResultValues($order, $modeText, $replayCode, $transId, $transConfirm, $storageId)
    {
        $order->add_order_note( 'NetpayIntl ' . $modeText .  ' payment result :' . $replayCode);
			  if ($transId) update_post_meta( $order->id, 'Transaction ID', wc_clean( $transId ) );
			  if ($transConfirm) update_post_meta( $order->id, 'Confirmation Number', wc_clean( $transConfirm ) );
			  if ($storageId) update_post_meta( $order->id, 'Storage ID', wc_clean( $storageId ) );
        if ($replayCode == '000' || $replayCode == '001') {
          $order->payment_complete();
          return true;
        } 
        return false;
    }

	  function process_payment( $order_id ) 
    {
		  $order = new WC_Order( $order_id );
		  if (!$this->useHpp) {
        $params = array( 'body' => $this->get_direct_args( $order ), 'method' => 'POST', 'headers' => array('Content-Type'=> 'application/x-www-form-urlencoded'), 'sslverify' => false);   
        $response_array = wp_remote_post($this->service_directUrl, $params);
        //print_r($params); 
        parse_str($response_array['body'], $r);
        if ($this->setProcessResultValues($order, 'Direct', $r['Reply'],  $r['TransID'], $r['ConfirmationNum'], $response_data['ccStorageID'])) {
            return array('result' => 'success', 'redirect' => $this->get_return_url( $order ));
        } 
        //echo '<br/>' . $response_array['body'];
        wc_add_notice(__('Payment error:', 'woothemes') . ' (' . $r['Reply'] . ') ' . $r['ReplyDesc'], 'error');
        return;
		  } else {
			  return array('result' => 'success', 'redirect'=> $order->get_checkout_payment_url( true ));
		  }
	  }

	  private function get_payed_order( $order_id ) {
		  $order = new WC_Order( $order_id );
		  /*
      if ( ! isset( $order->id ) ) {
			  $order_id 	= wc_get_order_id_by_order_key( $order_key );
			  $order 		  = new WC_Order( $order_id );
		  }
		  if ( $order->order_key !== $order_key ) exit;
      */
		  return $order;
	  }

	  public function thankyou_handler() {
		  $response_data = ! empty( $_GET ) ? $_GET : false;
		  if ($response_data && !empty( $response_data['replyCode'] ) ) {
			  $order = $this->get_payed_order( $response_data['trans_refNum'] );
			  if ( 'pending' != $order->status ) return false;
        if ($this->setProcessResultValues($order, 'Redirect back', $response_data['replyCode'], $response_data['trans_id'], null, $response_data['storage_id'])) {
					  return true;
        } else {
          header('Location: ' . $order->get_checkout_payment_url());
          die();
          return false;
        }
		  }
		  return false;
	  }

	  function hpp_notify_handler() {
		  @ob_clean();
		  $response_data = ! empty( $_GET ) ? $_GET : false;
      if ($response_data && !empty( $response_data['replyCode'] ) ) {
			  header( 'HTTP/1.1 200 OK' );
			  $order = $this->get_payed_order( $response_data['trans_refNum'] );
        $this->setProcessResultValues($order, 'Redirect Notify', $response_data['replyCode'],  $response_data['trans_id'], null, $response_data['storage_id']);
        echo 'OK';
		  } else {
			  wp_die( "NetpayIntl notify handler Request Failure", "NetpayIntl notify handler", array( 'response' => 200 ) );
		  }
	  }

  }
}      
