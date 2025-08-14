<?php

$logFileName = "ipn-adapter.log";

function log_to_file($msg) {
    global $logFileName;
    error_log(date('Y-m-d H:i:s') . "  " . $msg . PHP_EOL, 3, $logFileName);
}

?>
