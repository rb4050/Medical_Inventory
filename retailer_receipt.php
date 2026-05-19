<?php
session_start();
require "../config/db.php";

if (!isset($_GET["code"])) {
    die("Missing receipt code.");
}

$code = $_GET["code"];

// Load sale + joins
$sql = "
    SELECT s.*, 
           r.party_name AS retailer_name,
           c.party_name AS customer_name,
           m.generic_name, m.brand_name, m.price
    FROM retailer_customer_sales s
    JOIN parties r ON s.retailer_party_id = r.party_id
    JOIN parties c ON s.customer_party_id = c.party_id
    JOIN medicines m ON s.medicine_id = m.medicine_id
    WHERE s.receipt_code = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error (load receipt): " . $conn->error);
}
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
$sale = $res->fetch_assoc();

if (!$sale) {
    die("Receipt not found.");
}

// Build verification URL (adjust base URL if needed)
$verifyUrl = "http://localhost/medical_inventory/public/verify_receipt.php?code=" . urlencode($code);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt - <?= htmlspecialchars($sale["receipt_code"]) ?></title>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body {
    background: #e5e7eb;
    font-family: "Segoe UI", sans-serif;
}
.invoice-card {
    max-width: 800px;
    margin: 30px auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
    padding: 30px;
}
@media print {
    body {
        background: #fff;
    }
    .no-print {
        display: none !important;
    }
}
</style>
</head>
<body>

<div class="invoice-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Medical Inventory System</h3>
      <small class="text-muted">Retail Invoice / Receipt</small>
    </div>
    <div class="text-end">
      <span class="badge bg-primary">Receipt</span><br>
      <small class="text-muted">Code: <?= htmlspecialchars($sale["receipt_code"]) ?></small>
    </div>
  </div>

  <hr>

  <div class="row mb-3">
    <div class="col-md-6">
      <h6>Retailer</h6>
      <p class="mb-1"><?= htmlspecialchars($sale["retailer_name"]) ?></p>
      <small class="text-muted">Party ID: <?= $sale["retailer_party_id"] ?></small>
    </div>
    <div class="col-md-6 text-md-end">
      <h6>Customer</h6>
      <p class="mb-1"><?= htmlspecialchars($sale["customer_name"]) ?></p>
      <small class="text-muted">Sale Date: <?= $sale["sale_date"] ?></small>
    </div>
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>Medicine</th>
          <th>Brand</th>
          <th class="text-end">Unit Price (₹)</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Total (₹)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($sale["generic_name"]) ?></td>
          <td><?= htmlspecialchars($sale["brand_name"]) ?></td>
          <td class="text-end"><?= number_format($sale["unit_price"], 2) ?></td>
          <td class="text-end"><?= (int)$sale["qty"] ?></td>
          <td class="text-end"><?= number_format($sale["total_price"], 2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="row align-items-center">
    <div class="col-md-6">
      <h6>Verify Authenticity</h6>
      <p class="small text-muted mb-1">
        Scan the QR code or visit the verification link to confirm this receipt
        and see blockchain-backed trace data.
      </p>
      <code class="small d-block mb-2"><?= htmlspecialchars($verifyUrl) ?></code>
    </div>
    <div class="col-md-3 text-center">
      <div id="qrcode"></div>
    </div>
    <div class="col-md-3 text-end">
      <h4>Total</h4>
      <h3>₹<?= number_format($sale["total_price"], 2) ?></h3>
    </div>
  </div>

  <hr class="mt-4">

  <p class="small text-muted mb-0">
    This receipt is traceable in the system’s blockchain ledger as event:
    <strong>RETAIL_SALE</strong> with code <strong><?= htmlspecialchars($sale["receipt_code"]) ?></strong>.
  </p>

  <div class="mt-3 no-print text-end">
    <button class="btn btn-outline-secondary me-2" onclick="window.location.href='dashboard.php';">
      Back to Dashboard
    </button>
    <button class="btn btn-primary" onclick="window.print();">
      Print / Save as PDF
    </button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById("qrcode"), {
    text: "<?= htmlspecialchars($verifyUrl, ENT_QUOTES) ?>",
    width: 96,
    height: 96
  });
</script>

</body>
</html>
