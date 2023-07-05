<?php

include __DIR__ . '/vendor/autoload.php'; 
use Escopecz\MauticFormSubmit\Mautic;
use Escopecz\MauticFormSubmit\Mautic\Config;

// Require Composer autoloader
use Mautic\MauticApi;
use Mautic\Auth\ApiAuth;

class MiracleAbandonedCartsCronJob
{
	public $settings = array(
		'userName'   => 'username',
		'password'   => 'password'
	);
	public $apiUrl     = "mauticurl";
	
	public function abandonedCarts()
	{
	    $Posts = [9848, 12910, 9850, 9849];
	    foreach($Posts as $key => $value){
	        $Post = get_post($value);
		    $Post_Date = strtotime($Post->post_date);
		    $FiveDays = 5*24*60*60;
		    $Current_Date = time() - ($FiveDays);
		    if($Post_Date < $Current_Date){
			    $Post->post_date = date('Y/m/d H:i:s', (time() - (24*60*60)));
			    wp_update_post($Post);
		    }   
	    }
		global $wpdb;
		session_start();
		$initAuth = new ApiAuth();
		$auth = $initAuth->newAuth($this->settings, 'BasicAuth');
		$api = new MauticApi();
		$formApi = $api->newApi("forms", $auth, $this->apiUrl);
		$formResponse = $formApi->getSubmissions(22);
		$contactApi = $api->newApi("contacts", $auth, $this->apiUrl);

		$timeStamp = time();

		$abandonedCarts = $wpdb->get_results("
		SELECT * 
		FROM  {$wpdb->prefix}abandonned_cart
	");
	    try{
		foreach ($abandonedCarts as $abandonedCartKey => $abandonedCart) {
			$oldTimeStamp = $abandonedCart->timestampCart;
			$timeStamp = 20 * 60 * 1000;
			$newTimeStamp = $oldTimeStamp - $timeStamp;
			$orders = wc_get_orders(array('numberposts' => -1));
			$orderID = false;
			foreach ($orders as $order) {
				if ($abandonedCart->cart_hash == $order->get_cart_hash()) {
					$orderID = $order->ID;
				}
			}
			if ($oldTimeStamp - $newTimeStamp >= 1200000) {
				$contentJson = json_decode($abandonedCart->cartContents);
				$htmlContent = "
					<div class='tableProducts'>
						<div class='columnProducts'>";
				$abandonedContent = [];
				foreach ($contentJson as $key => $value) {
					$productVariable = new WC_Product_Variable($value->product_id);
					$variations = $productVariable->get_available_variations();
					foreach ($variations as $variation) {
						if ($variation['variation_id'] == $value->variation_id) {
							$variationSrc = $variation['image']['thumb_src'];
							$productImg = $this->UploadMidia($variationSrc);
						}
					}
					$product = array(
					    "product_id" => "$value->product_id",
					    "variation_id" => "$value->variation_id",
						"Title" => "$value->title",
						"color" => "$value->attribute_pa_color",
						"size" => "$value->attribute_size",
						"quantity" => "$value->quantity",
						"regular_price" => "$value->regular_price",
						"sale_price" => "$value->sale_price",
						"img" => $productImg,
						"total" => "$value->line_total"
					);
					array_push($abandonedContent, $product);
					$htmlContent .= "
							<div class='product'>
								<div class=' product_img'>
									<img src='" . $product['img'] . "'>
								</div>
								<div class='informationsProduct'>
									<div class='cellProduct product_title'>
										" . $product['Title'] . "
									</div>
									<div class='cellProduct product_quantity'>
										<span>Quantity: </span>" . $product['quantity'] . "
									</div>
									<div class='cellProduct product_total'>
										<span>Total Price: $</span>" . number_format($product['total'], 2, ',', '.') . "
									</div>
								</div>
							</div>";
				}
				$htmlContent .= "</div>
					</div>";
				if ($orderID == false) {
					$orderStatus = "draft-cart";
				} else {
					$order = new WC_Order($orderID);
					$orderStatus = $order->get_status();
				}


				$beforeUpdated = false;
				foreach ($formResponse as $keyResponse => $submissions) {
					if ($keyResponse == "submissions") {
						foreach ($submissions as $keysSubmission => $submission) {
							if ($submission['results']['abandoned_cart_hash'] == $abandonedCart->cart_hash) {
								$submissionId = $submission["id"];
								$beforeUpdated = true;
							}

						}
					}
				}

				if (!$beforeUpdated) {
					$this->submitToMautic($abandonedCart->email, $abandonedCart->cart_hash, $abandonedCart->timestampCart, json_encode($abandonedContent), $orderStatus);
				} else {
					$contacts = $contactApi->getList();
					$updateContact = false;
					foreach ($contacts['contacts'] as $key => $contact) {
						if ($contact['fields']['core']['email']['value'] == $abandonedCart->email) {
							if ($contact['fields']['core']['wporderstatus']['value'] != $orderStatus) {
								$updateContact = true;
							}
						}
					}
					if ($updateContact) {
						$this->submitToMautic($abandonedCart->email, $abandonedCart->cart_hash, $abandonedCart->timestampCart, json_encode($abandonedContent), $orderStatus);
						if ($orderStatus == 'completed' || $orderStatus == 'processing' || $orderStatus == 'cancelled') {
                            $to = "szguisantos@gmail.com";
		                    $subject = 'teste';
		                    $message = $abandonedCart->email . " Email: " . $orderStatus;
		                    $headers = array('From: FlowBeachTennis Alert <contact@flowbeachtennis.com>' . "\r\n", 'Content-Type: text/html; charset=UTF-8');
		                    wp_mail($to, $subject, $message, $headers);
							//deletar do banco de dados do wp
							$wpdb->delete($wpdb->prefix . "abandonned_cart", array('id' => $abandonedCart->id));
						}
					}
				}


			}
		}
	  }catch(Exception $err){
        $to = "szguisantos@gmail.com";
		$subject = 'teste';
		$message = $err;
		$headers = array('From: FlowBeachTennis Alert <contact@flowbeachtennis.com>' . "\r\n", 'Content-Type: text/html; charset=UTF-8');
		wp_mail($to, $subject, $message, $headers);
	  }
	}
	public function submitToMautic($email, $hash, $timestamp, $content, $orderStatus)
	{
		$abandonedCartDate = date('Y/m/d H:i:s', $timestamp);
		$config = new Config;
		$config->setCurlVerbose(true);
		$mautic = new Mautic($this->apiUrl);
		$form = $mautic->getForm(22);
		$result = $form->submit(['abandoned_cart_email' => "$email", 'abandoned_cart_hash' => "$hash", 'abandoned_cart_status' => "$orderStatus", 'abandoned_cart_time_stamp' => "$abandonedCartDate", 'abandoned_cart_content' => "$content"]);
		$to = "szguisantos@gmail.com";
		$subject = 'teste';
		$message = $err;
		$headers = array('From: FlowBeachTennis Alert <contact@flowbeachtennis.com>' . "\r\n", 'Content-Type: text/html; charset=UTF-8');
		wp_mail($to, $subject, $message, $headers);
	}
	public function UploadMidia($imgUrl)
	{
		session_start();
		$initAuth = new ApiAuth();
		$auth = $initAuth->newAuth($this->settings, 'BasicAuth');
		$api = new MauticApi();
		$assetApi = $api->newApi("assets", $auth, $this->apiUrl);
		$asset = $assetApi->getList("name:$imgUrl");
		$data = array(
			'title' => "$imgUrl",
			'storageLocation' => 'remote',
			'file' => "$imgUrl"
		);
		if ($asset['total'] > 0) {
			return $asset['assets'][0]['downloadUrl'];
		} else {
			$response = $assetApi->create($data);
			return $response['asset']['downloadUrl'];
		}
	}
}
