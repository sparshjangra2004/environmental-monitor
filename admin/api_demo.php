<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/sanitize.php';

requireAdmin();

$pageTitle = 'API Demo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">JWT API Demo</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 3 — demonstrates the full JWT authentication + API access flow</p>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-info-circle"></i> How JWT Works</h5>
        </div>
        <div class="card-body">
            <div class="grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px;">
                    <div style="font-size:24px; margin-bottom:8px;">1️⃣</div>
                    <strong>Authenticate</strong>
                    <p class="text-muted" style="font-size:13px; margin:6px 0 0;">
                        POST email + password to <code>/api/auth.php</code>.
                        Server verifies credentials and returns a signed JWT token.
                    </p>
                </div>
                <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px;">
                    <div style="font-size:24px; margin-bottom:8px;">2️⃣</div>
                    <strong>Token Structure</strong>
                    <p class="text-muted" style="font-size:13px; margin:6px 0 0;">
                        JWT = <code>header.payload.signature</code> (base64url encoded, separated by dots).
                        The payload holds user ID, role, issued-at, and expiry time.
                    </p>
                </div>
                <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px;">
                    <div style="font-size:24px; margin-bottom:8px;">3️⃣</div>
                    <strong>Access Protected API</strong>
                    <p class="text-muted" style="font-size:13px; margin:6px 0 0;">
                        Send token in the <code>Authorization: Bearer &lt;token&gt;</code> header.
                        Server verifies signature + expiry before returning data.
                    </p>
                </div>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-key"></i> Step 1 — Get JWT Token</h5>
        </div>
        <div class="card-body">
            <form id="authForm">
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end;">
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="authEmail"
                               value="admin@ecomonitor.com" placeholder="Email address">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="authPassword"
                               value="admin123" placeholder="Password">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Get Token
                        </button>
                    </div>
                </div>
            </form>

            <div id="authResult" style="display:none; margin-top:16px;">
                <label class="form-label" style="font-size:12px; font-weight:600;">Response from POST /api/auth.php:</label>
                <pre id="authJson" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px; font-size:12px; overflow-x:auto; white-space:pre-wrap;"></pre>
            </div>

            <div id="tokenBox" style="display:none; margin-top:12px;">
                <label class="form-label" style="font-size:12px; font-weight:600;">Extracted JWT Token:</label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" class="form-control" id="tokenValue"
                           style="font-size:11px; font-family:monospace;" readonly>
                    <button class="btn btn-outline" onclick="copyToken()" title="Copy token">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <p class="text-muted" style="font-size:11px; margin-top:6px;">
                    <i class="bi bi-clock"></i> Token expires in 1 hour (3600 seconds)
                </p>
            </div>
        </div>
    </div>

<div class="card mb-4" id="decoderCard" style="display:none;">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-braces"></i> Token Decoder — What's Inside the JWT?</h5>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div>
                    <p style="font-size:12px; font-weight:600; color:var(--color-primary);">Header (algorithm + type)</p>
                    <pre id="jwtHeader" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:12px; font-size:11px; white-space:pre-wrap;"></pre>
                </div>
                <div>
                    <p style="font-size:12px; font-weight:600; color:#f59e0b;">Payload (user claims)</p>
                    <pre id="jwtPayload" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:12px; font-size:11px; white-space:pre-wrap;"></pre>
                </div>
                <div>
                    <p style="font-size:12px; font-weight:600; color:var(--color-danger);">Signature (tamper-proof)</p>
                    <pre id="jwtSig" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:12px; font-size:11px; white-space:pre-wrap; word-break:break-all;"></pre>
                    <p class="text-muted" style="font-size:11px;">
                        HMAC-SHA256 of header+payload using the server's secret key.
                        If ANY character in the token changes, this verification fails.
                    </p>
                </div>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-database"></i> Step 2 — Fetch Readings from Protected API</h5>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end; margin-bottom:16px;">
                <div>
                    <label class="form-label">Limit (max 100)</label>
                    <input type="number" class="form-control" id="limitParam" value="5" min="1" max="100">
                </div>
                <div>
                    <label class="form-label">Location filter (optional)</label>
                    <input type="text" class="form-control" id="locationParam" placeholder="e.g. Lab A">
                </div>
                <div>
                    <button class="btn btn-primary" onclick="fetchReadings()">
                        <i class="bi bi-arrow-down-circle"></i> Fetch Readings
                    </button>
                </div>
            </div>

            <div id="curlExample" style="display:none; background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px; margin-bottom:16px;">
                <p style="font-size:12px; font-weight:600; margin:0 0 8px;">Equivalent cURL command:</p>
                <code id="curlCmd" style="font-size:11px; word-break:break-all;"></code>
            </div>

            <div id="readingsResult" style="display:none;">
                <label class="form-label" style="font-size:12px; font-weight:600;">Response from GET /api/readings.php:</label>
                <pre id="readingsJson" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px; font-size:12px; overflow-x:auto; white-space:pre-wrap; max-height:400px;"></pre>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-shield-x"></i> What Happens With an Invalid Token?</h5>
        </div>
        <div class="card-body">
            <button class="btn btn-outline" onclick="testInvalidToken()" style="margin-right:8px;">
                <i class="bi bi-x-circle"></i> Test Invalid Token
            </button>
            <button class="btn btn-outline" onclick="testNoToken()">
                <i class="bi bi-slash-circle"></i> Test Missing Token
            </button>
            <div id="invalidResult" style="display:none; margin-top:16px;">
                <pre id="invalidJson" style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:16px; font-size:12px; white-space:pre-wrap;"></pre>
            </div>
        </div>
    </div>

</div>

<script>
let jwtToken = '';

document.getElementById('authForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const email    = document.getElementById('authEmail').value;
    const password = document.getElementById('authPassword').value;

    const response = await fetch('/environmental_monitor/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });

    const data = await response.json();
    document.getElementById('authResult').style.display = 'block';
    document.getElementById('authJson').textContent = JSON.stringify(data, null, 2);

    if (data.token) {
        jwtToken = data.token;
        document.getElementById('tokenBox').style.display = 'block';
        document.getElementById('tokenValue').value = jwtToken;
        decodeToken(jwtToken);
    }
});

function decodeToken(token) {
    const parts = token.split('.');
    if (parts.length !== 3) return;

    function b64decode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        return JSON.parse(atob(str));
    }

    try {
        document.getElementById('jwtHeader').textContent  = JSON.stringify(b64decode(parts[0]), null, 2);
        document.getElementById('jwtPayload').textContent = JSON.stringify(b64decode(parts[1]), null, 2);
        document.getElementById('jwtSig').textContent     = parts[2];
        document.getElementById('decoderCard').style.display = 'block';
    } catch(e) {
        console.error('Could not decode JWT', e);
    }
}

async function fetchReadings() {
    if (!jwtToken) {
        alert('Get a JWT token first (Step 1).');
        return;
    }

    const limit    = document.getElementById('limitParam').value;
    const location = document.getElementById('locationParam').value;
    let   url      = `/environmental_monitor/api/readings.php?limit=${limit}`;
    if (location) url += `&location=${encodeURIComponent(location)}`;

    document.getElementById('curlExample').style.display = 'block';
    document.getElementById('curlCmd').textContent =
        `curl -H "Authorization: Bearer ${jwtToken.substring(0,20)}..." "${url}"`;

    const response = await fetch(url, {
        headers: { 'Authorization': 'Bearer ' + jwtToken }
    });

    const data = await response.json();
    document.getElementById('readingsResult').style.display = 'block';
    document.getElementById('readingsJson').textContent = JSON.stringify(data, null, 2);
}

async function testInvalidToken() {
    const response = await fetch('/environmental_monitor/api/readings.php', {
        headers: { 'Authorization': 'Bearer this.is.not.valid' }
    });
    const data = await response.json();
    document.getElementById('invalidResult').style.display = 'block';
    document.getElementById('invalidJson').textContent =
        `HTTP ${response.status}\n\n` + JSON.stringify(data, null, 2);
}

async function testNoToken() {
    const response = await fetch('/environmental_monitor/api/readings.php');
    const data = await response.json();
    document.getElementById('invalidResult').style.display = 'block';
    document.getElementById('invalidJson').textContent =
        `HTTP ${response.status}\n\n` + JSON.stringify(data, null, 2);
}

function copyToken() {
    navigator.clipboard.writeText(jwtToken).then(() => alert('Token copied to clipboard!'));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
