<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "MANUFACTURER") {
    die("Access denied.");
}

$party_id = $_SESSION["party_id"];

$sql = "SELECT b.*, m.generic_name, m.brand_name, m.price
        FROM manufacturer_batches b
        JOIN medicines m ON b.medicine_id = m.medicine_id
        WHERE b.created_by_party_id = ?
        ORDER BY b.batch_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $party_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-bezier2"></i> My Production Batches</h3>

<div class="card card-custom p-3 mt-3">
  <table class="table table-striped table-hover mb-0">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Medicine</th>
        <th>Batch</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Mfg</th>
        <th>Exp</th>
        <th>Created</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($res->num_rows === 0): ?>
      <tr><td colspan="8" class="text-center">No batches yet.</td></tr>
    <?php endif; ?>

    <?php while ($row = $res->fetch_assoc()): ?>
      <tr>
        <td><?= $row["batch_id"] ?></td>
        <td>
          <?= htmlspecialchars($row["generic_name"]) ?>
          (<?= htmlspecialchars($row["brand_name"]) ?>)
        </td>
        <td><?= htmlspecialchars($row["batch_no"]) ?></td>
        <td><?= (int)$row["quantity"] ?></td>
        <td>₹<?= number_format($row["price"], 2) ?></td>
        <td><?= $row["mfg_date"] ?></td>
        <td><?= $row["exp_date"] ?></td>
        <td><?= $row["created_at"] ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include "../includes/footer.php"; ?>
