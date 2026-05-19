<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$role     = $_SESSION["role"] ?? null;
$party_id = $_SESSION["party_id"] ?? null;

// Admin has no linked party/stock in this design
if ($role === "ADMIN") {
    include "../includes/header.php"; ?>
    <h3><i class="bi bi-box-seam"></i> My Stock</h3>
    <div class="alert alert-info mt-3">
        Admin account is for monitoring only and does not hold stock.
    </div>
    <?php include "../includes/footer.php";
    exit;
}

// If some user has no party linked
if (!$party_id) {
    include "../includes/header.php"; ?>
    <h3><i class="bi bi-box-seam"></i> My Stock</h3>
    <div class="alert alert-danger mt-3">
        Your user is not linked to a party. Please register properly as Manufacturer / Wholesaler / Retailer / Customer.
    </div>
    <?php include "../includes/footer.php";
    exit;
}

// Fetch stock for this party
$sql = "SELECT s.medicine_id, s.qty, m.generic_name, m.brand_name, m.price
        FROM stock s
        JOIN medicines m ON s.medicine_id = m.medicine_id
        WHERE s.party_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error in my_stock.php: " . $conn->error);
}
$stmt->bind_param("i", $party_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-box-seam"></i> My Stock</h3>

<div class="card card-custom p-3 mt-3">
<table class="table table-bordered table-striped mb-0">
    <thead class="table-dark">
        <tr>
            <th>Medicine</th>
            <th>Brand</th>
            <th>Price</th>
            <th>Quantity</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($res->num_rows === 0): ?>
        <tr><td colspan="4" class="text-center">No stock yet.</td></tr>
    <?php endif; ?>

    <?php while ($row = $res->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row["generic_name"]) ?></td>
            <td><?= htmlspecialchars($row["brand_name"]) ?></td>
            <td>₹<?= number_format((float)$row["price"], 2) ?></td>
            <td><?= (int)$row["qty"] ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php include "../includes/footer.php"; ?>
