<?php
include_once "settings.php";
if ($_GET["key"] != $settings["LOG_VIEWER_KEY"]) 
{
    http_response_code(403);
    exit;
}
?>
<html>
    <head>
        <title>IPN Logs</title>
</head>
<body style="font-family: Arial">
    <h2>IPN Adapter Logs</h2>
    <pre>
    <?php
    echo file_get_contents("ipn-adapter.log");
    ?>
    </pre>
</body>
</html>
