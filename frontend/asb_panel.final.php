<?php
/**
 * asb_panel.php — Redirect-safe same-origin proxy to Google Apps Script (GAS).
 * This version manually follows 30x redirects (needed on some IIS/PHP hosts where CURLOPT_FOLLOWLOCATION is disabled).
 * - Keeps POST + body across 301/302/307/308 (switches to GET for 303 per RFC).
 * - Handles CORS preflight (OPTIONS).
 * - Works on IIS (getallheaders polyfill).
 * - Includes /health and a tiny tester UI.
 */

define('GAS_WEBAPP_URL', 'https://script.google.com/macros/s/AKfycbyCDhAe3iUCn9zD7RgB_AdKnoEzAx6x3InQVrwaCuFAAtg7CIAEDfW77IoXHKhuCcxy_A/exec');

ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* Polyfill getallheaders for IIS/CLI */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE']))   $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        return $headers;
    }
}

/* CORS preflight */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

/* Health check */
if (isset($_GET['action']) && $_GET['action'] === 'health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'asb_panel (redirect-safe) is reachable',
        'php' => PHP_VERSION,
        'curl' => function_exists('curl_version') ? curl_version()['version'] : null,
    ]);
    exit;
}

/* ---- Redirect-safe cURL ---- */
function http_request_follow($url, $method, $headersAssoc, $body = null, $maxRedirects = 5) {
    $currentUrl = $url;
    $currentMethod = strtoupper($method);
    $currentBody = $body;

    // Normalize headers to array of "Key: Value"
    $headers = [];
    foreach ($headersAssoc as $k => $v) {
        $lk = strtolower($k);
        if ($lk === 'host' || $lk === 'content-length') continue;
        $headers[] = $k . ': ' . $v;
    }

    for ($i = 0; $i <= $maxRedirects; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $currentUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $currentMethod);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // so we can parse headers for Location
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, ""); // auto-decompress gzip/deflate
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);

        if ($currentMethod !== 'GET' && $currentBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $currentBody);
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return [502, 'Proxy request failed: ' . $err, []];
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($resp, 0, $headerSize);
        $bodyOut = substr($resp, $headerSize);
        curl_close($ch);

        // Parse headers into associative array (last header set wins)
        $respHeaders = [];
        foreach (preg_split("/\r?\n/", $rawHeaders) as $line) {
            if (stripos($line, 'HTTP/') === 0) continue;
            if (strpos($line, ':') !== false) {
                list($k, $v) = explode(':', $line, 2);
                $respHeaders[trim($k)] = trim($v);
            }
        }

        // Handle redirects manually
        if (in_array($status, [301, 302, 303, 307, 308]) && isset($respHeaders['Location']) && $i < $maxRedirects) {
            $loc = $respHeaders['Location'];
            // Resolve relative URLs
            if (strpos($loc, 'http://') !== 0 && strpos($loc, 'https://') !== 0) {
                // Build absolute from currentUrl
                $p = parse_url($currentUrl);
                $scheme = $p['scheme'] ?? 'https';
                $host = $p['host'] ?? '';
                $port = isset($p['port']) ? ':' . $p['port'] : '';
                $base = $scheme . '://' . $host . $port;
                if (isset($loc[0]) && $loc[0] !== '/') {
                    // relative path - append to dirname of current path
                    $path = isset($p['path']) ? $p['path'] : '/';
                    $dir = substr($path, 0, strrpos($path, '/') + 1);
                    $loc = $base . $dir . $loc;
                } else {
                    $loc = $base . $loc;
                }
            }
            $currentUrl = $loc;
            // For 303, switch to GET (as per RFC). For others, keep method & body.
            if ($status == 303) {
                $currentMethod = 'GET';
                $currentBody = null;
            }
            continue; // next loop follows redirect
        }

        // Not a redirect or max redirects reached: return final
        return [$status, $bodyOut, $respHeaders];
    }

    // Too many redirects
    return [508, 'Too many redirects', []];
}

/* GAS proxy endpoint */
if (isset($_GET['action']) && $_GET['action'] === 'gas_proxy') {
    header('Access-Control-Allow-Origin: *');

    if (!function_exists('curl_init')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'PHP cURL extension is not enabled.']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $headersIn = getallheaders();
    $body = null;
    if ($method !== 'GET') {
        $body = file_get_contents('php://input');
    }

    list($status, $respBody, $respHeaders) = http_request_follow(GAS_WEBAPP_URL, $method, $headersIn, $body, 5);

    if ($status) http_response_code($status);

    // Infer JSON content
    $isJson = false;
    $trim = ltrim((string)$respBody);
    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) $isJson = true;
    header('Content-Type: ' . ($isJson ? 'application/json' : 'text/plain') . '; charset=utf-8');

    echo $respBody;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ASB Panel (Redirect-safe)</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background:#0b1020; color:#e8ecf1; margin:0; }
    .wrap { max-width: 900px; margin: 40px auto; padding: 24px; background: rgba(255,255,255,0.04); border-radius: 16px; border:1px solid rgba(255,255,255,0.08); }
    h1 { margin-top:0; font-weight:700; letter-spacing:.5px; }
    label { display:block; margin:.5rem 0 .25rem; }
    input, button {
      width: 100%; padding:.7rem .9rem; border-radius: 10px; border:1px solid rgba(255,255,255,0.15);
      background: rgba(255,255,255,0.06); color:#fff; outline:none;
    }
    button { cursor:pointer; border:1px solid rgba(255,255,255,0.2); }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .muted { color:#a7b0c0; font-size:.9rem; }
    .card { padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); }
    .log { white-space:pre-wrap; background: #081028; padding:12px; border-radius:8px; min-height: 64px; border:1px dashed rgba(255,255,255,0.15); }
    .ok { color:#4be077; }
    .err { color:#ff6b6b; }
    .cta { display:flex; gap:8px; }
    .cta button { width:auto; padding:.7rem 1.1rem; }
    .grid { display:grid; grid-template-columns: 1fr; gap:16px; }
    @media (min-width: 720px) { .grid { grid-template-columns: 1fr 1fr; } }
    a { color:#87b7ff; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>ASB Panel (Redirect-safe)</h1>
    <p class="muted">Frontend should call <code>asb_panel.php?action=gas_proxy</code>. This build manually follows 30x and keeps POST across redirects.</p>

    <div class="grid">
      <div class="card">
        <h3>Update / Delete</h3>
        <div class="row">
          <div>
            <label>Student ID</label>
            <input id="sid" placeholder="e.g., 12345" />
          </div>
          <div>
            <label>Position</label>
            <input id="pos" placeholder="e.g., gb" />
          </div>
        </div>
        <div class="cta" style="margin-top:12px;">
          <button id="btnUpdate">Send Update</button>
          <button id="btnDelete" style="background:#402222;">Delete Student</button>
        </div>
      </div>

      <div class="card">
        <h3>Diagnostics</h3>
        <div class="cta">
          <button id="btnHealth">Proxy Health</button>
          <button id="btnPingGet">Direct GAS GET</button>
          <button id="btnPingPost">Direct GAS POST</button>
        </div>
        <p class="muted" style="margin-top:10px;">
          Ensure Apps Script is deployed as Web App (<b>Execute as: Me</b>, <b>Access: Anyone</b>), and sheet ID/tab are correct.
        </p>
      </div>
    </div>

    <div class="card" style="margin-top:16px;">
      <h3>Output</h3>
      <div id="out" class="log"></div>
    </div>
  </div>

<script>
const proxyUrl = 'asb_panel.php?action=gas_proxy';
const out = document.getElementById('out');
function log(msg, cls='') {
  const el = document.createElement('div');
  el.className = cls;
  el.textContent = typeof msg === 'string' ? msg : JSON.stringify(msg, null, 2);
  out.prepend(el);
}
async function callProxy(payload) {
  const res = await fetch(proxyUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const text = await res.text();
  let data; try { data = JSON.parse(text); } catch { data = null; }
  return { status: res.status, data, raw: text };
}
document.getElementById('btnUpdate').addEventListener('click', async () => {
  const studentId = document.getElementById('sid').value.trim();
  const position  = document.getElementById('pos').value.trim();
  if (!studentId || !position) return log('Please enter both Student ID and Position', 'err');
  log('Sending update...', 'muted');
  try {
    const { status, data, raw } = await callProxy({ action: 'update', studentId, position });
    const ok = status >= 200 && status < 300 && (data?.success === true || /"success"\s*:\s*true/i.test(raw));
    log({ status, data: data ?? raw }, ok ? 'ok' : 'err');
  } catch (e) { log('Network error: ' + e.message, 'err'); }
});
document.getElementById('btnDelete').addEventListener('click', async () => {
  const studentId = document.getElementById('sid').value.trim();
  if (!studentId) return log('Enter Student ID to delete', 'err');
  log('Sending delete...', 'muted');
  try {
    const { status, data, raw } = await callProxy({ action: 'delete', studentId });
    const ok = status >= 200 && status < 300 && (data?.success === true || /"success"\s*:\s*true/i.test(raw));
    log({ status, data: data ?? raw }, ok ? 'ok' : 'err');
  } catch (e) { log('Network error: ' + e.message, 'err'); }
});
// Diagnostics
document.getElementById('btnHealth').addEventListener('click', async () => {
  try {
    const res = await fetch('asb_panel.php?action=health');
    const js = await res.json();
    log(js, 'ok');
  } catch (e) {
    log('Health check failed: ' + e.message, 'err');
  }
});
document.getElementById('btnPingGet').addEventListener('click', async () => {
  try {
    const res = await fetch('<?php echo GAS_WEBAPP_URL; ?>', { method: 'GET' });
    const text = await res.text();
    log({ status: res.status, body: text.slice(0, 1500) + (text.length>1500?'…':'') }, res.ok ? 'ok' : 'err');
  } catch (e) { log('Direct GET to GAS failed: ' + e.message, 'err'); }
});
document.getElementById('btnPingPost').addEventListener('click', async () => {
  try {
    const res = await fetch('<?php echo GAS_WEBAPP_URL; ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', studentId: 'TEST', position: 'gb' })
    });
    const text = await res.text();
    log({ status: res.status, body: text.slice(0, 1500) + (text.length>1500?'…':'') }, res.ok ? 'ok' : 'err');
  } catch (e) {
    log('Direct POST to GAS failed (likely CORS in browser): ' + e.message, 'err');
  }
});
</script>
</body>
</html>
