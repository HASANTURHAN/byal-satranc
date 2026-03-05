<?php
require_once 'db.php';

// İstatistikler
$total_players = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$seed_players = $pdo->query("SELECT COUNT(*) FROM players WHERE is_seed = 1")->fetchColumn();
$current_round = $pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();
$completed_matches = $pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NOT NULL AND result != ''")->fetchColumn();
$pending_matches = $pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NULL OR result = ''")->fetchColumn();
$total_matches = $completed_matches + $pending_matches;

$tournament_name = get_setting('tournament_name', '2025-2026 Okul Satranç Turnuvası');
$status = get_setting('tournament_status', 'turnuva_basladi');

// Son 5 sonuç
$recent = $pdo->query("
    SELECT p.*,
           w.name as white_name, w.sinif as white_sinif,
           b.name as black_name, b.sinif as black_sinif
    FROM pairings p
    JOIN players w ON p.white_player_id = w.id
    LEFT JOIN players b ON p.black_player_id = b.id
    WHERE p.result IS NOT NULL AND p.result != ''
    ORDER BY p.played_at DESC, p.id DESC
    LIMIT 5
")->fetchAll();

// Top 5 oyuncu
$top5 = $pdo->query("SELECT * FROM players ORDER BY total_points DESC, name ASC LIMIT 5")->fetchAll();

// Seri başı oyuncular
$seeds = $pdo->query("SELECT * FROM players WHERE is_seed = 1 ORDER BY id")->fetchAll();

// Turnuva ilerleme - tur bazlı tamamlanma oranları
$totalRoundsTarget = 6;
$allRounds = $pdo->query("SELECT DISTINCT round FROM pairings ORDER BY round ASC")->fetchAll(PDO::FETCH_COLUMN);
$roundProgress = [];
foreach ($allRounds as $r) {
    $stT = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?");
    $stT->execute([(int)$r]);
    $rTotal = (int)$stT->fetchColumn();
    $stC = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ? AND result IS NOT NULL AND result != ''");
    $stC->execute([(int)$r]);
    $rCompleted = (int)$stC->fetchColumn();
    $roundProgress[(int)$r] = ['total' => $rTotal, 'completed' => $rCompleted, 'pct' => $rTotal > 0 ? round($rCompleted / $rTotal * 100) : 0];
}

// Yaklaşan maçlar (bugün ve sonrası, sonuç girilmemiş)
$today = date('Y-m-d');
$upcomingStmt = $pdo->prepare("
    SELECT p.*, w.name AS white_name, w.sinif AS white_sinif,
           b.name AS black_name, b.sinif AS black_sinif
    FROM pairings p
    LEFT JOIN players w ON p.white_player_id = w.id
    LEFT JOIN players b ON p.black_player_id = b.id
    WHERE (p.result IS NULL OR p.result = '')
      AND p.match_date >= ?
    ORDER BY p.match_date ASC, p.match_time ASC, p.table_no ASC
    LIMIT 10
");
$upcomingStmt->execute([$today]);
$upcomingMatches = $upcomingStmt->fetchAll();

// Yaklaşan maçları tarih+ders bazlı grupla
$upcomingGroups = [];
foreach ($upcomingMatches as $um) {
    $dateKey = $um['match_date'] ?: 'Belirsiz';
    $timeKey = $um['match_time'] ?: '';
    $key = $dateKey . ($timeKey ? ' | ' . $timeKey : '');
    $upcomingGroups[$key][] = $um;
}

// Beyaz/Siyah kazanma ve beraberlik istatistikleri (genel)
$whiteWinsAll = (int)$pdo->query("SELECT COUNT(*) FROM pairings WHERE result = '1-0'")->fetchColumn();
$blackWinsAll = (int)$pdo->query("SELECT COUNT(*) FROM pairings WHERE result = '0-1'")->fetchColumn();
$drawsAll = (int)$pdo->query("SELECT COUNT(*) FROM pairings WHERE result = '1/2-1/2'")->fetchColumn();
$totalCompletedAll = $whiteWinsAll + $blackWinsAll + $drawsAll;

// Sınıf bazlı sıralama özeti (top 3)
$allPlayersForClass = $pdo->query("SELECT sinif, total_points FROM players ORDER BY total_points DESC")->fetchAll();
$classStatsIndex = [];
foreach ($allPlayersForClass as $pl) {
    $sinif = $pl['sinif'] ?: 'Belirtilmemiş';
    if (!isset($classStatsIndex[$sinif])) {
        $classStatsIndex[$sinif] = ['sinif' => $sinif, 'count' => 0, 'total_points' => 0];
    }
    $classStatsIndex[$sinif]['count']++;
    $classStatsIndex[$sinif]['total_points'] += (float)$pl['total_points'];
}
foreach ($classStatsIndex as &$csi) {
    $csi['avg_points'] = $csi['count'] > 0 ? $csi['total_points'] / $csi['count'] : 0;
}
unset($csi);
usort($classStatsIndex, function($a, $b) { return $b['avg_points'] <=> $a['avg_points']; });
$top3Classes = array_slice($classStatsIndex, 0, 3);

// Türkçe tarih formatlama
function formatTurkishDateIndex($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return htmlspecialchars($dateStr);
    $aylar = ['', 'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    $gunler = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
    $gun = (int)date('j', $ts);
    $ay = (int)date('n', $ts);
    $haftaGunu = (int)date('w', $ts);
    return $gun . ' ' . $aylar[$ay] . ', ' . $gunler[$haftaGunu];
}

include 'header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white p-8 md:p-12 mb-8 shadow-2xl">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-4 right-8 text-8xl chess-piece-float">♔</div>
        <div class="absolute bottom-4 left-8 text-6xl chess-piece-float" style="animation-delay: 1s;">♚</div>
        <div class="absolute top-20 right-40 text-5xl chess-piece-float" style="animation-delay: 2s;">♜</div>
    </div>
    <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-500/20 rounded-full text-amber-300 text-sm font-medium mb-4">
            <span>♔</span>
            <span>2025-2026 Sezonu</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold mb-2"><?php echo htmlspecialchars($tournament_name); ?></h1>
        <p class="text-gray-300 text-lg mb-2">Sultangazi Bahattin Yıldız Anadolu Lisesi</p>
        <p class="text-gray-400 text-sm mb-6">İsviçre Sistemi (6 Tur) &middot; <?php echo $total_players; ?> Oyuncu &middot; <?php echo $seed_players; ?> Seri Başı</p>
        <div class="flex flex-wrap gap-4">
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $total_players; ?></div>
                <div class="text-xs text-gray-400">Oyuncu</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $current_round; ?>/6</div>
                <div class="text-xs text-gray-400">Tur</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $completed_matches; ?></div>
                <div class="text-xs text-gray-400">Oynanan Maç</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold">İsviçre</div>
                <div class="text-xs text-gray-400">Sistem</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
            <a href="round.php" class="inline-flex items-center px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl transition shadow-lg">
                ♟ Fikstürü Gör
            </a>
            <a href="standings.php" class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-xl transition border border-white/20">
                ♚ Puan Durumu
            </a>
        </div>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-gray-900"><?php echo $total_players; ?></div>
        <div class="text-sm text-gray-500 mt-1">Katılımcı</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-amber-600"><?php echo $current_round; ?>/6</div>
        <div class="text-sm text-gray-500 mt-1">Aktif Tur</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-green-600"><?php echo $completed_matches; ?></div>
        <div class="text-sm text-gray-500 mt-1">Oynanan Maç</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-orange-500"><?php echo $pending_matches; ?></div>
        <div class="text-sm text-gray-500 mt-1">Bekleyen Maç</div>
    </div>
</div>

<!-- Turnuva İlerlemesi -->
<?php if (!empty($allRounds)): ?>
<div class="card p-5 mb-8">
    <h2 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        Turnuva İlerlemesi
    </h2>
    <div class="flex items-center gap-1">
        <?php for ($t = 1; $t <= $totalRoundsTarget; $t++):
            $rp = $roundProgress[$t] ?? null;
            $pct = $rp ? $rp['pct'] : 0;
            $isPlayed = $rp && $rp['total'] > 0;
            $isDone = $rp && $rp['pct'] === 100;
            $isCurrent = $isPlayed && !$isDone;
        ?>
        <div class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full h-3 rounded-full overflow-hidden <?php echo $isPlayed ? '' : 'opacity-40'; ?> bg-gray-200">
                <div class="h-full rounded-full transition-all duration-700
                    <?php echo $isDone ? 'bg-green-500' : ($isCurrent ? 'bg-amber-500' : 'bg-gray-300'); ?>"
                     style="width: <?php echo $pct; ?>%"></div>
            </div>
            <div class="flex items-center gap-1">
                <?php if ($isDone): ?>
                    <span class="text-green-500 text-xs">&#10003;</span>
                <?php endif; ?>
                <span class="text-[10px] font-bold <?php echo $isCurrent ? 'text-amber-600' : ($isDone ? 'text-green-600' : 'text-gray-400'); ?>">T<?php echo $t; ?></span>
            </div>
        </div>
        <?php if ($t < $totalRoundsTarget): ?>
        <div class="w-2 h-0.5 bg-gray-300 mt-[-10px]"></div>
        <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid md:grid-cols-2 gap-6 mb-8">
    <!-- Yaklaşan Maçlar -->
    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Yaklaşan Maçlar
        </h2>
        <?php if (empty($upcomingMatches)): ?>
            <p class="text-gray-500 text-sm text-center py-4">Planlanmış yaklaşan maç bulunmuyor.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($upcomingGroups as $groupKey => $groupMatches):
                    $parts = explode(' | ', $groupKey);
                    $dateLabel = formatTurkishDateIndex($parts[0] ?? '');
                    $timeLabel = $parts[1] ?? '';
                ?>
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-xs font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-lg"><?php echo $dateLabel ?: 'Belirsiz'; ?></span>
                        <?php if ($timeLabel): ?>
                        <span class="text-xs font-semibold text-violet-600"><?php echo htmlspecialchars($timeLabel); ?></span>
                        <?php endif; ?>
                        <span class="text-xs text-gray-400"><?php echo count($groupMatches); ?> maç</span>
                    </div>
                    <?php foreach (array_slice($groupMatches, 0, 3) as $um): ?>
                    <div class="flex items-center gap-2 py-1.5 text-sm">
                        <span class="text-xs text-gray-400 w-8">M<?php echo (int)$um['table_no']; ?></span>
                        <span class="flex-1 text-right font-medium text-gray-900 truncate"><?php echo htmlspecialchars($um['white_name'] ?? '?'); ?></span>
                        <span class="text-xs text-gray-400 font-bold px-1">vs</span>
                        <span class="flex-1 font-medium text-gray-900 truncate"><?php echo htmlspecialchars($um['black_name'] ?? '?'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($groupMatches) > 3): ?>
                    <div class="text-xs text-gray-400 pl-8">+<?php echo count($groupMatches) - 3; ?> maç daha</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- İstatistikler & Sınıf Sıralaması -->
    <div class="space-y-6">
        <!-- Beyaz/Siyah Kazanma Oranı -->
        <?php if ($totalCompletedAll > 0): ?>
        <div class="card p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <span class="text-lg">&#9812;&#9818;</span> Sonuç Dağılımı
            </h3>
            <div class="flex h-6 rounded-full overflow-hidden bg-gray-100 mb-3">
                <?php if ($whiteWinsAll > 0): ?>
                <div class="bg-gradient-to-r from-gray-200 to-gray-300 flex items-center justify-center" style="width: <?php echo round($whiteWinsAll / $totalCompletedAll * 100); ?>%">
                    <span class="text-[10px] font-bold text-gray-700"><?php echo round($whiteWinsAll / $totalCompletedAll * 100); ?>%</span>
                </div>
                <?php endif; ?>
                <?php if ($drawsAll > 0): ?>
                <div class="bg-gradient-to-r from-amber-300 to-amber-400 flex items-center justify-center" style="width: <?php echo round($drawsAll / $totalCompletedAll * 100); ?>%">
                    <span class="text-[10px] font-bold text-amber-800"><?php echo round($drawsAll / $totalCompletedAll * 100); ?>%</span>
                </div>
                <?php endif; ?>
                <?php if ($blackWinsAll > 0): ?>
                <div class="bg-gradient-to-r from-gray-700 to-gray-900 flex items-center justify-center" style="width: <?php echo round($blackWinsAll / $totalCompletedAll * 100); ?>%">
                    <span class="text-[10px] font-bold text-white"><?php echo round($blackWinsAll / $totalCompletedAll * 100); ?>%</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>&#9812; Beyaz: <strong class="text-gray-700"><?php echo $whiteWinsAll; ?></strong></span>
                <span>Berabere: <strong class="text-amber-600"><?php echo $drawsAll; ?></strong></span>
                <span>&#9818; Siyah: <strong class="text-gray-700"><?php echo $blackWinsAll; ?></strong></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sınıf Sıralaması Özeti -->
        <?php if (count($top3Classes) >= 2): ?>
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span class="text-lg">&#9814;</span> Sınıf Sıralaması
                </h3>
                <a href="standings.php" class="text-xs text-amber-600 hover:text-amber-700 font-medium">Tümünü Gör &rarr;</a>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <?php
                $podiumStyles = [
                    ['bg' => 'bg-gradient-to-b from-amber-50 to-yellow-50', 'border' => 'border-amber-300', 'text' => 'text-amber-700', 'icon' => '&#9812;'],
                    ['bg' => 'bg-gradient-to-b from-gray-50 to-gray-100', 'border' => 'border-gray-300', 'text' => 'text-gray-600', 'icon' => '&#9815;'],
                    ['bg' => 'bg-gradient-to-b from-orange-50 to-orange-100', 'border' => 'border-orange-300', 'text' => 'text-orange-700', 'icon' => '&#9814;'],
                ];
                foreach ($top3Classes as $ci => $cls):
                    $ps = $podiumStyles[$ci] ?? $podiumStyles[2];
                ?>
                <div class="text-center p-3 rounded-xl <?php echo $ps['bg']; ?> border <?php echo $ps['border']; ?>">
                    <div class="text-xl mb-1"><?php echo $ps['icon']; ?></div>
                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($cls['sinif']); ?></div>
                    <div class="text-lg font-extrabold <?php echo $ps['text']; ?>"><?php echo number_format($cls['avg_points'], 2); ?></div>
                    <div class="text-[10px] text-gray-500">ort. puan</div>
                    <div class="text-[10px] text-gray-400"><?php echo $cls['count']; ?> öğrenci</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-8 mb-8">
    <!-- Top 5 Sıralama -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="text-amber-500">♚</span> Puan Sıralaması
            </h2>
            <a href="standings.php" class="text-sm text-amber-600 hover:text-amber-700 font-medium">Tümünü Gör &rarr;</a>
        </div>
        <?php if (empty($top5)): ?>
            <p class="text-gray-500 text-sm">Henüz puan verisi yok.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($top5 as $i => $p): ?>
                    <div class="flex items-center gap-3 p-2 rounded-lg <?php echo $i === 0 ? 'bg-amber-50' : ''; ?>">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                            <?php echo $i === 0 ? 'bg-amber-100 text-amber-700' : ($i === 1 ? 'bg-gray-100 text-gray-700' : ($i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-gray-50 text-gray-500')); ?>">
                            <?php echo $i === 0 ? '♔' : ($i === 1 ? '♕' : ($i === 2 ? '♖' : ($i + 1))); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 truncate">
                                <a href="player.php?id=<?php echo (int)$p['id']; ?>" class="hover:text-blue-600 hover:underline transition"><?php echo htmlspecialchars($p['name']); ?></a>
                                <?php if ($p['is_seed']): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-xs seed-badge rounded-full ml-1">⭐</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['sinif']); ?></div>
                        </div>
                        <div class="text-lg font-bold text-gray-900"><?php echo number_format($p['total_points'], 1); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Son Sonuçlar -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="text-green-500">♜</span> Son Sonuçlar
            </h2>
            <a href="results.php" class="text-sm text-amber-600 hover:text-amber-700 font-medium">Tümünü Gör &rarr;</a>
        </div>
        <?php if (empty($recent)): ?>
            <p class="text-gray-500 text-sm">Henüz tamamlanan maç yok.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recent as $m): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50">
                        <div class="flex-1 text-right">
                            <div class="font-medium text-sm <?php echo $m['result'] === '1-0' ? 'text-green-700 font-bold' : 'text-gray-700'; ?> truncate">
                                <?php echo htmlspecialchars($m['white_name']); ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($m['white_sinif']); ?></div>
                        </div>
                        <div class="px-3 py-1 rounded-lg text-xs font-bold
                            <?php echo $m['result'] === '1/2-1/2' ? 'bg-amber-100 text-amber-700' : 'bg-gray-900 text-white'; ?>">
                            <?php echo $m['result'] === '1/2-1/2' ? '½-½' : htmlspecialchars($m['result']); ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm <?php echo $m['result'] === '0-1' ? 'text-green-700 font-bold' : 'text-gray-700'; ?> truncate">
                                <?php echo htmlspecialchars($m['black_name'] ?? 'BYE'); ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($m['black_sinif'] ?? ''); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Seri Başı Oyuncular -->
<div class="card p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
        <span class="text-amber-500">⭐</span> Seri Başı Oyuncular (<?php echo $seed_players; ?> Kişi)
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php foreach ($seeds as $i => $s): ?>
            <div class="text-center p-4 rounded-xl bg-gradient-to-b from-amber-50 to-white border border-amber-200">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-lg font-bold mx-auto mb-2">
                    <?php echo $i + 1; ?>
                </div>
                <div class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($s['name']); ?></div>
                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($s['sinif']); ?> – No: <?php echo htmlspecialchars($s['school_no']); ?></div>
                <div class="mt-1 text-sm font-bold text-amber-600"><?php echo number_format($s['total_points'], 1); ?> puan</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Puanlama Sistemi -->
<div class="card p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
        <span>♝</span> Puanlama Sistemi
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 rounded-xl bg-green-50 border border-green-200">
            <div class="text-2xl font-bold text-green-700">1</div>
            <div class="text-sm text-green-600">Galibiyet</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-amber-50 border border-amber-200">
            <div class="text-2xl font-bold text-amber-700">0.5</div>
            <div class="text-sm text-amber-600">Beraberlik</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-red-50 border border-red-200">
            <div class="text-2xl font-bold text-red-700">0</div>
            <div class="text-sm text-red-600">Mağlubiyet</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-blue-50 border border-blue-200">
            <div class="text-2xl font-bold text-blue-700">1</div>
            <div class="text-sm text-blue-600">BYE (Bay)</div>
        </div>
    </div>
</div>

<!-- Hızlı Linkler -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="round.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♟</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Fikstür</div>
        <div class="text-xs text-gray-500">Maç Eşleştirmeleri</div>
    </a>
    <a href="results.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♜</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Sonuçlar</div>
        <div class="text-xs text-gray-500">Maç Sonuçları</div>
    </a>
    <a href="standings.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♚</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Puan Durumu</div>
        <div class="text-xs text-gray-500">Canlı Sıralama</div>
    </a>
    <a href="players.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♞</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Katılımcılar</div>
        <div class="text-xs text-gray-500"><?php echo $total_players; ?> Oyuncu</div>
    </a>
</div>

<?php include 'footer.php'; ?>
