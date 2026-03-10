<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';

pv_session_boot();
$pdo = pv_db();

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim((string)($_POST['author'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($author !== '' && $message !== '') {
        $stmt = $pdo->prepare('INSERT INTO guestbook (author, message, created_at) VALUES (:author, :message, datetime(\'now\'))');
        $stmt->execute([
            ':author' => $author,
            ':message' => $message,
        ]);
        $notice = 'Message saved in the Hall of Fame.';
    }
}

$entries = $pdo->query('SELECT id, author, message, created_at FROM guestbook ORDER BY id DESC LIMIT 60')->fetchAll();

pv_render_header('Hall of Fame');
?>
<div class="row g-4">
    <div class="col-12 col-lg-5">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 id="campione-title" class="jv-panel-title">Blackfield Champion</h3>
            <p class="jv-muted">Historic guestbook from the Blackfield supporters network. Messages are published in real time.</p>

            <?php if ($notice !== ''): ?>
                <div class="notice ok"><?php echo pv_escape($notice); ?></div>
            <?php endif; ?>

            <form method="post" class="d-grid gap-2">
                <label for="author" class="form-label mb-0">Name</label>
                <input id="author" class="form-control" type="text" name="author" value="<?php echo pv_escape($_POST['author'] ?? ''); ?>" placeholder="Club member nickname">

                <label for="message" class="form-label mb-0 mt-1">Message</label>
                <textarea id="message" class="form-control" name="message" rows="6" placeholder="Write your Hall of Fame message..."><?php echo pv_escape($_POST['message'] ?? ''); ?></textarea>

                <button class="btn btn-outline-light mt-2" type="submit">Post Message</button>
            </form>
        </section>
    </div>

    <div class="col-12 col-lg-7">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 class="jv-panel-title">Winners Wall</h3>
            <div class="d-grid gap-2">
                <?php foreach ($entries as $entry): ?>
                    <article class="guest-entry">
                        <div class="guest-meta">
                            #<?php echo (int)$entry['id']; ?> - <?php echo pv_escape($entry['author']); ?> - <?php echo pv_escape($entry['created_at']); ?>
                        </div>
                        <div>
                            <?php
                            // INTENTIONAL VULNERABILITY: stored XSS output without HTML encoding
                            echo $entry['message'];
                            ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php pv_render_footer(); ?>
