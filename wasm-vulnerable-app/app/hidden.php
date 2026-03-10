<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';

pv_session_boot();
$user = pv_current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

$target = (string)($_POST['target'] ?? '8.8.8.8');
$command = '';
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = trim((string)($_POST['target'] ?? ''));
    if ($target === '') {
        $target = '8.8.8.8';
    }

    // INTENTIONAL VULNERABILITY: authenticated command injection via target parameter.
    $command = 'ping -c 1 ' . $target;
    $result = shell_exec($command);
    $output = is_string($result) ? $result : '';
}

pv_render_header('Ping The World');
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <section class="jv-card p-3 p-md-4">
            <h3 class="jv-panel-title">Ping The World</h3>
            <p class="jv-muted">Internal diagnostics tool for authenticated members. Enter a host and run a connectivity check.</p>
            <p class="jv-muted mb-3">Hint: some admins concatenate shell commands unsafely.</p>

            <form method="post" class="d-grid gap-2 mb-3">
                <label for="target" class="form-label mb-0">Target host</label>
                <input id="target" class="form-control" type="text" name="target" value="<?php echo pv_escape($target); ?>" placeholder="8.8.8.8">
                <button class="btn btn-outline-light mt-2" type="submit">Ping</button>
            </form>

            <?php if ($command !== ''): ?>
                <p class="mb-2">Executed command: <span class="inline-code"><?php echo pv_escape($command); ?></span></p>
            <?php endif; ?>

            <?php if ($output !== ''): ?>
                <div class="jv-card p-3">
                    <strong class="d-block mb-2">Command Output</strong>
                    <pre><?php echo pv_escape($output); ?></pre>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php pv_render_footer(); ?>
