<?php
session_start();
require_once("../PHP/database-handler.php");

$db_handler = new Database();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = trim($_POST["price"]);
    $categoryName = trim($_POST["category"]);


    $result = $db_handler->createProduct($name, $categoryName, $description, $price, 0, true);
    if ($result == "Product created successfully") {
        header("Location: ../Pages/product_management.php?success=true");
    } else {
        header("Location: ../Pages/product_management.php?success=false");
    }
} else {
    echo "Invalid request method";
}
