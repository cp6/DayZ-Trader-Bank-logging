<?php

/**
 * DZ TBLogging
 * @author corbpie
 */
class configConnect
{
    const LOGS_DIR = "C:\Users\administrator\AppData\Local\DayZ\Trader\TBlogs\\";//TBlogs folder; Your DayZ server profile location
    const HAS_TRADER = true;//Always be true
    const HAS_ATM = true;//Set as false if not using banking/ATM mod

    const DISPLAY_PLAYER_NAMES = true;//Set as false if wanting to display player UID's
    const DISPLAY_ITEM_NAMES = true;//Set as false if wanting to display item class names

    const FIX_BROKEN_LOG_LINES = false;//"Try to" fix log lines that are split into 2 (unknown cause)

    public function db_connect(bool $select_only = false): object
    {
        if ($select_only) {//SELECT only MySQL user privilege (front end)
            $db_host = '127.0.0.1';
            $db_name = 'dz_tb_logs';
            $db_user = 'root';
            $db_password = '';
        } else {//MySql user that has INSERT and UPDATE privileges
            $db_host = '127.0.0.1';
            $db_name = 'dz_tb_logs';
            $db_user = 'root';
            $db_password = '';
        }
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC);
        return new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_password, $options);
    }
}

class dzTraderBankLogging
{
    private $log_type;
    private $date;

    public function __construct()
    {
        $this->date = date('Y-m-d');
    }

    public function db_connect(bool $select_only = false): object
    {
        return (new configConnect)->db_connect($select_only);
    }

    public function setDateAsYesterday(): void
    {
        $this->date = date('Y-m-d', strtotime("-1 days"));
    }

    public function setLogType(string $log_type): void
    {
        if ($log_type === 'trade' || $log_type === 'atm') {
            $this->log_type = $log_type;
        }
    }

    public function isFileFound(string $file): bool
    {
        if (file_exists($file)) {
            return true;
        } else {
            return false;
        }
    }

    public function lineExplode(string $line): array
    {
        return explode(',', $line);
    }

    public function tradeActionStringToInt(string $action): int
    {
        if ($action == 'BUY') {
            return 1;//Bought
        } elseif ($action == 'SELL') {
            return 2;//Sold
        } else {
            return 3;//What is it then???
        }
    }

    public function bankActionStringToInt(string $action): int
    {
        if ($action == 'DEPOSIT') {
            return 1;
        } elseif ($action == 'WITHDRAW') {
            return 2;
        } else {
            return 3;//What is it then???
        }
    }

    public function bankActionIntToString(int $action): string
    {
        if ($action == 1) {
            return 'Deposit';
        } elseif ($action == 2) {
            return 'Withdraw';
        } else {
            return 'Unknown';//What is it then???
        }
    }

    public function itemIdForClass(string $classname, string $name): int
    {
        $db = $this->db_connect();
        $select = $db->prepare("SELECT `id` FROM `items` WHERE `classname` = ? LIMIT 1;");
        $select->execute([$classname]);
        $row = $select->fetch();
        if ($select->rowCount() > 0) {
            return $row['id'];
        } else {
            $insert = $db->prepare('INSERT IGNORE INTO `items` (`classname`, `name`) VALUES (?,?)');
            $insert->execute([$classname, $name]);
            return $db->lastInsertId();
        }
    }

    public function lineValueCount(array $data)
    {
        return count($data);
    }

    public function insertTraderTransaction(array $data): void
    {
        $item_id = $this->itemIdForClass($data[4], $data[5]);//Will insert classname & name if doesnt exist, returns id
        $db = $this->db_connect();
        $insert = $db->prepare('INSERT IGNORE INTO `trader` (`type`, `amount`, `player_amount`, `trader_uid`, `item_id`, `player_uid`, `datetime`) VALUES (?,?,?,?,?,?,?);');
        $insert->execute([$this->tradeActionStringToInt($data[1]), $data[2], $data[3], $data[6], $item_id, $data[8], $data[0]]);
        if ($insert->rowCount() >= 1) {//Row doesnt exist yet = fresh insert
            $this->updateItem($item_id, $data[1]);//+ 1 onto bought or sold for item
        }
    }

    public function updateItem(int $item_id, string $action_type): void
    {
        $db = $this->db_connect();
        if ($action_type == 'SELL') {
            $insert = $db->prepare('UPDATE `items` set `sold` = (`sold` + 1) WHERE `id` = ? LIMIT 1;');
        } elseif ($action_type == 'BUY') {
            $insert = $db->prepare('UPDATE `items` set `bought` = (`bought` + 1) WHERE `id` = ? LIMIT 1;');
        }
        $insert->execute([$item_id]);
    }

    public function insertPlayer(string $uid, string $name): void
    {
        $db = $this->db_connect();
        $insert = $db->prepare('INSERT IGNORE INTO `players` (`uid`, `name`) VALUES (:uid, :name);');
        $insert->execute(array(':uid' => $uid, ':name' => $name));
    }

    public function insertBankTransaction(array $data): void
    {
        $db = $this->db_connect();
        if ($this->bankActionStringToInt($data[1]) == 1) {//Deposit
            $p_before = ($data[2] + $data[5]);//Player before
            $b_before = ($data[3] - $data[2]);//Bank account before
        } else {//Withdraw
            $p_before = ($data[5] - $data[2]);
            $b_before = ($data[2] + $data[3]);
        }
        $insert = $db->prepare('INSERT IGNORE INTO `bank` (`type`, `amount`, `p_before`, `p_after`, `b_before`, `b_after`, `uid`, `datetime`) VALUES (?,?,?,?,?,?,?,?);');
        $insert->execute([$this->bankActionStringToInt($data[1]), $data[2], $p_before, $data[5], $b_before, $data[3], $data[6], $data[0]]);
    }

    public function processLogs()
    {
        $file = configConnect::LOGS_DIR . $this->log_type . "_$this->date.log";//Full file link
        if ($this->isFileFound($file)) {//Log file locked and loaded
            $stored_array = [];
            $line_count = 0;
            foreach (file($file) as $line) {//Go through each line, top to bottom
                $line_count++;
                $line_array = $this->lineExplode($line);//Splitting the line into an array based on ,
                if ($this->log_type == 'trade') {//Its the trade log file
                    if (configConnect::FIX_BROKEN_LOG_LINES) {
                        if ($this->lineValueCount($line_array) == 10) {//All data is there
                            $this->insertTraderTransaction($line_array);
                            $this->insertPlayer($line_array[8], $line_array[9]);
                            $completed_line = true;
                            $previous_complete = true;
                        } else {//Line got split!?
                            $completed_line = false;
                            if (!$previous_complete && !$completed_line) {
                                $build_arr = array_merge($stored_array, array_filter($line_array, 'strlen'));
                                $line_array = $build_arr;
                                $this->insertTraderTransaction($line_array);
                                $this->insertPlayer($line_array[8], $line_array[9]);

                            } else {
                                $stored_array = array_filter($line_array, 'strlen');//Use store array for first part of broken line
                                $previous_complete = false;//Next loop will join arrays
                            }
                        }
                    } else {//If a line is broken it just wont be added to DB + it will throw an error
                        $this->insertTraderTransaction($line_array);
                        $this->insertPlayer($line_array[8], $line_array[9]);
                    }
                } elseif ($this->log_type == 'atm') {
                    $this->insertBankTransaction($line_array);
                    $this->insertPlayer($line_array[6], $line_array[7]);
                }
            }
            echo "Found " . number_format($line_count, 0) . " lines in [" . $this->log_type . "_" . $this->date . ".log]<br>";
        } else {
            echo "$file NOT FOUND";
        }
    }

    public function tradeTypeColor(int $type): string
    {
        if ($type == 1) {
            return "#4f252173";
        } else {
            return "#214f3373";
        }
    }

    public function tradeTypeClass(int $type): string
    {
        if ($type == 1) {
            return "item-sold";
        } else {
            return "item-bought";
        }
    }

    public function tradeTypeSymbol(int $type): string
    {
        if ($type == 1) {
            return "-";
        } else {
            return "+";
        }
    }

    public function tradeTypeString(int $type): string
    {
        if ($type == 1) {
            return "Sold";
        } else {
            return "Bought";
        }
    }

    public function doDateTimeFormat(string $datetime, string $format_as = 'g:ia D jS M'): string
    {
        $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $datetime->format($format_as);
    }

    public function mainTablePreface(int $hours, int $limit)
    {
        echo "<div class='row'><div class='col-12'>";
        echo "<h2 class='text-center'>Trades from past $hours hours</h2>";
        echo "<p class='mute text-center'>Most recent to oldest (limit:$limit)</p>";
    }

    public function tableThead(array $items)
    {
        echo "<table class='table'><thead><tr>";
        foreach ($items as $th) {
            echo "<th scope='col'>$th</th>";
        }
        echo "</tr></thead><tbody>";
    }

    public function tableClose()
    {
        echo "</tbody></table>";
    }

    public function playerNameForUID(string $uid)
    {
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $select = $db->prepare("SELECT `name` FROM `players` WHERE `uid` = ? ORDER BY `datetime` DESC LIMIT 1;");
        $select->execute([$uid]);
        $player_name = $uid;
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $player_name = $row['name'];
        }
        return $player_name;
    }

    public function recentTradeTable(int $hours = 12, int $limit = 400)
    {
        $this->tableThead(['Item', 'Player', 'Amount', 'Player amount', 'Datetime']);
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) ORDER BY trader.datetime DESC LIMIT ?;");
        $select->execute([$hours, $limit]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $amount = $row['amount'];
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            $uid = $row['player_uid'];
            (configConnect::DISPLAY_PLAYER_NAMES) ? $player = $this->playerNameForUID($uid) : $player = $uid;
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
                     <td><a href='item_history.php?id={$row['item_id']}&hours=$hours'>$item</a></td>
                     <td><a href='player_history.php?uid=$uid&type=trade'>$player</a></td>
                     <td>" . $this->tradeTypeSymbol($type) . "$amount</td>
                     <td>" . $row['player_amount'] . "</td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
        echo "</div></div>";//Close col and row
    }

    public function topItemsCountTable(string $type = 'BUY', int $amount = 10)
    {
        $this->tableThead(['Item', 'Amount']);
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        if ($type == 'BUY') {
            $select = $db->prepare("SELECT `id`, `classname`, `name`, `bought` FROM `items` ORDER BY `bought` DESC LIMIT ?;");
        } else {
            $select = $db->prepare("SELECT `id`, `classname`, `name`, `sold` FROM `items` ORDER BY `sold` DESC LIMIT ?;");
        }
        $select->execute([$amount]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            if ($type == 'BUY') {
                $count = $row['bought'];
            } else {
                $count = $row['sold'];
            }
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            if ($count == 0)//No need to show items that have count of 0
                break;
            echo "<tr class='normal-table'><td><a href='item_history.php?id={$row['id']}&hours=24'>$item</a></td><td>$count</td></tr>";
        }
        $this->tableClose();
    }

    public function itemsCountTables(int $amount = 10)
    {
        ?>
        <div class="row">
            <div class="col-12 col-lg-6">
                <h3 class="text-center">Total bought count</h3>
                <?php $this->topItemsCountTable('BUY', $amount); ?>
            </div>
            <div class="col-12 col-lg-6">
                <h3 class="text-center">Total sold count</h3>
                <?php $this->topItemsCountTable('SOLD', $amount); ?>
            </div>
        </div>
        <?php
    }

    public function playerTradeHistoryTable(string $uid, string $action = 'all', int $days = 3)
    {
        $this->tableThead(['Item', 'Amount', 'Player amount', 'Datetime']);
        $db = $this->db_connect(true);
        if ($action == 'buy') {
            $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE trader.player_uid = ? AND trader.type = 2 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? DAY) ORDER BY trader.datetime DESC;");
        } elseif ($action == 'sell') {
            $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE trader.player_uid = ? AND trader.type = 1 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? DAY) ORDER BY trader.datetime DESC;");
        } else {
            $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE trader.player_uid = ? AND `datetime` > DATE_ADD(NOW(), INTERVAL -? DAY) ORDER BY trader.datetime DESC;");
        }
        $select->execute([$uid, $days]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $amount = $row['amount'];
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
                     <td><a href='item_history.php?id={$row['item_id']}&hours=48'>$item</a></td>
                     <td>" . $this->tradeTypeSymbol($type) . "$amount</td>
                     <td>" . $row['player_amount'] . "</td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
    }

    public function playerNameHistoryTable(string $uid)
    {
        $this->tableThead(['Name', 'First recorded']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT `name`, `datetime` FROM `players` WHERE `uid` = ? ORDER BY `datetime` LIMIT 200;");
        $select->execute([$uid]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td><a href='player_history.php?uid=$uid&type=atm'>{$row['name']}</a></td>
                  <td>" . $this->doDateTimeFormat($row['datetime']) . "</td></tr>";
        }
        $this->tableClose();
    }

    public function playerATMHistoryTable(string $uid)
    {
        $this->tableThead(['Item', 'Amount', 'Player amount', 'Datetime']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE trader.player_uid = ? ORDER BY trader.datetime;");
        $select->execute([$uid]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $amount = $row['amount'];
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
                     <td><a href='item_history.php?id={$row['item_id']}&hours=48'>$item</a></td>
                     <td>" . $this->tradeTypeSymbol($type) . "$amount</td>
                     <td>" . $row['player_amount'] . "</td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
    }


    public function itemTradeHistoryTable(string $item_id, int $hours = 24)
    {
        $this->tableThead(['Item', 'Player', 'Amount', 'Player amount', 'Datetime']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT trader.type, trader.amount, trader.item_id, trader.player_amount, trader.player_uid, trader.datetime, items.name, items.classname FROM `trader` INNER JOIN items ON trader.item_id = items.id WHERE trader.item_id = ? AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) ORDER BY trader.datetime;");
        $select->execute([$item_id, $hours]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $amount = $row['amount'];
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            $uid = $row['player_uid'];
            (configConnect::DISPLAY_PLAYER_NAMES) ? $player = $this->playerNameForUID($uid) : $player = $uid;
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
                     <td><a href='item_history.php?id={$row['item_id']}&hours=48'>$item</a></td>
                     <td><a href='player_history.php?uid=$uid&type=trade'>$player</a></td>
                     <td>" . $this->tradeTypeString($type) . " for $amount</td>
                     <td>" . $row['player_amount'] . "</td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
    }

    public function noItemIdSetCard()
    {
        ?>
        <div class="col-12 text-center">
            <div class="card">
                <div class="card-header">
                    <h2>No item id set</h2>
                </div>
                <div class="card-body">
                    <p>This is set via ?id=</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function traderTallies(int $past_hours = 6)
    {
        $db = $this->db_connect(true);
        $sel_b = $db->prepare("SELECT count(*) as the_count FROM `trader` WHERE `type` = 1 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) LIMIT 1;");
        $sel_b->execute([$past_hours]);
        $total_items_bought = $sel_b->fetch();
        $sel_s = $db->prepare("SELECT count(*) as the_count FROM `trader` WHERE `type` = 2 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) LIMIT 1;");
        $sel_s->execute([$past_hours]);
        $total_items_sold = $sel_s->fetch();
        $sel_made = $db->prepare("SELECT sum(`amount`) as the_sum FROM `trader` WHERE `type` = 1 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) LIMIT 1;");
        $sel_made->execute([$past_hours]);
        $total_made = $sel_made->fetch();
        $sel_lost = $db->prepare("SELECT sum(`amount`) as the_sum FROM `trader` WHERE `type` = 2 AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) LIMIT 1;");
        $sel_lost->execute([$past_hours]);
        $total_lost = $sel_lost->fetch();
        ?>
        <h2 class="text-center">Server totals past <?php echo $past_hours; ?> hours</h2>
        <div class="row">
            <div class="col-12 col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <span class='badge badge-pill badge-info'><?php echo $total_items_bought['the_count']; ?></span>
                        Items bought
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <span class='badge badge-pill badge-info'><?php echo $total_items_sold['the_count']; ?></span>
                        Items sold
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <span class='badge badge-pill badge-info'><?php echo number_format($total_made['the_sum'], 0); ?></span>
                        Money spent
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <span class='badge badge-pill badge-info'><?php echo number_format($total_lost['the_sum'], 0); ?></span>
                        Money made
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function hotItems(string $type, int $past_hours = 6, int $amount = 3)
    {
        ($type === 'SOLD') ? $type_int = 2 : $type_int = 1;
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $select = $db->prepare("SELECT count(*) as the_count, item_id, items.name, items.classname FROM `trader` INNER JOIN `items` ON trader.item_id = items.id WHERE trader.type = ? AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) GROUP BY `item_id` ORDER BY the_count DESC LIMIT ?;");
        $select->execute([$type_int, $past_hours, $amount]);
        return $select->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hotPlayerTrading(string $type, int $past_hours = 6, int $amount = 3)
    {
        ($type === 'MADE') ? $type_int = 2 : $type_int = 1;
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $select = $db->prepare("SELECT sum(`amount`) as the_sum, player_uid FROM `trader` WHERE trader.`type` = ? AND `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) GROUP BY `player_uid` ORDER BY the_sum DESC LIMIT ?;");
        $select->execute([$type_int, $past_hours, $amount]);
        return $select->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hotItemsCard(string $type, int $past_hours = 6, int $amount = 3)
    {
        ($type === 'SOLD') ? $type_str = 'Sold' : $type_str = 'Bought';
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Items <?php echo $type_str; ?></h3>
                <p class="text-center">Top <?php echo $amount ?> for past <?php echo $past_hours ?> hours.</p>
            </div>
            <ul class="list-group list-group-flush">
                <?php
                $data = $this->hotItems($type, $past_hours, $amount);
                foreach ($data as $row) {
                    (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
                    echo "<li class='list-group-item'><span class='badge badge-pill badge-info'>" . $row['the_count'] . "</span> <a href='item_history.php?id={$row['item_id']}&hours=$past_hours'>$item</a></li>";
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function hotPlayerTradingCard(string $type, int $past_hours = 6, int $amount = 3)
    {
        ($type === 'MADE') ? $type_str = 'Made' : $type_str = 'Spent';
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">Has <?php echo $type_str; ?></h3>
                <p class="text-center">Top <?php echo $amount ?> for past <?php echo $past_hours ?> hours.</p>
            </div>
            <ul class="list-group list-group-flush">
                <?php
                $data = $this->hotPlayerTrading($type, $past_hours, $amount);
                foreach ($data as $row) {
                    $uid = $row['player_uid'];
                    (configConnect::DISPLAY_PLAYER_NAMES) ? $player = $this->playerNameForUID($uid) : $player = $uid;
                    echo "<li class='list-group-item'><span class='badge badge-pill badge-info'>" . number_format($row['the_sum'], 0) . "</span> <a href='player_history.php?uid=$uid&type=trade'>$player</a></li>";
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function hotTradingRow(int $past_hours = 6, int $amount = 3)
    {
        ?>
        <h2 class="text-center">Running hot past <?php echo $past_hours; ?> hours</h2>
        <div class="row">
            <div class="col-12 col-lg-3 col-md-6">
                <?php
                $this->hotItemsCard('BOUGHT', $past_hours, $amount);
                ?>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <?php
                $this->hotItemsCard('SOLD', $past_hours, $amount);
                ?>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <?php
                $this->hotPlayerTradingCard('MADE', $past_hours, $amount);
                ?>
            </div>
            <div class="col-12 col-lg-3 col-md-6">
                <?php
                $this->hotPlayerTradingCard('SPENT', $past_hours, $amount);
                ?>
            </div>
        </div>
        <?php
    }

    public function IssetCheck(string $type, string $value)
    {
        if ($type == 'GET') {
            if (isset($_GET[$value])) {
                return $_GET[$value];
            } else {
                return false;
            }
        } elseif ($type == 'POST') {
            if (isset($_POST[$value])) {
                return $_POST[$value];
            } else {
                return false;
            }
        }
    }


    public function playerData(string $uid)
    {
        $db = $this->db_connect(true);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $select = $db->prepare("SELECT `name`, `datetime` FROM `players` WHERE `uid` = ? ORDER BY `datetime` DESC;");
        $select->execute([$uid]);
        return $select->fetchAll(PDO::FETCH_ASSOC);
    }


    public function noPlayerFoundCard(string $uid_tried)
    {
        ?>
        <div class="card">
            <div class="card-body">
                <p class="text-center">No player found with UID <?php echo $uid_tried; ?></p>
            </div>
        </div>
        <?php

    }

    public function playerHistoryCard(string $uid, string $type, int $past_hours = 48)
    {
        $data = $this->playerData($uid);
        $rows = count($data);
        if ($rows == 0) {
            $this->noPlayerFoundCard($uid);
            $this->playerUidForm();
            exit;
        }
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">History
                    for
                    <a href="https://steamcommunity.com/profiles/<?php echo $uid; ?>"><?php echo $data[0]['name']; ?></a>
                </h3>
                <p class="text-center">Has used <?php echo $rows; ?> name/s</p>
                <p class="text-center">First seen <?php echo $this->doDateTimeFormat($data[($rows - 1)]['datetime']); ?>
                    as <?php echo $data[($rows - 1)]['name']; ?></p>
                <p class="text-center">Name history:</p>
            </div>
            <ul class="list-group list-group-flush">
                <?php
                foreach ($data as $a_name) {
                    echo "<li class='list-group-item'><span class='badge badge-pill badge-blue'>" . $a_name['datetime'] . "</span>" . $a_name['name'] . "</li>";
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function navBar(string $location, int $depth = 0)
    {
        $home = $item = $rtradet = $bankrt = $item_stock = '';
        if ($location == 'HOME') {
            $home = 'active';
        } elseif ($location == 'ITEM_HISTORY') {
            $item_history = 'active';
        } elseif ($location == 'TRADE_TABLE') {
            $trade_table = 'active';
        } elseif ($location == 'BANK_TABLE') {
            $bank_table = 'active';
        } elseif ($location == 'ITEM_STOCK_TABLE') {
            $item_stock_table = 'active';
        }
        ?>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand" href="index.php">DZ TBL</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item <?php echo $home; ?>">
                        <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
                    </li>
                    <li class="nav-item <?php echo $item_history; ?>">
                        <a class="nav-link" href="item_history.php">item History</a>
                    </li>
                    <li class="nav-item <?php echo $trade_table; ?>">
                        <a class="nav-link" href="trades_table.php">Trade recent table</a>
                    </li>
                    <?php if (configConnect::HAS_ATM) { ?>
                        <li class="nav-item <?php echo $bank_table; ?>">
                            <a class="nav-link" href="bank_table.php">Bank recent table</a>
                        </li>
                    <?php } ?>
                    <li class="nav-item <?php echo $item_stock_table; ?>">
                        <a class="nav-link" href="item_stock.php">Item Stock table</a>
                    </li>
                </ul>
                <form class="form-inline my-2 my-lg-0" method="post" action="player_history.php?hours=48">
                    <input class="form-control mr-sm-2" name="uid" id="uid" type="text" placeholder="Player UID"
                           aria-label="Search">
                    <button class="btn purple-btn my-2 my-sm-0" type="submit">Search</button>
                </form>
            </div>
        </nav>
        <?php
    }

    public function playerUidForm(string $attempted_uid = null)
    {
        ?>
        <div class="card">
            <div class="card-header">
                <h1 class="shrink-header">Search player by UID</h1>
            </div>
            <div class="card-body">
                <form class="form-inline" method="post" action="player_history.php?hours=48">
                    <div class="form-group mb-2">
                        <input type="text" readonly class="form-control-plaintext" id="uid_dummy" value="Player UID">
                    </div>
                    <div class="form-group mx-sm-3 mb-2">
                        <label for="uid" class="sr-only">Player UID</label>
                        <input type="text" class="form-control" id="uid" name="uid" placeholder="Player UID">
                    </div>
                    <button type="submit" class="btn purple-btn mb-2">Search</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function uidSet()
    {
        if ($this->IssetCheck('GET', 'uid')) {
            $uid = $this->IssetCheck('GET', 'uid');
        } elseif ($this->IssetCheck('POST', 'uid')) {
            $uid = $this->IssetCheck('POST', 'uid');
        } else {
            return false;
        }
        return $uid;
    }

    public function playerHistoryPageTitleBuilder(string $uid, string $action, string $type, int $days): string
    {
        $string = "$uid ";
        if ($type == 'trade') {
            $string .= "trader ";
            if ($action == 'buy') {
                $string .= "bought items ";
            } elseif ($action == 'sell') {
                $string .= "sold items ";
            } else {
                $string .= "bought + sold items ";
            }
        } elseif ($type == 'atm') {
            $string .= "atm ";
        }
        $string .= "past $days days";
        return $string;
    }

    public function bankTablePreface(int $hours, int $limit)
    {
        echo "<div class='row'><div class='col-12'>";
        echo "<h2 class='text-center'>Bank transactions past $hours hours</h2>";
        echo "<p class='mute text-center'>Most recent to oldest (limit:$limit)</p>";
    }

    public function recentBankTable(int $hours = 12, int $limit = 400)
    {
        $this->tableThead(['Action', 'Amount', '$ player before', '$ player after', '$ bank before', '$ bank after', 'Player', 'Datetime']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT `type`, `amount`, `p_before`, `p_after`, `b_before`, `b_after`, `uid`, `datetime` FROM `bank` WHERE `datetime` > DATE_ADD(NOW(), INTERVAL -? HOUR) ORDER BY `datetime` DESC LIMIT ?;");
        $select->execute([$hours, $limit]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $uid = $row['uid'];
            (configConnect::DISPLAY_PLAYER_NAMES) ? $player = $this->playerNameForUID($uid) : $player = $uid;
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
                     <td>{$this->bankActionIntToString($type)}</td>
                     <td>" . number_format($row['amount'], 0) . "</td>
                     <td>" . number_format($row['p_before'], 0) . "</td>
                     <td>" . number_format($row['p_after'], 0) . "</td>
                     <td>" . number_format($row['b_before'], 0) . "</td>
                     <td>" . number_format($row['b_after'], 0) . "</td>
                     <td><a href='player_history.php?uid=$uid&type=bank'>$player</a></td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
        echo "</div></div>";//Close col and row
    }

    public function itemStockTablePreface()
    {
        echo "<div class='row'><div class='col-12'>";
        echo "<h2 class='text-center'>Rolling item stock</h2>";
        echo "<p class='mute text-center'>If item is 0 sold and bought it is not show.</p>";
    }

    public function itemStockTable()
    {
        $this->tableThead(['Item', 'Amount', 'Sold', 'Bought']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT `id`, `name`, `bought`, `sold`, `classname` FROM `items` ORDER BY `bought` DESC,`sold` DESC;");
        $select->execute();
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            if ($row['bought'] == 0 && $row['sold'] == 0)
                break;
            $rolling_count = ($row['sold'] - $row['bought']);
            if ($rolling_count < 0) {
                $type = 1;
            } else {
                $type = 2;
            }
            (configConnect::DISPLAY_ITEM_NAMES) ? $item = $row['name'] : $item = $row['classname'];
            echo "<tr class='" . $this->tradeTypeClass($type) . "'>
             <td><a href='item_history.php?id={$row['id']}&hours=24'>$item</a>
             </td><td>$rolling_count</td>
             </td><td>" . $row['sold'] . "</td>
             </td><td>" . $row['bought'] . "</td>
             </tr>";
        }
        $this->tableClose();
    }

    public function richListTablePreface()
    {
        echo "<div class='row'><div class='col-12'>";
        echo "<h2 class='text-center'>Most money in bank</h2>";
        echo "<p class='mute text-center'>As per last accessed.</p>";
    }

    public function richListTable(int $amount = 10)
    {
        $this->tableThead(['Amount in bank', 'Player', 'Last access']);
        $db = $this->db_connect(true);
        $select = $db->prepare("SELECT `uid`, MAX(b_after), `datetime` FROM `bank` GROUP BY `uid` ORDER BY MAX(b_after) DESC LIMIT ?;");
        $select->execute([$amount]);
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $amount = $row['MAX(b_after)'];
            $uid = $row['uid'];
            (configConnect::DISPLAY_PLAYER_NAMES) ? $player = $this->playerNameForUID($uid) : $player = $uid;
            echo "<tr class='normal-table'>
                     <td>" . number_format($amount, 0) . "</td>
                     <td><a href='player_history.php?uid=$uid&type=trade'>$player</a></td>
                     <td>" . $this->doDateTimeFormat($row['datetime']) . "</td>
                     </tr>";
        }
        $this->tableClose();
        echo "</div></div>";//Close col and row
    }

    public function footerText()
    {
        echo "<p class='footer-text'>DayZ Trader & Banking logs</p>";
    }

    //END OF CLASS
}