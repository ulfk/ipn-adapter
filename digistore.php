<?php

/**
 * Digistore specific code
 */

// This function was copied from https://www.digistore24.com/download/ipn/examples/ipn/sha_sign.php
function calculate_digistore_signature($sha_passphrase, $parameters, $convert_keys_to_uppercase = false, $do_html_decode=false )
{
    $algorythm           = 'sha512';
    $sort_case_sensitive = true;

    if (!$sha_passphrase)
    {
        return 'no_signature_passphrase_provided';
    }

    unset( $parameters[ 'sha_sign' ] );
    unset( $parameters[ 'SHASIGN' ] );

    if ($convert_keys_to_uppercase)
    {
        $sort_case_sensitive = false;
    }

    $keys = array_keys($parameters);
    $keys_to_sort = array();
    foreach ($keys as $key)
    {
        $keys_to_sort[] = $sort_case_sensitive
            ? $key
            : strtoupper( $key );
    }

    array_multisort( $keys_to_sort, SORT_STRING, $keys );

    $sha_string = "";
    foreach ($keys as $key)
    {
        $value = $parameters[$key];

        if ($do_html_decode) {
            $value = html_entity_decode( $value );
        }

        $is_empty = !isset($value) || $value === "" || $value === false;
        if ($is_empty)
        {
            continue;
        }

        $upperkey = $convert_keys_to_uppercase
            ? strtoupper( $key )
            : $key;

        $sha_string .= "$upperkey=$value$sha_passphrase";
    }

    $sha_sign = strtoupper( hash( $algorythm, $sha_string) );

    return $sha_sign;
}

function has_valid_signature($data, $secret)
{
    $receivedSignature = isset( $data['sha_sign'] ) ? $data['sha_sign'] : false;
    $calculatedSignature = calculate_digistore_signature($secret, $data);

    return $calculatedSignature == $receivedSignature;
}


function get_email($data) {
	return $data['buyer_email'] ?? $data['address_email'];
}

?>