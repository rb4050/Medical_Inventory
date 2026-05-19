<?php
session_start();
require "../config/db.php";

$message = "";
if (isset($_GET['registered'])) {
    $message = "Account created successfully! Please login.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"]  = $user["user_id"];
            $_SESSION["role"]     = $user["role"];
            $_SESSION["party_id"] = $user["party_id"];

            header("Location: dashboard.php");
            exit;
        }
    }
    $message = "Invalid email or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Medical Inventory</title>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body {
    background: linear-gradient(135deg, #0f172a, #1e40af);
    height: 100vh;
    color: #fff;
    font-family: "Segoe UI", sans-serif;
}
.login-box {
    width: 420px;
    background: #111827;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 0 20px rgba(0,0,0,.4);
}
.form-control {
    background: #1f2937;
    color: #e5e7eb;
    border: none;
}
.form-control:focus {
    background: #1f2937;
    color: #e5e7eb;
    box-shadow: 0 0 0 1px #3b82f6;
}
.btn-primary {
    background: #2563eb;
    border: none;
}
.btn-primary:hover {
    background: #1d4ed8;
}
.btn-toggle {
    border-color: #4b5563;
}
</style>
</head>
<body>

<div class="d-flex justify-content-center align-items-center h-100">
  <div class="login-box">
    <h3 class="text-center mb-3">
        <i class="bi bi-shield-lock-fill"></i> Login
    </h3>

    <?php if ($message): ?>
      <div class="alert alert-warning text-dark py-2"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
      <label class="mt-2">Email *</label>
      <input type="email" name="email" class="form-control" required>

      <label class="mt-3">Password *</label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" required>
        <button type="button" class="btn btn-outline-light btn-toggle" onclick="togglePass()">
          <i class="bi bi-eye"></i>
        </button>
      </div>

      <button class="btn btn-primary w-100 mt-4">
        <i class="bi bi-box-arrow-in-right"></i> Login
      </button>
    </form>

    <p class="mt-3 text-center">
        Don’t have an account?
        <a href="register.php" class="text-info">Register</a>
    </p>
  </div>
</div>

<script>
function togglePass() {
    const p = document.getElementById("password");
    p.type = (p.type === "password") ? "text" : "password";
}
</script>

</body>
</html>
