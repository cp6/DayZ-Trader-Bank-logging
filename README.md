# DayZ Trader and Bank logging

### Requires

[Trader Bank logging from Steam Workshop](https://steamcommunity.com/sharedfiles/filedetails/?id=2114540423 "@TBLogging") OR [Trader logging from Steam Workshop](https://steamcommunity.com/sharedfiles/filedetails/?id=2115376735 "@TLogging")

[Trader mod by Dr_J0nes](https://steamcommunity.com/sharedfiles/filedetails/?id=1590841260)

[Banking mod by Deadcraft](https://steamcommunity.com/workshop/filedetails/?id=1836257061)


### Instructions

Open `class.php` and edit your DayZ server profile directory at line 9. This is where your log files and other mod config files are.

If you are only doing trader logging set `HAS_ATM = false;` at line 11 of `class.php`

Run `database.sql` onto your MySQL server.

Edit in MySQL connection details, starting line 16 of `class.php`. 

*You can have a SELECT only privileged user for tight security on the front end or just use an INSERT, UPDATE, SELECT privileged user for the whole class.*

If using admin system add your Steam API key into line 20 of `class.php` at `$api_key = 'APIKEYHERE';`

#### Fetching logs

Putting log files into the Database (`fetch.php`):

```php
<?php
require_once('class.php');
$dz = new dzTraderBankLogging();

$dz->setLogType('trade');//Trader logs
$dz->processLogs();

//Dont need following if only doing trader logs:
$dz->setLogType('atm');//ATM logs
$dz->processLogs();

//To Get yesterdays logs use setDateAsYesterday()
//Like:
$dz->setLogType('trade');//Trader logs
$dz->setDateAsYesterday();//Get yesterdays (date) logfile
$dz->processLogs();
```
Point a Cron job at this for two minutes for up-to date data or once an hour otherwise.
___

### Admin system
You can now protect the pages from anyone and everyone viewing them if they knew its URL.

Setting 
```php
ONLY_ADMINS_CAN_VIEW = true;
```
at line 18 of `class.php`

Means that to access any of the pages you need to sign in with Steam and have your Steam uid in the admins table.

This authorization is done with [OpenId](https://openid.net/) `openid.php`

##### Adding admins:
```sql
INSERT IGNORE INTO `dz_tb_logs`.`admins` (`uid`) VALUES ('ADMIN_STEAM_UID_HERE');
```

##### Updating existing database to add admin table:
*Only for those with a previous version installed
```sql
USE `dz_tb_logs`;
CREATE TABLE `admins` (
	`uid` VARCHAR(64) NULL,
	PRIMARY KEY (`uid`)
)
COLLATE='latin1_swedish_ci';
```

`index.php` has most recent hours set as 24 by default and limit for most recent table as 100 default.
Change these with: `index.php?hours=X&limit=X`

#### Screenshots

![](https://steamuserimages-a.akamaihd.net/ugc/1044219148020747745/112A3D2EFCA4C07C25532AD04F81315028805665/?imw=637&imh=358)

![](https://steamuserimages-a.akamaihd.net/ugc/1044219148020736417/758A2331CCEBF035CF4AA488D060EB6ABF5B2079/?imw=637&imh=358)

![](https://steamuserimages-a.akamaihd.net/ugc/1044219148020748533/7DA322E50BB87607F2E51DB90C5EAB2FA2C1B18E/?imw=637&imh=358)

![](https://steamuserimages-a.akamaihd.net/ugc/1044219148020748836/75776473D43AA4840620BCBE47037A44CDD2FDF9/?imw=637&imh=358)



