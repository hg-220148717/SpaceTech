<?php

session_start();
include_once("database-handler.php");

$db_handler = new Database();
$db_handler->testDatabaseConnection();
$db_handler->checkSetup();

if(!isset($_SESSION["user_id"])) {
    header("Location: login.php");
}

$address = $_POST["address_line1"] . "\n" . $_POST["address_line2"] . "\n" . $_POST["address_line3"];
$comments = "";
$order_total = $db_handler->getBasketTotal($_SESSION["user_id"]);
$is_paid = handlePayment($order_total, $_POST["card_no"], $_POST["card_expiry"], $_POST["card_cvv"], $_POST["card_name"]);

if($is_paid) {
    $db_handler->submitOrder($_SESSION["user_id"], $address, $comments, $order_total, true);
    header("Location: cart.php?success=true");
} else {
    header("Location: cart.php?error=An+error+occurred+taking+payment.");
}


function handlePayment($amount, $card_no, $card_expiry, $card_cvv, $card_name) {
    // this function would be used to contact a payment gateway to verify payment details.
    // As this is to be implemented as a dummy system, this will always return true.
    return true;
}

?>