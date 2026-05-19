<?php
session_start();
require "../config/db.php";

$code = $_GET["code"] ?? "";

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Receipt</title>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body {
    background: #0f172a;
    color: #e5e7eb;
    font-family: "Segoe UI", sans-serif;
}
.verify-card {
    max-width: 700px;
    margin: 40px auto;
    background: #111827;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,.6);
}
</style>
</head>
<body>

<div class="verify-card">
  <h3 class="mb-3"><i class="bi bi-shield-check"></i> Verify Receipt</h3>

<?php
if ($code === "") {
    echo '<div class="alert alert-warning text-dark">No receipt code provided.</div>';
} else {
    $sql = "
        SELECT s.*, 
               r.party_name AS retailer_name,
               c.party_name AS customer_name,
               m.generic_name, m.brand_name
        FROM retailer_customer_sales s
        JOIN parties r ON s.retailer_party_id = r.party_id
        JOIN parties c ON s.customer_party_id = c.party_id
        JOIN medicines m ON s.medicine_id = m.medicine_id
        WHERE s.receipt_code = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="alert alert-danger text-dark">Error verifying receipt.</div>';
    } else {
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        $sale = $res->fetch_assoc();

        if (!$sale) {
            echo '<div class="alert alert-danger text-dark">Invalid or unknown receipt code.</div>';
        } else {
            ?>
            <div class="alert alert-success text-dark">
                This receipt is <strong>VALID</strong> and recorded in the system.
            </div>

            <p class="mb-1"><strong>Receipt Code:</strong> <?= htmlspecialchars($sale["receipt_code"]) ?></p>
            <p class="mb-1"><strong>Retailer:</strong> <?= htmlspecialchars($sale["retailer_name"]) ?></p>
            <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($sale["customer_name"]) ?></p>
            <p class="mb-1"><strong>Medicine:</strong> 
                <?= htmlspecialchars($sale["generic_name"]) ?> (<?= htmlspecialchars($sale["brand_name"]) ?>)
            </p>
            <p class="mb-1"><strong>Quantity:</strong> <?= (int)$sale["qty"] ?></p>
            <p class="mb-1"><strong>Total Paid:</strong> ₹<?= number_format($sale["total_price"], 2) ?></p>
            <p class="mb-3"><strong>Sale Date:</strong> <?= $sale["sale_date"] ?></p>

            <p class="small text-muted">
                This sale was also logged as a blockchain event <strong>RETAIL_SALE</strong> using the same receipt code,
                along with upstream trace (manufacturer → wholesaler → retailer) in the ledger.
            </p>

            <a href="blockchain_view.php" class="btn btn-sm btn-outline-light mt-2">
                View Blockchain Ledger
            </a>
            <?php
        }
    }
}
?>

</div>

</body>
</html>
