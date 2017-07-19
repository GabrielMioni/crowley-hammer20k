<?php

require_once('company_data_viewer.php');

$company_viewer = new company_data_viewer();

$table = $company_viewer->return_table();

?>

<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="UTF-8">
        <title>Crowley-Hammer10k</title>
        <link href="https://fonts.googleapis.com/css?family=Fjalla+One|Oswald" rel="stylesheet">
        <style>
            table {
                border-collapse: collapse;
                border-style: inset;
                font-family: "Oswald",sans-serif;
                margin: 0 auto;
                font-size: 20px;
            }

            th {
                padding-right: 20px;
                text-align: left;
            }

            .company {
                padding-right: 20px;
            }
        </style>
    </head>
    <body>
    <?php echo $table; ?>
    </body>
</html>
