<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "WHOLESALER") {
    die("Access denied. Wholesaler only.");
}

$party_id = $_SESSION["party_id"]; // wholesaler party_id
$bc = new Blockchain($conn);
$msg = "";

// Wholesaler's current stock
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

// All retailers to choose from
$retailers = $conn->query("
    SELECT party_id, party_name
    FROM parties
    WHERE party_type = 'RETAILER'
    ORDER BY party_name ASC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicine_id = (int)$_POST["medicine_id"];
    $to_party_id = (int)$_POST["retailer_id"];
    $qty         = (int)$_POST["qty"];
    $ship_date   = $_POST["ship_date"] ?: date('Y-m-d');

    if ($medicine_id <= 0 || $to_party_id <= 0 || $qty <= 0) {
        $msg = "Please select medicine, retailer and a positive quantity.";
    } else {
        // Check wholesaler available stock
        $chk = $conn->prepare("SELECT qty FROM stock WHERE medicine_id=? AND party_id=?");
        if (!$chk) {
            die("SQL Error (check stock): " . $conn->error);
        }
        $chk->bind_param("ii", $medicine_id, $party_id);
        $chk->execute();
        $r = $chk->get_result();

        if ($r->num_rows === 0) {
            $msg = "You have no stock for this medicine.";
        } else {
            $available = (int)$r->fetch_assoc()["qty"];
            if ($available < $qty) {
                $msg = "Not enough stock. Available: $available";
            } else {
                // Create PENDING shipment (no stock change yet)
                $ship = $conn->prepare("
                    INSERT INTO wholesaler_retailer_shipments
                    (from_party_id, to_party_id, medicine_id, qty, ship_date, status)
                    VALUES (?,?,?,?,?,'PENDING')
                ");
                if (!$ship) {
                    die("SQL Error (create shipment): " . $conn->error);
                }
                $ship->bind_param("iiiis", $party_id, $to_party_id, $medicine_id, $qty, $ship_date);
                $ship->execute();

                $shipment_id = $conn->insert_id;

                // Blockchain log: request created
                $bc->addRecord("WHL_TO_RETAILER_REQUESTED", [
                    "shipment_id"   => $shipment_id,
                    "from_party_id" => $party_id,
                    "to_party_id"   => $to_party_id,
                    "medicine_id"   => $medicine_id,
                    "qty"           => $qty,
                    "ship_date"     => $ship_date,
                    "status"        => "PENDING"
                ]);

                $msg = "Shipment request created. Retailer must confirm to receive stock.";
            }
        }
    }

    // refresh stock list for dropdowns/cards
    $st->execute();
    $stockRes = $st->get_result();
}
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-arrow-right-circle"></i> Send Medicines to Retailer</h3>
<p class="text-muted">
    Choose a retailer and send specific quantities from your stock.
    Retailer will confirm before stock is actually moved.
</p>

<?php if ($msg): ?>
  <div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<div class="row mt-3">
  <!-- Left: Form -->
  <div class="col-md-5">
    <div class="card card-custom p-3 mb-3">
      <h5><i class="bi bi-sliders"></i> Create Shipment</h5>
      <form method="POST">
        <label class="form-label mt-2">Retailer</label>
        <select name="retailer_id" class="form-control" required>
          <option value="">-- Select Retailer --</option>
          <?php while ($r = $retailers->fetch_assoc()): ?>
            <option value="<?= $r["party_id"] ?>">
              <?= htmlspecialchars($r["party_name"]) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label class="form-label mt-3">Medicine</label>
        <select name="medicine_id" class="form-control" required>
          <option value="">-- Select from Your Stock --</option>
          <?php
          // we need a fresh pointer: re-run
          $st->execute();
          $stockRes2 = $st->get_result();
          while ($s = $stockRes2->fetch_assoc()): ?>
            <option value="<?= $s["medicine_id"] ?>">
              <?= htmlspecialchars($s["generic_name"]) ?>
              (<?= htmlspecialchars($s["brand_name"]) ?>) — In stock: <?= (int)$s["qty"] ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label class="form-label mt-3">Quantity to Send *</label>
        <input type="number" name="qty" min="1" class="form-control" required>

        <label class="form-label mt-3">Shipment Date</label>
        <input type="date" name="ship_date" class="form-control" value="<?= date('Y-m-d') ?>">

        <button class="btn btn-primary w-100 mt-4">
          <i class="bi bi-send-check"></i> Create Shipment
        </button>
      </form>
    </div>
  </div>

  <!-- Right: Card-style preview of stock -->
  <div class="col-md-7">
    <div class="card card-custom p-3 mb-3">
      <h5><i class="bi bi-box-seam"></i> Your Available Stock</h5>
      <p class="small text-muted mb-2">
        This is a quick view of what you can send to retailers.
      </p>
      <div class="row g-3 mt-1">
        <?php if ($stockRes->num_rows === 0): ?>
          <p class="text-muted">No stock available.</p>
        <?php endif; ?>

        <?php while ($s = $stockRes->fetch_assoc()): ?>
          <div class="col-md-6">
            <div class="border rounded-3 p-2 h-100">
              <div class="d-flex justify-content-between">
                <strong><?= htmlspecialchars($s["generic_name"]) ?></strong>
                <span class="badge bg-secondary">
                  Qty: <?= (int)$s["qty"] ?>
                </span>
              </div>
              <div class="small text-muted">
                Brand: <?= htmlspecialchars($s["brand_name"]) ?><br>
                Price: ₹<?= number_format($s["price"], 2) ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
