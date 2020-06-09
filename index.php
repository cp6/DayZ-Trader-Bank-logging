<?php
require_once('class.php');//Class file is required!
$dz = new dzTraderBankLogging();//Call instance of dzTraderBankLogging
?>
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
    if ($dz->mainViewSystem()) {//Is admin system in use or everyone can view
        //If ?hours=12 in URL $hours will be set as 12 else if not in URL default is 24
        ($dz->IssetCheck('GET', 'hours')) ? $hours = $_GET['hours'] : $hours = 24;
        //If &limit=50 in URL $limit will be set as 50 else if not in URL default is 100
        ($dz->IssetCheck('GET', 'limit')) ? $limit = $_GET['limit'] : $limit = 100;
        ($dz->IssetCheck('GET', 'hotlimit')) ? $hot_limit = $_GET['hotlimit'] : $hot_limit = 4;
        ($dz->IssetCheck('GET', 'otherlimit')) ? $other_limit = $_GET['otherlimit'] : $other_limit = 10;
        $dz->navBar('HOME');//Nav bar home text is active
        $dz->traderTallies($hours);//Trader tallies for past defined hours
        $dz->hotTradingRow($hours, $hot_limit);//Highest count amount for hours, amount to show
        $dz->itemsCountTables($other_limit);//Total bought and sold, X amount to show.
        if ($dz::HAS_ATM) {//Has banking/atm enabled (true)
            $dz->richListTablePreface();//Text explaining the table below
            $dz->richListTable($other_limit);//Shows the richest X players per last ATM access.
        }
        $dz->mainTablePreface($hours, $limit);//Text explaining the table below
        $dz->recentTradeTable($hours, $limit);//Recent trades made for defined hours, max rows to show
        $dz->footerText();//Footer text string
    } else {//Not logged in OR not an Admin
        $dz->loginButtonPressed();//Login button was pressed
        $dz->unAuthOutputs();//Not an admin OR login is required
    }
    ?>
</div>
</body>
</html>