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

$party_id = $_SESSION["party_id"]; // current retailer
$bc = new Blockchain($conn);
$msg = "";

// Handle confirmation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm"])) {
    $shipment_id  = (int)$_POST["shipment_id"];
    $receive_date = $_POST["receive_date"] ?: date('Y-m-d');

    // Load shipment row
    $stmt = $conn->prepare("
        SELECT from_party_id, to_party_id, medicine_id, qty
        FROM wholesaler_retailer_shipments
        WHERE id = ? AND to_party_id = ? AND status = 'PENDING'
    ");
    if (!$stmt) {
        die("SQL Error (load shipment): " . $conn->error);
    }
    $stmt->bind_param("ii", $shipment_id, $party_id);
    $stmt->execute();
    $shRes = $stmt->get_result();
    $shipment = $shRes->fetch_assoc();

    if (!$shipment) {
        $msg = "Shipment not found or already processed.";
    } else {
        $from_party  = (int)$shipment["from_party_id"];
        $to_party    = (int)$shipment["to_party_id"];
        $medicine_id = (int)$shipment["medicine_id"];
        $qty         = (int)$shipment["qty"];

        // Check wholesaler (sender) stock
        $chk = $conn->prepare("SELECT qty FROM stock WHERE medicine_id=? AND party_id=?");
        if (!$chk) {
            die("SQL Error (check wholesaler stock): " . $conn->error);
        }
        $chk->bind_param("ii", $medicine_id, $from_party);
        $chk->execute();
        $r = $chk->get_result();
        $row = $r->fetch_assoc();
        $available = $row ? (int)$row["qty"] : 0;

        if ($available < $qty) {
            $msg = "Wholesaler does not have enough stock anymore. (Available: $available)";
        } else {
            // 1) Reduce wholesaler stock
            $upd = $conn->prepare("
                UPDATE stock SET qty = qty - ?
                WHERE medicine_id = ? AND party_id = ?
            ");
            if (!$upd) {
                die("SQL Error (reduce wholesaler stock): " . $conn->error);
            }
            $upd->bind_param("iii", $qty, $medicine_id, $from_party);
            $upd->execute();

            // 2) Increase retailer stock
            $ins = $conn->prepare("
                INSERT INTO stock (medicine_id, party_id, qty)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
            ");
            if (!$ins) {
                die("SQL Error (add retailer stock): " . $conn->error);
            }
            $ins->bind_param("iii", $medicine_id, $to_party, $qty);
            $ins->execute();

            // 3) Update shipment status
            $updShip = $conn->prepare("
                UPDATE wholesaler_retailer_shipments
                SET status = 'ACCEPTED', received_date = ?
                WHERE id = ?
            ");
            if (!$updShip) {
                die("SQL Error (update shipment status): " . $conn->error);
            }
            $updShip->bind_param("si", $receive_date, $shipment_id);
            $updShip->execute();

            // 4) Blockchain log
            $bc->addRecord("RETAILER_RECEIVED", [
                "shipment_id"   => $shipment_id,
                "from_party_id" => $from_party,
                "to_party_id"   => $to_party,
                "medicine_id"   => $medicine_id,
                "qty"           => $qty,
                "received_date" => $receive_date
            ]);

            $msg = "Shipment accepted and stock updated.";
        }
    }
}

// Load pending shipments for THIS retailer
$sql = "
    SELECT s.id, s.medicine_id, s.qty, s.ship_date,
           p_from.party_name AS from_name,
           m.generic_name, m.brand_name, m.price
    FROM wholesaler_retailer_shipments s
    JOIN parties p_from ON s.from_party_id = p_from.party_id
    JOIN medicines m ON s.medicine_id = m.medicine_id
    WHERE s.to_party_id = ? AND s.status = 'PENDING'
    ORDER BY s.id DESC
";
$st = $conn->prepare($sql);
if (!$st) {
    die("SQL Error (load pending wr shipments): " . $conn->error);
}
$st->bind_param("i", $party_id);
$st->execute();
$shipments = $st->get_result();
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-box-arrow-down"></i> Pending Shipments from Wholesalers</h3>
<p class="text-muted">Confirm shipments sent to you. Once confirmed, your stock will be updated.</p>

<?php if ($msg): ?>
  <div class="alert alert-info"><?= $msg ?></div>
<?php endif; ?>

<div class="card card-custom p-3 mt-3">
<table class="table table-hover table-bordered mb-0">
    <thead class="table-dark">
        <tr>
            <th>From</th>
            <th>Medicine</th>
            <th>Qty</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($shipments->num_rows === 0): ?>
        <tr><td colspan="3" class="text-center">No pending shipments.</td></tr>
    <?php endif; ?>

    <?php while ($s = $shipments->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($s["from_name"]) ?></td>
            <td>
                <?= htmlspecialchars($s["generic_name"]) ?>
                (<?= htmlspecialchars($s["brand_name"]) ?>)
                – ₹<?= number_format($s["price"], 2) ?>
            </td>
            <td>
                <form method="POST" class="d-flex align-items-center">
                    <input type="hidden" name="shipment_id" value="<?= $s["id"] ?>">

                    <span class="me-2">Qty: <?= (int)$s["qty"] ?></span>

                    <input type="date"
                           name="receive_date"
                           value="<?= date('Y-m-d') ?>"
                           class="form-control form-control-sm me-2"
                           style="width:140px;"
                           required>

                    <button type="submit" name="confirm" class="btn btn-success btn-sm">
                        Confirm
                    </button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php include "../includes/footer.php"; ?>
