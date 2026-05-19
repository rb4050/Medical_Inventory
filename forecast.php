<?php
// forecast.php - dashboard with easy-to-understand textual summary + chart
// Save to: C:\xampp\htdocs\medical_inventory\forecast.php

// ---------- Config (edit if your setup differs) ----------
$AI_URL = "http://127.0.0.1:5000/predict";    // local Flask AI service
$DEFAULT_ITEM = isset($_GET['item_id']) ? intval($_GET['item_id']) : 1;
$DEFAULT_PERIODS = isset($_GET['periods']) ? intval($_GET['periods']) : 7;
$DEFAULT_WINDOW = isset($_GET['window']) ? intval($_GET['window']) : 7;

// DB connection for optional friendly item name (adjust if needed)
$DB_HOST = "127.0.0.1";
$DB_USER = "root";
$DB_PASS = "";   // your XAMPP root password, or leave empty if none
$DB_NAME = "medical_inventory";
$DB_PORT = 3307; // match your phpMyAdmin port
// ---------------------------------------------------------

function call_ai($item_id, $periods, $window, $days_back = 60) {
    global $AI_URL;
    $payload = json_encode([
        "item_id" => intval($item_id),
        "periods" => intval($periods),
        "window" => intval($window),
        "days_back" => intval($days_back)
    ]);
    $ch = curl_init($AI_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) return ["error" => "cURL: " . $curl_err];
    $json = json_decode($resp, true);
    if ($json === null) return ["error" => "Invalid response from AI: " . substr($resp, 0, 200)];
    return $json;
}

// Friendly item name lookup (tries a few common fields)
function try_item_name($item_id) {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;
    $name = null;
    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    if ($mysqli->connect_errno) return null;
    $candidates = [
        "SELECT name FROM medicines WHERE id = ? LIMIT 1",
        "SELECT name FROM medicines WHERE medicine_id = ? LIMIT 1",
        "SELECT med_name FROM medicines WHERE id = ? LIMIT 1",
        "SELECT medicine_name FROM medicines WHERE id = ? LIMIT 1"
    ];
    foreach ($candidates as $q) {
        $stmt = $mysqli->prepare($q);
        if (!$stmt) continue;
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stmt->bind_result($n);
        if ($stmt->fetch()) { $name = $n; $stmt->close(); break; }
        $stmt->close();
    }
    $mysqli->close();
    return $name;
}

// Handle Download CSV
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $item_id = $DEFAULT_ITEM; $periods = $DEFAULT_PERIODS; $window = $DEFAULT_WINDOW;
    $result = call_ai($item_id, $periods, $window);
    if (isset($result['error'])) { header("Content-Type: text/plain"); echo "Error: " . $result['error']; exit; }
    $filename = "forecast_item_{$item_id}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['item_id', 'date', 'predicted', 'generated_at']);
    foreach ($result['forecast'] as $row) fputcsv($out, [$item_id, $row['ds'], $row['predicted'], $result['generated_at'] ?? '']);
    fclose($out);
    exit;
}

$item_id = $DEFAULT_ITEM;
$periods = $DEFAULT_PERIODS;
$window = $DEFAULT_WINDOW;
$ai_response = null;
$error = null;
if (isset($_GET['item_id']) || isset($_GET['periods'])) {
    $ai_response = call_ai($item_id, $periods, $window);
    if (isset($ai_response['error'])) { $error = $ai_response['error']; $ai_response = null; }
}

$item_name = try_item_name($item_id);

// Build a plain-language summary if we have forecast
$summary_text = "";
if ($ai_response && isset($ai_response['forecast'])) {
    $preds = array_map(function($r){ return floatval($r['predicted']); }, $ai_response['forecast']);
    $avg = round(array_sum($preds)/count($preds), 3);
    $max = round(max($preds), 3);
    $min = round(min($preds), 3);
    $start = $ai_response['forecast'][0]['ds'];
    $end = end($ai_response['forecast'])['ds'];
    $name_display = $item_name ? "{$item_name} (ID {$item_id})" : "Item ID {$item_id}";
    // Friendly sentences:
    $summary_text = "Forecast for <strong>{$name_display}</strong>: from <strong>{$start}</strong> to <strong>{$end}</strong>, "
        . "the expected average daily demand is <strong>{$avg}</strong> units (typical range: {$min} — {$max}). "
        . "This means you should plan for about {$avg} units per day over the next {$periods} days.";
}

// Short plain explanation of what the AI is doing
$explain_short = "This AI uses a simple and reliable method called a <strong>moving average</strong>. "
    . "It looks at recent daily usage and averages the last few days (you control that with <em>Window</em>) "
    . "then uses that average as the prediction for each coming day. "
    . "It's fast, robust, and a good baseline — later we can upgrade to more advanced models if you want.";

// Tips in simple language
$tips = [
    "If predicted values are too constant, try a larger window to smooth things, or smaller to react to changes.",
    "Save forecasts and compare with actual sales later to see how accurate the model is.",
    "If you need weekly or monthly forecasts, change the Periods value or ask me to add aggregation."
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forecast — Simple & Clear</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; margin:0; padding:18px; background:#f6f8fb}
    .wrap{max-width:1100px;margin:0 auto}
    .card{background:#fff;border-radius:10px;padding:16px;box-shadow:0 6px 18px rgba(15,23,42,0.06);margin-bottom:14px}
    header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    h1{margin:0;font-size:20px}
    .controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    input[type=number]{padding:8px;border:1px solid #e6e9ef;border-radius:8px;min-width:90px}
    button{background:#2563eb;color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer}
    .muted{color:#6b7280;font-size:13px}
    .summary{font-size:15px;padding:12px;background:#f8fafc;border-radius:8px;margin-bottom:10px}
    .explain{font-size:14px;color:#374151}
    .chart-wrap{height:320px;padding:6px}
    table{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px}
    th,td{padding:8px;border-bottom:1px solid #f0f2f6;text-align:left}
    .actions{display:flex;gap:8px}
    .tips{color:#374151;font-size:13px}
    .error{background:#fff2f2;padding:10px;border-radius:8px;color:#7f1d1d}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="wrap">
    <header>
      <h1>Forecast — Simple & Clear</h1>
      <div class="actions">
        <a href="forecast.php" style="text-decoration:none"><button style="background:#e6eefc;color:#2563eb">Refresh</button></a>
        <button onclick="document.getElementById('downloadForm').submit();">Download CSV</button>
      </div>
    </header>

    <div class="card">
      <form method="get" id="controlForm" class="controls" onsubmit="return onSubmitForm()">
        <div>
          <label class="muted">Item ID</label><br>
          <input type="number" name="item_id" id="item_id" value="<?php echo htmlspecialchars($item_id); ?>" min="1">
        </div>
        <div>
          <label class="muted">Periods (days)</label><br>
          <input type="number" name="periods" id="periods" value="<?php echo htmlspecialchars($periods); ?>" min="1">
        </div>
        <div>
          <label class="muted">Window (days)</label><br>
          <input type="number" name="window" id="window" value="<?php echo htmlspecialchars($window); ?>" min="1">
        </div>
        <div style="margin-left:auto">
          <button type="submit">Get Forecast</button>
        </div>
      </form>

      <?php if ($error): ?>
        <div class="error" style="margin-top:10px"><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if ($summary_text): ?>
        <div class="summary">
          <?php echo $summary_text; ?>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
        <div style="flex:1" class="card">
          <div class="chart-wrap">
            <canvas id="forecastChart"></canvas>
          </div>
          <?php if ($ai_response && isset($ai_response['forecast'])): ?>
            <table>
              <thead><tr><th>Date</th><th>Predicted</th></tr></thead>
              <tbody>
                <?php foreach ($ai_response['forecast'] as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['ds']); ?></td>
                    <td><?php echo htmlspecialchars($row['predicted']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="muted" style="padding:12px">No forecast loaded yet. Enter item id and click <strong>Get Forecast</strong>.</div>
          <?php endif; ?>
        </div>

        <div style="width:320px">
          <div class="card">
            <h3 style="margin-top:0">What this AI is doing (simple)</h3>
            <p class="explain"><?php echo $explain_short; ?></p>
            <h4 style="margin-bottom:6px">Quick interpretation</h4>
            <p class="muted" style="font-size:14px">
              <?php
                if ($summary_text) {
                  echo "In short: expect about <strong>" . round($avg ?? 0,3) . "</strong> units per day for the next <strong>{$periods}</strong> days.";
                } else {
                  echo "Run a forecast to see the plain-language summary here.";
                }
              ?>
            </p>
            <hr>
            <h4 style="margin-bottom:6px">Tips (easy)</h4>
            <ul class="tips">
              <?php foreach ($tips as $t): ?><li><?php echo $t; ?></li><?php endforeach; ?>
            </ul>
          </div>

          <div class="card" style="margin-top:10px">
            <h4 style="margin-top:0">Raw info</h4>
            <p class="muted">AI endpoint: <code><?php echo $AI_URL; ?></code></p>
            <p class="muted">Generated at: <strong><?php echo htmlspecialchars($ai_response['generated_at'] ?? '-'); ?></strong></p>
            <form id="downloadForm" method="get">
              <input type="hidden" name="download" value="1">
              <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item_id); ?>">
              <input type="hidden" name="periods" value="<?php echo htmlspecialchars($periods); ?>">
              <input type="hidden" name="window" value="<?php echo htmlspecialchars($window); ?>">
            </form>
          </div>
        </div>
      </div>

    </div>

    <footer style="color:#6b7280;font-size:13px;margin-top:8px">If chart is empty, start the AI service: <code>cd C:\xampp\htdocs\medical_inventory\ai_service &amp;&amp; .\venv\Scripts\activate &amp;&amp; python app.py</code>.</footer>
  </div>

  <script>
    function onSubmitForm(){
      const iid = document.getElementById('item_id').value;
      if (!iid || iid < 1) { alert('Enter a valid item id'); return false; }
      return true;
    }

    const aiResponse = <?php echo json_encode($ai_response ?? null); ?>;
    const ctx = document.getElementById('forecastChart')?.getContext('2d');
    if (aiResponse && aiResponse.forecast && ctx) {
      const labels = aiResponse.forecast.map(r => r.ds);
      const data = aiResponse.forecast.map(r => Number(r.predicted));
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Predicted',
            data: data,
            fill: true,
            tension: 0.2,
            backgroundColor: 'rgba(37,99,235,0.08)',
            borderColor: 'rgba(37,99,235,1)',
            pointRadius: 4
          }]
        },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
      });
    }
  </script>
</body>
</html>
