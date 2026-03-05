<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = is_admin();
$csrfToken = $isLoggedIn ? csrf_token() : '';

// Get all distinct rounds from pairings
$allRounds = $pdo->query("SELECT DISTINCT round FROM pairings ORDER BY round ASC")->fetchAll(PDO::FETCH_COLUMN);

// (Takvim bilgileri artık pairings tablosunda, maç bazlı)

// Determine selected round
$selectedRound = isset($_GET['round']) ? (int)$_GET['round'] : 0;
if ($selectedRound <= 0 && !empty($allRounds)) {
    $selectedRound = (int)end($allRounds);
}

// Fetch pairings for the selected round
$pairings = [];
$totalTables = 0;
$completedCount = 0;
$pendingCount = 0;

if ($selectedRound > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, w.name AS white_name, w.sinif AS white_sinif, w.school_no AS white_school_no, w.is_seed AS white_is_seed,
               b.name AS black_name, b.sinif AS black_sinif, b.school_no AS black_school_no, b.is_seed AS black_is_seed
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.round = ?
        ORDER BY p.table_no ASC
    ");
    $stmt->execute([$selectedRound]);
    $pairings = $stmt->fetchAll();

    $totalTables = count($pairings);
    foreach ($pairings as $p) {
        if ($p['result'] !== null && $p['result'] !== '') $completedCount++;
        else $pendingCount++;
    }
}

// Genel turnuva istatistikleri
$totalAllMatches = (int)$pdo->query("SELECT COUNT(*) FROM pairings")->fetchColumn();
$totalAllCompleted = (int)$pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NOT NULL AND result != ''")->fetchColumn();
$totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$maxRound = !empty($allRounds) ? (int)end($allRounds) : 0;
$totalRoundsTarget = 6;

// Beyaz/siyah kazanma & beraberlik istatistikleri (seçili tur)
$whiteWins = 0; $blackWins = 0; $draws = 0;
foreach ($pairings as $p) {
    if ($p['result'] === '1-0') $whiteWins++;
    elseif ($p['result'] === '0-1') $blackWins++;
    elseif ($p['result'] === '1/2-1/2' || $p['result'] === '0.5-0.5') $draws++;
}

// Her tur için tamamlanma oranları (grafik için)
$roundStats = [];
foreach ($allRounds as $r) {
    $rTotal = (int)$pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?")->execute([(int)$r]) ? 0 : 0;
    $stT = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?");
    $stT->execute([(int)$r]);
    $rTotal = (int)$stT->fetchColumn();
    $stC = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ? AND result IS NOT NULL AND result != ''");
    $stC->execute([(int)$r]);
    $rCompleted = (int)$stC->fetchColumn();
    $roundStats[(int)$r] = ['total' => $rTotal, 'completed' => $rCompleted, 'pct' => $rTotal > 0 ? round($rCompleted / $rTotal * 100) : 0];
}

include 'header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white p-6 md:p-8 mb-8">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-2 right-8 text-7xl chess-piece-float">♟</div>
    </div>
    <div class="relative z-10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-2xl md:text-3xl font-extrabold mb-1">Fikstür & Eşleştirmeler</h2>
                <p class="text-gray-400 text-sm">İsviçre Sistemi &middot; <?php echo $totalPlayers; ?> Oyuncu &middot; <?php echo $totalRoundsTarget; ?> Tur</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2 text-center">
                    <div class="text-xl font-bold"><?php echo $maxRound; ?>/<?php echo $totalRoundsTarget; ?></div>
                    <div class="text-[10px] text-gray-400 uppercase">Tur</div>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2 text-center">
                    <div class="text-xl font-bold"><?php echo $totalAllCompleted; ?>/<?php echo $totalAllMatches; ?></div>
                    <div class="text-[10px] text-gray-400 uppercase">Maç</div>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2 text-center">
                    <div class="text-xl font-bold"><?php echo $totalAllMatches > 0 ? round($totalAllCompleted / $totalAllMatches * 100) : 0; ?>%</div>
                    <div class="text-[10px] text-gray-400 uppercase">Tamamlanma</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($allRounds)): ?>
<div class="card p-12 text-center">
    <div class="text-5xl mb-4 opacity-30">♟</div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Henüz eşleştirme yapılmadı</h3>
    <p class="text-sm text-gray-500">Turnuva eşleşmeleri oluşturulduğunda burada görünecek.</p>
</div>
<?php else: ?>

<!-- Turnuva İlerleme Grafiği -->
<div class="card p-5 mb-6">
    <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Turnuva İlerleme Durumu
    </h3>
    <div class="grid grid-cols-<?php echo $totalRoundsTarget; ?> gap-2">
        <?php for ($t = 1; $t <= $totalRoundsTarget; $t++):
            $rs = $roundStats[$t] ?? null;
            $pct = $rs ? $rs['pct'] : 0;
            $isActive = $t == $selectedRound;
            $isPlayed = $rs && $rs['total'] > 0;
            $isDone = $rs && $rs['pct'] === 100;
        ?>
        <a href="<?php echo $isPlayed ? 'round.php?round=' . $t : '#'; ?>"
           class="relative group <?php echo !$isPlayed ? 'opacity-40 cursor-default' : ''; ?>">
            <div class="flex flex-col items-center">
                <div class="w-full bg-gray-100 rounded-full h-20 relative overflow-hidden border-2 transition-all
                    <?php echo $isActive ? 'border-amber-500 shadow-lg shadow-amber-200/50' : ($isDone ? 'border-green-400' : 'border-gray-200'); ?>">
                    <div class="absolute bottom-0 w-full rounded-full transition-all duration-700
                        <?php echo $isDone ? 'bg-gradient-to-t from-green-500 to-green-400' : ($isActive ? 'bg-gradient-to-t from-amber-500 to-amber-400' : 'bg-gradient-to-t from-blue-400 to-blue-300'); ?>"
                         style="height: <?php echo $pct; ?>%"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-xs font-bold <?php echo $pct > 50 ? 'text-white' : 'text-gray-500'; ?>">
                        <?php echo $isPlayed ? $pct . '%' : '-'; ?>
                    </div>
                </div>
                <span class="mt-1.5 text-xs font-bold <?php echo $isActive ? 'text-amber-600' : 'text-gray-500'; ?>">T<?php echo $t; ?></span>
                <?php if ($isDone): ?>
                    <span class="text-green-500 text-xs">&#10003;</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endfor; ?>
    </div>
</div>

<!-- Round Selector Tabs -->
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($allRounds as $round): ?>
    <a href="round.php?round=<?php echo (int)$round; ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?php echo $selectedRound == $round
                  ? 'bg-gray-900 text-white shadow-md'
                  : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'; ?>">
        Tur <?php echo (int)$round; ?>
        <?php $rs = $roundStats[(int)$round] ?? null; if ($rs && $rs['pct'] === 100): ?>
            <span class="ml-1 text-green-400">&#10003;</span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Maç Takvimi Özeti -->
<?php
    // Maçları tarihe göre grupla
    $scheduleGroups = [];
    $unscheduled = [];
    foreach ($pairings as $p) {
        $dateKey = trim($p['match_date'] ?? '');
        $timeKey = trim($p['match_time'] ?? '');
        if ($dateKey || $timeKey) {
            $key = ($dateKey ?: 'Tarih belirtilmemiş') . ($timeKey ? ' - ' . $timeKey : '');
            $scheduleGroups[$key] = ($scheduleGroups[$key] ?? 0) + 1;
        } else {
            $unscheduled[] = $p;
        }
    }
    $hasAnySchedule = !empty($scheduleGroups);
?>
<?php if ($hasAnySchedule): ?>
<div class="card p-5 mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200/60">
    <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2 mb-3">
        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Maç Programı
    </h3>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($scheduleGroups as $label => $count): ?>
        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-blue-200/60 shadow-sm">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($label); ?></span>
            <span class="text-xs font-bold bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full"><?php echo $count; ?> maç</span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($unscheduled)): ?>
        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200/60">
            <span class="text-sm text-amber-700">Takvim atanmamış:</span>
            <span class="text-xs font-bold bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded-full"><?php echo count($unscheduled); ?> maç</span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Tur İstatistikleri -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?php echo $totalTables; ?></div>
        <div class="text-[10px] text-gray-500 font-medium uppercase mt-1">Masa</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-green-600"><?php echo $completedCount; ?></div>
        <div class="text-[10px] text-gray-500 font-medium uppercase mt-1">Tamamlanan</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-amber-600"><?php echo $pendingCount; ?></div>
        <div class="text-[10px] text-gray-500 font-medium uppercase mt-1">Bekleyen</div>
    </div>
    <div class="card p-4 text-center">
        <div class="flex items-center justify-center gap-1">
            <span class="text-lg font-bold text-gray-700"><?php echo $whiteWins; ?></span>
            <span class="text-xs text-gray-400">B</span>
            <span class="text-gray-300 mx-0.5">/</span>
            <span class="text-lg font-bold text-amber-600"><?php echo $draws; ?></span>
            <span class="text-xs text-gray-400">D</span>
            <span class="text-gray-300 mx-0.5">/</span>
            <span class="text-lg font-bold text-gray-900"><?php echo $blackWins; ?></span>
            <span class="text-xs text-gray-400">S</span>
        </div>
        <div class="text-[10px] text-gray-500 font-medium uppercase mt-1">Kazanan Dağılımı</div>
    </div>
    <div class="card p-4 text-center">
        <?php $pct = $totalTables > 0 ? round($completedCount / $totalTables * 100) : 0; ?>
        <div class="relative w-14 h-14 mx-auto mb-1">
            <svg class="w-14 h-14 transform -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                <circle cx="18" cy="18" r="15.5" fill="none"
                    stroke="<?php echo $pct === 100 ? '#22c55e' : '#f59e0b'; ?>"
                    stroke-width="3" stroke-dasharray="<?php echo round($pct * 97.4 / 100); ?> 97.4"
                    stroke-linecap="round"/>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-700"><?php echo $pct; ?>%</div>
        </div>
        <div class="text-[10px] text-gray-500 font-medium uppercase">Tur İlerlemesi</div>
    </div>
</div>

<!-- Kazanma Dağılımı Çubuğu -->
<?php if ($completedCount > 0): ?>
<div class="card p-4 mb-6">
    <div class="flex items-center gap-3 mb-2">
        <span class="text-xs font-bold text-gray-500 uppercase">Tur <?php echo $selectedRound; ?> Sonuç Dağılımı</span>
    </div>
    <div class="flex h-5 rounded-full overflow-hidden bg-gray-100">
        <?php if ($whiteWins > 0): ?>
        <div class="bg-gradient-to-r from-gray-200 to-gray-300 flex items-center justify-center transition-all duration-700"
             style="width: <?php echo round($whiteWins / $completedCount * 100); ?>%">
            <span class="text-[10px] font-bold text-gray-700"><?php echo $whiteWins; ?> Beyaz</span>
        </div>
        <?php endif; ?>
        <?php if ($draws > 0): ?>
        <div class="bg-gradient-to-r from-amber-300 to-amber-400 flex items-center justify-center transition-all duration-700"
             style="width: <?php echo round($draws / $completedCount * 100); ?>%">
            <span class="text-[10px] font-bold text-amber-800"><?php echo $draws; ?> Berabere</span>
        </div>
        <?php endif; ?>
        <?php if ($blackWins > 0): ?>
        <div class="bg-gradient-to-r from-gray-700 to-gray-900 flex items-center justify-center transition-all duration-700"
             style="width: <?php echo round($blackWins / $completedCount * 100); ?>%">
            <span class="text-[10px] font-bold text-white"><?php echo $blackWins; ?> Siyah</span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Round Card with Matches -->
<div class="card overflow-hidden mb-6">
    <div class="px-5 py-4 bg-gradient-to-r from-gray-900 to-gray-800 text-white flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">♞</span>
            <div>
                <h3 class="font-bold text-lg">Tur <?php echo $selectedRound; ?></h3>
                <p class="text-gray-400 text-xs"><?php echo $totalTables; ?> masa &middot; <?php echo $completedCount; ?> tamamlandı</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
            <?php echo ($completedCount === $totalTables && $totalTables > 0)
                ? 'bg-green-500/20 text-green-300 border border-green-500/30'
                : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'; ?>">
            <?php if ($completedCount === $totalTables && $totalTables > 0): ?>
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <?php endif; ?>
            <?php echo ($completedCount === $totalTables && $totalTables > 0) ? 'Tamamlandı' : 'Devam Ediyor'; ?>
        </span>
    </div>

    <?php if (empty($pairings)): ?>
    <div class="text-center py-12">
        <span class="text-4xl mb-3 block opacity-20">♟</span>
        <p class="text-sm text-gray-500">Bu tura ait eşleştirme bulunamadı.</p>
    </div>
    <?php else: ?>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider w-20">Masa</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider w-36">Tarih / Saat</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold text-gray-500 uppercase tracking-wider">Beyaz Oyuncu</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold text-gray-500 uppercase tracking-wider w-28">Sonuç</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-500 uppercase tracking-wider">Siyah Oyuncu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($pairings as $pairing):
                    $hasResult = $pairing['result'] !== null && $pairing['result'] !== '';
                    $isSeed = (int)$pairing['is_seed_table'] === 1;
                    $whiteWon = $hasResult && $pairing['result'] === '1-0';
                    $blackWon = $hasResult && $pairing['result'] === '0-1';
                    $isDraw = $hasResult && ($pairing['result'] === '1/2-1/2' || $pairing['result'] === '0.5-0.5');

                    $rowClass = '';
                    if ($isSeed && !$hasResult) $rowClass = 'bg-gradient-to-r from-green-50/60 to-emerald-50/30 border-l-4 border-green-400';
                    elseif ($isSeed && $hasResult) $rowClass = 'bg-gradient-to-r from-green-50/30 to-white border-l-4 border-green-300';
                    elseif (!$hasResult) $rowClass = 'bg-amber-50/30';
                    else $rowClass = 'bg-white';
                ?>
                <tr class="<?php echo $rowClass; ?> hover:bg-blue-50/40 transition group">
                    <td class="px-4 py-3.5 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <span class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-700 group-hover:bg-gray-200 transition"><?php echo (int)$pairing['table_no']; ?></span>
                            <?php if ($isSeed): ?>
                                <span class="seed-badge text-[9px] px-1.5 py-0.5 rounded font-bold">S</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php
                            $mDate = trim($pairing['match_date'] ?? '');
                            $mTime = trim($pairing['match_time'] ?? '');
                        ?>
                        <?php if ($mDate || $mTime): ?>
                        <div class="flex flex-col items-center gap-0.5">
                            <?php if ($mDate): ?>
                            <span class="text-xs font-medium text-gray-700"><?php echo htmlspecialchars($mDate); ?></span>
                            <?php endif; ?>
                            <?php if ($mTime): ?>
                            <span class="text-[11px] text-blue-600 font-semibold"><?php echo htmlspecialchars($mTime); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span class="text-[11px] text-gray-300 italic">Belirlenmedi</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if ($whiteWon): ?><span class="text-green-500 text-sm">♔</span><?php endif; ?>
                                    <span class="text-sm font-medium <?php echo $whiteWon ? 'font-bold text-green-700' : ($isDraw ? 'text-amber-700' : 'text-gray-900'); ?>">
                                        <?php echo htmlspecialchars($pairing['white_name'] ?? '?'); ?>
                                    </span>
                                    <?php if ((int)($pairing['white_is_seed'] ?? 0)): ?><span class="text-amber-500 text-xs">⭐</span><?php endif; ?>
                                </div>
                                <span class="text-[11px] text-gray-400"><?php
                                    $wi = [];
                                    if (!empty($pairing['white_sinif'])) $wi[] = htmlspecialchars($pairing['white_sinif']);
                                    if (!empty($pairing['white_school_no'])) $wi[] = '#' . htmlspecialchars($pairing['white_school_no']);
                                    echo implode(' / ', $wi);
                                ?></span>
                            </div>
                            <div class="w-6 h-6 rounded border-2 border-gray-300 bg-white shadow-sm flex-shrink-0"></div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($whiteWon): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-sm">1 - 0</span>
                        <?php elseif ($blackWon): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-sm">0 - 1</span>
                        <?php elseif ($isDraw): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-700 border border-amber-300">½ - ½</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-500 border border-blue-200 animate-pulse">VS</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-left">
                        <?php if (empty($pairing['black_player_id'])): ?>
                            <span class="text-sm text-gray-400 italic">Bay (Rakip Yok)</span>
                        <?php else: ?>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded bg-gray-800 border-2 border-gray-700 shadow-sm flex-shrink-0"></div>
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <?php if ((int)($pairing['black_is_seed'] ?? 0)): ?><span class="text-amber-500 text-xs">⭐</span><?php endif; ?>
                                    <span class="text-sm font-medium <?php echo $blackWon ? 'font-bold text-green-700' : ($isDraw ? 'text-amber-700' : 'text-gray-900'); ?>">
                                        <?php echo htmlspecialchars($pairing['black_name'] ?? '?'); ?>
                                    </span>
                                    <?php if ($blackWon): ?><span class="text-green-500 text-sm">♚</span><?php endif; ?>
                                </div>
                                <span class="text-[11px] text-gray-400"><?php
                                    $bi = [];
                                    if (!empty($pairing['black_sinif'])) $bi[] = htmlspecialchars($pairing['black_sinif']);
                                    if (!empty($pairing['black_school_no'])) $bi[] = '#' . htmlspecialchars($pairing['black_school_no']);
                                    echo implode(' / ', $bi);
                                ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden divide-y divide-gray-100">
        <?php foreach ($pairings as $pairing):
            $hasResult = $pairing['result'] !== null && $pairing['result'] !== '';
            $isSeed = (int)$pairing['is_seed_table'] === 1;
            $whiteWon = $hasResult && $pairing['result'] === '1-0';
            $blackWon = $hasResult && $pairing['result'] === '0-1';
            $isDraw = $hasResult && ($pairing['result'] === '1/2-1/2' || $pairing['result'] === '0.5-0.5');
            $cardBg = $isSeed ? 'bg-green-50/40 border-l-4 border-green-400' : (!$hasResult ? 'bg-amber-50/30' : '');
        ?>
        <div class="px-4 py-4 <?php echo $cardBg; ?>">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-700"><?php echo (int)$pairing['table_no']; ?></span>
                    <?php if ($isSeed): ?><span class="seed-badge text-[9px] px-1.5 py-0.5 rounded-full font-bold">Seri Başı</span><?php endif; ?>
                </div>
                <?php if ($whiteWon): ?>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-900 text-white">1-0</span>
                <?php elseif ($blackWon): ?>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-gray-900 text-white">0-1</span>
                <?php elseif ($isDraw): ?>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-700">½-½</span>
                <?php else: ?>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-500 animate-pulse">VS</span>
                <?php endif; ?>
            </div>
            <?php
                $mDateM = trim($pairing['match_date'] ?? '');
                $mTimeM = trim($pairing['match_time'] ?? '');
            ?>
            <?php if ($mDateM || $mTimeM): ?>
            <div class="flex items-center gap-1.5 mb-3 text-[11px]">
                <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?php if ($mDateM): ?><span class="font-medium text-gray-600"><?php echo htmlspecialchars($mDateM); ?></span><?php endif; ?>
                <?php if ($mTimeM): ?><span class="font-semibold text-blue-600"><?php echo htmlspecialchars($mTimeM); ?></span><?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mb-3"></div>
            <?php endif; ?>
            <div class="flex items-center">
                <div class="flex-1 text-right pr-3">
                    <div class="w-5 h-5 rounded border-2 border-gray-300 bg-white inline-block mb-1"></div>
                    <p class="text-sm font-medium <?php echo $whiteWon ? 'font-bold text-green-700' : 'text-gray-900'; ?>">
                        <?php echo htmlspecialchars($pairing['white_name'] ?? '?'); ?>
                    </p>
                    <p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($pairing['white_sinif'] ?? ''); ?></p>
                </div>
                <div class="w-px h-10 bg-gray-200"></div>
                <div class="flex-1 pl-3">
                    <?php if (empty($pairing['black_player_id'])): ?>
                        <p class="text-sm text-gray-400 italic">Bay</p>
                    <?php else: ?>
                        <div class="w-5 h-5 rounded bg-gray-800 inline-block mb-1"></div>
                        <p class="text-sm font-medium <?php echo $blackWon ? 'font-bold text-green-700' : 'text-gray-900'; ?>">
                            <?php echo htmlspecialchars($pairing['black_name'] ?? '?'); ?>
                        </p>
                        <p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($pairing['black_sinif'] ?? ''); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Gösterge -->
<div class="card p-4">
    <div class="flex flex-wrap gap-4 text-xs text-gray-600">
        <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded bg-gradient-to-r from-green-50 to-emerald-50 border border-green-300"></span>
            <span>Seri Başı Masası</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded bg-amber-50 border border-amber-200"></span>
            <span>Sonuç Bekleniyor</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-green-500 font-bold">♔</span>
            <span>Kazanan</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-amber-500">⭐</span>
            <span>Seri Başı Oyuncu</span>
        </div>
    </div>
</div>

<?php endif; ?>


<?php include 'footer.php'; ?>
