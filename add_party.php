<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

$bc = new Blockchain($conn);
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["party_name"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];
    $type = $_POST["party_type"];

    $stmt = $conn->prepare("INSERT INTO parties(party_name,email,phone,address,party_type) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss",$name,$email,$phone,$address,$type);
    if ($stmt->execute()) {
        $bc->addRecord("PARTY_ADDED", [
            "party_name"=>$name,
            "type"=>$type
        ]);
        $msg = "Party added successfully!";
    } else {
        $msg = "Error: " . $conn->error;
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="container mt-4">
<div class="card card-custom p-4">

<h3 class="text-primary"><i class="bi bi-building"></i> Add Party</h3>

<?php if($msg): ?>
<div class="alert alert-info mt-2"><?= $msg ?></div>
<?php endif; ?>

<form method="POST" class="mt-3">
    <label class="form-label">Name *</label>
    <input type="text" name="party_name" class="form-control" required>

    <label class="form-label mt-3">Email</label>
    <input type="email" name="email" class="form-control">

    <label class="form-label mt-3">Phone</label>
    <input type="text" name="phone" class="form-control">

    <label class="form-label mt-3">Address</label>
    <textarea name="address" class="form-control"></textarea>

    <label class="form-label mt-3">Party Type *</label>
    <select name="party_type" class="form-control" required>
        <option value="MANUFACTURER">Manufacturer</option>
        <option value="WHOLESALER">Wholesaler</option>
        <option value="RETAILER">Retailer</option>
        <option value="CUSTOMER">Customer</option>
    </select>

    <button class="btn btn-primary w-100 mt-4">
        <i class="bi bi-plus-circle"></i> Save Party
    </button>
</form>

</div>
</div>

<?php include "../includes/footer.php"; ?>
