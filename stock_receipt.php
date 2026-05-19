<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";
$bc = new Blockchain($conn);

$msg = "";
$med = $conn->query("SELECT * FROM medicines");
$parties = $conn->query("SELECT * FROM parties WHERE party_type IN ('MANUFACTURER','WHOLESALER','RETAILER')");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $medicine_id = $_POST['medicine_id'];
    $batch = $_POST['batch_no'];
    $expiry = $_POST['expiry_date'];
    $qty = $_POST['qty'];
    $party = $_POST['to_party'];

    // Insert batch
    $stmt = $conn->prepare("INSERT INTO stock_batches(medicine_id,batch_no,expiry_date,qty,location) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issii", $medicine_id,$batch,$expiry,$qty,$party);
    $stmt->execute();
    $batch_id = $conn->insert_id;

    // Ledger
    $conn->query("INSERT INTO stock_ledger(medicine_id,batch_id,to_party,qty,movement,reference)
      VALUES ($medicine_id,$batch_id,$party,$qty,'IN','GRN')");

    // Blockchain
    $bc->addRecord("STOCK_IN",[
        "medicine_id"=>$medicine_id,
        "qty"=>$qty,
        "party"=>$party
    ]);

    $msg = "Stock added successfully!";
}
?>

<?php include "../includes/header.php"; ?>

<div class="card card-custom p-4">
<h3><i class="bi bi-box-arrow-in-down"></i> Stock IN</h3>

<?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<form method="POST">
<label class="mt-2">Select Medicine</label>
<select name="medicine_id" class="form-control" required>
<?php while($r=$med->fetch_assoc()): ?>
<option value="<?= $r['medicine_id'] ?>"><?= $r['generic_name'] ?></option>
<?php endwhile; ?>
</select>

<label class="mt-3">Batch No</label>
<input name="batch_no" class="form-control">

<label class="mt-3">Expiry</label>
<input type="date" name="expiry_date" class="form-control">

<label class="mt-3">Quantity</label>
<input type="number" name="qty" class="form-control" required>

<label class="mt-3">Receiver Party</label>
<select name="to_party" class="form-control" required>
<?php while($p=$parties->fetch_assoc()): ?>
<option value="<?= $p['party_id'] ?>"><?= $p['party_name']." - ".$p['party_type'] ?></option>
<?php endwhile; ?>
</select>

<button class="btn btn-primary w-100 mt-4">Save</button>
</form>
</div>

<?php include "../includes/footer.php"; ?>
