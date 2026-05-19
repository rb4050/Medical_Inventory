<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "MANUFACTURER") {
    die("Access denied. Manufacturer only.");
}

$party_id = $_SESSION["party_id"]; // manufacturer party_id from users table
$bc = new Blockchain($conn);
$msg = "";

// Manufacturer's current stock
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

// All wholesalers
$wholesalers = $conn->query("
    SELECT party_id, party_name
    FROM parties
    WHERE party_type = 'WHOLESALER'
    ORDER BY party_name ASC
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicine_id = (int)$_POST["medicine_id"];
    $to_party_id = (int)$_POST["wholesaler_id"];
    $qty         = (int)$_POST["qty"];
    $ship_date   = $_POST["ship_date"] ?: date('Y-m-d');

    if ($medicine_id <= 0 || $to_party_id <= 0 || $qty <= 0) {
        $msg = "Please select medicine, wholesaler and a positive quantity.";
    } else {
        // Check manufacturer available stock BEFORE creating shipment
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
                // 1) Create shipment as PENDING (no stock change yet)
                $ship = $conn->prepare("
                    INSERT INTO mfg_wholesaler_shipments
                    (from_party_id, to_party_id, medicine_id, qty, ship_date, status)
                    VALUES (?,?,?,?,?,'PENDING')
                ");
                if (!$ship) {
                    die("SQL Error (create shipment): " . $conn->error);
                }
                $ship->bind_param("iiiis", $party_id, $to_party_id, $medicine_id, $qty, $ship_date);
                $ship->execute();

                $shipment_id = $conn->insert_id;

                // 2) Blockchain log: shipment created (awaiting wholesaler confirm)
                $bc->addRecord("MFG_TO_WHOLESALER_REQUESTED", [
                    "shipment_id"   => $shipment_id,
                    "from_party_id" => $party_id,
                    "to_party_id"   => $to_party_id,
                    "medicine_id"   => $medicine_id,
                    "qty"           => $qty,
                    "ship_date"     => $ship_date,
                    "status"        => "PENDING"
                ]);

                $msg = "Shipment request created successfully. Wholesaler must confirm to receive stock.";
            }
        }
    }

    // Refresh stock dropdown
    $st->execute();
    $stockRes = $st->get_result();
}
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-arrow-right-circle"></i> Send Medicines to Wholesaler</h3>
<p class="text-muted">
    Choose a wholesaler by name and a quantity from your current stock; this creates a
    <strong>pending shipment</strong> that the wholesaler must confirm.
</p>

<?php if ($msg): ?>
  <div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<div class="row mt-3">
  <div class="col-md-6">
    <div class="card card-custom p-3 mb-3">
      <h5><i class="bi bi-sliders"></i> Create Shipment</h5>
      <form method="POST">
        <label class="form-label mt-1">Select Medicine (Your Stock)</label>
        <select name="medicine_id" class="form-control" required>
          <option value="">-- Select Medicine --</option>
          <?php while ($s = $stockRes->fetch_assoc()): ?>
            <option value="<?= $s["medicine_id"] ?>">
              <?= htmlspecialchars($s["generic_name"]) ?>
              (<?= htmlspecialchars($s["brand_name"]) ?>)
              — In stock: <?= (int)$s["qty"] ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label class="form-label mt-3">Select Wholesaler</label>
        <select name="wholesaler_id" class="form-control" required>
          <option value="">-- Choose Wholesaler --</option>
          <?php while ($w = $wholesalers->fetch_assoc()): ?>
            <option value="<?= $w["party_id"] ?>">
              <?= htmlspecialchars($w["party_name"]) ?>
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

  <div class="col-md-6">
    <div class="card card-custom p-3 mb-3">
      <h5><i class="bi bi-info-circle"></i> How it works</h5>
      <p class="small text-muted mb-1">
        • This does <strong>not</strong> move stock instantly.<br>
        • It creates a <strong>PENDING shipment</strong> entry for the chosen wholesaler.<br>
        • The wholesaler logs in and sees only their own pending shipments.<br>
        • When they confirm, stock moves from you to them and blockchain logs it.
      </p>
    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
