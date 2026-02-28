<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!is_admin()) { header("Location: login.php"); exit(); }

$message = '';
$messageType = '';

// Tur bilgisi
$current_round = (int)$pdo->query("SELECT COALESCE(MAX(round_number), 0) FROM matches")->fetchColumn();
$next_round = $current_round + 1;

// Tamamlanmamış maç sayısı
$uncompleted_matches = 0;
if ($current_round > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE round_number = ? AND status = 'pending'");
    $stmt->execute([$current_round]);
    $uncompleted_matches = (int)$stmt->fetchColumn();
}

// Daha önce kimlerin eşleştiğini bul (rematch engelleme)
function getPreviousOpponents($pdo) {
    $opponents = [];
    $stmt = $pdo->query("SELECT player1_id, player2_id FROM matches WHERE player2_id IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $p1 = $row['player1_id'];
        $p2 = $row['player2_id'];
        $opponents[$p1][$p2] = true;
        $opponents[$p2][$p1] = true;
    }
    return $opponents;
}

// Bay geçmiş oyuncuları bul
function getByePlayers($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT player1_id FROM matches WHERE player2_id IS NULL");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// EŞLEŞTİRME OLUŞTUR
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'generate_matches' && verify_csrf()) {

    if ($current_round > 0 && $uncompleted_matches > 0) {
        $message = "Onceki turun tum maclarini tamamlayin!";
        $messageType = 'error';
    } else {
        $players = $pdo->query("SELECT * FROM players WHERE is_active = 1")->fetchAll();
        $playerCount = count($players);

        if ($playerCount < 2) {
            $message = "En az 2 oyuncu olmali.";
            $messageType = 'error';
        } else {
            $pairs = [];
            $byes = [];
            $previousOpponents = getPreviousOpponents($pdo);
            $byePlayers = getByePlayers($pdo);

            if ($next_round == 1) {
                // 1. TUR: Rastgele eşleştirme
                $pdo->exec("UPDATE settings SET setting_value = 'turnuva_basladi' WHERE setting_key = 'tournament_status'");
                shuffle($players);

                if ($playerCount % 2 != 0) {
                    $byes[] = array_pop($players);
                }

                for ($i = 0; $i < count($players); $i += 2) {
                    $pairs[] = [$players[$i], $players[$i + 1]];
                }
            } else {
                // İSVİÇRE SİSTEMİ: Puana göre sırala, daha önce oynamayanları eşleştir
                usort($players, function ($a, $b) {
                    if ($a['points'] != $b['points']) {
                        return $b['points'] <=> $a['points'];
                    }
                    return $b['wins'] <=> $a['wins'];
                });

                $unpaired = $players;

                // BAY seçimi: En düşük puanlı ve daha önce bay geçmemiş
                if (count($unpaired) % 2 != 0) {
                    $byeIndex = null;
                    for ($i = count($unpaired) - 1; $i >= 0; $i--) {
                        if (!in_array($unpaired[$i]['id'], $byePlayers)) {
                            $byeIndex = $i;
                            break;
                        }
                    }
                    if ($byeIndex === null) {
                        $byeIndex = count($unpaired) - 1;
                    }
                    $byes[] = $unpaired[$byeIndex];
                    array_splice($unpaired, $byeIndex, 1);
                }

                // Greedy eşleştirme: Daha önce oynamamış, puan yakın
                $paired = [];
                while (count($unpaired) >= 2) {
                    $p1 = array_shift($unpaired);
                    $bestMatch = null;
                    $bestIndex = null;

                    // Önce daha önce oynamadığı birini ara
                    foreach ($unpaired as $idx => $candidate) {
                        if (!isset($previousOpponents[$p1['id']][$candidate['id']])) {
                            $bestMatch = $candidate;
                            $bestIndex = $idx;
                            break;
                        }
                    }

                    // Bulamadıysa ilk kişiyi al
                    if ($bestMatch === null) {
                        $bestIndex = 0;
                        $bestMatch = $unpaired[0];
                    }

                    $pairs[] = [$p1, $bestMatch];
                    array_splice($unpaired, $bestIndex, 1);
                }
            }

            // Veritabanına kaydet
            try {
                $pdo->beginTransaction();

                $insertStmt = $pdo->prepare("INSERT INTO matches (round_number, player1_id, player2_id, status) VALUES (?, ?, ?, 'pending')");
                foreach ($pairs as $pair) {
                    $insertStmt->execute([$next_round, $pair[0]['id'], $pair[1]['id']]);
                }

                // BAY geçenler
                $byeStmt = $pdo->prepare("INSERT INTO matches (round_number, player1_id, player2_id, result, status) VALUES (?, ?, NULL, '1-0', 'completed')");
                $updateStmt = $pdo->prepare("UPDATE players SET points = points + 1, wins = wins + 1, matches_played = matches_played + 1 WHERE id = ?");
                foreach ($byes as $bye) {
                    $byeStmt->execute([$next_round, $bye['id']]);
                    $updateStmt->execute([$bye['id']]);
                }

                $pdo->commit();
                $message = $next_round . ". Tur eslesmeleri olusturuldu!";
                $messageType = 'success';
                $current_round = $next_round;
                $uncompleted_matches = count($pairs);

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Hata: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// MACI SIL (Tur iptali)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'delete_round' && verify_csrf()) {
    $del_round = (int)($_POST['round_number'] ?? 0);
    if ($del_round > 0 && $del_round === $current_round) {
        try {
            $pdo->beginTransaction();

            // Bu turdaki tamamlanmış maçların puanlarını geri al
            $roundMatches = $pdo->prepare("SELECT * FROM matches WHERE round_number = ?");
            $roundMatches->execute([$del_round]);
            while ($m = $roundMatches->fetch()) {
                if ($m['status'] === 'completed' && $m['result']) {
                    $p1 = $m['player1_id'];
                    $p2 = $m['player2_id'];

                    if ($p2 === null) {
                        // BAY
                        $pdo->prepare("UPDATE players SET points = points - 1, wins = wins - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p1]);
                    } else {
                        if ($m['result'] === '1-0') {
                            $pdo->prepare("UPDATE players SET points = points - 1, wins = wins - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p1]);
                            $pdo->prepare("UPDATE players SET losses = losses - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p2]);
                        } elseif ($m['result'] === '0-1') {
                            $pdo->prepare("UPDATE players SET losses = losses - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p1]);
                            $pdo->prepare("UPDATE players SET points = points - 1, wins = wins - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p2]);
                        } elseif ($m['result'] === '0.5-0.5') {
                            $pdo->prepare("UPDATE players SET points = points - 0.5, draws = draws - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p1]);
                            $pdo->prepare("UPDATE players SET points = points - 0.5, draws = draws - 1, matches_played = matches_played - 1 WHERE id = ?")->execute([$p2]);
                        }
                    }
                } elseif ($m['status'] === 'pending' && $m['player2_id'] !== null) {
                    // Pending maçlar: matches_played düşürme yok
                }
            }

            $pdo->prepare("DELETE FROM matches WHERE round_number = ?")->execute([$del_round]);
            $pdo->commit();

            $current_round = (int)$pdo->query("SELECT COALESCE(MAX(round_number), 0) FROM matches")->fetchColumn();
            $message = $del_round . ". Tur iptal edildi.";
            $messageType = 'success';

            if ($current_round === 0) {
                $pdo->exec("UPDATE settings SET setting_value = 'basvurular_acik' WHERE setting_key = 'tournament_status'");
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Hata: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Güncel tur maçları
$round_matches = [];
$next_round = $current_round + 1;
if ($current_round > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*, p1.name as player1_name, p1.class_name as p1_class,
               p2.name as player2_name, p2.class_name as p2_class
        FROM matches m
        LEFT JOIN players p1 ON m.player1_id = p1.id
        LEFT JOIN players p2 ON m.player2_id = p2.id
        WHERE m.round_number = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$current_round]);
    $round_matches = $stmt->fetchAll();

    // Uncompleted count yeniden hesapla
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE round_number = ? AND status = 'pending'");
    $stmt2->execute([$current_round]);
    $uncompleted_matches = (int)$stmt2->fetchColumn();
}

include 'header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Eslestirme Motoru</h2>
        <p class="text-sm text-gray-500 mt-1">Turlari baslatin ve otomatik kura cekimi yapin.</p>
    </div>
    <div class="flex gap-2">
        <a href="admin.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 rounded-xl hover:bg-gray-50 transition">
            &larr; Panel
        </a>
        <a href="results.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-xl hover:bg-green-700 transition shadow-sm">
            Sonuc Gir &rarr;
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="mb-6 rounded-xl p-4 flex items-start gap-3 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
    <span class="text-sm font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo $message; ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Eşleştirme Kartı -->
    <div class="card p-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-blue-100 flex items-center justify-center mx-auto mb-4">
            <span class="text-2xl font-bold text-blue-600"><?php echo $next_round; ?></span>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2"><?php echo $next_round; ?>. Turu Baslat</h3>
        <p class="text-sm text-gray-500 mb-6">
            <?php if ($next_round == 1): ?>
                Rastgele kura ile eslestirme yapilir.
            <?php else: ?>
                Isvicre Sistemi ile puani yakin oyuncular eslesir.
            <?php endif; ?>
        </p>

        <form action="matchmaking.php" method="POST"
              onsubmit="return confirm('<?php echo $next_round; ?>. Tur eslesmeleri olusturulsun mu?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="generate_matches">

            <?php if ($current_round > 0 && $uncompleted_matches > 0): ?>
                <button type="button" disabled class="w-full py-3 px-4 bg-gray-200 text-gray-500 rounded-xl text-sm font-medium cursor-not-allowed">
                    Onceki Tur Bitmedi
                </button>
                <p class="mt-2 text-xs text-amber-600"><?php echo $uncompleted_matches; ?> mac devam ediyor</p>
            <?php else: ?>
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition shadow-lg shadow-blue-600/25">
                    Eslestir ve Kura Cek
                </button>
            <?php endif; ?>
        </form>

        <?php if ($current_round > 0): ?>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <form action="matchmaking.php" method="POST"
                  onsubmit="return confirm('DIKKAT: <?php echo $current_round; ?>. tur silinecek! Bu islem geri alinamaz.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_round">
                <input type="hidden" name="round_number" value="<?php echo $current_round; ?>">
                <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">
                    <?php echo $current_round; ?>. Turu Iptal Et
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Maçlar -->
    <div class="md:col-span-2">
        <?php if ($current_round > 0 && !empty($round_matches)): ?>
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900"><?php echo $current_round; ?>. Tur Fiksturu</h3>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                    <?php echo $uncompleted_matches > 0 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'; ?>">
                    <?php echo $uncompleted_matches > 0 ? 'Devam Ediyor' : 'Tamamlandi'; ?>
                </span>
            </div>

            <div class="divide-y divide-gray-50">
                <?php foreach ($round_matches as $match): ?>
                <div class="px-5 py-3.5 flex items-center hover:bg-gray-50 transition">
                    <div class="flex-1 text-right pr-3">
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($match['player1_name']); ?></span>
                        <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['p1_class'] ?? ''); ?></span>
                    </div>
                    <div class="px-2 flex-shrink-0">
                        <?php if (empty($match['player2_id'])): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold bg-green-100 text-green-700">BAY</span>
                        <?php elseif ($match['status'] === 'completed'): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-900 text-white">
                                <?php echo $match['result'] === '0.5-0.5' ? '½-½' : htmlspecialchars($match['result']); ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold text-gray-400">VS</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 pl-3">
                        <span class="text-sm font-medium <?php echo empty($match['player2_id']) ? 'text-gray-400 italic' : 'text-gray-900'; ?>">
                            <?php echo empty($match['player2_id']) ? 'Bay' : htmlspecialchars($match['player2_name']); ?>
                        </span>
                        <?php if (!empty($match['player2_id'])): ?>
                        <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['p2_class'] ?? ''); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 text-center">
                <a href="results.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Sonuc gir &rarr;</a>
            </div>
        </div>
        <?php else: ?>
        <div class="card flex items-center justify-center h-full min-h-[300px]">
            <div class="text-center p-8">
                <div class="text-4xl mb-4">&#9812;</div>
                <h3 class="text-base font-semibold text-gray-900 mb-2">Henuz eslesmeme yapilmadi</h3>
                <p class="text-sm text-gray-500">Sol taraftaki butonu kullanarak ilk turu baslatin.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
