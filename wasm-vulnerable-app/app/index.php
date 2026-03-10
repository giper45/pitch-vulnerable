<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';
require_once __DIR__ . '/challenge/admin_service_logic.php';

pv_session_boot();

function pv_preview_fetch($url) {
    $parts = parse_url($url);

    if (is_array($parts)) {
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');

        if (($host === '127.0.0.1' || $host === 'localhost') && $path === '/admin_service.php') {
            $queryParams = [];
            parse_str((string)($parts['query'] ?? ''), $queryParams);
            $action = (string)($queryParams['action'] ?? 'status');

            [$statusCode, $payload] = pv_admin_service_response($action, '127.0.0.1', true);
            $payload['simulated_status'] = $statusCode;
            $payload['ssrf_via'] = 'internal_preview';

            return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    return @file_get_contents($url);
}

$previewUrl = '';
$previewOutput = '';
$previewError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_url'])) {
    $previewUrl = trim((string)($_POST['preview_url'] ?? ''));

    if ($previewUrl !== '') {
        // INTENTIONAL VULNERABILITY: user controlled URL fetch (SSRF)
        $fetched = pv_preview_fetch($previewUrl);

        if ($fetched === false) {
            $previewError = 'Preview unavailable. URL is unreachable.';
        } else {
            $previewOutput = substr($fetched, 0, 1500);
        }
    } else {
        $previewError = 'Enter a valid URL for preview.';
    }
}

pv_render_header('Pitch-Vulnerable Home');
?>
<section class="jv-hero">
    <img src="/assets/images/pitch-hero.svg" alt="Stylized football arena illustration">
    <div class="jv-hero-overlay">
        <h2>Pitch Security Arena</h2>
        <p>Newsroom, player bios, and red-team style exercises for offensive security training.</p>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 class="jv-panel-title">Latest News</h3>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                <div class="col">
                    <article class="card jv-news-card h-100">
                        <img class="card-img-top jv-cover" src="/assets/images/striker-card.svg" alt="Striker role card">
                        <div class="card-body">
                            <h5 class="card-title mb-1">No. 9 Tactical Report</h5>
                            <p class="card-text text-secondary mb-2">Advanced finishing drills and secure vault access discussion.</p>
                            <a class="btn btn-outline-light btn-sm mt-auto" href="/index.php?page=striker.php">Open Biography</a>
                        </div>
                    </article>
                </div>

                <div class="col">
                    <article class="card jv-news-card h-100">
                        <img class="card-img-top jv-cover" src="/assets/images/winger-card.svg" alt="Winger role card">
                        <div class="card-body">
                            <h5 class="card-title mb-1">Wing Acceleration Notes</h5>
                            <p class="card-text text-secondary mb-2">How to break lines and how attackers break weak input handling.</p>
                            <a class="btn btn-outline-light btn-sm mt-auto" href="/index.php?page=winger.php">Open Biography</a>
                        </div>
                    </article>
                </div>

                <div class="col">
                    <article class="card jv-news-card h-100">
                        <img class="card-img-top jv-cover jv-logo-cover" src="/assets/images/playmaker-card.svg" alt="Playmaker role card">
                        <div class="card-body">
                            <h5 class="card-title mb-1">Training Lab</h5>
                            <p class="card-text text-secondary mb-2">Explore weak routes and recover hidden data from the virtual filesystem.</p>
                            <a class="btn btn-outline-light btn-sm mt-auto" href="/index.php?page=playmaker.php">Open Biography</a>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 class="jv-panel-title">External Link Preview</h3>
            <?php if (pv_debug_enabled()): ?>
                <p class="jv-muted mb-2">Debug tool for red-team exercises: enter a URL and inspect remote content.</p>
            <?php else: ?>
                <p class="jv-muted mb-2">Enter a URL and preview remote content.</p>
            <?php endif; ?>
            <?php if (pv_debug_enabled()): ?>
                <p class="mb-3">CTF hint: try <span class="inline-code">http://127.0.0.1:9999/admin_service.php</span></p>
            <?php endif; ?>

            <?php if ($previewError !== ''): ?>
                <div class="notice bad"><?php echo pv_escape($previewError); ?></div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-2">
                <input class="form-control" type="text" name="preview_url" value="<?php echo pv_escape($previewUrl); ?>" placeholder="http://example.com or http://127.0.0.1:9999/admin_service.php">
                <button class="btn btn-outline-light" type="submit">Load Preview</button>
            </form>

            <?php if ($previewOutput !== ''): ?>
                <div class="jv-card p-3 mt-3">
                    <strong class="d-block mb-2">Remote Response:</strong>
                    <pre><?php echo pv_escape($previewOutput); ?></pre>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<section class="jv-card p-3 p-md-4">
    <h3 class="jv-panel-title">Player Cards + Biography</h3>
    <!-- <p>Card links use the parameter <span class="inline-code">?page=</span>. This part is intentionally fragile.</p> -->

    <?php
    $page = (string)($_GET['page'] ?? '');
    if ($page === '') {
        echo '<div class="notice warn">Select a player card. </div>';
    } else {
        $includeTarget = __DIR__ . '/biographies/' . $page;
        if (pv_debug_enabled()) {
            echo '<p class="mb-2">Loading biography from: <span class="inline-code">' . pv_escape($includeTarget) . '</span></p>';
        }

        // INTENTIONAL VULNERABILITY: user-controlled include
        @include $includeTarget;
    }
    ?>
</section>
<?php pv_render_footer(); ?>
