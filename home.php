<?php
require_once('steamauth/steamauth.php');
require_once('class.php');
# You would uncomment the line beneath to make it refresh the data every time the page is loaded
// unset($_SESSION['steam_uptodate']);
if (!isset($_SESSION['steamid'])) {
    echo "welcome guest! please login<br><br>";
    loginbutton(); //login button
} else {
    include('steamauth/userInfo.php');
    //Protected content
    $dz = new dzTraderBankLogging();
    if ($dz->isAdmin($steamprofile['steamid'])) {
        echo "You're an admin<br>";
    };
    echo "Welcome back " . $steamprofile['personaname'] . "</br>";
    echo "" . $steamprofile['steamid'] . "</br>";
    echo "here is your avatar: </br>" . '<img src="' . $steamprofile['avatarfull'] . '" title="" alt="" /><br>'; // Display their avatar!

    logoutbutton();
}