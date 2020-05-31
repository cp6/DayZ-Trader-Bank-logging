<?php
require_once('class.php');
$dz = new dzTraderBankLogging();
if ($dz->uidSet()) {
    $uid = $dz->uidSet();
} else {
    $dz->playerUidForm();
    exit;
}
($dz->IssetCheck('GET', 'type')) ? $type = $dz->IssetCheck('GET', 'type') : $type = 'trade';
if ($type == 'trade') {
    ($dz->IssetCheck('GET', 'action')) ? $action = $dz->IssetCheck('GET', 'action') : $action = 'all';
}
($dz->IssetCheck('GET', 'days')) ? $days = $dz->IssetCheck('GET', 'days') : $days = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $dz->playerHistoryPageTitleBuilder($uid, $action, $type, $days); ?></title>
    <link rel="stylesheet" href="style.min.css"/>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <?php
            $dz->navBar('PLAYER_HISTORY');
            $dz->playerHistoryCard($uid, 'trade');
            if ($type == 'trade') {
                $dz->playerTradeHistoryTable($uid, $action, $days);
            } elseif ($type == 'atm') {
                $dz->playerATMHistoryTable($uid);
            }
            $dz->footerText();
            ?>
        </div>
    </div>
</div>
</body>
</html>