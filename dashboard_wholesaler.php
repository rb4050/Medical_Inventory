<?php include "../includes/header.php"; ?>

<h2><i class="bi bi-truck"></i> Wholesaler Dashboard</h2>
<p class="text-muted">
  Confirm shipments sent by manufacturers and distribute stock to retailers.
</p>

<div class="row mt-4 g-3">

  <!-- Receive from Manufacturer -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-box-arrow-down"></i> Receive Shipments</h5>
      <p class="small text-muted mb-2">
        Confirm shipments addressed to you from manufacturers.
      </p>
      <a href="wholesaler_receive.php" class="btn btn-sm btn-success w-100">
        Receive Incoming Shipments
      </a>
    </div>
  </div>

  <!-- Send to Retailer -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-arrow-right-circle"></i> Send to Retailer</h5>
      <p class="small text-muted mb-2">
        Select retailers and send medicines from your stock.
      </p>
      <a href="wholesaler_send_retailer.php" class="btn btn-sm btn-warning w-100">
        Send Medicines to Retailer
      </a>
    </div>
  </div>

  <!-- Stock / Blockchain -->
  <div class="col-md-4">
    <div class="card card-custom p-3">
      <h5><i class="bi bi-box-seam"></i> Stock & Ledger</h5>
      <a href="my_stock.php" class="btn btn-sm btn-outline-primary w-100 mb-2">
        View My Stock
      </a>
      <a href="blockchain_view.php" class="btn btn-sm btn-dark w-100">
        View Blockchain Ledger
      </a>
    </div>
  </div>

</div>

<?php include "../includes/footer.php"; ?>
