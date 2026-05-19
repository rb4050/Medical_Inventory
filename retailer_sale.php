<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "RETAILER") {
    die("Access denied. Retailer only.");
}

$party_id = $_SESSION["party_id"]; // retailer party
$bc = new Blockchain($conn);
$msg = "";

// Optional: linked customer order (if coming from retailer_orders.php)
$linkedOrderId = isset($_GET["order_id"]) ? (int)$_GET["order_id"] : 0;

// Retailer stock
$sqlStock = "
    SELECT s.medicine_id, s.qty, m.generic_name, m.brand_name, m.price
    FROM stock s
    JOIN medicines m ON s.medicine_id = m.medicine_id
    WHERE s.party_id = ?
";
$st = $conn->prepare($sqlStock);
if (!$st) {
    die("SQL Error (stock select): " . $conn->error);
}
$st->bind_param("i", $party_id);
$st->execute();
$stockRes = $st->get_result();

// Existing customers
$customers = $conn->query("
    SELECT party_id, party_name
    FROM parties
    WHERE party_type = 'CUSTOMER'
    ORDER BY party_name ASC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicine_id    = (int)$_POST["medicine_id"];
    $qty            = (int)$_POST["qty"];
    $sale_date      = $_POST["sale_date"] ?: date('Y-m-d');
    $customer_mode  = $_POST["customer_mode"] ?? "existing";
    $customer_id    = 0;
    $order_id_post  = isset($_POST["order_id"]) ? (int)$_POST["order_id"] : 0;

    if ($customer_mode === "existing") {
        $customer_id = (int)($_POST["customer_id"] ?? 0);
    } else {
        $new_name  = trim($_POST["new_customer_name"] ?? "");
        if ($new_name === "") {
            $msg = "Please enter customer name.";
        } else {
            // create new customer party
            $stmtC = $conn->prepare("
                INSERT INTO parties (party_name, party_type)
                VALUES (?, 'CUSTOMER')
            ");
            if (!$stmtC) {
                die("SQL Error (create customer): " . $conn->error);
            }
            $stmtC->bind_param("s", $new_name);
            $stmtC->execute();
            $customer_id = $conn->insert_id;
        }
    }

    if ($medicine_id <= 0 || $qty <= 0 || $customer_id <= 0) {
        if (!$msg) {
            $msg = "Please select medicine, valid quantity and customer.";
        }
    } else {
        // Check retailer stock
        $chk = $conn->prepare("SELECT qty, m.price 
                               FROM stock s 
                               JOIN medicines m ON s.medicine_id = m.medicine_id
                               WHERE s.medicine_id=? AND s.party_id=?");
        if (!$chk) {
            die("SQL Error (check stock): " . $conn->error);
        }
        $chk->bind_param("ii", $medicine_id, $party_id);
        $chk->execute();
        $r = $chk->get_result();
        $row = $r->fetch_assoc();

        if (!$row) {
            $msg = "You have no stock for this medicine.";
        } else {
            $available  = (int)$row["qty"];
            $unit_price = (float)$row["price"];

            if ($available < $qty) {
                $msg = "Not enough stock. Available: $available";
            } else {
                $total = $unit_price * $qty;

                // 1) Reduce stock
                $upd = $conn->prepare("
                    UPDATE stock SET qty = qty - ?
                    WHERE medicine_id=? AND party_id=?
                ");
                if (!$upd) {
                    die("SQL Error (reduce stock): " . $conn->error);
                }
                $upd->bind_param("iii", $qty, $medicine_id, $party_id);
                $upd->execute();

                // 2) Generate receipt code
                $receipt_code = bin2hex(random_bytes(8)); // 16-char hex

                // 3) Insert sale
                $ins = $conn->prepare("
                    INSERT INTO retailer_customer_sales
                    (retailer_party_id, customer_party_id, medicine_id, qty, unit_price, total_price, sale_date, receipt_code)
                    VALUES (?,?,?,?,?,?,?,?)
                ");
                if (!$ins) {
                    die("SQL Error (insert sale): " . $conn->error);
                }
                $ins->bind_param(
                    "iiiiddss",
                    $party_id,
                    $customer_id,
                    $medicine_id,
                    $qty,
                    $unit_price,
                    $total,
                    $sale_date,
                    $receipt_code
                );
                $ins->execute();
                $sale_id = $conn->insert_id;

                // 4) If this bill is for a specific customer order, mark it COMPLETED
                if ($order_id_post > 0) {
                    $updOrder = $conn->prepare("
                        UPDATE customer_orders
                        SET status = 'COMPLETED'
                        WHERE id = ? AND retailer_party_id = ?
                    ");
                    if ($updOrder) {
                        $updOrder->bind_param("ii", $order_id_post, $party_id);
                        $updOrder->execute();
                    }
                }

                // 5) Blockchain log
                $bc->addRecord("RETAIL_SALE", [
                    "sale_id"          => $sale_id,
                    "receipt_code"     => $receipt_code,
                    "retailer_party_id"=> $party_id,
                    "customer_party_id"=> $customer_id,
                    "medicine_id"      => $medicine_id,
                    "qty"              => $qty,
                    "unit_price"       => $unit_price,
                    "total_price"      => $total,
                    "sale_date"        => $sale_date,
                    "linked_order_id"  => $order_id_post ?: null
                ]);

                // 6) Redirect to receipt page
                header("Location: retailer_receipt.php?code=" . urlencode($receipt_code));
                exit;
            }
        }
    }

    // Refresh stock after sale attempt
    $st->execute();
    $stockRes = $st->get_result();
}
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-receipt-cutoff"></i> New Sale / Bill</h3>
<p class="text-muted">
    Create a bill for a customer. Stock will update and a verifiable receipt with QR will be generated.
</p>

<?php if ($msg): ?>
  <div class="alert alert-danger"><?= $msg ?></div>
<?php endif; ?>

<div class="row mt-3">
  <div class="col-md-6">
    <div class="card card-custom p-3 mb-3">
      <h5><i class="bi bi-person-vcard"></i> Customer & Item</h5>
      <form method="POST">
        <!-- If came from a pending customer order, keep the order ID -->
        <?php if ($linkedOrderId): ?>
          <input type="hidden" name="order_id" value="<?= $linkedOrderId ?>">
        <?php endif; ?>

        <!-- Customer selection -->
        <label class="form-label mt-2">Customer</label>

        <div class="form-check">
          <input class="form-check-input" type="radio" name="customer_mode" id="cust_existing"
                 value="existing" checked
                 onclick="toggleCustomerMode()">
          <label class="form-check-label" for="cust_existing">
            Existing Customer
          </label>
        </div>
        <select name="customer_id" id="customer_select" class="form-control mt-1">
          <option value="">-- Select Existing Customer --</option>
          <?php while ($c = $customers->fetch_assoc()): ?>
            <option value="<?= $c["party_id"] ?>">
              <?= htmlspecialchars($c["party_name"]) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <div class="form-check mt-2">
          <input class="form-check-input" type="radio" name="customer_mode" id="cust_new"
                 value="new" onclick="toggleCustomerMode()">
          <label class="form-check-label" for="cust_new">
            New Customer
          </label>
        </div>
        <input type="text" name="new_customer_name" id="new_customer_name"
               class="form-control mt-1" placeholder="Enter new customer name" disabled>

        <!-- Medicine & qty -->
        <label class="form-label mt-3">Medicine (Your Stock)</label>
        <select name="medicine_id" class="form-control" required>
          <option value="">-- Select Medicine --</option>
          <?php while ($m = $stockRes->fetch_assoc()): ?>
            <option value="<?= $m["medicine_id"] ?>">
              <?= htmlspecialchars($m["generic_name"]) ?>
              (<?= htmlspecialchars($m["brand_name"]) ?>)
              — In stock: <?= (int)$m["qty"] ?> — ₹<?= number_format($m["price"], 2) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label class="form-label mt-3">Quantity *</label>
        <input type="number" name="qty" class="form-control" min="1" required>

        <label class="form-label mt-3">Sale Date</label>
        <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>">

        <button class="btn btn-primary w-100 mt-4">
          <i class="bi bi-bag-check-fill"></i> Generate Bill
        </button>
      </form>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-custom p-4 mb-3">
      <h5><i class="bi bi-info-circle"></i> What happens after you submit?</h5>
      <ul class="small text-muted mb-0">
        <li>Retailer stock decreases.</li>
        <li>Sale entry is stored with a unique receipt code.</li>
        <li>Blockchain logs the sale event.</li>
        <li>If this sale came from a customer order, that order’s status becomes <strong>COMPLETED</strong>.</li>
        <li>You’ll be redirected to a printable receipt with QR code.</li>
        <li>Customer (or inspector) can verify the receipt online later.</li>
      </ul>
    </div>
  </div>
</div>

<script>
function toggleCustomerMode() {
  const existing = document.getElementById('cust_existing').checked;
  document.getElementById('customer_select').disabled = !existing;
  document.getElementById('new_customer_name').disabled = existing;
}
</script>

<?php include "../includes/footer.php"; ?>
