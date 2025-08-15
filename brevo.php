<?php
/**
 * Brevo specific part for adding a new email as contact and add that contact to mailing-lists
 */


function brevo_upsert_contact($email, $listIds, $attributes, $secret)
{
    $log = new Log_Viewer();
    $data = 
    [
        "email" => $email, 
        "listIds" => $listIds, 
        "attributes" => $attributes,
        "updateEnabled" => true
    ];

    $log->write_log("Send data to Brevo: ".print_r($data,true));
    $response = callBrevoEndpoint("contacts",$secret, $data);
	$log->write_log("Brevo response: ".print_r($response, true), ($response['success'] ? "INFO" : "ERROR"));
    return $response['success'];
}

function callBrevoEndpoint($url, $apiKey, $data) 
{
    // JSON-Body erstellen
    $jsonData = json_encode($data);
    
    // cURL-Session initialisieren
    $curl = curl_init();

    $brevoBaseEndpoint = "https://api.brevo.com/v3/";
    
    // cURL-Optionen setzen
    curl_setopt_array($curl, [
        CURLOPT_URL => $brevoBaseEndpoint.$url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'api-key: ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    // Request ausführen
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    // cURL-Session schließen
    curl_close($curl);
    
    // Response zurückgeben
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $response
    ];
}

?>