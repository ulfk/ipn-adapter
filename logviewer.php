<?php
if ($_GET["key"] != "your-secret-key") 
{
    http_response_code(403);
    exit;
}
?>
<html>
    <head>
        <title>IPN Logs</title>
</head>
<body style="font-family: Courier New">
    <pre>
<?php
echo file_get_contents("ipn-adapter.log");
?>
</pre>
</body>
</html>
