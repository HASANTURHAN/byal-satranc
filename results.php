<?php
// results.php - Maç Sonuçlarını Girme (SQL Injection düzeltildi)
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!is_admin()) { header("Location: login.php"); exit(); }

$message = '';
$messageType = '';

$current_round = (int)$pdo->query("SELECT COALESCE(MAX(round_number), 0) FROM matches")->fetchColumn();

// SONUÇ KAYDETME
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'save_result' && verify_csrf()) {
    $match_id = (int)($_POST['match_id'] ?? 0);
    $result = $_POST['result'] ?? '';

    if ($match_id > 0 && in_array($result, ['1-0', '0-1', '0.5-0.5'])) {
        try {
            $pdo->beginTransaction();

            $matchStmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
            $matchStmt->execute([$match_id]);
            $match = $matchStmt->fetch();

            if ($match) {
                $p1_id = (int)$match['player1_id'];
                $p2_id = (int)$match['player2_id'];
                $old_status = $match['status'];
                $old_result = $match['result'];

                // Eski sonucu geri al (prepared statements ile - SQL injection düzeltildi)
                if ($old_status === 'completed' && !empty($old_result)) {
                    if ($old_result === '1-0') {
                        $pdo->prepare("UPDATE players SET points = points - 1, wins = wins - 1 WHERE id = ?")->execute([$p1_id]);
                        $pdo->prepare("UPDATE players SET losses = losses - 1 WHERE id = ?")->execute([$p2_id]);
                    } elseif ($old_result === '0-1') {
                        $pdo->prepare("UPDATE players SET losses = losses - 1 WHERE id = ?")->execute([$p1_id]);
                        $pdo->prepare("UPDATE players SET points = points - 1, wins = wins - 1 WHERE id = ?")->execute([$p2_id]);
                    } elseif ($old_result === '0.5-0.5') {
                        $pdo->prepare("UPDATE players SET points = points - 0.5, draws = draws - 1 WHERE id = ?")->execute([$p1_id]);
                        $pdo->prepare("UPDATE players SET points = points - 0.5, draws = draws - 1 WHERE id = ?")->execute([$p2_id]);
                    }
                } else {
                    // İlk kez sonuç giriliyorsa matches_played artır
                    $pdo->prepare("UPDATE players SET matches_played = matches_played + 1 WHERE id = ?")->execute([$p1_id]);
                    $pdo->prepare("UPDATE players SET matches_played = matches_played + 1 WHERE id = ?")->execute([$p2_id]);
                }

                // Yeni sonucu uygula
                if ($result === '1-0') {
                    $pdo->prepare("UPDATE players SET points = points + 1, wins = wins + 1 WHERE id = ?")->execute([$p1_id]);
                    $pdo->prepare("UPDATE players SET losses = losses + 1 WHERE id = ?")->execute([$p2_id]);
                } elseif ($result === '0-1') {
                    $pdo->prepare("UPDATE players SET losses = losses + 1 WHERE id = ?")->execute([$p1_id]);
                    $pdo->prepare("UPDATE players SET points = points + 1, wins = wins + 1 WHERE id = ?")->execute([$p2_id]);
                } elseif ($result === '0.5-0.5') {
                    $pdo->prepare("UPDATE players SET points = points + 0.5, draws = draws + 1 WHERE id = ?")->execute([$p1_id]);
                    $pdo->prepare("UPDATE players SET points = points + 0.5, draws = draws + 1 WHERE id = ?")->execute([$p2_id]);
                }

                $updateStmt = $pdo->prepare("UPDATE matches SET result = ?, status = 'completed', played_at = datetime('now') WHERE id = ?");
                $updateStmt->execute([$result, $match_id]);

                $pdo->commit();
                $message = "Sonuc kaydedildi.";
                $messageType = 'success';
            } else {
                $pdo->rollBack();
                $message = "Mac bulunamadi.";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Hata: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Görüntülenecek tur
$view_round = isset($_GET['round']) ? (int)$_GET['round'] : $current_round;
$all_rounds = $pdo->query("SELECT DISTINCT round_number FROM matches ORDER BY round_number DESC")->fetchAll(PDO::FETCH_COLUMN);

$matches = [];
if ($view_round > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*, p1.name as player1_name, p1.class_name as p1_class,
               p2.name as player2_name, p2.class_name as p2_class
        FROM matches m
        LEFT JOIN players p1 ON m.player1_id = p1.id
        LEFT JOIN players p2 ON m.player2_id = p2.id
        WHERE m.round_number = ?
        ORDER BY m.status ASC, m.id ASC
    ");
    $stmt->execute([$view_round]);
    $matches = $stmt->fetchAll();
}

include 'header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Mac Sonuclari</h2>
        <p class="text-sm text-gray-500 mt-1">Oynanan maclarin sonuclarini girin.</p>
    </div>
    <div class="flex gap-2">
        <a href="matchmaking.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 rounded-xl hover:bg-gray-50 transition">
            &larr; Eslestirmeler
        </a>
        <a href="standings.php" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition">
            Puan Durumu &rarr;
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="mb-6 rounded-xl p-4 flex items-start gap-3 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
    <span class="text-sm font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo $message; ?></span>
</div>
<?php endif; ?>

<?php if (empty($all_rounds)): ?>
<div class="card p-12 text-center">
    <div class="text-4xl mb-4">&#9812;</div>
    <h3 class="text-base font-semibold text-gray-900 mb-2">Henuz mac yok</h3>
    <p class="text-sm text-gray-500 mb-4">Eslestirme Motorundan tur baslatin.</p>
    <a href="matchmaking.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">Eslestirme Yap</a>
</div>
<?php else: ?>

<!-- Tur Seçici -->
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($all_rounds as $round): ?>
    <a href="results.php?round=<?php echo $round; ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?php echo $view_round == $round ? 'bg-gray-900 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'; ?>">
        <?php echo $round; ?>. Tur
    </a>
    <?php endforeach; ?>
</div>

<!-- Maç Listesi -->
<div class="card overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900"><?php echo $view_round; ?>. Tur Maclari</h3>
    </div>

    <?php if (empty($matches)): ?>
    <div class="text-center py-12">
        <p class="text-sm text-gray-500">Bu tura ait mac bulunamadi.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
        <?php foreach ($matches as $match): ?>
        <div class="px-5 py-4 <?php echo $match['status'] === 'completed' ? 'bg-green-50/30' : ''; ?> hover:bg-gray-50/50 transition">
            <div class="flex items-center">
                <!-- Oyuncu 1 -->
                <div class="flex-1 text-right pr-3">
                    <div class="flex items-center justify-end gap-2">
                        <div>
                            <span class="text-sm font-semibold <?php echo $match['result'] === '1-0' ? 'text-green-700' : 'text-gray-900'; ?>">
                                <?php echo htmlspecialchars($match['player1_name']); ?>
                            </span>
                            <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['p1_class'] ?? ''); ?></span>
                        </div>
                        <div class="w-6 h-6 rounded bg-white border-2 border-gray-200 flex-shrink-0 hidden sm:block"></div>
                    </div>
                </div>

                <!-- Sonuç Butonları -->
                <div class="px-3 flex-shrink-0">
                    <?php if (empty($match['player2_id'])): ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-green-100 text-green-700">BAY (1-0)</span>
                    <?php else: ?>
                        <form action="results.php?round=<?php echo $view_round; ?>" method="POST" class="flex flex-col items-center gap-1.5">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_result">
                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">

                            <div class="flex gap-1">
                                <button type="submit" name="result" value="1-0" title="Beyaz Kazandi"
                                        class="px-3 py-1.5 text-xs font-bold rounded-lg border transition
                                        <?php echo $match['result'] === '1-0' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">
                                    1-0
                                </button>
                                <button type="submit" name="result" value="0.5-0.5" title="Berabere"
                                        class="px-3 py-1.5 text-xs font-bold rounded-lg border transition
                                        <?php echo $match['result'] === '0.5-0.5' ? 'bg-amber-400 text-amber-900 border-amber-500' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">
                                    ½-½
                                </button>
                                <button type="submit" name="result" value="0-1" title="Siyah Kazandi"
                                        class="px-3 py-1.5 text-xs font-bold rounded-lg border transition
                                        <?php echo $match['result'] === '0-1' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">
                                    0-1
                                </button>
                            </div>

                            <?php if ($match['status'] === 'completed'): ?>
                            <span class="text-[10px] font-medium text-green-600 flex items-center">
                                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Kaydedildi
                            </span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Oyuncu 2 -->
                <div class="flex-1 pl-3">
                    <?php if (empty($match['player2_id'])): ?>
                        <span class="text-sm text-gray-400 italic">Rakip Yok</span>
                    <?php else: ?>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded bg-gray-800 border-2 border-gray-700 flex-shrink-0 hidden sm:block"></div>
                        <div>
                            <span class="text-sm font-semibold <?php echo $match['result'] === '0-1' ? 'text-green-700' : 'text-gray-900'; ?>">
                                <?php echo htmlspecialchars($match['player2_name']); ?>
                            </span>
                            <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['p2_class'] ?? ''); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
