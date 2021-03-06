<?php
$nzshpcrt_gateways[$num]['name'] = 'Viva Payments';
$nzshpcrt_gateways[$num]['internalname'] = 'hellaspay';
$nzshpcrt_gateways[$num]['function'] = 'gateway_hellaspay';
$nzshpcrt_gateways[$num]['form'] = "form_hellaspay";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_hellaspay";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";
$nzshpcrt_gateways[$num]['display_name'] = 'Credit Card';

function gateway_hellaspay($separator, $sessionid)
{
	global $wpdb,$wpsc_cart; 
	$purchase_log_sql = "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1";
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
	$cart = $wpdb->get_results($cart_sql,ARRAY_A) ;

  	$email_data = $wpdb->get_results("SELECT `id`,`type` FROM `".WPSC_TABLE_CHECKOUT_FORMS."` WHERE `type` IN ('email') AND `active` = '1'",ARRAY_A);
  	foreach((array)$email_data as $email)
    {
    	$datadp['CardHolderEmail'] = $_POST['collected_data'][$email['id']];
    }
  	if(($_POST['collected_data'][get_option('email_form_field')] != null) && ($datadp['email'] == null))
    {
    	$datadp['CardHolderEmail'] = $_POST['collected_data'][get_option('email_form_field')];
    }

	// Get Currency details abd price
	$currency_code = $wpdb->get_results("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1",ARRAY_A);
	$local_currency_code = $currency_code[0]['code'];
	$hellaspay_currency_code = 'EUR';

	// ChronoPay only processes in the set currency.  This is USD or EUR dependent on what the Chornopay account is set up with.
	// This must match the ChronoPay settings set up in wordpress.  Convert to the chronopay currency and calculate total.
	$curr=new CURRENCYCONVERTER();
	$decimal_places = 2;
	$total_price = 0;

	$i = 1;

	$all_donations = true;
	$all_no_shipping = true;

	foreach($cart as $item)
	{
		$product_data = $wpdb->get_results("SELECT * FROM `" . $wpdb->posts . "` WHERE `id`='".$item['prodid']."' LIMIT 1",ARRAY_A);
		$product_data = $product_data[0];
		$variation_count = count($product_variations);

		$variation_sql = "SELECT * FROM `".WPSC_TABLE_CART_ITEM_VARIATIONS."` WHERE `cart_id`='".$item['id']."'";
		$variation_data = $wpdb->get_results($variation_sql,ARRAY_A);
		$variation_count = count($variation_data);

		if($variation_count >= 1)
      	{
      		$variation_list = " (";
      		$j = 0;
      		foreach($variation_data as $variation)
        	{
        		if($j > 0)
          		{
          			$variation_list .= ", ";
          		}
        		$value_id = $variation['venue_id'];
        		$value_data = $wpdb->get_results("SELECT * FROM `".WPSC_TABLE_VARIATION_VALUES."` WHERE `id`='".$value_id."' LIMIT 1",ARRAY_A);
        		$variation_list .= $value_data[0]['name'];
        		$j++;
        	}
      		$variation_list .= ")";
      	}
      	else
        {
        	$variation_list = '';
        }
    
    	$local_currency_productprice = $item['price'];

			$local_currency_shipping = $item['pnp'];
    	

			$hellaspay_currency_productprice = $local_currency_productprice;
			$hellaspay_currency_shipping = $local_currency_shipping;
			
    	$data['item_name_'.$i] = $product_data['name'].$variation_list;
    	$data['amount_'.$i] = number_format(sprintf("%01.2f", $hellaspay_currency_productprice),$decimal_places,'.','');
    	$data['quantity_'.$i] = $item['quantity'];
    	$data['item_number_'.$i] = $product_data['id'];
    	
		if($item['donation'] !=1)
      	{
      		$all_donations = false;
      		$data['shipping_'.$i] = number_format($hellaspay_currency_shipping,$decimal_places,'.','');
      		$data['shipping2_'.$i] = number_format($hellaspay_currency_shipping,$decimal_places,'.','');      
      	}
      	else
      	{
      		$data['shipping_'.$i] = number_format(0,$decimal_places,'.','');
      		$data['shipping2_'.$i] = number_format(0,$decimal_places,'.','');
      	}
        
    	if($product_data['no_shipping'] != 1) {
      		$all_no_shipping = false;
      	}
    
		
		$total_price = $total_price + ($data['amount_'.$i] * $data['quantity_'.$i]);

		if( $all_no_shipping != false )
			$total_price = $total_price + $data['shipping_'.$i] + $data['shipping2_'.$i];

    	$i++;
	}
  	$base_shipping = $purchase_log[0]['base_shipping'];
  	if(($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false))
    {
		$data['handling_cart'] = number_format($base_shipping,$decimal_places,'.','');
		$total_price += number_format($base_shipping,$decimal_places,'.','');
    }

	$total_price = $wpsc_cart->calculate_total_price();
	
	$hellaspay_amount = round($total_price * 100);
	$hellaspay_ref = "REF".substr(md5(uniqid(rand(), true)), 0, 9);
	$TmSecureKey = 'd2ViaXQuYnovbGljZW5zZS50eHQ='; // for extra encryption options
	
	$MerchantID = get_option('hellaspay_merchantid');
	$Password =  get_option('hellaspay_merchantpass');
	
	$poststring['Amount'] = $hellaspay_amount;
	$poststring['RequestLang'] = get_option('hellaspay_language');
	
	$poststring['Email'] = $datadp['CardHolderEmail'];
	if(get_option('hellaspay_instal')=='No'){
	$poststring['MaxInstallments'] = '1';
	} else {
	$poststring['MaxInstallments'] = '36';
	}
	$poststring['MerchantTrns'] = $hellaspay_ref;
	$poststring['SourceCode'] = get_option('hellaspay_source');
	$poststring['PaymentTimeOut'] = '300';

	if(get_option('hellaspay_mode')=='Test'){
	$curl = curl_init("http://demo.vivapayments.com/api/orders");
	} else {
	$curl = curl_init("https://www.vivapayments.com/api/orders");
	curl_setopt($curl, CURLOPT_PORT, 443);
	}
	
	$postargs = 'Amount='.urlencode($poststring['Amount']).'&RequestLang='.urlencode($poststring['RequestLang']).'&Email='.urlencode($poststring['Email']).'&MaxInstallments='.urlencode($poststring['MaxInstallments']).'&MerchantTrns='.urlencode($poststring['MerchantTrns']).'&SourceCode='.urlencode($poststring['SourceCode']).'&PaymentTimeOut=300';
	
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password); 
	$curlversion = curl_version();
	if(!preg_match("/NSS/" , $curlversion['ssl_version'])){
	curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, "TLSv1");
	}
	
	// execute curl
	$response = curl_exec($curl);
	
	if(curl_error($curl)){
	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	$response = curl_exec($curl);
	}
	
	
		curl_close($curl);
		
		try {
		if (version_compare(PHP_VERSION, '5.3.99', '>=')) {
		$resultObj=json_decode($response, false, 512, JSON_BIGINT_AS_STRING);
		} else {
		$response = preg_replace( "/\"(\d+)\"/", '"$1"', $response );
		$resultObj = json_decode($response);
		}
		} catch( Exception $e ) {
			throw new Exception("Result is not a json object (" . $e->getMessage() . ")");
		}
		
		if ($resultObj->ErrorCode==0){	//success when ErrorCode = 0
		$OrderCode = $resultObj->OrderCode;
		$ErrorCode = $resultObj->ErrorCode;
		$ErrorText = $resultObj->ErrorText;
		}
		else{
			throw new Exception("Unable to create order code (" . $resultObj->ErrorText . ")");
		}
	
	if(get_option('hellaspay_mode')=='Test'){
	$hellaspay_url = "http://demo.vivapayments.com/web/newtransaction.aspx";
	} else {
	$hellaspay_url = "https://www.vivapayments.com/web/newtransaction.aspx";
	}
	
	$query = "insert into ". $wpdb->prefix . "hellaspay_data (ref, ordercode, email, total_cost, currency, order_state, sessionid, timestamp) values ('".$hellaspay_ref."','".$OrderCode."','". $datadp['CardHolderEmail'] ."','".$hellaspay_amount."','978','I','". $sessionid . "', now())";
	$wpdb->query($query);	
	
	// Create Form to post to hellaspay
	$output = "
		<form id=\"hellaspay_form\" name=\"hellaspay_form\" method=\"get\" action=\"$hellaspay_url\">\n";
		$output .= "			<input type=\"hidden\" name=\"Ref\" value=\"$OrderCode\" />\n";
	    $output .= "			<input type=\"submit\" value=\"Continue to Viva Payments\" />
		</form>
	";


	// echo form..
	if( get_option('hellaspay_debug') == 1)
	{
		echo ("DEBUG MODE ON!!<br/>");
		echo("The following form is created and would be posted to hellaspay for processing.  Press submit to continue:<br/>");
		echo("<pre>".htmlspecialchars($output)."</pre>");
	}

	echo($output);
	
	if(get_option('hellaspay_debug') == 0)
	{
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('hellaspay_form').submit();</script>";
	}

  	exit();
}

function nzshpcrt_hellaspay_callback()
{
	global $wpdb,$wpsc_cart;
	// needs to execute on page start
	// look at page 36
	if(isset($_GET['hellaspay_callback']) && $_GET['hellaspay_callback'] == 'true' && isset($_GET['success']) && isset($_GET['s']) ){
	$tm_ref = trim(addslashes($_GET['s']));

$check_query = $wpdb->get_results("SELECT order_state, sessionid FROM ". $wpdb->prefix . "hellaspay_data WHERE order_state='I' and ordercode = '".$tm_ref."'",ARRAY_A);
$check_query_count = count($check_query);
	if($check_query_count >= 1){
	$sessionid = $check_query[0]['sessionid'];
					$data = array(
						'processed'  => 3,
						'transactid' => $tm_ref,
						'date'       => time(),
					);
					$where = array( 'sessionid' => $sessionid );
					$format = array( '%d', '%s', '%s' );
					
					/*
					$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
					
					wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
					transaction_results($sessionid, false, $tm_ref);
					
					$purchase_log_object = new WPSC_Purchase_Log( $sessionid, 'sessionid' );
					$purchase_log = $purchase_log_object->get_data();
					*/
					
					//added for WP4/WPE 3.8.14.3
					wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
					transaction_results($sessionid, false, $tm_ref);
					//end added
					
					do_action('wpsc_payment_successful');
					$transaction_url_with_sessionid = esc_url_raw(add_query_arg( 'sessionid', $sessionid, get_option('transact_url')));
					wp_redirect( $transaction_url_with_sessionid );
					exit();
	}
} //end success

 //fail
if(isset($_GET['fail']) && isset($_GET['s'])){	
$tm_ref = trim(addslashes($_GET['s']));

$check_query = $wpdb->get_results("SELECT sessionid FROM ". $wpdb->prefix . "hellaspay_data WHERE ordercode = '".$tm_ref."'",ARRAY_A);
$check_query_count = count($check_query);
	if($check_query_count >= 1){
	$sessionid = $check_query[0]['sessionid'];
					$log_id = $wpdb->get_var( $wpdb->prepare( "SELECT `id` FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`=%s LIMIT 1", $sessionid ) );
	            	$delete_log_form_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`=%d", $log_id );
	            	$cart_content = $wpdb->get_results($delete_log_form_sql,ARRAY_A);
	            	foreach((array)$cart_content as $cart_item)
	              	{
	              		$cart_item_variations = $wpdb->query( $wpdb->prepare( "DELETE FROM `".WPSC_TABLE_CART_ITEM_VARIATIONS."` WHERE `cart_id` = %d", $cart_item['id'] ), ARRAY_A);
	              	}
	            	$wpdb->query( $wpdb->prepare( "DELETE FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`=%d", $log_id ) );
	            	$wpdb->query( $wpdb->prepare( "DELETE FROM `".WPSC_TABLE_SUBMITED_FORM_DATA."` WHERE `log_id` IN ( %d )", $log_id ) );
	            	$wpdb->query( $wpdb->prepare( "DELETE FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `id`=%d LIMIT 1", $log_id ) );					
					$location = get_option('transact_url')."?act=error";
					wp_redirect($location);
					exit();
	}
} //end fail


		// If in debug, email details
		if(get_option('hellaspay_debug') == 1)
		{
			$message = "This is a debugging message sent because it appears that you are in debug mode.\n\rEnsure hellaspay debug is turned off once you are happy with the function.\n\r\n\r";
			$message .= "OUR_POST:\n\r".print_r($header . $req,true)."\n\r\n\r";
			$message .= "THEIR_POST:\n\r".print_r($_POST,true)."\n\r\n\r";
			$message .= "GET:\n\r".print_r($_GET,true)."\n\r\n\r";
			$message .= "SERVER:\n\r".print_r($_SERVER,true)."\n\r\n\r";
			mail(get_option('purch_log_email'), "hellaspay Data", $message);
		}
	}


function nzshpcrt_hellaspay_results()
{
	// Function used to translate the ChronoPay returned cs1=sessionid POST variable into the recognised GET variable for the transaction results page.
	if(isset($_POST['Param2']) && ($_POST['Param2'] !='') && ($_GET['sessionid'] == ''))
	{
		$_GET['sessionid'] = $_POST['Param2'];
	}
}

function submit_hellaspay()
{
	if(isset($_POST['hellaspay_merchantid']))
    {
    	update_option('hellaspay_merchantid', $_POST['hellaspay_merchantid']);
    }
	
	if(isset($_POST['hellaspay_merchantpass']))
    {
    	update_option('hellaspay_merchantpass', $_POST['hellaspay_merchantpass']);
    }
	
	if(isset($_POST['hellaspay_source']))
    {
    	update_option('hellaspay_source', $_POST['hellaspay_source']);
    }
	
	
	if(isset($_POST['hellaspay_language']))
    {
    	update_option('hellaspay_language', $_POST['hellaspay_language']);
    }
	
	if(isset($_POST['hellaspay_instal']))
    {
    	update_option('hellaspay_instal', $_POST['hellaspay_instal']);
    }

  	if(isset($_POST['hellaspay_debug']))
    {
    	update_option('hellaspay_debug', $_POST['hellaspay_debug']);
    }
	
	if(isset($_POST['hellaspay_mode']))
    {
    	update_option('hellaspay_mode', $_POST['hellaspay_mode']);
    }

    if (!isset($_POST['hellaspay_form'])) $_POST['hellaspay_form'] = array();
	foreach((array)$_POST['hellaspay_form'] as $form => $value)
    {
    	update_option(('hellaspay_form_'.$form), $value);
    }
	return true;
}

function form_hellaspay()
{
global $wpdb;
$query = "CREATE TABLE IF NOT EXISTS ". $wpdb->prefix . "hellaspay_data (id int(11) unsigned NOT NULL AUTO_INCREMENT, ref varchar(100) DEFAULT NULL, ordercode varchar(255) DEFAULT NULL, email varchar(150) DEFAULT NULL, orderid varchar(100) DEFAULT NULL, total_cost int(11) DEFAULT NULL, currency char(3) DEFAULT NULL, tm_password varchar(100) DEFAULT NULL, order_state char(1) DEFAULT NULL, sessionid varchar(32) DEFAULT NULL, timestamp datetime DEFAULT NULL, PRIMARY KEY (id))";	
$wpdb->query($query);
	
	$hellaspay_debug = get_option('hellaspay_debug');
	$hellaspay_debug1 = "";
	$hellaspay_debug2 = "";
	switch($hellaspay_debug)
	{
		case 0:
			$hellaspay_debug2 = "checked ='checked'";
			break;
		case 1:
			$hellaspay_debug1 = "checked ='checked'";
			break;
	}

	$hellaspay_mode = get_option('hellaspay_mode');
	$hellaspay_mode1 = "";
	$hellaspay_mode2 = "";
	switch($hellaspay_mode)
	{
		case 'Live':
			$hellaspay_mode2 = "checked ='checked'";
			break;
		case 'Test':
			$hellaspay_mode1 = "checked ='checked'";
			break;
	}
	
	
	$hellaspay_instal = get_option('hellaspay_instal');
	$hellaspay_instal1 = "";
	$hellaspay_instal2 = "";
	switch($hellaspay_instal)
	{
		case 'No':
			$hellaspay_instal2 = "checked ='checked'";
			break;
		case 'Yes':
			$hellaspay_instal1 = "checked ='checked'";
			break;
	}
	
	$output = "
		<tr>
			<td>Merchant ID</td>
			<td><input type='text' size='50' value='".get_option('hellaspay_merchantid')."' name='hellaspay_merchantid' /></td>
		</tr>
		<tr>
			<td>API Key</td>
			<td><input type='text' size='40' value='".get_option('hellaspay_merchantpass')."' name='hellaspay_merchantpass' /></td>
		</tr>
		<tr>
			<td>Source Code</td>
			<td><input type='text' size='40' value='".get_option('hellaspay_source')."' name='hellaspay_source' /></td>
		</tr>
		<tr>
			<td>Viva Payments Language</td>
			<td><input type='text' size='40' value='".get_option('hellaspay_language')."' name='hellaspay_language' />
			<br /><small><strong>el-GR</strong> for Greek <strong>en-US</strong> for English</small>
			</td>
		</tr>
		<tr>
			<td>Instalments</td>
			<td>
				<input type='radio' value='No' name='hellaspay_instal' id='hellaspay_instal1' ".$hellaspay_instal1." /> <label for='hellaspay_instal1'>".__('No Instalments', 'wpsc')."</label> &nbsp;
				<input type='radio' value='Yes' name='hellaspay_instal' id='hellaspay_instal2' ".$hellaspay_instal2." /> <label for='hellaspay_instal2'>".__('Allow Instalments', 'wpsc')."</label>
			</td>
		</tr>
		
		<tr>
			<td>Operation Mode</td>
			<td>
				<input type='radio' value='Test' name='hellaspay_mode' id='hellaspay_mode1' ".$hellaspay_mode1." /> <label for='hellaspay_mode1'>".__('Testing', 'wpsc')."</label> &nbsp;
				<input type='radio' value='Live' name='hellaspay_mode' id='hellaspay_mode2' ".$hellaspay_mode2." /> <label for='hellaspay_mode2'>".__('Live', 'wpsc')."</label>
			</td>
		</tr>
		<tr>
			<td>Debug Mode</td>
			<td>
				<input type='radio' value='1' name='hellaspay_debug' id='hellaspay_debug1' ".$hellaspay_debug1." /> <label for='hellaspay_debug1'>".__('Yes', 'wpsc')."</label> &nbsp;
				<input type='radio' value='0' name='hellaspay_debug' id='hellaspay_debug2' ".$hellaspay_debug2." /> <label for='hellaspay_debug2'>".__('No', 'wpsc')."</label>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><small>Debug mode is used to write HTTP communications between the Viva Payments server and your host to a log file.  This should only be activated for testing!</small></td>
		</tr>";
	
	return $output;
}
  
  
add_action('init', 'nzshpcrt_hellaspay_callback');
add_action('init', 'nzshpcrt_hellaspay_results');
	
?>