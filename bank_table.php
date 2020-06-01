<?php
require_once('class.php');
$dz = new dzTraderBankLogging();
($dz->IssetCheck('GET', 'hours')) ? $hours = $_GET['hours'] : $hours = 24;
($dz->IssetCheck('GET', 'limit')) ? $limit = $_GET['limit'] : $limit = 500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transactions table from past <?php echo $hours; ?> hours</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.min.css"/>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <?php
            $dz->navBar('BANK_TABLE');
            $cfg = new configConnect();
            if ($cfg::HAS_ATM) {
                $dz->bankTablePreface($hours, $limit);
                $dz->recentBankTable($hours, $limit);
            }
            $dz->footerText();
            ?>
        </div>
    </div>
</div>
</body>
</html>