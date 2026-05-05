<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use DigitalInvoice\InvoiceReader;
use DigitalInvoice\InvoiceRenderer;

$rendered  = null;
$error     = null;
$filename  = null;
$renderer  = new InvoiceRenderer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['invoice'])) {
    $file = $_FILES['invoice'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error (code ' . (int) $file['error'] . ').';
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = 'File too large (max 10 MB).';
    } else {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['text/xml', 'application/xml', 'application/pdf', 'text/plain'], true)) {
            $error = 'Unsupported file type (expected XML or PDF).';
        } else {
            $filename = htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8');
            $content  = file_get_contents($file['tmp_name']);
            if ($content === false || $content === '') {
                $error = 'Could not read the uploaded file.';
            } else {
                try {
                    $data     = InvoiceReader::read($content);
                    $rendered = $renderer->render($data);
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
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
/* ── Viewer chrome ── */
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
.drop-zone label { display: block; cursor: pointer; font-size: 13px; line-height: 1.6; }
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

.formats { font-size: 11px; color: #aaa; text-align: center; }
.current-file { font-size: 12px; color: #666; word-break: break-all; }

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

</style>
</head>
<body>

<header>Invoice Viewer</header>

<div class="layout">

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
    <p class="current-file">&#128196; <?= $filename ?></p>
    <?php endif; ?>
  </aside>

  <main class="panel-preview">
    <?php if ($error): ?>
      <div class="alert-error">
        <strong>Could not read invoice</strong>
        <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
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
