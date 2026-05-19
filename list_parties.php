<?php
session_start();
require "../config/db.php";
$result = $conn->query("SELECT * FROM parties ORDER BY party_id DESC");
?>

<?php include "../includes/header.php"; ?>

<div class="container mt-4">
<h3 class="text-success"><i class="bi bi-people-fill"></i> All Parties</h3>

<table class="table table-striped table-hover mt-3">
<thead class="table-dark">
<tr>
    <th>Name</th>
    <th>Type</th>
    <th>Phone</th>
    <th>Email</th>
</tr>
</thead>
<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row["party_name"] ?></td>
    <td><span class="badge bg-info"><?= $row["party_type"] ?></span></td>
    <td><?= $row["phone"] ?></td>
    <td><?= $row["email"] ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</div>

<?php include "../includes/footer.php"; ?>
