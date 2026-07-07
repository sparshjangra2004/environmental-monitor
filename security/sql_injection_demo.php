<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';

requireAdmin();

$normalInput   = "admin@ecomonitor.com";
$injectedInput = "' OR '1'='1' --";
$dropInput     = "'; DROP TABLE users; --";

$vulnerable_normal   = "SELECT * FROM users WHERE email = '$normalInput'";
$vulnerable_injected = "SELECT * FROM users WHERE email = '$injectedInput'";
$vulnerable_drop     = "SELECT * FROM users WHERE email = '$dropInput'";

$conn = getDBConnection();
$result_normal    = null;
$result_injected  = null;

$stmt = $conn->prepare("SELECT id, name, email, status FROM users WHERE email = ?");
$stmt->bind_param("s", $normalInput);
$stmt->execute();
$result_normal = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT id, name, email, status FROM users WHERE email = ?");
$stmt->bind_param("s", $injectedInput);
$stmt->execute();
$result_injected = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$pageTitle = 'SQL Injection Demo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">SQL Injection Demo</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 5 — shows exactly why prepared statements prevent SQL injection attacks</p>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-info-circle"></i> What is SQL Injection?</h5>
        </div>
        <div class="card-body">
            <p>SQL Injection is when an attacker puts SQL syntax into a form field,
            hoping the server concatenates it into a query — giving them control over the database.</p>
            <p style="margin:0;">
                A classic attack: entering <code>' OR '1'='1' --</code> as an email turns a login query
                into one that always returns true, bypassing authentication entirely.
                The <code>--</code> comments out the rest of the query (including the password check).
            </p>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header" style="background:rgba(220,53,69,0.08);">
            <h5 style="margin:0; color:var(--color-danger);">
                <i class="bi bi-bug"></i> Attack 1 — Authentication Bypass
                <small style="font-size:12px; font-weight:400; margin-left:8px;">input: <code>' OR '1'='1' --</code></small>
            </h5>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

<div>
                    <p style="font-size:12px; font-weight:700; color:var(--color-danger); text-transform:uppercase; letter-spacing:0.5px;">
                        VULNERABLE — String Concatenation
                    </p>
                    <pre style="background:#fff0f0; border:1px solid rgba(220,53,69,0.3); border-radius:8px; padding:16px; font-size:12px; white-space:pre-wrap; word-break:break-all;"><?php

echo htmlspecialchars("// PHP code:\n");
                        echo htmlspecialchars('$email = $_POST[\'email\']; // raw, unsanitized');
                        echo "\n";
                        echo htmlspecialchars('$sql = "SELECT * FROM users WHERE email = \'" . $email . "\'"');
                        echo "\n";
                        echo htmlspecialchars('$conn->query($sql); // executes injected SQL!');
                        echo "\n\n";
                        echo htmlspecialchars("// Generated SQL:\n");
                        echo htmlspecialchars($vulnerable_injected);
                    ?></pre>
                    <div class="alert alert-danger" style="font-size:13px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Result: BYPASSED.</strong>
                        The condition <code>'1'='1'</code> is always true, so this returns every user in the database.
                        The attacker logs in as the first user (usually admin). Password check is gone.
                    </div>
                </div>

<div>
                    <p style="font-size:12px; font-weight:700; color:var(--color-primary); text-transform:uppercase; letter-spacing:0.5px;">
                        PROTECTED — Prepared Statement
                    </p>
                    <pre style="background:rgba(25,135,84,0.05); border:1px solid rgba(25,135,84,0.3); border-radius:8px; padding:16px; font-size:12px; white-space:pre-wrap; word-break:break-all;"><?php
                        echo htmlspecialchars("// PHP code:\n");
                        echo htmlspecialchars('$email = $_POST[\'email\']; // same raw input');
                        echo "\n";
                        echo htmlspecialchars('$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");');
                        echo "\n";
                        echo htmlspecialchars('$stmt->bind_param("s", $email); // ? is NEVER treated as SQL');
                        echo "\n";
                        echo htmlspecialchars('$stmt->execute();');
                        echo "\n\n";
                        echo htmlspecialchars("// What the DB actually searches for:\n");
                        echo htmlspecialchars("// email = '" . $injectedInput . "'\n");
                        echo htmlspecialchars("// (the entire string is treated as a literal value)");
                    ?></pre>
                    <div class="alert alert-success" style="font-size:13px;">
                        <i class="bi bi-shield-check"></i>
                        <strong>Result: BLOCKED.</strong>
                        The entire attack string is treated as a literal email address.
                        No user has that email, so zero rows returned. Attack completely neutralised.
                        <br><br>
                        <strong>Live test — rows found:</strong>
                        <?php echo $result_injected ? 'User found (unexpected!)' : '<span style="color:var(--color-primary);">0 rows — attack blocked ✓</span>'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header" style="background:rgba(220,53,69,0.08);">
            <h5 style="margin:0; color:var(--color-danger);">
                <i class="bi bi-trash"></i> Attack 2 — DROP TABLE Destructive Query
                <small style="font-size:12px; font-weight:400; margin-left:8px;">input: <code>'; DROP TABLE users; --</code></small>
            </h5>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
                <div>
                    <p style="font-size:12px; font-weight:700; color:var(--color-danger); text-transform:uppercase; letter-spacing:0.5px;">
                        VULNERABLE — What SQL would be generated
                    </p>
                    <pre style="background:#fff0f0; border:1px solid rgba(220,53,69,0.3); border-radius:8px; padding:16px; font-size:12px; white-space:pre-wrap; word-break:break-all;"><?php
                        echo htmlspecialchars($vulnerable_drop);
                        echo "\n\n// The ; ends the SELECT and starts a new DROP TABLE command\n// -- comments out the trailing quote\n// Result: users table DELETED";
                    ?></pre>
                    <div class="alert alert-danger" style="font-size:13px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Result: TABLE DESTROYED.</strong>
                        The semicolon ends the first query. The database then executes <code>DROP TABLE users</code> as a second command.
                        All user accounts permanently deleted. Application broken.
                    </div>
                </div>
                <div>
                    <p style="font-size:12px; font-weight:700; color:var(--color-primary); text-transform:uppercase; letter-spacing:0.5px;">
                        PROTECTED — Prepared Statement
                    </p>
                    <pre style="background:rgba(25,135,84,0.05); border:1px solid rgba(25,135,84,0.3); border-radius:8px; padding:16px; font-size:12px; white-space:pre-wrap; word-break:break-all;"><?php
                        echo htmlspecialchars("// The entire string is passed as a bound parameter\n");
                        echo htmlspecialchars("// MySQLi prepared statements do NOT support multi-query\n");
                        echo htmlspecialchars("// via bind_param — the ; is just another character\n\n");
                        echo htmlspecialchars("// Effective search: email = \"'; DROP TABLE users; --\"\n");
                        echo htmlspecialchars("// No user has that email. 0 rows. Nothing dropped.");
                    ?></pre>
                    <div class="alert alert-success" style="font-size:13px;">
                        <i class="bi bi-shield-check"></i>
                        <strong>Result: BLOCKED.</strong>
                        Prepared statements separate the SQL structure from the data.
                        The database driver never sees the DROP TABLE as a command — just as part of a string value.
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header" style="background:rgba(25,135,84,0.08);">
            <h5 style="margin:0; color:var(--color-primary);">
                <i class="bi bi-check-circle"></i> Normal Query — Prepared Statement Works Fine
                <small style="font-size:12px; font-weight:400; margin-left:8px;">input: <code>admin@ecomonitor.com</code></small>
            </h5>
        </div>
        <div class="card-body">
            <p>A normal email works exactly as expected — the prepared statement finds the correct user:</p>
            <?php if ($result_normal): ?>
                <table class="table">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($result_normal['id']); ?></td>
                            <td><?php echo htmlspecialchars($result_normal['name']); ?></td>
                            <td><?php echo htmlspecialchars($result_normal['email']); ?></td>
                            <td><span class="badge badge-success"><?php echo htmlspecialchars($result_normal['status']); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle"></i>
                    No user found with email <code>admin@ecomonitor.com</code>. Make sure the admin account exists.
                </div>
            <?php endif; ?>
        </div>
    </div>

<div class="card">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-lightbulb"></i> The Key Principle</h5>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
                <div>
                    <p style="font-weight:600; color:var(--color-danger);">Never do this:</p>
                    <pre style="background:#fff0f0; border-radius:6px; padding:12px; font-size:13px;"><?php
                        echo htmlspecialchars('$sql = "WHERE email = \'" . $_POST[\'email\'] . "\'"');
                    ?></pre>
                    <p class="text-muted" style="font-size:13px;">User input is treated as part of the SQL command structure.</p>
                </div>
                <div>
                    <p style="font-weight:600; color:var(--color-primary);">Always do this:</p>
                    <pre style="background:rgba(25,135,84,0.05); border-radius:6px; padding:12px; font-size:13px;"><?php
                        echo htmlspecialchars('$stmt = $conn->prepare("WHERE email = ?");');
                        echo "\n";
                        echo htmlspecialchars('$stmt->bind_param("s", $_POST[\'email\']);');
                    ?></pre>
                    <p class="text-muted" style="font-size:13px;">The <code>?</code> placeholder is always data — never SQL.</p>
                </div>
            </div>
            <div style="margin-top:16px; padding:12px 16px; background:var(--color-bg); border-radius:8px; border:1px solid var(--color-border);">
                <strong>Every query in EcoMonitor uses prepared statements.</strong>
                You can verify this by searching for <code>$conn->prepare(</code> and <code>bind_param(</code>
                in every PHP file — there is no string concatenation of user input anywhere.
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
