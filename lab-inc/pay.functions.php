<?php
/**
* 	PayUmoney WooCommerce payment gateway
* 	Author: Subhadeep Mondal
*	Author URI: https://www.linkedin.com/in/subhadeep-mondal
*	Created: 24/07/2018
*	modified: 09/12/2019
**/
if (!defined('ABSPATH')) exit;
	class Wpl_PayLabs_WC_Payu extends WC_Payment_Gateway 
	{
		/**
	    * construct function for this plugin __construct()
	    *
	    */
		public function __construct() 
		{
			global $woocommerce;
			$this->id				= 'wpl_paylabs_payu';
			$this->method_title = __('PayUmoney PluginStar', 'wpl_woocommerce_paylabs_payu');
			$this->method_description = __('PayUmoney is a trusted way to payment in India (INR)', 'wpl_woocommerce_paylabs_payu');
			$this->icon 			= PSPUM_URL.'images/icon.png';
			$this->has_fields 		= true;
			$this->liveurl			= 'https://secure.payu.in/_payment';
			$this->testurl			= 'https://sandboxsecure.payu.in/_payment';
			$this->init_form_fields();
			$this->init_settings();
			$this->responseVal		= '';
			if(get_option( 'woocommerce_currency')=='INR') 
			{
				$paylabs_payu_enabled = $this->settings['enabled'];
			}
			else 
			{
				$paylabs_payu_enabled = 'no';
			} 
			$this->enabled			= $paylabs_payu_enabled;
			$this->testmode			= $this->settings['testmode'];

			if(isset($this->settings['thank_you_message']))
				$this->thank_you_message = __($this->settings['thank_you_message'], 'wpl_woocommerce_paylabs_payu');
			else
				$this->thank_you_message = __('Thank you! your order has been received.', 'wpl_woocommerce_paylabs_payu');

			if(isset($this->settings['redirect_message']) && $this->settings['redirect_message']!='')
				$this->redirect_message = __( $this->settings['redirect_message'], 'wpl_woocommerce_paylabs_payu' );
			else
				$this->redirect_message = __( 'Thank you for your order. We are now redirecting you to Pay with PayUmoney to make payment.', 'wpl_woocommerce_paylabs_payu' );

			$this->merchantid   = $this->settings['merchantid'];
			$this->salt   		= $this->settings['salt'];

			if('yes'==$this->testmode) 
			{
				$this->title 		= 'Sandbox PayUmoney';
				$this->description 	= __('card number: <strong>4012001037141112</strong><br>'."\n"
				.'card CVV: <strong>123</strong><br>'."\n"
				.'expiry Date: <strong>05/20</strong><br>'."\n"
				.'<a href="https://www.payumoney.com/dev-guide/development/testmode.html" target="_blank">Development Guide</a><br>'."\n");
			}
			else
			{
				$this->title 				= $this->settings['title'];
				$this->description  		= $this->settings['description'];
			}

			if(isset($_GET['wpl_paylabs_payu_callback']) && isset($_GET['results']) && esc_attr($_GET['wpl_paylabs_payu_callback'])==1 && esc_attr($_GET['results']) != '') 
			{
				$this->responseVal = $_GET['results'];
				add_filter( 'woocommerce_thankyou_order_received_text', array($this, 'wpl_payu_thankyou'));
			}
			add_action('init', array(&$this, 'wpl_payu_transaction'));
			add_action( 'woocommerce_api_'.strtolower(get_class( $this )) , array( $this, 'wpl_payu_transaction' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'wpl_payu_receipt_page' ) ); 
		} // End Constructor

	   	/**
		* init Gateway Form Fields init_form_fields()
		*
		*/
		function init_form_fields() 
		{
			$this->form_fields = array(
				'enabled' => array(
					'title'			=> __('Enable/Disable:','wpl_woocommerce_paylabs_payu'),
					'type'			=> 'checkbox',
					'label' 		=> __( 'Enable PayUmoney', 'wpl_woocommerce_paylabs_payu' ),
					'default'		=> 'yes'
				),
				'title' => array(
					'title' 		=> __( 'Title:', 'wpl_woocommerce_paylabs_payu' ),
					'type' 			=> 'text',
					'custom_attributes' => array( 'required' => 'required' ),
					'description'	=> __( 'This controls the title which the user sees during checkout.', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> __( 'PayUmoney', 'wpl_woocommerce_paylabs_payu' )
				),
				'description' => array(
					'title' 		=> __( 'Description:', 'wpl_woocommerce_paylabs_payu' ),
					'type' 			=> 'textarea',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> __( 'Direct payment via PayUmoney. PayUmoney accepts VISA, MasterCard, Debit Cards and the Net Banking of all major banks.', 'wpl_woocommerce_paylabs_payu' ),
				),
				'merchantid' => array(
					'title' 		=> __( 'Merchant Key:', 'wpl_woocommerce_paylabs_payu' ),
					'type' 			=> 'text',
					'custom_attributes' => array( 'required' => 'required' ),
					'description' 	=> __( 'This key is generated at the time of activation of your site and helps to uniquely identify you to PayUmoney', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> ''
				),
				'salt' => array(
					'title' 		=> __( 'SALT:', 'wpl_woocommerce_paylabs_payu' ),
					'type'	 		=> 'text',
					'custom_attributes' => array( 'required' => 'required' ),
					'description' 	=> __( 'String of characters provided by PayUmoney', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> ''
				),
				'testmode' => array(
					'title' 		=> __('Mode of transaction:', 'wpl_woocommerce_paylabs_payu'),
					'type' 			=> 'select',
					'label' 		=> __('PayUindia Tranasction Mode.', 'wpl_woocommerce_paylabs_payu'),
					'options' 		=> array('yes'=>'Test / Sandbox Mode','no'=>'Live Mode'),
					'default' 		=> 'no',
					'description' 	=> __('Mode of PayUindia activities'),
					'desc_tip' 		=> true
	                ),
				'thank_you_message' => array(
					'title' 		=> __( 'Thank you page message:', 'wpl_woocommerce_paylabs_payu' ),
					'type' 			=> 'textarea',
					'description' 	=> __( 'Thank you page order success message when order has been received', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> __( 'Thank you. Your order has been received.', 'wpl_woocommerce_paylabs_payu' ),
					),
				'redirect_message' => array(
					'title' 		=> __( 'Redirecting you to Pay with PayUmoney:', 'wpl_woocommerce_paylabs_payu' ),
					'type' 			=> 'textarea',
					'description' 	=> __( 'We are now redirecting you to PayUmoney to make payment', 'wpl_woocommerce_paylabs_payu' ),
					'default' 		=> __( 'Thank you for your order. We are now redirecting you to Pay with PayUmoney to make payment.', 'wpl_woocommerce_paylabs_payu' ),
					),
				);
		} // function init_form_fields() end

		/**
		* WP Admin Options admin_options() 
		*
		*/
		public function admin_options() 
		{
	    	?>
	    	<h3><?php _e( 'PayUmoney PluginStar', 'wpl_woocommerce_paylabs_payu' ); ?></h3>
	    	<p><?php _e( 'PayUmoney works by sending the user to PayUmoney to enter their payment information. Note that PayUmoney will only take payments in Indian Rupee.', 'wpl_woocommerce_paylabs_payu' ); ?></p>
			<?php
				if ( get_option( 'woocommerce_currency' ) == 'INR' ) 
				{
				?>
					<table class="form-table">
						<?php $this->generate_settings_html(); ?>
					</table>
				<?php
				} 
				else 
				{
					?>
					<div class="inline error">
						<p><strong><?php _e( 'PayUmoney PluginStar Gateway Disabled', 'wpl_woocommerce_paylabs_payu' ); ?></strong>
							<?php echo sprintf( __( 'Choose Indian Rupee (Rs.) as your store currency in <a href="%s">Pricing Options</a> to enable the PayUmoney WooCommerce payment gateway', 'wpl_woocommerce_paylabs_payu' ), admin_url( 'admin.php?page=wc-settings' ) ); ?>
						</p>
					</div>
					<?php
				} // End check currency
		} // function admin_options() end

		/**
		* Build the form after click on PayUmoney button wpl_generate_paylabs_payu_form()
		*
		*/
	    private function wpl_generate_paylabs_payu_form($order_id) 
	    {
			$this->wpl_payu_clear_cache();
			global $wp;
			global $woocommerce;
			$order = new WC_Order($order_id);
			$txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
			$returnURL = $woocommerce->api_request_url(strtolower(get_class( $this )));
			update_post_meta( $order_id, '_transaction_id', $txnid);
			$productinfo = sprintf( esc_html__( 'Order ID: #%1$s Transaction ID: #%2$s', 'wpl_woocommerce_paylabs_payu' ), $order_id, $txnid);

			$hash_data['key']				 = $this->merchantid;
			$hash_data['txnid'] 			 = $txnid;
			$hash_data['amount'] 			 = $order->get_total();
			$hash_data['productinfo'] 		 = $productinfo;
			$hash_data['firstname']			 = $order->get_billing_first_name();
			$hash_data['email'] 			 = $order->get_billing_email();
			$hash_data['phone'] 			 = $order->get_billing_phone();
			$hash_data['udf5'] 			 	 = "WooCommerce_v_3.x_BOLT";
			$hash_data['hash'] 				 = $this->wpl_calculate_hash_before_transaction(array_filter($hash_data));
			
			$paylabs_payu_args = array(
				'key'					=> $this->merchantid,
				'surl'					=> $returnURL,
				'furl'					=> $returnURL,
				'curl'					=> esc_url_raw($order->get_checkout_payment_url(false)),
				'firstname'				=> $order->get_billing_first_name(),
				'lastname'				=> $order->get_billing_last_name(),
				'email'					=> $order->get_billing_email(),
				'address1'				=> $order->get_billing_address_1(),
				'address2'				=> $order->get_billing_address_2(),
				'city'					=> $order->get_billing_city(),
				'state'					=> $order->get_billing_state(),
				'zipcode'				=> $order->get_billing_postcode(),
				'country'				=> $order->get_billing_country(),
				'phone' 	            => $order->get_billing_phone(),
	            'service_provider' => 'payu_paisa',
				'productinfo'		    => $productinfo,
				'amount'			   	=> $order->get_total()
			);

			$paylabs_payu_args = array_filter($paylabs_payu_args);
			$payuform = '';

			foreach($paylabs_payu_args as $key => $value) 
			{
	   			if($value) 
	   			{
		   			$payuform .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
	   			}
			}
			$payuform .= '<input type="hidden" name="txnid" value="' . $txnid . '" />' . "\n";
			$payuform .= '<input type="hidden" name="udf5" value="' . $hash_data['udf5'] . '" />' . "\n";
			$payuform .= '<input type="hidden" name="hash" value="' . $hash_data['hash'] . '" />' . "\n";

			$posturl = $this->liveurl;
			if($this->testmode=='yes') 
			{
				$posturl=$this->testurl;
			}

			return '<form action="' . $posturl . '" method="POST" name="payform" id="payform">
					' . $payuform . '
					<input type="submit" class="button" id="submit_paylabs_payu_payment_form" value="' . __( 'Pay via PayUmoney', 'wpl_woocommerce_paylabs_payu' ) . '" /> <a class="button cancel" href="' . $order->get_checkout_payment_url(false). '">'.__( 'Cancel order &amp; restore cart', 'wpl_woocommerce_paylabs_payu' ) . '</a>
					<script type="text/javascript">
						jQuery(function(){
							jQuery("body").block(
								{
									message: "'.__($this->redirect_message, 'wpl_woocommerce_paylabs_payu').'",
									overlayCSS:
									{
										background: "#fff",
										opacity: 0.6
									},
									css: {
								        padding:        20,
								        textAlign:      "center",
								        color:          "#555",
								        border:         "3px solid #aaa",
								        backgroundColor:"#fff",
								        cursor:         "wait"
								    }
								});
								jQuery("#payform").submit();
								jQuery("#submit_paylabs_payu_payment_form").click();
						});
					</script>
				</form>';
		} // function wpl_generate_paylabs_payu_form() end

		/**
		* Process the payment for checkout process_payment() 
		*
		*/
		function process_payment($order_id) 
		{
			$this->wpl_payu_clear_cache();
			global $woocommerce;
    		$order = new WC_Order( $order_id );
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url(true)
			);
		} // function process_payment() end

		/**
		* Page after cheout button and redirect to PayUmoney payment page wpl_payu_receipt_page()
		* 
		*/
		function wpl_payu_receipt_page($order_id ) 
		{
			$this->wpl_payu_clear_cache();
			global $woocommerce;
			$order = new WC_Order($order_id);
			printf('<h3>%1$s</h3>',__('Thank you for your order, please click the button below to Pay with PayUmoney.', 'wpl_woocommerce_paylabs_payu'));
			_e($this->wpl_generate_paylabs_payu_form($order_id ));

		} // function wpl_payu_receipt_page() end

		/**
		* Clear cache for the previous value wpl_payu_clear_cache()
		*
		*/
		private function wpl_payu_clear_cache()
		{
			header("Pragma: no-cache");
			header("Cache-Control: no-cache");
			header("Expires: 0");
		}// function wpl_payu_clear_cache() end

		/**
		* Check the status of current transaction and get response with $_POST wpl_payu_transaction()
		*
		*/
		function wpl_payu_transaction() 
		{
			global $woocommerce;
			global $wpdb;
			if(isset($_POST['txnid']) && $_POST['txnid'] != '')
			{
				$trnid = $_POST['txnid'];
			}
			$args = array(
		        'post_type'   => 'shop_order',
		        'post_status' => array('wc'), 
		        'numberposts' => 1,
		        'meta_query' => array(
		               array(
		                   'key' => '_transaction_id',
		                   'value' => $trnid,
		                   'compare' => '=',
		               )
		           )
		        );
		    $post_id_arr = get_posts( $args );
		    if(isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='')
		    	$order_id = $post_id_arr[0]->ID;
		    $order = new WC_Order($order_id);
			$salt = $this->salt;
			if(!empty($_POST)) 
			{
				foreach($_POST as $key => $value) 
				{
					$this->responseVal[$key] = htmlentities($value, ENT_QUOTES);
				}
			}
			else 
			{
				wc_add_notice( __('Error on payment: PayUmoney payment failed!', 'wpl_woocommerce_paylabs_payu'), 'error');
				wp_redirect($order->get_cancel_order_url());
			}

			$postResp = $_POST;
			if($this->wpl_check_hash_after_transaction($salt, $postResp)) 
			{
				if($postResp['status']=='success')
				{
					$order_note = sprintf( __('Reference Order ID: %1$s<br>PayUmoney Transaction ID: %2$s<br>Bank Ref: %3$s<br>Transaction method: %4$s', 'wpl_woocommerce_paylabs_payu' ), $this->responseVal['txnid'], $this->responseVal['payuMoneyId'], $this->responseVal['bank_ref_num'], $this->responseVal['bankcode'].' ( '.$this->responseVal['mode'].' )') ;
					$order->add_order_note($order_note);
					$order->payment_complete();
					
				}
				elseif($postResp['status'] == 'pending')
				{
					$order_note = sprintf( __('Reference Order ID: %1$s<br>PayUmoney Transaction ID: %2$s<br>Bank Ref: %3$s<br>Transaction method: %4$s', 'wpl_woocommerce_paylabs_payu' ), $this->responseVal['txnid'], $this->responseVal['payuMoneyId'], $this->responseVal['bank_ref_num'], $this->responseVal['bankcode'].' ( '.$this->responseVal['mode'].' )') ;
					$order->add_order_note($order_note);
					$order->update_status('on-hold');
				}
				else
				{
					$order_note = sprintf( __('PayUmoney payment is failed.<br>Reference Order ID: %1$s<br>Error: %2$s' ), $this->responseVal['txnid'], $this->responseVal['field9']) ;
					$order->add_order_note($order_note);
					wc_add_notice( __('Error on payment: PayUmoney payment failed! Reference Order ID: '.$this->responseVal['txnid'].' ('.$this->responseVal['field9']. ' )', 'wpl_woocommerce_paylabs_payu'), 'error');
					wp_redirect($order->get_cancel_order_url_raw()); die();
				}

				$results = urlencode(base64_encode(json_encode($_POST)));
				$return_url = add_query_arg(array('wpl_paylabs_payu_callback'=>1,'results'=>$results), $this->get_return_url($order));
		        wp_redirect($return_url);
			}
		} // function wpl_payu_transaction() end

		/**
		* Calculate hash before transaction wpl_calculate_hash_before_transaction()
		* 
		*/
		private function wpl_calculate_hash_before_transaction($hash_data) 
		{
			$hash_sequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
			$hash_vars_seq = explode('|', $hash_sequence);
			$hash_string = '';

			foreach($hash_vars_seq as $hash_var) {
			  $hash_string .= isset($hash_data[$hash_var]) ? $hash_data[$hash_var] : '';
			  $hash_string .= '|';
			}

			$hash_string .= $this->salt;
			$hash_data['hash'] = strtolower(hash('sha512', $hash_string));

			return $hash_data['hash'];

		} // function wpl_calculate_hash_before_transaction() end

		/**
		* Calculate hash after transaction wpl_check_hash_after_transaction()
		* 
		*/
		private function wpl_check_hash_after_transaction($salt, $txnRs) 
		{
			$hash_sequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
			$hash_vars_seq = explode('|', $hash_sequence);
			$hash_vars_seq = array_reverse($hash_vars_seq);
			$merc_hash_string = $salt . '|' . $txnRs['status'];
			foreach ($hash_vars_seq as $merc_hash_var) {
				$merc_hash_string .= '|';
				$merc_hash_string .= isset($txnRs[$merc_hash_var]) ? $txnRs[$merc_hash_var] : '';
			}
			$merc_hash = strtolower(hash('sha512', $merc_hash_string));
			if($merc_hash == $txnRs['hash']) {
				return true;
			} else {
				return false;
			}

		} // function wpl_check_hash_after_transaction() end

		/**
		* Thank you page success data wpl_payu_thankyou()
		* 
		*/
		function wpl_payu_thankyou() 
		{
			$wpl_paylabs_response = json_decode(base64_decode(urldecode($this->responseVal)), true);
			global $woocommerce;
			global $wpdb;

			if(isset($wpl_paylabs_response['txnid']) && $wpl_paylabs_response['txnid'] != '')
			{
				$trnid = $wpl_paylabs_response['txnid'];
			}
			$args = array(
		        'post_type'   => 'shop_order',
		        'post_status' => array('wc'), 
		        'numberposts' => 1,
		        'meta_query' => array(
		               array(
		                   'key' => '_transaction_id',
		                   'value' => $trnid,
		                   'compare' => '=',
		               )
		           )
		        );
		    $post_id_arr = get_posts( $args );
		    if(isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='')
		    	$order_id = $post_id_arr[0]->ID;
		    $order = new WC_Order($order_id);

			$added_text = '';

			if(strtolower($wpl_paylabs_response['status'])=='success')
			{
				$added_text .= printf('<section class="woocommerce-order-details">
											<h3>'.$this->thank_you_message.'</h3>
											<h2 class="woocommerce-order-details__title">Transaction details</h2>
											<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
												<thead>
													<tr>
														<th class="woocommerce-table__product-name product-name">Reference Order ID:</th>
														<th class="woocommerce-table__product-table product-total">'.$wpl_paylabs_response['txnid'].'</th>
													</tr>
												</thead>
												<tbody>
													<tr class="woocommerce-table__line-item order_item">
														<td class="woocommerce-table__product-name product-name">PayUmoney Transaction ID:</td>
														<td class="woocommerce-table__product-total product-total">'.$wpl_paylabs_response['payuMoneyId'].'</td>
													</tr>
												</tbody>
												<tfoot>
													<tr class="woocommerce-table__line-item order_item">
														<td class="woocommerce-table__product-name product-name">Bank Ref:</td>
														<td class="woocommerce-table__product-total product-total">'.$wpl_paylabs_response['bank_ref_num'].'</td>
													</tr>
													<tr>
														<th scope="row">Transaction method:</th>
														<td>'.$wpl_paylabs_response['bankcode'].' ( '.$wpl_paylabs_response['mode'].' )</td>
													</tr>
												</tfoot>
											</table>
										</section>');
			}
			elseif(strtolower($wpl_paylabs_response['status'])=='pending')
	        {
	            $added_text .= printf('<section class="woocommerce-order-details">
											<h3>PayUmoney payment is pending</h3>
											<h2 class="woocommerce-order-details__title">Transaction details</h2>
											<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
												<thead>
													<tr>
														<th class="woocommerce-table__product-name product-name">Reference Order ID</th>
														<th class="woocommerce-table__product-table product-total">'.$wpl_paylabs_response['txnid'].'</th>
													</tr>
												</thead>
												<tbody>
													<tr class="woocommerce-table__line-item order_item">
														<td class="woocommerce-table__product-name product-name">PayUmoney Transaction ID:</td>
														<td class="woocommerce-table__product-total product-total">'.$wpl_paylabs_response['payuMoneyId'].'</td>
													</tr>
												</tbody>
											</table>
										</section>');
			}
	        else
	        {
				wp_redirect($order->get_checkout_payment_url(false));
	        }

		}// function wpl_payu_thankyou() end

	} //  End Wpl_PayLabs_WC_Payu Class