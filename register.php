<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

$bc = new Blockchain($conn);
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name  = $_POST["name"];
    $email = $_POST["email"];
    $pass  = $_POST["password"];
    $type  = $_POST["party_type"];  // MANUFACTURER / WHOLESALER / RETAILER / CUSTOMER

    // Basic validation
    if ($name === "" || $email === "" || $pass === "") {
        $msg = "All fields are required.";
    } else {
        // Check email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $r = $check->get_result();
        if ($r->num_rows > 0) {
            $msg = "Email is already registered.";
        } else {
            // Create Party
            $stmtP = $conn->prepare("INSERT INTO parties (party_name, party_type) VALUES (?,?)");
            $stmtP->bind_param("ss", $name, $type);
            $stmtP->execute();
            $party_id = $conn->insert_id;

            // Create User linked to that party
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmtU = $conn->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, party_id)
                 VALUES (?,?,?,?,?)"
            );
            $stmtU->bind_param("ssssi", $name, $email, $hash, $type, $party_id);
            if ($stmtU->execute()) {
                $bc->addRecord("USER_REGISTERED", [
                    "name" => $name,
                    "email" => $email,
                    "role"  => $type
                ]);

                header("Location: login.php?registered=1");
                exit;
            } else {
                $msg = "Error creating user.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Medical Inventory</title>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 480px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h3 class="text-center text-primary">Create Account</h3>

      <?php if ($msg): ?>
        <div class="alert alert-danger mt-2"><?= $msg ?></div>
      <?php endif; ?>

      <form method="POST">
        <label class="mt-2">Full Name *</label>
        <input type="text" name="name" class="form-control" required>

        <label class="mt-2">Email *</label>
        <input type="email" name="email" class="form-control" required>

        <label class="mt-2">Password *</label>
        <input type="password" name="password" class="form-control" required>

        <label class="mt-2">Account Type *</label>
        <select name="party_type" class="form-control" required>
            <option value="MANUFACTURER">Manufacturer</option>
            <option value="WHOLESALER">Wholesaler</option>
            <option value="RETAILER">Retailer</option>
            <option value="CUSTOMER">Customer</option>
        </select>

        <button class="btn btn-success w-100 mt-4">Register</button>
      </form>

      <p class="mt-3 text-center">
        Already have an account? <a href="login.php">Login</a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
