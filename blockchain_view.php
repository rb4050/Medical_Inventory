<?php
session_start();
require "../config/db.php";
require "../config/blockchain.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Ensure table exists (constructor creates it if missing)
$bc = new Blockchain($conn);

// Fetch latest 100 entries
$res = $conn->query("SELECT * FROM blockchain_ledger ORDER BY id DESC LIMIT 100");
if ($res === false) {
    die("SQL Error in blockchain_view.php: " . $conn->error);
}
?>

<?php include "../includes/header.php"; ?>

<h3><i class="bi bi-link-45deg"></i> Blockchain Ledger (Last 100)</h3>

<div class="card card-custom p-3 mt-3">
    <?php if ($res->num_rows === 0): ?>
        <p>No blockchain records yet.</p>
    <?php else: ?>
        <ul class="list-group">
            <?php while ($row = $res->fetch_assoc()): ?>
                <?php $data = json_decode($row["data"], true); ?>
                <li class="list-group-item">
                    <strong>#<?= $row["id"] ?> | <?= htmlspecialchars($row["action"]) ?></strong><br>
                    <small class="text-muted"><?= $row["created_at"] ?></small><br>
                    <small>Data:
                        <?= htmlspecialchars(json_encode($data)) ?>
                    </small><br>
                    <small>Hash: <?= substr($row["current_hash"], 0, 18) ?>...</small>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
