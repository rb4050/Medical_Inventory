<?php

require "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "CUSTOMER") {
    die("Access denied. Customer only.");
}

$party_id = $_SESSION["party_id"];

// Summary: medicines received
$sqlSummary = "
    SELECT COUNT(DISTINCT medicine_id) AS distinct_meds,
           COALESCE(SUM(qty), 0) AS total_qty
    FROM retailer_customer_sales
    WHERE customer_party_id = ?
";
$stmt = $conn->prepare($sqlSummary);
$stmt->bind_param("i", $party_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$distinct_meds = (int)$summary["distinct_meds"];
$total_qty     = (int)$summary["total_qty"];
?>

<?php include "../includes/header.php"; ?>

<h2><i class="bi bi-person-hearts"></i> Customer Dashboard</h2>
<p class="mt-2 text-muted">
  View your received medicines, search & order from retailers, and verify your bills.
</p>

<div class="row mt-4 g-3">

  <!-- Medicines received summary card -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-capsule"></i> Medicines Received</h5>
      <p class="small text-muted mb-2">
        Total medicines you have purchased through verified retailers.
      </p>
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="h4 mb-0"><?= $distinct_meds ?></div>
          <small class="text-muted">Distinct medicines</small>
        </div>
        <div class="text-end">
          <div class="h4 mb-0"><?= $total_qty ?></div>
          <small class="text-muted">Total quantity</small>
        </div>
      </div>
      <a href="customer_my_medicines.php" class="btn btn-sm btn-outline-primary w-100 mt-3">
        View All Medicines & Bills
      </a>
    </div>
  </div>

  <!-- Search & Order -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-search-heart"></i> Search & Order</h5>
      <p class="small text-muted mb-2">
        Search medicines or retailers and place an order request.
      </p>
      <a href="customer_order.php" class="btn btn-sm btn-primary w-100">
        Search & Order Medicines
      </a>
    </div>
  </div>

  <!-- Verify Receipts / Blockchain -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-receipt-cutoff"></i> Bills & Traceability</h5>
      <p class="small text-muted mb-2">
        View receipts and blockchain-backed traceability.
      </p>
      <a href="customer_my_medicines.php" class="btn btn-sm btn-outline-secondary w-100 mb-2">
        View My Bills
      </a>
      <a href="blockchain_view.php" class="btn btn-sm btn-dark w-100">
        View Blockchain Ledger
      </a>
    </div>
  </div>

</div>

<?php include "../includes/footer.php"; ?>
