<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$bc = new Blockchain($conn);
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $generic = trim($_POST["generic_name"]);
    $brand   = trim($_POST["brand_name"]);
    $price   = (float)$_POST["price"];

    if ($generic === "" || $price <= 0) {
        $msg = "Generic name and positive price are required.";
    } else {
        $sql  = "INSERT INTO medicines (generic_name, brand_name, price) VALUES (?,?,?)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            // Helpful during development if anything goes wrong with SQL
            die("SQL Error in add_medicine: " . $conn->error);
        }

        $stmt->bind_param("ssd", $generic, $brand, $price);

        if ($stmt->execute()) {
            $bc->addRecord("MEDICINE_ADDED", [
                "generic" => $generic,
                "brand"   => $brand,
                "price"   => $price
            ]);
            $msg = "Medicine added successfully.";
        } else {
            $msg = "Error adding medicine.";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<div class="row">
  <div class="col-md-8 offset-md-2">
    <div class="card card-custom p-4">
      <h3><i class="bi bi-capsule"></i> Add Medicine</h3>
      <p class="text-muted mb-3">Add a new medicine with its selling price. This action is recorded on the blockchain.</p>

      <?php if ($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
      <?php endif; ?>

      <form method="POST" class="mt-3">
        <label class="form-label">Generic Name *</label>
        <input type="text" name="generic_name" class="form-control" required>

        <label class="form-label mt-3">Brand Name</label>
        <input type="text" name="brand_name" class="form-control">

        <label class="form-label mt-3">Price *</label>
        <input type="number" name="price" step="0.01" min="0.01" class="form-control" required>

        <button class="btn btn-success w-100 mt-4">
          <i class="bi bi-plus-circle"></i> Save Medicine
        </button>
      </form>
    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
