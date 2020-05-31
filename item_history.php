<?php
require_once('class.php');
$dz = new dzTraderBankLogging();
($dz->IssetCheck('GET', 'hours')) ? $hours = $_GET['hours'] : $hours = 24;
($dz->IssetCheck('GET', 'id')) ? $item_id = $_GET['id'] : $item_id = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>DZ TBLogging item history <?php echo $item_id; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.min.css"/>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <?php
            $dz->navBar('ITEM_HISTORY');
            ($item_id) ? $dz->itemTradeHistoryTable($item_id, $hours) : $dz->noItemIdSetCard();
            $dz->footerText();
            ?>
        </div>
    </div>
</div>
</body>
</html>