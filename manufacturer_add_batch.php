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

$party_id = $_SESSION["party_id"];
$bc = new Blockchain($conn);

$msg = "";
$medicines = $conn->query("SELECT * FROM medicines ORDER BY generic_name ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicine_id = (int)$_POST["medicine_id"];
    $batch_no    = trim($_POST["batch_no"]);
    $mfg_date    = $_POST["mfg_date"] ?: NULL;
    $exp_date    = $_POST["exp_date"] ?: NULL;
    $qty         = (int)$_POST["quantity"];

    if ($medicine_id <= 0 || $batch_no === "" || $qty <= 0) {
        $msg = "Medicine, batch number and positive quantity are required.";
    } else {
        // 1) Insert batch record
        $sql = "INSERT INTO manufacturer_batches
                (medicine_id, batch_no, mfg_date, exp_date, quantity, created_by_party_id)
                VALUES (?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("SQL Error in manufacturer_add_batch: " . $conn->error);
        }
        $stmt->bind_param("isssii",
            $medicine_id, $batch_no, $mfg_date, $exp_date, $qty, $party_id
        );

        if ($stmt->execute()) {
            $batch_id = $conn->insert_id;

            // 2) Update stock for this manufacturer
            $stmtStock = $conn->prepare("
                INSERT INTO stock (medicine_id, party_id, qty)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
            ");
            $stmtStock->bind_param("iii", $medicine_id, $party_id, $qty);
            $stmtStock->execute();

            // 3) Blockchain log
            $bc->addRecord("MFG_BATCH_CREATED", [
                "batch_id"    => $batch_id,
                "party_id"    => $party_id,
                "medicine_id" => $medicine_id,
                "batch_no"    => $batch_no,
                "qty"         => $qty,
                "mfg_date"    => $mfg_date,
                "exp_date"    => $exp_date
            ]);

            $msg = "Production batch saved and stock updated.";
        } else {
            $msg = "Error saving batch.";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="row">
  <div class="col-md-8 offset-md-2">
    <div class="card card-custom p-4">
      <h3 class="mb-3">
        <i class="bi bi-bezier2"></i> Add Production Batch
      </h3>
      <p class="text-muted mb-3">
        Manufacturer manually enters batch details; stock and blockchain update automatically.
      </p>

      <?php if ($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
      <?php endif; ?>

      <form method="POST">
        <label class="form-label">Medicine *</label>
        <select name="medicine_id" class="form-control" required>
          <option value="">-- Select Medicine --</option>
          <?php while ($m = $medicines->fetch_assoc()): ?>
            <option value="<?= $m["medicine_id"] ?>">
              <?= htmlspecialchars($m["generic_name"]) ?>
              (<?= htmlspecialchars($m["brand_name"]) ?>) – ₹<?= number_format($m["price"], 2) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label class="form-label mt-3">Batch Number *</label>
        <input type="text" name="batch_no" class="form-control" required>

        <div class="row">
          <div class="col-md-6">
            <label class="form-label mt-3">Mfg Date</label>
            <input type="date" name="mfg_date" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label mt-3">Expiry Date</label>
            <input type="date" name="exp_date" class="form-control">
          </div>
        </div>

        <label class="form-label mt-3">Quantity *</label>
        <input type="number" name="quantity" class="form-control" min="1" required>

        <button class="btn btn-primary w-100 mt-4">
          <i class="bi bi-check-circle"></i> Save Batch & Update Stock
        </button>
      </form>
    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
