<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "CUSTOMER") {
    die("Access denied. Customer only.");
}

$party_id = $_SESSION["party_id"]; // customer party
$bc = new Blockchain($conn);
$msg = "";

// ----------------------
// 1) Load list of AVAILABLE medicines (at retailers)
// ----------------------
$medicineList = [];
$medRes = $conn->query("
    SELECT DISTINCT m.medicine_id, m.generic_name, m.brand_name
    FROM stock s
    JOIN medicines m ON s.medicine_id = m.medicine_id
    JOIN parties p ON s.party_id = p.party_id
    WHERE p.party_type = 'RETAILER' AND s.qty > 0
    ORDER BY m.generic_name ASC
");
if ($medRes) {
    while ($row = $medRes->fetch_assoc()) {
        $medicineList[] = $row;
    }
}

// ----------------------
// 2) Handle order placement (POST)
// ----------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {
    $retailer_id = (int)$_POST["retailer_id"];
    $medicine_id = (int)$_POST["medicine_id"];
    $qty         = (int)$_POST["qty"];

    if ($retailer_id <= 0 || $medicine_id <= 0 || $qty <= 0) {
        $msg = "Please select retailer, medicine and a valid quantity.";
    } else {
        // Check retailer stock
        $chk = $conn->prepare("
            SELECT qty 
            FROM stock 
            WHERE medicine_id = ? AND party_id = ?
        ");
        if (!$chk) {
            die("SQL Error (check retailer stock): " . $conn->error);
        }
        $chk->bind_param("ii", $medicine_id, $retailer_id);
        $chk->execute();
        $stockRow = $chk->get_result()->fetch_assoc();
        $available = $stockRow ? (int)$stockRow["qty"] : 0;

        if ($available <= 0) {
            $msg = "This retailer currently has no stock for this medicine.";
        } elseif ($qty > $available) {
            $msg = "Only $available units available with this retailer.";
        } else {
            // Insert order request (PENDING)
            $ins = $conn->prepare("
                INSERT INTO customer_orders
                (customer_party_id, retailer_party_id, medicine_id, qty, status)
                VALUES (?,?,?,?, 'PENDING')
            ");
            if (!$ins) {
                die("SQL Error (insert customer order): " . $conn->error);
            }
            $ins->bind_param("iiii", $party_id, $retailer_id, $medicine_id, $qty);
            $ins->execute();
            $order_id = $conn->insert_id;

            // Blockchain log
            $bc->addRecord("CUSTOMER_ORDER_PLACED", [
                "order_id"          => $order_id,
                "customer_party_id" => $party_id,
                "retailer_party_id" => $retailer_id,
                "medicine_id"       => $medicine_id,
                "qty"               => $qty
            ]);

            $msg = "Order placed successfully. The retailer will see your request as a pending order.";
        }
    }
}

// ----------------------
// 3) After selecting a medicine (GET), show retailers with that medicine
// ----------------------
$selected_medicine_id = isset($_GET["medicine_id"]) ? (int)$_GET["medicine_id"] : 0;
$retailer_stock = null;

if ($selected_medicine_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.medicine_id, s.qty,
               m.generic_name, m.brand_name, m.price,
               p.party_id AS retailer_id, p.party_name
        FROM stock s
        JOIN medicines m ON s.medicine_id = m.medicine_id
        JOIN parties p ON s.party_id = p.party_id
        WHERE s.medicine_id = ? 
          AND p.party_type = 'RETAILER'
          AND s.qty > 0
        ORDER BY p.party_name ASC
    ");
    if (!$stmt) {
        die("SQL Error (load retailer stock): " . $conn->error);
    }
    $stmt->bind_param("i", $selected_medicine_id);
    $stmt->execute();
    $retailer_stock = $stmt->get_result();
}

?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-search-heart"></i> Order Medicines</h3>
<p class="text-muted">
    Select an available medicine from the dropdown to see which retailers have it,
    along with price and stock. Then choose quantity and place an order.
</p>

<?php if ($msg): ?>
  <div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<!-- Medicine dropdown -->
<div class="card card-custom p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-8">
      <label class="form-label">Available Medicines</label>
      <select name="medicine_id" class="form-control" required>
        <option value="">-- Select Medicine --</option>
        <?php foreach ($medicineList as $m): ?>
          <option value="<?= $m["medicine_id"] ?>"
            <?= $selected_medicine_id === (int)$m["medicine_id"] ? "selected" : "" ?>>
            <?= htmlspecialchars($m["generic_name"]) ?>
            (<?= htmlspecialchars($m["brand_name"]) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary w-100">
        Show Retailers
      </button>
    </div>
  </form>
</div>

<!-- Retailers that have the selected medicine -->
<?php if ($selected_medicine_id > 0): ?>
  <div class="card card-custom p-3">
    <h5 class="mb-3"><i class="bi bi-box-seam"></i> Retailers with this Medicine</h5>

    <?php if ($retailer_stock && $retailer_stock->num_rows > 0): ?>
      <div class="row g-3">
        <?php while ($row = $retailer_stock->fetch_assoc()): ?>
          <div class="col-md-6">
            <div class="border rounded-3 p-3 h-100">
              <div class="d-flex justify-content-between">
                <strong><?= htmlspecialchars($row["party_name"]) ?></strong>
                <span class="badge bg-secondary">
                  In stock: <?= (int)$row["qty"] ?>
                </span>
              </div>
              <div class="small text-muted">
                Medicine: <?= htmlspecialchars($row["generic_name"]) ?>
                (<?= htmlspecialchars($row["brand_name"]) ?>)<br>
                Price: ₹<?= number_format($row["price"], 2) ?>
              </div>

              <!-- Order form -->
              <form method="POST" class="mt-2 d-flex align-items-center">
                <input type="hidden" name="retailer_id" value="<?= $row["retailer_id"] ?>">
                <input type="hidden" name="medicine_id" value="<?= $row["medicine_id"] ?>">

                <input type="number"
                       name="qty"
                       min="1"
                       max="<?= (int)$row["qty"] ?>"
                       class="form-control form-control-sm me-2"
                       style="width:110px;"
                       placeholder="Qty"
                       required>

                <button type="submit"
                        name="place_order"
                        class="btn btn-sm btn-success">
                  Place Order
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">No retailer currently has this medicine in stock.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include "../includes/footer.php"; ?>
