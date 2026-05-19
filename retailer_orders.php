<?php
require "../config/db.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "RETAILER") {
    die("Access denied. Retailer only.");
}

$party_id = $_SESSION["party_id"]; // retailer party_id
$msg = "";

// Load customer orders for this retailer
$sql = "
    SELECT o.id, o.medicine_id, o.qty, o.status, o.created_at,
           c.party_name AS customer_name,
           m.generic_name, m.brand_name
    FROM customer_orders o
    JOIN parties c ON o.customer_party_id = c.party_id
    JOIN medicines m ON o.medicine_id = m.medicine_id
    WHERE o.retailer_party_id = ?
    ORDER BY 
        CASE WHEN o.status = 'PENDING' THEN 0 ELSE 1 END,
        o.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error (load customer orders): " . $conn->error);
}
$stmt->bind_param("i", $party_id);
$stmt->execute();
$orders = $stmt->get_result();
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-bell"></i> Customer Orders</h3>
<p class="text-muted">
    These are the medicine order requests placed by your customers.
    For pending orders, you can generate a bill directly. Once the bill is created,
    the order status will change from <strong>PENDING</strong> to <strong>COMPLETED</strong>.
</p>

<?php if ($msg): ?>
  <div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<div class="card card-custom p-3 mt-3">
  <table class="table table-hover table-bordered mb-0">
    <thead class="table-dark">
      <tr>
        <th>Placed At</th>
        <th>Customer</th>
        <th>Medicine</th>
        <th>Qty</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($orders->num_rows === 0): ?>
      <tr><td colspan="6" class="text-center">No orders from customers yet.</td></tr>
    <?php endif; ?>

    <?php while ($row = $orders->fetch_assoc()): ?>
      <tr class="<?= $row["status"] === 'PENDING' ? 'table-warning' : '' ?>">
        <td><?= $row["created_at"] ?></td>
        <td><?= htmlspecialchars($row["customer_name"]) ?></td>
        <td>
          <?= htmlspecialchars($row["generic_name"]) ?>
          (<?= htmlspecialchars($row["brand_name"]) ?>)
        </td>
        <td><?= (int)$row["qty"] ?></td>
        <td>
          <?php if ($row["status"] === "PENDING"): ?>
            <span class="badge bg-warning text-dark">PENDING</span>
          <?php elseif ($row["status"] === "COMPLETED" || $row["status"] === "APPROVED"): ?>
            <span class="badge bg-success">COMPLETED</span>
          <?php else: ?>
            <span class="badge bg-secondary"><?= htmlspecialchars($row["status"]) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($row["status"] === "PENDING"): ?>
            <!-- Generate Bill button: links to retailer_sale with order_id -->
            <a href="retailer_sale.php?order_id=<?= $row['id'] ?>"
               class="btn btn-sm btn-primary">
              Generate Bill
            </a>
          <?php else: ?>
            <span class="text-muted small">No action</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include "../includes/footer.php"; ?>
