<?php

/*
Plugin Name: Easy Digital Downloads Pay4App Payment Gateway
Plugin URI: https://pay4app.com
Description: Pay4App Payments for Easy Digital Downloads
Version: 0.2
Author: Pay4App
Author URI: https://pay4app.com
*/

//registering the gateway

function pay4app_edd_register_gateway($gateways){
	$gateways['pay4app_edd'] = array('admin_label' => 'Pay4App', 'checkout_label' => __('EcoCash, TeleCash, ZimSwitch and VISA via Pay4App', 'pay4app_edd'));
	return $gateways;
}

add_filter('edd_payment_gateways', 'pay4app_edd_register_gateway');

function pay4app_edd_gateway_cc_form(){
	return;
}
add_action('edd_pay4app_edd_cc_form', 'pay4app_edd_gateway_cc_form');

function pay4app_edd_settings($settings){
	$s = array(
			array(
				'id' 	=> 'pay4app_edd_settings_h',
				'name' 	=> '<strong>' . __('Pay4App Settings', 'pay4app_edd') . '</strong>',
				'desc' 	=> __('Configure the gateway settings', 'pay4app_edd'),
				'type'	=> 'header'
				),
			array(
				'id'	=> 'pay4app_merchant_id',
				'name'	=> __('Merchant ID', 'pay4app_edd'),
				'desc'	=> __('Your merchant ID as specified in your Pay4App Merchant Account'),
				'type'	=> 'text',
				'size'	=> 'regular'
				),
			array(
				'id'	=> 'pay4app_api_secret_key',
				'name'	=> __('Pay4App Secret Key', 'pay4app_edd'),
				'desc'	=> __('Your Secret Key as specified in your Pay4App Merchant API Keys Settings'),
				'type'	=> 'text',
				'size' 	=> 'regular'
				)
			);
	return array_merge($settings, $s);
}

add_filter('edd_settings_gateways', 'pay4app_edd_settings');


function pay4app_process_payment($purchase_data){
	/*
	TODO: ACCOMODATE SANDBOX CHECKOUT
	if (edd_is_test_mode()){
		
	}
	*/

	global $edd_options;

	$pay4app_checkout_url = "https://pay4app.com/checkout.php";	

	$purchase_summary = edd_get_purchase_summary($purchase_data);
	$payment = array(
		'price' 		=> $purchase_data['price'],
		'date' 			=> $purchase_data['date'],
		'user_email' 	=> $purchase_data['user_email'],
		'purchase_key' 	=> $purchase_data['purchase_key'],
		'currency' 		=> $edd_options['currency'],
		'downloads' 	=> $purchase_data['downloads'],
		'cart_details' 	=> $purchase_data['cart_details'],
		'user_info' 	=> $purchase_data['user_info'],
		'status' 		=> 'pending',
		'gateway' 		=> 'pay4app_edd'
		);




	$payment = edd_insert_payment($payment);
	//TODO check API keys


	//Check payment
	if (!$payment){
		edd_record_gateway_error( __( 'Payment error', 'edd' ), sprintf( __( 'Payment creation failed before sending buyer to Pay4App. Payment data: %s', 'edd' ), json_encode($payment_data) ), $payment ); 
		//redirect the buyer again top checkout
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}
	else{

		$redirect_url = get_permalink( $edd_options['success_page'] );

		$hash = $edd_options['pay4app_merchant_id'].$purchase_data['purchase_key'].$purchase_data['price'].$edd_options['pay4app_api_secret_key'];
		$hash = hash('sha256', $hash);

		$pay4app_args = array(
			'merchantid' => $edd_options['pay4app_merchant_id'],
			'orderid' => $payment,
			'signature' => $hash,
			'amount' => $purchase_data['price'],
			'redirect' => $redirect_url,
			'transferpending' => $redirect_url

			);

		$pay4app_args_array = array();
		foreach( $pay4app_args as $key=>$value ){
			$pay4app_args_array[] = "<input type='hidden' name='$key' value='$value' />";
		}

		$pay4app_form_code = 'Redirecting to Pay4App..please wait<form action="'.$pay4app_checkout_url.'" method="post" name="pay4app_payment_form" id="pay4app_payment_form">'.implode('', $pay4app_args_array).'
				<script type="text/javascript" language="JavaScript">document.pay4app_payment_form.submit();</script></form>';

		$pay4app_noscript_form_code = '<noscript><form action="'.$pay4app_checkout_url.'" method="post" name="pay4app_payment_form" id="pay4app_payment_form">'.implode('', $pay4app_args_array).'
				<input type="submit" class="button-alt" id="submit_pay4app_payment_form" value="'.__('Click this button to proceed to Pay4App', 'pay4app_edd').'" /></form></noscript>';

		echo $pay4app_form_code.$pay4app_noscript_form_code;		
		exit();

		//redirect to Pay4App



	}

}

add_action('edd_gateway_pay4app_edd', 'pay4app_process_payment');


function showPay4AppRedirectMessage($content){
		return '<h2>Payment Received</h2><div class="edd_errors"><p style="" class="edd_error">Thank you, your account has been successfully charged</p></div>'.$content;
}

function showPay4AppTransferPendingMessage($content){
		return '<h2>Payment Notification Is Pending</h2><div class="edd_errors"><p style="" class="edd_error">Thank you. The payment confirmation is still to be received, it should shortly. We will update you on progress over email.</p></div>'.$content;
}

function p4a_equal_floats($a, $b){
		$a = (float)$a;
		$b = (float)$b;
		if (abs(($a-$b)/$b) < 0.00001) {
			return TRUE;
		}
		return FALSE;

	}

function annoy(){
	if ( edd_check_for_existing_payment(639)){
		echo "2 exists";
	}
	else{
		echo "No 2";	
	}
	exit;
}


function is_pay4app_redirect_or_callback(){
    global $edd_options;
	$merchant = $edd_options['pay4app_merchant_id'];
	$apisecret = $edd_options['pay4app_api_secret_key'];

    if ( isset($_GET['merchant']) AND isset($_GET['checkout']) AND isset($_GET['order'])
          AND isset($_GET['amount']) AND isset($_GET['email']) AND isset($_GET['phone'])
          AND isset($_GET['timestamp']) AND isset($_GET['digest']) 
        ){ 


        //for readability the concatenation is split over two lines   
        $digest = $_GET['merchant'].$_GET['checkout'].$_GET['order'].$_GET['amount'];
        $digest .= $_GET['email'].$_GET['phone'].$_GET['timestamp'].$apisecret;

        $digesthash = hash("sha256", $digest);

        if ($_GET['digest'] !== $digesthash){

            return FALSE;

          }

      return TRUE;
    }
    else{
      
      return FALSE;

    }
}

function is_pay4app_transfer_pending_redirect(){
    global $edd_options;
	$merchant = $edd_options['pay4app_merchant_id'];
	$apisecret = $edd_options['pay4app_api_secret_key'];

	if ( isset($_GET['merchant']) AND isset($_GET['order']) AND isset($_GET['digest']) ){
		$expecteddigest = $_GET['merchant'].$_GET['order'].$apisecret;
		$expecteddigest = hash("sha256", $expecteddigest);
		if ($_GET['digest'] !== $expecteddigest){
		  return FALSE;
		}
		return TRUE;
	}
	return FALSE;      
}

function pay4app_edd_listen_for_redirect_callback_or_pending(){
	global $edd_options;
	
	/*

	if is trnsfer pending{
		prepare message and add notif content filter
		clear the damn cart
	}
	if redirect{
		do the stuff

		if cb
			echo and exit
		else
			prepare message
		
	}

	*/

	//annoy();

	
	if (is_pay4app_transfer_pending_redirect()){
		add_action('the_content', 'showPay4AppTransferPendingMessage');
		return;

	}

	if (is_pay4app_redirect_or_callback()){
		$p4a_payment = $_GET['order'];
		$p4a_checkout = $_GET['checkout'];
		$p4a_email = $_GET['email'];
		$p4a_amount = $_GET['amount'];
		$p4a_phone = $_GET['phone'];

		if (!$payment_postp4a = get_post( $p4a_payment )) die(json_encode(array('status'=>1, 'message'=>'post no exist')));

		//var_dump($payment_postp4a);		

		if ( !( $payment_postp4a->post_type == 'edd_payment'  ) ){

			$p4a_data = "Received $".$p4a_amount." from ".$p4a_email." , ".$p4a_phone."(Pay4App Checkout ID - ".$p4a_checkout.") for supposed payment ID ".$p4a_payment." which cannot be found. Please contact the customer";
			
			$p4a_subject = '[System] Money received for non-existing order: '.$p4a_payment;			

			wp_mail( get_option('admin_email'), $p4a_subject, $p4a_data );

			if (isset($_GET['EDD_Pay4App_CB'])){
				echo json_encode(array('status'=>1, 'message'=>'payment not found'));
				exit;
			}
			return;
		}





		if ( get_post_status( $p4a_payment ) == 'publish' ){
			if (isset($_GET['EDD_Pay4App_CB'])){
				echo json_encode(array('status'=>1, 'message'=>'already published'));
				exit;
			}
			return;
		}

		if ( edd_get_payment_gateway( $p4a_payment ) != 'pay4app_edd' ){
			if (isset($_GET['EDD_Pay4App_CB'])){
				echo json_encode(array('status'=>1, 'message'=>'bad gateway' ));
				exit;
			}
			return;
		}

		if ( ! edd_get_payment_user_email( $p4a_payment ) ){

			//akward, no email for this. Update with Pay4App's
			update_post_meta( $p4a_payment, '_edd_payment_user_email', $_GET['email'] );
			$user_info = array(
				'id' 			=> '-1',
				'email'			=> $_GET['email'],
				'first_name'	=> 'NoneGiven',
				'last_name'	=> 'NoneGiven',
				);

			$payment_meta = get_post_meta( $p4a_payment, '_edd_payment_meta', true );
			$payment_meta['user_info'] = serialize( $user_info );
			update_post_meta( $p4a_payment, '_edd_payment_meta', $payment_meta );

		}

		if ( !p4a_equal_floats( $p4a_amount, edd_get_payment_amount ( $p4a_payment ) ) ){
			edd_record_gateway_error( __( 'Amount mismatch error', 'pay4app_edd' ), sprintf( __( 'The amount paid (%s) and the amount expected are different', 'pay4app_edd'), $p4a_amount), $p4a_payment );
			
			if (isset($_GET['EDD_Pay4App_CB'])){
				echo json_encode(array('status'=>1, 'message'=>'money not teh same'));
				exit;
			}
			
			return;

		}


		//if we get this far, it means we're in

		edd_insert_payment_note( $p4a_payment, sprintf( __(' Pay4App Checkout ID: %s, Paid by: %s, with email: %s', 'pay4app_edd' ), $p4a_checkout, $p4a_phone, $p4a_email ) );
		edd_update_payment_status( $p4a_payment, 'publish' );

		if (isset($_GET['EDD_Pay4App_CB'])){
			echo json_encode(array('status'=>1, 'message'=>'successfully completed'));
			exit;
		}

		add_action('the_content', 'showPay4AppRedirectMessage');
		return;
		


	}

}



add_action( 'init', 'pay4app_edd_listen_for_redirect_callback_or_pending' );
//b603a6a757a41d5b24a7e9ff00a076fc