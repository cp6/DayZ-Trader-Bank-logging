<?php
require_once('class.php');
$dz = new dzTraderBankLogging();
$dz->sessionStart();
if ($dz->checkIsLoggedIn()) {
    $dz->logout(true);
} else {
    $dz->headerExit('index.php');
}