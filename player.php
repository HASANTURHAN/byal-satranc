<?php
require_once 'db.php';

$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($playerId <= 0) {
    header("Location: players.php");
    exit();
}

// Oyuncu bilgileri
$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
$stmt->execute([$playerId]);
$player = $stmt->fetch();

if (!$player) {
    header("Location: players.php");
    exit();
}

// Oyuncunun tüm maçları
$matchStmt = $pdo->prepare("
    SELECT p.*,
           w.name AS white_name, w.sinif AS white_sinif, w.id AS white_id,
           b.name AS black_name, b.sinif AS black_sinif, b.id AS black_id
    FROM pairings p
    LEFT JOIN players w ON p.white_player_id = w.id
    LEFT JOIN players b ON p.black_player_id = b.id
    WHERE (p.white_player_id = ? OR p.black_player_id = ?)
    ORDER BY p.round ASC, p.table_no ASC
");
$matchStmt->execute([$playerId, $playerId]);
$matches = $matchStmt->fetchAll();

// İstatistikleri hesapla
$wins = 0;
$draws = 0;
$losses = 0;
$totalPlayed = 0;
$roundResults = []; // tur bazlı sonuçlar

foreach ($matches as $m) {
    $hasResult = $m['result'] !== null && $m['result'] !== '';
    $isWhite = (int)$m['white_player_id'] === $playerId;

    if ($hasResult) {
        $totalPlayed++;
        $points = $isWhite ? (float)$m['white_points'] : (float)$m['black_points'];

        if ($points >= 1) {
            $wins++;
            $roundResults[(int)$m['round']] = 'win';
        } elseif ($points > 0) {
            $draws++;
            $roundResults[(int)$m['round']] = 'draw';
        } else {
            $losses++;
            $roundResults[(int)$m['round']] = 'loss';
        }
    } else {
        $roundResults[(int)$m['round']] = 'pending';
    }
}

// Genel sıralama pozisyonu
$allPlayers = $pdo->query("SELECT id FROM players ORDER BY total_points DESC, name ASC")->fetchAll(PDO::FETCH_COLUMN);
$rank = array_search($playerId, $allPlayers);
$rank = $rank !== false ? $rank + 1 : '-';
$totalPlayerCount = count($allPlayers);

// Max tur
$maxRound = (int)$pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();

include 'header.php';
?>

<!-- Profil Başlığı -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white p-6 md:p-8 mb-8">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-4 right-8 text-8xl chess-piece-float"><?php echo $player['is_seed'] ? '&#9812;' : '&#9822;'; ?></div>
    </div>
    <div class="relative z-10">
        <a href="players.php" class="inline-flex items-center gap-1 text-gray-400 hover:text-white text-sm mb-4 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Katılımcılara Dön
        </a>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="w-16 h-16 rounded-full <?php echo $player['is_seed'] ? 'bg-gradient-to-br from-amber-400 to-amber-600' : 'bg-gray-700'; ?> flex items-center justify-center text-white font-bold text-2xl flex-shrink-0 border-2 border-white/20">
                <?php echo htmlspecialchars(mb_substr($player['name'], 0, 1, 'UTF-8')); ?>
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold">
                    <?php echo htmlspecialchars($player['name']); ?>
                    <?php if ($player['is_seed']): ?>
                        <span class="seed-badge inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold ml-2">&#9733; Seri Başı</span>
                    <?php endif; ?>
                </h1>
                <div class="flex flex-wrap items-center gap-3 mt-1 text-gray-400 text-sm">
                    <?php if ($player['sinif']): ?>
                        <span class="inline-flex items-center gap-1"><span class="text-amber-400">&#9814;</span> <?php echo htmlspecialchars($player['sinif']); ?></span>
                    <?php endif; ?>
                    <?php if ($player['school_no']): ?>
                        <span>Okul No: <?php echo htmlspecialchars($player['school_no']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 mb-8">
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?php echo $rank; ?></div>
        <div class="text-xs text-gray-500 mt-1">Sıralama</div>
        <div class="text-[10px] text-gray-400">/<?php echo $totalPlayerCount; ?></div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-amber-600"><?php echo number_format($player['total_points'], 1); ?></div>
        <div class="text-xs text-gray-500 mt-1">Toplam Puan</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-green-600"><?php echo $wins; ?></div>
        <div class="text-xs text-gray-500 mt-1">Galibiyet</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-yellow-600"><?php echo $draws; ?></div>
        <div class="text-xs text-gray-500 mt-1">Beraberlik</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-red-500"><?php echo $losses; ?></div>
        <div class="text-xs text-gray-500 mt-1">Mağlubiyet</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-blue-600"><?php echo $totalPlayed; ?></div>
        <div class="text-xs text-gray-500 mt-1">Oynanan Maç</div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-8">
    <!-- Performans Trendi -->
    <div class="card p-6">
        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span class="text-amber-500">&#9813;</span> Performans Trendi
        </h3>
        <?php if ($totalPlayed > 0): ?>
        <div class="flex items-end gap-2 h-32">
            <?php for ($r = 1; $r <= max($maxRound, 6); $r++):
                $res = $roundResults[$r] ?? null;
                $barColor = 'bg-gray-200';
                $barHeight = '20%';
                $label = '-';
                if ($res === 'win') { $barColor = 'bg-green-500'; $barHeight = '100%'; $label = '1'; }
                elseif ($res === 'draw') { $barColor = 'bg-yellow-400'; $barHeight = '50%'; $label = '½'; }
                elseif ($res === 'loss') { $barColor = 'bg-red-400'; $barHeight = '25%'; $label = '0'; }
                elseif ($res === 'pending') { $barColor = 'bg-blue-200'; $barHeight = '15%'; $label = '...'; }
            ?>
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full flex items-end justify-center" style="height: 100px;">
                    <div class="w-full max-w-8 rounded-t-lg <?php echo $barColor; ?> transition-all duration-500" style="height: <?php echo $barHeight; ?>"></div>
                </div>
                <?php if ($res && $res !== 'pending'): ?>
                <span class="text-xs font-bold <?php echo $res === 'win' ? 'text-green-600' : ($res === 'draw' ? 'text-yellow-600' : 'text-red-500'); ?>"><?php echo $label; ?></span>
                <?php else: ?>
                <span class="text-xs text-gray-400"><?php echo $label; ?></span>
                <?php endif; ?>
                <span class="text-[10px] text-gray-400">T<?php echo $r; ?></span>
            </div>
            <?php endfor; ?>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-400 text-center py-6">Henüz oynanan maç yok.</p>
        <?php endif; ?>
    </div>

    <!-- Sonuç Dağılımı -->
    <div class="card p-6">
        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span class="text-green-500">&#9816;</span> Sonuç Dağılımı
        </h3>
        <?php if ($totalPlayed > 0): ?>
        <div class="space-y-4">
            <!-- Yatay oran çubuğu -->
            <div class="flex h-8 rounded-full overflow-hidden bg-gray-100">
                <?php if ($wins > 0): ?>
                <div class="bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center transition-all duration-700"
                     style="width: <?php echo round($wins / $totalPlayed * 100); ?>%">
                    <span class="text-[11px] font-bold text-white"><?php echo $wins; ?>G</span>
                </div>
                <?php endif; ?>
                <?php if ($draws > 0): ?>
                <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 flex items-center justify-center transition-all duration-700"
                     style="width: <?php echo round($draws / $totalPlayed * 100); ?>%">
                    <span class="text-[11px] font-bold text-yellow-800"><?php echo $draws; ?>B</span>
                </div>
                <?php endif; ?>
                <?php if ($losses > 0): ?>
                <div class="bg-gradient-to-r from-red-400 to-red-500 flex items-center justify-center transition-all duration-700"
                     style="width: <?php echo round($losses / $totalPlayed * 100); ?>%">
                    <span class="text-[11px] font-bold text-white"><?php echo $losses; ?>M</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detay -->
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="p-3 rounded-xl bg-green-50 border border-green-100">
                    <div class="text-xl font-bold text-green-600"><?php echo $totalPlayed > 0 ? round($wins / $totalPlayed * 100) : 0; ?>%</div>
                    <div class="text-xs text-green-700 font-medium">Galibiyet</div>
                </div>
                <div class="p-3 rounded-xl bg-yellow-50 border border-yellow-100">
                    <div class="text-xl font-bold text-yellow-600"><?php echo $totalPlayed > 0 ? round($draws / $totalPlayed * 100) : 0; ?>%</div>
                    <div class="text-xs text-yellow-700 font-medium">Beraberlik</div>
                </div>
                <div class="p-3 rounded-xl bg-red-50 border border-red-100">
                    <div class="text-xl font-bold text-red-500"><?php echo $totalPlayed > 0 ? round($losses / $totalPlayed * 100) : 0; ?>%</div>
                    <div class="text-xs text-red-600 font-medium">Mağlubiyet</div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-400 text-center py-6">Henüz oynanan maç yok.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Maç Geçmişi -->
<div class="card overflow-hidden mb-8">
    <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200">
        <h3 class="font-bold text-gray-900 text-lg flex items-center gap-2">
            <span>&#9820;</span> Maç Geçmişi
        </h3>
    </div>
    <?php if (empty($matches)): ?>
    <div class="p-8 text-center text-gray-400 text-sm">Henüz maç kaydı bulunmuyor.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-14">Tur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rakip</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Renk</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Sonuç</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Puan</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase hidden sm:table-cell">Tarih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($matches as $m):
                    $isWhite = (int)$m['white_player_id'] === $playerId;
                    $opponentName = $isWhite ? ($m['black_name'] ?? 'BYE') : ($m['white_name'] ?? 'BYE');
                    $opponentId = $isWhite ? ($m['black_id'] ?? null) : ($m['white_id'] ?? null);
                    $opponentSinif = $isWhite ? ($m['black_sinif'] ?? '') : ($m['white_sinif'] ?? '');
                    $color = $isWhite ? 'Beyaz' : 'Siyah';
                    $colorIcon = $isWhite ? '&#9812;' : '&#9818;';

                    $hasResult = $m['result'] !== null && $m['result'] !== '';
                    $points = '-';
                    $resultLabel = '';
                    $resultClass = '';

                    if ($hasResult) {
                        $pts = $isWhite ? (float)$m['white_points'] : (float)$m['black_points'];
                        $points = ($pts == (int)$pts) ? (int)$pts : number_format($pts, 1);
                        if ($pts >= 1) { $resultLabel = 'Galibiyet'; $resultClass = 'bg-green-100 text-green-700'; }
                        elseif ($pts > 0) { $resultLabel = 'Beraberlik'; $resultClass = 'bg-amber-100 text-amber-700'; }
                        else { $resultLabel = 'Mağlubiyet'; $resultClass = 'bg-red-50 text-red-600'; }
                    } else {
                        $resultLabel = 'Bekliyor';
                        $resultClass = 'bg-blue-50 text-blue-500';
                    }

                    $dateStr = '';
                    if (!empty($m['match_date'])) {
                        $dateStr = date('d.m.Y', strtotime($m['match_date']));
                    } elseif (!empty($m['played_at'])) {
                        $dateStr = date('d.m.Y', strtotime($m['played_at']));
                    }
                ?>
                <tr class="hover:bg-blue-50/50 transition">
                    <td class="px-4 py-3 text-center text-sm font-bold text-gray-400"><?php echo (int)$m['round']; ?></td>
                    <td class="px-4 py-3">
                        <?php if ($opponentId): ?>
                        <a href="player.php?id=<?php echo (int)$opponentId; ?>" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline transition">
                            <?php echo htmlspecialchars($opponentName); ?>
                        </a>
                        <?php else: ?>
                        <span class="text-sm text-gray-400 italic"><?php echo htmlspecialchars($opponentName); ?></span>
                        <?php endif; ?>
                        <?php if ($opponentSinif): ?>
                        <span class="text-xs text-gray-400 ml-1">(<?php echo htmlspecialchars($opponentSinif); ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-lg" title="<?php echo $color; ?>"><?php echo $colorIcon; ?></span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold <?php echo $resultClass; ?>">
                            <?php echo $resultLabel; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-sm font-bold text-gray-700"><?php echo $points; ?></td>
                    <td class="px-4 py-3 text-center text-xs text-gray-400 hidden sm:table-cell"><?php echo $dateStr ?: '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
