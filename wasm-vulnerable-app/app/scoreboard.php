<?php
define('PV_APP', true);
require_once __DIR__ . '/lib/pv_core.php';
require_once __DIR__ . '/challenge/score_engine.php';

pv_session_boot();
pv_init_scoreboard_state();

$flags = pv_load_flags();
$flash = '';
$flashClass = '';
$questIds = ['quest_1', 'quest_2', 'quest_3', 'quest_4', 'quest_5'];

$teamThemes = [
    'Coastline Blue' => ['primary' => '#2f98ea', 'secondary' => '#0f2f4d', 'accent' => '#9fd8ff'],
    'Hilltown Green' => ['primary' => '#1f8d3e', 'secondary' => '#102217', 'accent' => '#b7f0c7'],
    'Midnight Red' => ['primary' => '#b1091a', 'secondary' => '#141414', 'accent' => '#ff5d6e'],
    'Capital Amber' => ['primary' => '#9a6720', 'secondary' => '#26180c', 'accent' => '#f0c36b'],
    'River Sky' => ['primary' => '#79b8e8', 'secondary' => '#13263c', 'accent' => '#cbe8ff'],
];

$countSolved = function () use ($questIds) {
    $count = 0;
    foreach ($questIds as $questId) {
        if (pv_is_solved($questId)) {
            $count++;
        }
    }
    return $count;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uiAction = (string)($_POST['ui_action'] ?? '');
    $questId = (string)($_POST['quest_id'] ?? '');
    $rawAnswer = (string)($_POST['answer'] ?? '');
    $answer = trim($rawAnswer);
    $xssProbe = (string)($_POST['xss_probe'] ?? '');
    $solvedBefore = $countSolved();

    if ($uiAction === 'pick_team') {
        if ($solvedBefore < count($questIds)) {
            $flash = 'Finish all quests before choosing the league winner.';
            $flashClass = 'bad';
        } else {
            $pickedTeam = (string)($_POST['champion_team'] ?? '');
            if (isset($teamThemes[$pickedTeam])) {
                $_SESSION['pv_scoreboard']['serie_a_pick'] = $pickedTeam;
                pv_log_activity('League winner pick updated: ' . $pickedTeam);
                $flash = 'Theme updated for ' . $pickedTeam . '.';
                $flashClass = 'ok';
            } else {
                $flash = 'Select a valid club theme.';
                $flashClass = 'bad';
            }
        }
    } else {
        $valid = false;

        if ($questId === 'quest_1') {
            $valid = hash_equals((string)$flags['quest_1'], $answer);
        } elseif ($questId === 'quest_2') {
            $expectedPasswd = pv_expected_passwd_content();
            $valid = $expectedPasswd !== '' && hash_equals($expectedPasswd, pv_normalize_passwd_blob($rawAnswer));
        } elseif ($questId === 'quest_3') {
            $valid = $xssProbe === 'verified';
        } elseif ($questId === 'quest_4') {
            $valid = hash_equals((string)$flags['quest_4'], $answer);
        } elseif ($questId === 'quest_5') {
            $expectedRceFlag = pv_expected_rce_flag();
            $valid = $expectedRceFlag !== '' && hash_equals($expectedRceFlag, $answer);
        }

        if ($valid && $questId !== '') {
            pv_mark_solved($questId);
            pv_log_activity('Quest solved: ' . $questId . ' with answer token ' . substr($answer, 0, 16));
            $flash = 'Quest completed: ' . $questId;
            $flashClass = 'ok';
        } elseif ($questId !== '') {
            pv_log_activity('Quest failed attempt: ' . $questId);
            $flash = 'Wrong flag or unmet condition.';
            $flashClass = 'bad';
        }
    }
}

$quests = [
    [
        'id' => 'quest_1',
        'difficulty' => 'Easy',
        'title' => 'Ghost Pass',
        'description' => 'Enter the locker room as admin and recover the striker secret from the roster table.',
        'placeholder' => 'striker password',
    ],
    [
        'id' => 'quest_2',
        'difficulty' => 'Medium',
        'title' => 'Backdoor Biography',
        'description' => 'Find the vulnerability to copy the full /etc/passwd content.',
        'placeholder' => "root:root:0:0:root:/root:/bin/bash\n...",
    ],
    [
        'id' => 'quest_3',
        'difficulty' => 'Medium',
        'title' => 'Derby Repaint',
        'description' => 'Remove every Blackfield word from the Hall of Fame.',
        'placeholder' => '',
    ],
    [
        'id' => 'quest_4',
        'difficulty' => 'Hard',
        'title' => 'Inside Line',
        'description' => 'Find the internal admin service and retrieve the internal_service.flag.',
        'placeholder' => 'SSRF{...}',
    ],
    [
        'id' => 'quest_5',
        'difficulty' => 'Hard',
        'title' => 'Ping Breakout',
        'description' => 'Find the hidden ping console, break out of the command, and read the secret flag file under the uploads directory.',
        'placeholder' => 'RCE{...}',
    ],
];

$solvedCount = $countSolved();
$allSolved = $solvedCount === count($questIds);
$selectedTeam = (string)($_SESSION['pv_scoreboard']['serie_a_pick'] ?? '');
if (!isset($teamThemes[$selectedTeam])) {
    $selectedTeam = '';
}

pv_render_header('Scoreboard');
?>
<?php if ($selectedTeam !== ''): ?>
    <?php
    $theme = $teamThemes[$selectedTeam];
    ?>
    <style>
        :root {
            --jv-bg: <?php echo $theme['secondary']; ?>;
            --jv-bg-soft: <?php echo $theme['primary']; ?>;
            --jv-surface: rgba(8, 9, 14, 0.84);
            --jv-border: rgba(255, 255, 255, 0.24);
            --jv-text: #f7f8fb;
            --jv-muted: #d7deea;
        }
        body.jv-page {
            background:
                radial-gradient(circle at 10% 18%, <?php echo $theme['primary']; ?>aa, transparent 36%),
                radial-gradient(circle at 88% 13%, <?php echo $theme['accent']; ?>88, transparent 32%),
                linear-gradient(126deg, <?php echo $theme['secondary']; ?> 0%, <?php echo $theme['primary']; ?> 55%, #0e1118 100%);
        }
        .champion-banner {
            border-color: <?php echo $theme['accent']; ?>99;
            box-shadow: 0 18px 28px rgba(0, 0, 0, 0.36), inset 0 0 0 1px <?php echo $theme['accent']; ?>55;
        }
    </style>
<?php endif; ?>
<div class="row g-4">
    <div class="col-12">
        <section class="jv-card p-3 p-md-4">
            <h3 class="jv-panel-title">Scoreboard - Mission Briefing</h3>
            <p class="jv-muted">Complete 5 offensive security challenges in a fictional football-club environment.</p>
            <div class="notice warn">Progress: <strong><?php echo $solvedCount; ?>/<?php echo count($quests); ?></strong> quests solved.</div>

            <?php if ($flash !== ''): ?>
                <div class="notice <?php echo pv_escape($flashClass); ?>"><?php echo pv_escape($flash); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Difficulty</th>
                            <th>Quest</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submit Flag</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quests as $quest): ?>
                        <tr>
                            <td><?php echo pv_escape($quest['id']); ?></td>
                            <td><?php echo pv_escape($quest['difficulty']); ?></td>
                            <td><?php echo pv_escape($quest['title']); ?></td>
                            <td><?php echo pv_escape($quest['description']); ?></td>
                            <td>
                                <?php if (pv_is_solved($quest['id'])): ?>
                                    <span class="badge solved rounded-pill">Solved</span>
                                <?php else: ?>
                                    <span class="badge unsolved rounded-pill">Unsolved</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="d-grid gap-2">
                                    <input type="hidden" name="quest_id" value="<?php echo pv_escape($quest['id']); ?>">
                                    <input type="hidden" name="xss_probe" class="xss-probe-field" value="">
                                    <?php if ($quest['id'] === 'quest_2'): ?>
                                        <textarea class="form-control form-control-sm" name="answer" rows="5" placeholder="<?php echo pv_escape($quest['placeholder']); ?>"></textarea>
                                    <?php elseif ($quest['id'] === 'quest_3'): ?>
                                        <input type="hidden" name="answer" value="">
                                        <div class="small text-secondary">No text flag required. Run the XSS probe and submit when status is verified.</div>
                                        <div id="xss-probe-status" class="small text-info">Probe status: idle</div>
                                        <button id="run-xss-probe" class="btn btn-outline-info btn-sm" type="button">Run XSS Probe</button>
                                    <?php else: ?>
                                        <input class="form-control form-control-sm" type="text" name="answer" placeholder="<?php echo pv_escape($quest['placeholder']); ?>">
                                    <?php endif; ?>
                                    <button class="btn btn-outline-light btn-sm" type="submit">Validate</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

 

    <!-- <div class="col-12 col-lg-5">
        <section class="jv-card p-3 p-md-4 h-100">
            <h3 class="jv-panel-title">Attack Log</h3>
            <div class="attack-log">

                <?php /* foreach (pv_attack_feed() as $line): */ ?>
                    <?php /* echo pv_escape($line); */ ?><br>
                <?php /*endforeach; */?>
            </div>
        </section>
    </div> -->
</div>

<?php if ($allSolved): ?>
    <div class="row g-4 mt-1">
        <div class="col-12">
            <section class="jv-card p-3 p-md-4">
                <h3 class="jv-panel-title">League Champion Selector</h3>
                <p class="jv-muted">All flags captured. Pick a fictional league winner and recolor the whole page theme.</p>

                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="ui_action" value="pick_team">
                    <div class="col-12 col-md-8 col-xl-6">
                        <label class="form-label mb-1" for="champion_team">Choose team</label>
                        <select class="form-select" name="champion_team" id="champion_team">
                            <option value="">-- Select a team --</option>
                            <?php foreach ($teamThemes as $teamName => $teamTheme): ?>
                                <option value="<?php echo pv_escape($teamName); ?>"<?php echo $selectedTeam === $teamName ? ' selected' : ''; ?>>
                                    <?php echo pv_escape($teamName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-outline-light" type="submit">Apply Team Theme</button>
                    </div>
                </form>

                <?php if ($selectedTeam !== ''): ?>
                    <div class="champion-banner mt-4">
                        the next winner of the league is <?php echo pv_escape($selectedTeam); ?>
                        <?php if ($selectedTeam === 'Hilltown Green'): ?>
                            <div class="champion-banner-note">From the hills to the spotlight: the underdog story is still alive.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>

<iframe id="xss-probe-frame" class="hidden-frame" title="xss probe"></iframe>

<script>
(function () {
    var statusEl = document.getElementById('xss-probe-status');
    var probeBtn = document.getElementById('run-xss-probe');
    var frame = document.getElementById('xss-probe-frame');
    var probeFields = document.querySelectorAll('.xss-probe-field');

    if (!frame || probeFields.length === 0) {
        return;
    }

    function setProbeState(value, message) {
        for (var i = 0; i < probeFields.length; i++) {
            probeFields[i].value = value;
        }
        if (statusEl) {
            statusEl.textContent = message;
        }
    }

    function runProbe() {
        setProbeState('', 'Probe status: running...');
        frame.src = '/winners.php?probe=' + Date.now();
    }

    frame.addEventListener('load', function () {
        setTimeout(function () {
            var success = false;

            try {
                var doc = frame.contentDocument || frame.contentWindow.document;
                var fullText = '';
                if (doc && doc.body) {
                    fullText = String(doc.body.textContent || '');
                }

                var containsBlackfield = /blackfield/i.test(fullText);
                if (!containsBlackfield) {
                    success = true;
                }
            } catch (e) {
                success = false;
            }

            if (success) {
                setProbeState('verified', 'Probe status: verified (no Blackfield tokens detected)');
            } else {
                setProbeState('', 'Probe status: failed (Blackfield still present)');
            }
        }, 550);
    });

    if (probeBtn) {
        probeBtn.addEventListener('click', runProbe);
    }
    runProbe();
})();
</script>
<?php pv_render_footer(); ?>
