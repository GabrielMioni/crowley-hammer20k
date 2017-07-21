<?php

require_once('company_data_viewer.php');

$company_viewer = new company_data_viewer();

$table = $company_viewer->return_table();

?>

<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="UTF-8">
        <title>Crowley-Hammer 20k</title>
        <link href="https://fonts.googleapis.com/css?family=Fjalla+One|Oswald" rel="stylesheet">
        <link rel="stylesheet" href="css/crowley-hammer20k.css">
    </head>
    <body>
        <h1>CROWLEY-HAMMER 20K</h1>
        <?php echo $table; ?>
    </body>
</html>
