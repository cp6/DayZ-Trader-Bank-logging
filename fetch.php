<?php
require_once('class.php');
$dz = new dzTraderBankLogging();
$dz->setLogType('trade');//Trader logs
$dz->processLogs();

$dz->setLogType('atm');//ATM logs
$dz->processLogs();