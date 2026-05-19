<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if(!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["role"];
$party_id = $_SESSION["party_id"];
$bc = new Blockchain($conn);

// Role-based valid transfer chain
$allowed_receivers_sql = "";
if($role == "MANUFACTURER") {
    $allowed_receivers_sql = "SELECT * FROM parties WHERE party_type='WHOLESALER'";
} elseif($role == "WHOLESALER") {
    $allowed_receivers_sql = "SELECT * FROM parties WHERE party_type='RETAILER'";
} elseif($role == "RETAILER") {
    $allowed_receivers_sql = "SELECT * FROM parties WHERE party_type='CUSTOMER'";
} else {
    die("Not allowed");
}

$meds = $conn->query("
    SELECT b.batch_id, b.batch_no, b.qty, m.generic_name, m.brand_name
    FROM stock_batches b
    JOIN medicines m ON b.medicine_id = m.medicine_id
    WHERE b.location = $party_id AND b.qty > 0
");

$receivers = $conn->query($allowed_receivers_sql);

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $batch_id = $_POST["batch_id"];
    $to_party = $_POST["to_party"];
    $qty = $_POST["qty"];

    // Fetch batch details
    $batch = $conn->query("SELECT * FROM stock_batches WHERE batch_id=$batch_id")->fetch_assoc();
    if($batch["qty"] < $qty) {
        $msg = "Not enough stock!";
    } else {
        $new_qty = $batch["qty"] - $qty;
        $conn->query("UPDATE stock_batches SET qty=$new_qty WHERE batch_id=$batch_id");

        // Ledger OUT
        $conn->query("INSERT INTO stock_ledger(medicine_id,batch_id,from_party,to_party,qty,movement,reference)
                      VALUES ({$batch['medicine_id']},$batch_id,$party_id,$to_party,$qty,'TRANSFER','ISSUE')");

        // Add new batch to receiver
        $conn->query("
            INSERT INTO stock_batches (medicine_id,batch_no,expiry_date,qty,location)
            VALUES ({$batch['medicine_id']},'{$batch['batch_no']}','{$batch['expiry_date']}',$qty,$to_party)
        ");
        $new_batch = $conn->insert_id;

        // Blockchain log
        $bc->addRecord("STOCK_TRANSFER",[
            "from"=>$party_id,
            "to"=>$to_party,
            "batch"=>$batch_id,
            "qty"=>$qty
        ]);

        $msg = "Stock transfer recorded!";
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="card card-custom p-4">
<h3><i class="bi bi-box-arrow-right"></i> Issue / Transfer Stock</h3>

<?php if($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

<form method="POST">

    <label class="mt-3">Select Batch / Medicine</label>
    <select name="batch_id" class="form-control" required>
        <?php while($b = $meds->fetch_assoc()): ?>
            <option value="<?= $b['batch_id'] ?>">
                <?= $b['generic_name'] ?> 
                <?= $b['brand_name'] ?> —
                Batch: <?= $b["batch_no"] ?> —
                Qty: <?= $b["qty"] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label class="mt-3">Quantity *</label>
    <input type="number" name="qty" class="form-control" required>

    <label class="mt-3">Send To</label>
    <select name="to_party" class="form-control" required>
        <?php while($p = $receivers->fetch_assoc()): ?>
            <option value="<?= $p['party_id'] ?>">
                <?= $p["party_name"] ?> — <?= $p["party_type"] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button class="btn btn-warning w-100 mt-4">
        <i class="bi bi-send-check"></i> Transfer
    </button>
</form>

</div>

<?php include "../includes/footer.php"; ?>
