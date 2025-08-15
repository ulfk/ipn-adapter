<?php
/**
 * @package IPN-Adapter
 */
/*
Plugin Name: IPN-Adapter
Plugin URI: https://github.com/ulfk/ipn-adapter
Description: IPN Adapter by Ulf Kuehnle.
Version: 0.1.2
Author: Ulf Kuehnle
Author URI: https://ulf-kuehnle.de/
License: free
*/

include_once "settings.php";
include_once "logging.php";
include_once "brevo.php";
include_once "digistore.php";

function get_request_data() {
	return array_merge(empty($_POST) ? array() : $_POST, (array) json_decode(file_get_contents('php://input'), true), $_GET);
}

function has_valid_email($data) {
	$email = get_email($data);
	return $email != "" && $email != null;
}


log_to_file("*********************************");

$data = get_request_data();
log_to_file("INFO Data received from Digistore: " . print_r($data, true));

if (!has_valid_signature($data, $settings["DIGISTORE_SECRET"]))
{
    log_to_file("ERROR: invalid digitore signature");
    http_response_code(403); 
    exit();
}

if (!has_valid_email($data))
{
    log_to_file("ERROR: no valid email found");
    http_response_code(400); 
    exit();
}

$email = get_email($data);
$product = $data['product_id'];
$event = $data['event'];
$attributes = [
    'VORNAME' => $data['address_first_name'] ?? null, 
    'NACHNAME' => $data['address_last_name'] ?? null, 
    'COURSE_ID' => $product, 
    'LAST_ORDER_ID' => $data['order_id'] ?? null
];
$listIds = array();
if(isset($settings["COURSE_LIST_ID_".strval($product)])) {
    $listIds[] = $settings["COURSE_LIST_ID_".strval($product)];
}
if ($settings["AddToNewsletterList"] && isset($settings["NEWSLETTER_LIST_ID"])) {
    $listIds[] = $settings["NEWSLETTER_LIST_ID"];
}

if ($event === 'on_payment') {
    $succeeded = brevo_upsert_contact($email, $listIds, $attributes, $settings["BREVO_SECRET"]);
    if(!$succeeded) {
        log_to_file("ERROR: Brevo call failed");
        http_response_code(502); 
        exit();       
    }
}

echo "OK";
exit();

?>