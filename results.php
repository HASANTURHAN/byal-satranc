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

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <span class="text-3xl">♜</span>
            <h2 class="text-3xl font-bold text-gray-900">Mac Sonuclari</h2>
        </div>
        <p class="text-sm text-gray-500">Oynanan maclarin sonuclarini girin ve duzenleyin.</p>
    </div>
    <div class="flex gap-2">
        <a href="matchmaking.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 rounded-xl hover:bg-gray-50 transition shadow-sm">
            &larr; Eslestirmeler
        </a>
        <a href="standings.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-gray-800 to-gray-900 text-white text-sm font-semibold rounded-xl hover:from-gray-900 hover:to-black transition shadow-lg">
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
    <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200">
        <div class="flex items-center gap-3">
            <span class="text-2xl">♝</span>
            <h3 class="font-bold text-gray-900 text-lg"><?php echo $view_round; ?>. Tur Maclari</h3>
        </div>
    </div>

    <?php if (empty($matches)): ?>
    <div class="text-center py-12">
        <span class="text-4xl mb-3 block opacity-20">♟</span>
        <p class="text-sm text-gray-500">Bu tura ait mac bulunamadi.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-100">
        <?php foreach ($matches as $match): ?>
        <div class="px-5 py-5 <?php echo $match['status'] === 'completed' ? 'bg-gradient-to-r from-green-50/40 to-emerald-50/20' : 'bg-white'; ?> hover:bg-gray-50/70 transition">
            <div class="flex items-center">
                <!-- Oyuncu 1 -->
                <div class="flex-1 text-right pr-4">
                    <div class="flex items-center justify-end gap-2">
                        <div>
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($match['result'] === '1-0'): ?>
                                    <span class="text-green-600 font-bold text-base">♔</span>
                                <?php endif; ?>
                                <span class="text-sm font-semibold <?php echo $match['result'] === '1-0' ? 'text-green-700 font-bold' : 'text-gray-900'; ?>">
                                    <?php echo htmlspecialchars($match['player1_name']); ?>
                                </span>
                            </div>
                            <span class="text-xs text-gray-400 block mt-0.5"><?php echo htmlspecialchars($match['p1_class'] ?? ''); ?></span>
                        </div>
                        <div class="w-7 h-7 rounded border-2 border-gray-300 bg-white flex-shrink-0 hidden sm:block shadow-sm"></div>
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

                            <div class="flex gap-1.5">
                                <button type="submit" name="result" value="1-0" title="Beyaz Kazandi"
                                        class="px-3 py-2 text-xs font-bold rounded-lg border-2 transition-all transform hover:scale-105 shadow-sm
                                        <?php echo $match['result'] === '1-0' ? 'bg-gradient-to-r from-gray-800 to-gray-900 text-white border-gray-900 shadow-md' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 hover:border-gray-400'; ?>">
                                    <?php echo $match['result'] === '1-0' ? '♔' : ''; ?> 1-0
                                </button>
                                <button type="submit" name="result" value="0.5-0.5" title="Berabere"
                                        class="px-3 py-2 text-xs font-bold rounded-lg border-2 transition-all transform hover:scale-105 shadow-sm
                                        <?php echo $match['result'] === '0.5-0.5' ? 'bg-gradient-to-r from-amber-400 to-yellow-400 text-amber-900 border-amber-500 shadow-md' : 'bg-white text-gray-600 border-gray-300 hover:bg-amber-50 hover:border-amber-300'; ?>">
                                    ½-½
                                </button>
                                <button type="submit" name="result" value="0-1" title="Siyah Kazandi"
                                        class="px-3 py-2 text-xs font-bold rounded-lg border-2 transition-all transform hover:scale-105 shadow-sm
                                        <?php echo $match['result'] === '0-1' ? 'bg-gradient-to-r from-gray-800 to-gray-900 text-white border-gray-900 shadow-md' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 hover:border-gray-400'; ?>">
                                    0-1 <?php echo $match['result'] === '0-1' ? '♚' : ''; ?>
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
                <div class="flex-1 pl-4">
                    <?php if (empty($match['player2_id'])): ?>
                        <span class="text-sm text-gray-400 italic">Rakip Yok (Bay)</span>
                    <?php else: ?>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded bg-gray-800 border-2 border-gray-700 flex-shrink-0 hidden sm:block shadow-sm"></div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold <?php echo $match['result'] === '0-1' ? 'text-green-700 font-bold' : 'text-gray-900'; ?>">
                                    <?php echo htmlspecialchars($match['player2_name']); ?>
                                </span>
                                <?php if ($match['result'] === '0-1'): ?>
                                    <span class="text-green-600 font-bold text-base">♚</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-400 block mt-0.5"><?php echo htmlspecialchars($match['p2_class'] ?? ''); ?></span>
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
