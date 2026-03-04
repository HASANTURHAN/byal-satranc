<?php
require_once 'db.php';

// Get all distinct rounds from pairings
$allRounds = $pdo->query("SELECT DISTINCT round FROM pairings ORDER BY round ASC")->fetchAll(PDO::FETCH_COLUMN);

// Determine selected round: use ?round=N param or default to latest
$selectedRound = isset($_GET['round']) ? (int)$_GET['round'] : 0;
if ($selectedRound <= 0 && !empty($allRounds)) {
    $selectedRound = (int)end($allRounds);
}

// Fetch pairings for the selected round with player info
$pairings = [];
$totalTables = 0;
$completedCount = 0;
$pendingCount = 0;

if ($selectedRound > 0) {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.round,
            p.table_no,
            p.is_seed_table,
            p.result,
            p.white_points,
            p.black_points,
            p.white_photo,
            p.black_photo,
            p.played_at,
            p.white_player_id,
            p.black_player_id,
            w.name AS white_name,
            w.sinif AS white_sinif,
            w.school_no AS white_school_no,
            w.is_seed AS white_is_seed,
            b.name AS black_name,
            b.sinif AS black_sinif,
            b.school_no AS black_school_no,
            b.is_seed AS black_is_seed
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.round = ?
        ORDER BY p.table_no ASC
    ");
    $stmt->execute([$selectedRound]);
    $pairings = $stmt->fetchAll();

    $totalTables = count($pairings);
    foreach ($pairings as $pairing) {
        if ($pairing['result'] !== null && $pairing['result'] !== '') {
            $completedCount++;
        } else {
            $pendingCount++;
        }
    }
}

include 'header.php';
?>

<div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
        <span class="text-3xl">&#9823;</span>
        <h2 class="text-3xl font-bold text-gray-900">Fikstür</h2>
    </div>
    <p class="text-sm text-gray-500">Tur bazli eslesmeler ve mac sonuclari.</p>
</div>

<?php if (empty($allRounds)): ?>
<div class="card p-12 text-center">
    <div class="text-4xl mb-4">&#9812;</div>
    <h3 class="text-base font-semibold text-gray-900 mb-2">Henüz eslestirme yapilmadi</h3>
    <p class="text-sm text-gray-500">Turnuva eslesmeleri hakem tarafindan olusturuldugunda burada gorunecek.</p>
</div>
<?php else: ?>

<!-- Round Selector Tabs -->
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($allRounds as $round): ?>
    <a href="round.php?round=<?php echo (int)$round; ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?php echo $selectedRound == $round
                  ? 'bg-gray-900 text-white shadow-sm'
                  : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'; ?>">
        Tur <?php echo (int)$round; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?php echo $totalTables; ?></div>
        <div class="text-xs text-gray-500 font-medium mt-1">Toplam Masa</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-green-600"><?php echo $completedCount; ?></div>
        <div class="text-xs text-gray-500 font-medium mt-1">Tamamlanan</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-amber-600"><?php echo $pendingCount; ?></div>
        <div class="text-xs text-gray-500 font-medium mt-1">Bekleyen</div>
    </div>
</div>

<!-- Round Header -->
<div class="card overflow-hidden mb-8">
    <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">&#9822;</span>
            <h3 class="font-bold text-gray-900 text-lg">Tur <?php echo $selectedRound; ?> Eslesmeleri</h3>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500 font-medium"><?php echo $completedCount; ?>/<?php echo $totalTables; ?> mac</span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-sm
                <?php echo ($completedCount === $totalTables && $totalTables > 0)
                    ? 'bg-green-100 text-green-700 border border-green-200'
                    : 'bg-amber-100 text-amber-700 border border-amber-200'; ?>">
                <?php if ($completedCount === $totalTables && $totalTables > 0): ?>
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
                <?php echo ($completedCount === $totalTables && $totalTables > 0) ? 'Tamamlandi' : 'Devam Ediyor'; ?>
            </span>
        </div>
    </div>

    <?php if (empty($pairings)): ?>
    <div class="text-center py-12">
        <span class="text-4xl mb-3 block opacity-20">&#9823;</span>
        <p class="text-sm text-gray-500">Bu tura ait eslestirme bulunamadi.</p>
    </div>
    <?php else: ?>

    <!-- Desktop Table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Masa No</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Beyaz Oyuncu</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Sonuc</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Siyah Oyuncu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($pairings as $pairing):
                    $hasResult = $pairing['result'] !== null && $pairing['result'] !== '';
                    $isSeed = (int)$pairing['is_seed_table'] === 1;
                    $whiteWon = $hasResult && $pairing['result'] === '1-0';
                    $blackWon = $hasResult && $pairing['result'] === '0-1';
                    $isDraw = $hasResult && ($pairing['result'] === '1/2-1/2' || $pairing['result'] === '0.5-0.5');

                    // Row background
                    $rowClass = '';
                    if ($isSeed && !$hasResult) {
                        $rowClass = 'bg-gradient-to-r from-green-50/60 to-emerald-50/30 border-l-4 border-green-400';
                    } elseif ($isSeed && $hasResult) {
                        $rowClass = 'bg-gradient-to-r from-green-50/40 to-emerald-50/20 border-l-4 border-green-300';
                    } elseif (!$hasResult) {
                        $rowClass = 'bg-amber-50/40';
                    } else {
                        $rowClass = 'bg-white';
                    }

                    // Result display
                    $resultDisplay = 'Oynanmadi';
                    $resultClass = 'bg-gray-100 text-gray-500 border border-gray-200';
                    if ($whiteWon) {
                        $resultDisplay = '1 - 0';
                        $resultClass = 'bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-md';
                    } elseif ($blackWon) {
                        $resultDisplay = '0 - 1';
                        $resultClass = 'bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-md';
                    } elseif ($isDraw) {
                        $resultDisplay = '½ - ½';
                        $resultClass = 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-300';
                    } elseif (!$hasResult) {
                        $resultDisplay = 'VS';
                        $resultClass = 'bg-blue-50 text-blue-600 border border-blue-200';
                    }
                ?>
                <tr class="<?php echo $rowClass; ?> hover:bg-blue-50/50 transition group">
                    <!-- Masa No -->
                    <td class="px-4 py-4 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <span class="text-sm font-bold text-gray-700"><?php echo (int)$pairing['table_no']; ?></span>
                            <?php if ($isSeed): ?>
                                <span class="seed-badge text-[10px] px-1.5 py-0.5 rounded-full font-bold">S</span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Beyaz Oyuncu -->
                    <td class="px-4 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if ($whiteWon): ?>
                                        <span class="text-green-600 font-bold text-base">&#9812;</span>
                                    <?php endif; ?>
                                    <span class="text-sm font-medium <?php echo $whiteWon ? 'font-bold text-green-700' : ($isDraw ? 'font-semibold text-amber-700' : 'text-gray-900'); ?>">
                                        <?php echo htmlspecialchars($pairing['white_name'] ?? 'Bilinmiyor'); ?>
                                    </span>
                                    <?php if ((int)($pairing['white_is_seed'] ?? 0) === 1): ?>
                                        <span class="seed-badge text-[9px] px-1 py-0.5 rounded font-bold">SIRA</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-400 block mt-0.5">
                                    <?php
                                    $whiteInfo = [];
                                    if (!empty($pairing['white_sinif'])) $whiteInfo[] = htmlspecialchars($pairing['white_sinif']);
                                    if (!empty($pairing['white_school_no'])) $whiteInfo[] = '#' . htmlspecialchars($pairing['white_school_no']);
                                    echo implode(' / ', $whiteInfo);
                                    ?>
                                </span>
                            </div>
                            <div class="w-7 h-7 rounded border-2 border-gray-300 bg-white flex-shrink-0 shadow-sm"></div>
                        </div>
                    </td>

                    <!-- Sonuc -->
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm transition-transform group-hover:scale-105 <?php echo $resultClass; ?>">
                            <?php echo $resultDisplay; ?>
                        </span>
                    </td>

                    <!-- Siyah Oyuncu -->
                    <td class="px-4 py-4 text-left">
                        <?php if (empty($pairing['black_player_id'])): ?>
                            <span class="text-sm text-gray-400 italic">Bay (Rakip Yok)</span>
                        <?php else: ?>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded bg-gray-800 border-2 border-gray-700 flex-shrink-0 shadow-sm"></div>
                            <div class="text-left">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium <?php echo $blackWon ? 'font-bold text-green-700' : ($isDraw ? 'font-semibold text-amber-700' : 'text-gray-900'); ?>">
                                        <?php echo htmlspecialchars($pairing['black_name'] ?? 'Bilinmiyor'); ?>
                                    </span>
                                    <?php if ((int)($pairing['black_is_seed'] ?? 0) === 1): ?>
                                        <span class="seed-badge text-[9px] px-1 py-0.5 rounded font-bold">SIRA</span>
                                    <?php endif; ?>
                                    <?php if ($blackWon): ?>
                                        <span class="text-green-600 font-bold text-base">&#9818;</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-400 block mt-0.5">
                                    <?php
                                    $blackInfo = [];
                                    if (!empty($pairing['black_sinif'])) $blackInfo[] = htmlspecialchars($pairing['black_sinif']);
                                    if (!empty($pairing['black_school_no'])) $blackInfo[] = '#' . htmlspecialchars($pairing['black_school_no']);
                                    echo implode(' / ', $blackInfo);
                                    ?>
                                </span>
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

            $cardBg = '';
            if ($isSeed && !$hasResult) {
                $cardBg = 'bg-gradient-to-r from-green-50/60 to-emerald-50/30 border-l-4 border-green-400';
            } elseif ($isSeed && $hasResult) {
                $cardBg = 'bg-gradient-to-r from-green-50/40 to-emerald-50/20 border-l-4 border-green-300';
            } elseif (!$hasResult) {
                $cardBg = 'bg-amber-50/40';
            } else {
                $cardBg = 'bg-white';
            }

            $resultDisplay = 'VS';
            $resultClass = 'bg-blue-50 text-blue-600 border border-blue-200';
            if ($whiteWon) {
                $resultDisplay = '1-0';
                $resultClass = 'bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-md';
            } elseif ($blackWon) {
                $resultDisplay = '0-1';
                $resultClass = 'bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-md';
            } elseif ($isDraw) {
                $resultDisplay = '½-½';
                $resultClass = 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-300';
            }
        ?>
        <div class="px-4 py-4 <?php echo $cardBg; ?>">
            <!-- Masa No Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase">Masa</span>
                    <span class="text-sm font-bold text-gray-700"><?php echo (int)$pairing['table_no']; ?></span>
                    <?php if ($isSeed): ?>
                        <span class="seed-badge text-[10px] px-1.5 py-0.5 rounded-full font-bold">Seri Basli</span>
                    <?php endif; ?>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold <?php echo $resultClass; ?>">
                    <?php echo $resultDisplay; ?>
                </span>
            </div>

            <!-- Players -->
            <div class="flex items-center">
                <!-- White -->
                <div class="flex-1 text-right pr-3">
                    <div class="flex items-center justify-end gap-1.5">
                        <?php if ($whiteWon): ?>
                            <span class="text-green-600 text-sm">&#9812;</span>
                        <?php endif; ?>
                        <div class="w-5 h-5 rounded border-2 border-gray-300 bg-white flex-shrink-0"></div>
                    </div>
                    <p class="text-sm font-medium <?php echo $whiteWon ? 'font-bold text-green-700' : 'text-gray-900'; ?> mt-1">
                        <?php echo htmlspecialchars($pairing['white_name'] ?? 'Bilinmiyor'); ?>
                    </p>
                    <p class="text-[11px] text-gray-400">
                        <?php
                        $wInfo = [];
                        if (!empty($pairing['white_sinif'])) $wInfo[] = htmlspecialchars($pairing['white_sinif']);
                        if (!empty($pairing['white_school_no'])) $wInfo[] = '#' . htmlspecialchars($pairing['white_school_no']);
                        echo implode(' / ', $wInfo);
                        ?>
                    </p>
                </div>

                <!-- VS Divider -->
                <div class="flex-shrink-0 px-2">
                    <div class="w-px h-10 bg-gray-200 mx-auto"></div>
                </div>

                <!-- Black -->
                <div class="flex-1 pl-3">
                    <?php if (empty($pairing['black_player_id'])): ?>
                        <p class="text-sm text-gray-400 italic">Bay</p>
                    <?php else: ?>
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded bg-gray-800 border-2 border-gray-700 flex-shrink-0"></div>
                        <?php if ($blackWon): ?>
                            <span class="text-green-600 text-sm">&#9818;</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm font-medium <?php echo $blackWon ? 'font-bold text-green-700' : 'text-gray-900'; ?> mt-1">
                        <?php echo htmlspecialchars($pairing['black_name'] ?? 'Bilinmiyor'); ?>
                    </p>
                    <p class="text-[11px] text-gray-400">
                        <?php
                        $bInfo = [];
                        if (!empty($pairing['black_sinif'])) $bInfo[] = htmlspecialchars($pairing['black_sinif']);
                        if (!empty($pairing['black_school_no'])) $bInfo[] = '#' . htmlspecialchars($pairing['black_school_no']);
                        echo implode(' / ', $bInfo);
                        ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Legend -->
<div class="card p-4 mb-8">
    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Gosterge</h4>
    <div class="flex flex-wrap gap-4 text-xs text-gray-600">
        <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded bg-gradient-to-r from-green-50 to-emerald-50 border border-green-300"></span>
            <span>Seri Basli Masa</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded bg-amber-50 border border-amber-200"></span>
            <span>Sonuc Bekleniyor</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded bg-white border border-gray-200"></span>
            <span>Tamamlandi</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="seed-badge text-[9px] px-1.5 py-0.5 rounded font-bold">S</span>
            <span>Seri Basli Masa</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-green-600 font-bold">&#9812;</span>
            <span>Kazanan Oyuncu</span>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
