<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["role"];

switch ($role) {
    case "ADMIN":
        include "dashboard_admin.php";
        break;
    case "MANUFACTURER":
        include "dashboard_manufacturer.php";
        break;
    case "WHOLESALER":
        include "dashboard_wholesaler.php";
        break;
    case "RETAILER":
        include "dashboard_retailer.php";
        break;
    case "CUSTOMER":
        include "dashboard_customer.php";
        break;
    default:
        echo "Role not recognized.";
}
