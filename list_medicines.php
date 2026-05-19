<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$result = $conn->query("SELECT * FROM medicines ORDER BY medicine_id DESC");
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-list-task"></i> Medicines</h3>

<div class="card card-custom p-3 mt-3">
<table class="table table-striped table-hover mb-0">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Generic Name</th>
            <th>Brand Name</th>
            <th>Price</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="4" class="text-center">No medicines added yet.</td></tr>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row["medicine_id"] ?></td>
            <td><?= htmlspecialchars($row["generic_name"]) ?></td>
            <td><?= htmlspecialchars($row["brand_name"]) ?></td>
            <td>₹<?= number_format((float)$row["price"], 2) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php include "../includes/footer.php"; ?>
