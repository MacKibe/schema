<?php
namespace root;

include_once 'schema.php';
//
$x = microtime(true);
$dbase= new database('mutallco_login');
$y=microtime(true);
//
$json = json_encode($dbase, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
//
$html = "<pre>$json</pre>";
?>
<html>
    <head>
        
    </head>
    <body>
        <?php
        echo $y-$x;
        ?>
    </body>
</html>
