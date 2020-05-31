<!DOCTYPE html>
<html lang="en">
<head>
    <title>DZ TBLogging home</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.min.css"/>
</head>
<body>
<div class="container">
    <?php
    require_once('class.php');
    $dz = new dzTraderBankLogging();
    ($dz->IssetCheck('GET', 'hours')) ? $hours = $_GET['hours'] : $hours = 24;
    ($dz->IssetCheck('GET', 'limit')) ? $limit = $_GET['limit'] : $limit = 100;
    $dz->navBar('HOME');
    $dz->traderTallies($hours);
    $dz->hotTradingRow($hours, 4);
    $dz->itemsCountTables(10);
    $dz->richListTablePreface();
    $dz->richListTable(10);
    $dz->mainTablePreface($hours, $limit);
    $dz->recentTradeTable($hours);
    $dz->footerText();
    ?>
</div>
</body>
</html>