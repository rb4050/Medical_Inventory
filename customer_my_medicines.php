<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "CUSTOMER") {
    die("Access denied. Customer only.");
}

$party_id = $_SESSION["party_id"];

$sql = "
    SELECT s.*, 
           r.party_name AS retailer_name,
           m.generic_name, m.brand_name
    FROM retailer_customer_sales s
    JOIN parties r ON s.retailer_party_id = r.party_id
    JOIN medicines m ON s.medicine_id = m.medicine_id
    WHERE s.customer_party_id = ?
    ORDER BY s.sale_date DESC, s.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $party_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-capsule-pill"></i> My Medicines & Bills</h3>
<p class="text-muted">
  These are the medicines you have received from retailers, with access to each digital bill.
</p>

<div class="card card-custom p-3 mt-3">
<table class="table table-hover table-bordered mb-0">
  <thead class="table-dark">
    <tr>
      <th>Date</th>
      <th>Medicine</th>
      <th>Retailer</th>
      <th>Qty</th>
      <th>Total (₹)</th>
      <th>Receipt</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($res->num_rows === 0): ?>
    <tr><td colspan="6" class="text-center">No medicines received yet.</td></tr>
  <?php endif; ?>

  <?php while ($row = $res->fetch_assoc()): ?>
    <tr>
      <td><?= $row["sale_date"] ?></td>
      <td>
        <?= htmlspecialchars($row["generic_name"]) ?>
        (<?= htmlspecialchars($row["brand_name"]) ?>)
      </td>
      <td><?= htmlspecialchars($row["retailer_name"]) ?></td>
      <td><?= (int)$row["qty"] ?></td>
      <td>₹<?= number_format($row["total_price"], 2) ?></td>
      <td>
        <a href="retailer_receipt.php?code=<?= urlencode($row["receipt_code"]) ?>"
           class="btn btn-sm btn-outline-primary" target="_blank">
          View Receipt
        </a>
      </td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>
</div>

<?php include "../includes/footer.php"; ?>
