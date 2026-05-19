<?php include "../includes/header.php"; ?>

<h2><i class="bi bi-building"></i> Manufacturer Dashboard</h2>
<p class="mt-2 text-muted">
  Manually manage medicines and production batches; the system handles stock, distribution, and blockchain.
</p>

<div class="row mt-4 g-3">
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-capsule"></i> Medicines</h5>
      <p class="small text-muted mb-2">Add and view medicines with price.</p>
      <a href="add_medicine.php" class="btn btn-sm btn-primary w-100 mb-2">Add Medicine</a>
      <a href="list_medicines.php" class="btn btn-sm btn-outline-secondary w-100">View Medicines</a>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-bezier2"></i> Production Batches</h5>
      <p class="small text-muted mb-2">Manual entry; stock + blockchain auto-update.</p>
      <a href="manufacturer_add_batch.php" class="btn btn-sm btn-success w-100 mb-2">
        Add Production Batch
      </a>
      <a href="manufacturer_batches.php" class="btn btn-sm btn-outline-success w-100">
        View My Batches
      </a>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-box-seam"></i> Stock & Distribution</h5>
      <p class="small text-muted mb-2">Control stock and send specific quantities to wholesalers.</p>
      <a href="my_stock.php" class="btn btn-sm btn-outline-primary w-100 mb-2">
        View My Stock
      </a>
      <a href="manufacturer_send_wholesaler.php" class="btn btn-sm btn-warning w-100 mb-2">
        Send to Wholesaler
      </a>
      <a href="blockchain_view.php" class="btn btn-sm btn-dark w-100">
        Blockchain Ledger
      </a>
    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
