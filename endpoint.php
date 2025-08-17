<?php

include_once "settings.php";
include_once "logging.php";
include_once "brevo.php";
include_once "digistore.php";

function get_request_data() {
	return array_merge(empty($_POST) ? array() : $_POST, (array) json_decode(file_get_contents('php://input', false, null, 0, 50*1014), true), $_GET);
}

function has_valid_email($data) {
	$email = get_email($data);
	return $email != "" && $email != null;
}

/*======================================================== */

$log_viewer = new Log_Viewer();
$log_viewer->write_log("*********************************");

$settings_manager = new Settings_Manager();
$settings = $settings_manager->load_settings_from_file();

$data = get_request_data();
$log_viewer->write_log("Data received from Digistore: " . print_r($data, true));

if (!has_valid_signature($data, $settings["DIGISTORE_SECRET"]))
{
    $log_viewer->write_log("Invalid digitore signature", "ERROR");
    http_response_code(403); 
    exit();
}

if (!has_valid_email($data))
{
    $log_viewer->write_log("No valid email found", "ERROR");
    http_response_code(400); 
    exit();
}

$email = get_email($data);
$product = $data['product_id'];
$event = $data['event'];
$attributes = [
    'VORNAME' => $data['address_first_name'] ?? null, 
    'NACHNAME' => $data['address_last_name'] ?? null, 
    'PRODUCT_ID' => $product, 
    'LAST_ORDER_ID' => $data['order_id'] ?? null
];
$listIds = array();
if(isset($settings["PRODUCT_LIST_ID_".strval($product)])) {
    $listIds[] = $settings["PRODUCT_LIST_ID_".strval($product)];
}
if ($settings["AddToNewsletterList"] && isset($settings["NEWSLETTER_LIST_ID"])) {
    $listIds[] = $settings["NEWSLETTER_LIST_ID"];
}

if ($event === 'on_payment') {
    $succeeded = brevo_upsert_contact($email, $listIds, $attributes, $settings["BREVO_SECRET"]);
    if($succeeded) {
        $log_viewer->write_log("Brevo call succeded");
    } else {
        $log_viewer->write_log("Brevo call failed", "ERROR");
        http_response_code(502); 
        exit();       
    }
}

echo "OK";
exit();

?>