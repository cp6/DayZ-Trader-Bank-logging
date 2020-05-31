<!DOCTYPE html>
<html lang="en">
<head>
    <title>DZ TBLogging rolling item stock</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/4.5.0/darkly/bootstrap.min.css"
          integrity="sha256-MwJT4aoRf8awkXH2gl6jjykb0GW7x7QeffW2n4608a0=" crossorigin="anonymous"/>
    <link rel="stylesheet" href="style.min.css"/>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <?php
            require_once('class.php');
            $dz = new dzTraderBankLogging();
            $dz->navBar('ITEM_STOCK_TABLE');
            $dz->itemStockTablePreface();
            $dz->itemStockTable();
            $dz->footerText();
            ?>
        </div>
    </div>
</div>
</body>
</html>