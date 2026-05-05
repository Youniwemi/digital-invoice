<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use DigitalInvoice\InvoiceReader;
use DigitalInvoice\InvoiceRenderer;

$rendered = null;
$error    = null;
$filename = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice'])) {
    $file = $_FILES['invoice'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . $file['error'] . ').';
    } else {
        $filename = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8');
        $content  = file_get_contents($file['tmp_name']);
        if ($content === false || $content === '') {
            $error = 'Could not read the uploaded file.';
        } else {
            try {
                $data     = InvoiceReader::read($content);
                $rendered = (new InvoiceRenderer())->render($data);
            } catch (\Throwable $e) {
                $error = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice Viewer</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: system-ui, sans-serif;
  font-size: 14px;
  background: #f4f5f7;
  color: #1a1a2e;
  height: 100vh;
  display: flex;
  flex-direction: column;
}

header {
  background: #1a1a2e;
  color: #fff;
  padding: 12px 24px;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: .03em;
}

.layout {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* ── Left panel ── */
.panel-upload {
  width: 280px;
  min-width: 220px;
  background: #fff;
  border-right: 1px solid #e0e0e8;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.panel-upload h2 {
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #666;
}

.drop-zone {
  border: 2px dashed #c0c4d0;
  border-radius: 8px;
  padding: 28px 16px;
  text-align: center;
  color: #888;
  cursor: pointer;
  transition: border-color .2s, background .2s;
}
.drop-zone:hover { border-color: #4f6ef7; background: #f0f3ff; }
.drop-zone input[type=file] { display: none; }
.drop-zone label {
  display: block;
  cursor: pointer;
  font-size: 13px;
  line-height: 1.6;
}
.drop-zone .icon { font-size: 28px; margin-bottom: 8px; display: block; }

.btn {
  display: block;
  width: 100%;
  padding: 10px;
  background: #4f6ef7;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
}
.btn:hover { background: #3a57e8; }

.formats {
  font-size: 11px;
  color: #aaa;
  text-align: center;
}

/* ── Right panel ── */
.panel-preview {
  flex: 1;
  overflow-y: auto;
  padding: 32px;
  background: #f4f5f7;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #bbb;
  gap: 12px;
}
.empty-state .icon { font-size: 48px; }

.alert-error {
  background: #fff0f0;
  border: 1px solid #f5c0c0;
  border-radius: 8px;
  padding: 16px 20px;
  color: #c0392b;
  font-size: 13px;
}
.alert-error strong { display: block; margin-bottom: 4px; }

/* ── Invoice styles ── */
.di-invoice {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
  padding: 32px 36px;
  max-width: 860px;
  margin: 0 auto;
}

.di-header {
  display: flex;
  gap: 32px;
  flex-wrap: wrap;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 2px solid #e8e8f0;
}
.di-header__meta { flex: 1; }
.di-invoice-id { display: block; font-size: 22px; font-weight: 700; margin-top: 4px; }
.di-header__dates, .di-header__misc { display: flex; flex-direction: column; gap: 6px; justify-content: center; }

.di-label {
  display: inline-block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #999;
  margin-right: 6px;
}

.di-notes {
  margin-bottom: 20px;
}
.di-note {
  background: #fffbe6;
  border-left: 3px solid #f5c518;
  padding: 8px 12px;
  font-size: 13px;
  color: #555;
  border-radius: 0 4px 4px 0;
  margin-bottom: 6px;
}

.di-parties {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
  margin-bottom: 28px;
}
.di-party {
  flex: 1;
  min-width: 200px;
  padding: 16px;
  background: #f9f9fc;
  border-radius: 8px;
}
.di-party__role {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #999;
  margin-bottom: 8px;
}
.di-party strong { display: block; font-size: 15px; margin-bottom: 4px; }
.di-party em { display: block; font-size: 12px; color: #777; margin-bottom: 6px; font-style: normal; }
address { font-style: normal; font-size: 13px; line-height: 1.6; color: #555; margin: 6px 0; }
.di-tax-reg { font-size: 12px; color: #777; margin-top: 4px; }
.di-buyer-ref { font-size: 12px; color: #777; margin-top: 6px; }
.di-contact { font-size: 12px; color: #777; margin-top: 6px; }

.di-items { margin-bottom: 24px; }
.di-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.di-table th {
  text-align: left;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #999;
  border-bottom: 2px solid #e8e8f0;
  padding: 8px 10px;
}
.di-table td {
  padding: 10px 10px;
  border-bottom: 1px solid #f0f0f5;
  vertical-align: top;
}
.di-table td small { display: block; color: #888; margin-top: 3px; font-size: 11px; }
.di-col--qty, .di-col--unit, .di-col--price, .di-col--tax, .di-col--total { text-align: right; white-space: nowrap; }

.di-totals {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  margin-bottom: 24px;
}
.di-total-row {
  display: flex;
  gap: 48px;
  font-size: 13px;
  color: #555;
}
.di-total-row span:last-child { min-width: 120px; text-align: right; font-variant-numeric: tabular-nums; }
.di-total-row--grand {
  font-size: 16px;
  font-weight: 700;
  color: #1a1a2e;
  padding-top: 8px;
  border-top: 2px solid #e8e8f0;
  margin-top: 4px;
}

.di-payment { font-size: 13px; color: #555; }
.di-payment h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #999; margin-bottom: 10px; }
.di-payment-mean { background: #f9f9fc; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; line-height: 1.8; }
.di-payment-terms { margin-top: 8px; color: #777; font-size: 12px; }
</style>
</head>
<body>

<header>Invoice Viewer</header>

<div class="layout">

  <!-- ── Upload panel ── -->
  <aside class="panel-upload">
    <h2>Upload invoice</h2>
    <form method="post" enctype="multipart/form-data" id="upload-form">
      <div class="drop-zone" onclick="document.getElementById('file-input').click()">
        <span class="icon">&#128196;</span>
        <label for="file-input">
          Click to select<br>or drop a file here
        </label>
        <input type="file" id="file-input" name="invoice" accept=".xml,.pdf"
               onchange="document.getElementById('upload-form').submit()">
      </div>
      <noscript><button type="submit" class="btn" style="margin-top:12px">View invoice</button></noscript>
    </form>
    <p class="formats">Supported: FacturX, ZUGFeRD, UBL (Peppol…)<br>Formats: XML · PDF</p>
    <?php if ($filename): ?>
    <p style="font-size:12px;color:#666;word-break:break-all;">&#128196; <?= $filename ?></p>
    <?php endif; ?>
  </aside>

  <!-- ── Preview panel ── -->
  <main class="panel-preview">
    <?php if ($error): ?>
      <div class="alert-error">
        <strong>Could not read invoice</strong>
        <?= $error ?>
      </div>

    <?php elseif ($rendered): ?>
      <?= $rendered ?>

    <?php else: ?>
      <div class="empty-state">
        <span class="icon">&#128203;</span>
        <span>Upload an invoice to preview it here</span>
      </div>
    <?php endif; ?>
  </main>

</div>

</body>
</html>
