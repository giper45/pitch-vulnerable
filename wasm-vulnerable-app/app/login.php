<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';

pv_session_boot();
$pdo = pv_db();

$error = '';
$info = '';
$lastQuery = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string)($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // INTENTIONAL VULNERABILITY: direct SQL concatenation
    $lastQuery = "SELECT id, username, role FROM users WHERE username = '" . $username . "' AND password = '" . $password . "' LIMIT 1";

    try {
        $row = $pdo->query($lastQuery)->fetch();

        if ($row) {
            $_SESSION['pv_user'] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'role' => $row['role'],
            ];
            $info = 'Logged in as ' . $row['username'] . ' (' . $row['role'] . ')';
        } else {
            $error = 'Invalid credentials.';
        }
    } catch (Throwable $e) {
        $error = 'SQL error: ' . $e->getMessage();
    }
}

$user = pv_current_user();
pv_render_header('Club Member Login');
?>
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 class="jv-panel-title">Club Member Area</h3>
            <p class="jv-muted">Sign in to access restricted first-team profiles.</p>

            <?php if ($error !== ''): ?>
                <div class="notice bad"><?php echo pv_escape($error); ?></div>
            <?php endif; ?>

            <?php if ($info !== ''): ?>
                <div class="notice ok"><?php echo pv_escape($info); ?></div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-2">
                <label for="username" class="form-label mb-0">Username</label>
                <input id="username" class="form-control" type="text" name="username" value="<?php echo pv_escape($_POST['username'] ?? ''); ?>" placeholder="admin">

                <label for="password" class="form-label mb-0 mt-1">Password</label>
                <input id="password" class="form-control" type="password" name="password" value="<?php echo pv_escape($_POST['password'] ?? ''); ?>" placeholder="********">

                <button class="btn btn-outline-light mt-2" type="submit">Login</button>
            </form>

            <?php if (pv_debug_enabled() && $lastQuery !== ''): ?>
                <p class="mt-3 mb-0">Debug query: <span class="inline-code"><?php echo pv_escape($lastQuery); ?></span></p>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($user): ?>
        <div class="col-12 col-lg-6">
            <section class="jv-card p-3 p-md-4 h-100">
                <h3 class="jv-panel-title">Restricted Dashboard</h3>
                <p>Active user: <span class="inline-code"><?php echo pv_escape($user['username']); ?></span> (<?php echo pv_escape($user['role']); ?>)</p>

                <?php
                if (($user['role'] ?? '') === 'admin') {
                    $rows = $pdo->query('SELECT username, password, role, secret_hint FROM users ORDER BY id ASC')->fetchAll();
                    echo '<div class="notice warn">Admin view: full users table dump available.</div>';
                    echo '<div class="table-responsive"><table class="table table-dark table-striped table-hover align-middle">';
                    echo '<thead><tr><th>Username</th><th>Password</th><th>Role</th><th>Secret Hint</th></tr></thead><tbody>';
                    foreach ($rows as $row) {
                        echo '<tr>';
                        echo '<td>' . pv_escape($row['username']) . '</td>';
                        echo '<td>' . pv_escape($row['password']) . '</td>';
                        echo '<td>' . pv_escape($row['role']) . '</td>';
                        echo '<td>' . pv_escape($row['secret_hint']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="notice warn">Limited permissions: only admin can view the full user dump.</div>';
                }
                ?>
            </section>
        </div>
    <?php endif; ?>
</div>
<?php pv_render_footer(); ?>
