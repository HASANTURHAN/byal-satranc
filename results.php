<?php
require_once 'db.php';

// Get all distinct rounds that have at least one completed match
$roundsStmt = $pdo->query("SELECT DISTINCT round FROM pairings WHERE result IS NOT NULL ORDER BY round ASC");
$completedRounds = $roundsStmt->fetchAll(PDO::FETCH_COLUMN);

// Also get all rounds for the filter (even if not yet completed)
$allRoundsStmt = $pdo->query("SELECT DISTINCT round FROM pairings ORDER BY round ASC");
$allRounds = $allRoundsStmt->fetchAll(PDO::FETCH_COLUMN);

// Round filter from query parameter
$filterRound = isset($_GET['round']) ? (int)$_GET['round'] : null;

// Build the query for completed matches
$sql = "
    SELECT p.id, p.round, p.table_no, p.result, p.white_points, p.black_points,
           p.white_photo, p.black_photo, p.is_seed_table, p.played_at,
           w.id AS white_id, w.name AS white_name, w.sinif AS white_sinif,
           b.id AS black_id, b.name AS black_name, b.sinif AS black_sinif
    FROM pairings p
    LEFT JOIN players w ON p.white_player_id = w.id
    LEFT JOIN players b ON p.black_player_id = b.id
    WHERE p.result IS NOT NULL
";
$params = [];

if ($filterRound !== null && $filterRound > 0) {
    $sql .= " AND p.round = ?";
    $params[] = $filterRound;
}

$sql .= " ORDER BY p.round ASC, p.table_no ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

include 'header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
        <span class="text-3xl">&#9820;</span>
        <h2 class="text-3xl font-bold text-gray-900">Sonuçlar</h2>
    </div>
    <p class="text-sm text-gray-500">Tamamlanan maçların sonuçları ve detayları.</p>
</div>

<!-- Round Filter Buttons -->
<?php if (!empty($allRounds)): ?>
<div class="flex gap-2 mb-8 overflow-x-auto pb-2 scrollbar-thin">
    <a href="results.php"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?php echo $filterRound === null ? 'bg-gray-900 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'; ?>">
        Tüm Turlar
    </a>
    <?php foreach ($allRounds as $round): ?>
    <a href="results.php?round=<?php echo (int)$round; ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition
              <?php echo $filterRound === (int)$round ? 'bg-gray-900 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'; ?>">
        Tur <?php echo (int)$round; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Results Cards Grid -->
<?php if (empty($matches)): ?>
<div class="card p-12 text-center">
    <div class="text-5xl mb-4 opacity-30">&#9812;</div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Henüz tamamlanan maç bulunmuyor</h3>
    <p class="text-sm text-gray-500">Maçlar oynadıkça sonuçlar burada görünecektir.</p>
</div>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
    <?php foreach ($matches as $match):
        $result = $match['result'];
        $whiteWon = ($result === '1-0');
        $blackWon = ($result === '0-1');
        $isDraw   = ($result === '1/2-1/2' || $result === '0.5-0.5');

        // Determine highlight classes for each side
        if ($whiteWon) {
            $whiteBg    = 'bg-green-50 border-green-200';
            $whiteText  = 'text-green-700';
            $whiteRing  = 'ring-2 ring-green-400 ring-offset-1';
            $blackBg    = 'bg-red-50 border-red-200';
            $blackText  = 'text-red-600';
            $blackRing  = '';
            $resultBadge = 'bg-gradient-to-r from-green-500 to-emerald-600 text-white';
        } elseif ($blackWon) {
            $whiteBg    = 'bg-red-50 border-red-200';
            $whiteText  = 'text-red-600';
            $whiteRing  = '';
            $blackBg    = 'bg-green-50 border-green-200';
            $blackText  = 'text-green-700';
            $blackRing  = 'ring-2 ring-green-400 ring-offset-1';
            $resultBadge = 'bg-gradient-to-r from-green-500 to-emerald-600 text-white';
        } else {
            // Draw
            $whiteBg    = 'bg-blue-50 border-blue-200';
            $whiteText  = 'text-blue-700';
            $whiteRing  = '';
            $blackBg    = 'bg-blue-50 border-blue-200';
            $blackText  = 'text-blue-700';
            $blackRing  = '';
            $resultBadge = 'bg-gradient-to-r from-amber-400 to-yellow-400 text-amber-900';
        }

        // Format result display
        if ($isDraw) {
            $resultDisplay = '&#189; &ndash; &#189;';
        } else {
            $resultDisplay = htmlspecialchars($result);
        }
    ?>
    <div class="match-card card overflow-hidden">
        <!-- Card Header: Round & Table -->
        <div class="px-5 py-3 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-base">&#9823;</span>
                <span class="text-sm font-bold text-gray-800">Tur <?php echo (int)$match['round']; ?></span>
                <span class="text-gray-300">&ndash;</span>
                <span class="text-sm font-medium text-gray-500">Masa <?php echo (int)$match['table_no']; ?></span>
            </div>
            <?php if ($match['is_seed_table']): ?>
            <span class="seed-badge text-[10px] font-bold px-2 py-0.5 rounded-full">SERİ BAŞI</span>
            <?php endif; ?>
        </div>

        <!-- Players Section -->
        <div class="px-5 py-5">
            <div class="flex items-center justify-between gap-3">

                <!-- White Player -->
                <div class="flex-1 flex flex-col items-center text-center">
                    <div class="relative mb-2">
                        <div class="w-16 h-16 rounded-full border-2 overflow-hidden <?php echo $whiteBg; ?> <?php echo $whiteRing; ?> flex items-center justify-center shadow-sm">
                            <?php if (!empty($match['white_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($match['white_photo']); ?>" alt="<?php echo htmlspecialchars($match['white_name'] ?? 'Beyaz'); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-2xl" title="Beyaz">&#9812;</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($whiteWon): ?>
                        <div class="absolute -top-1 -right-1 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <?php endif; ?>
                        <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[10px] font-bold bg-white border border-gray-200 rounded-full px-1.5 py-0 shadow-sm text-gray-500">Beyaz</span>
                    </div>
                    <p class="text-sm font-semibold <?php echo $whiteText; ?> mt-2 leading-tight">
                        <?php echo htmlspecialchars($match['white_name'] ?? 'Bilinmiyor'); ?>
                    </p>
                    <?php if (!empty($match['white_sinif'])): ?>
                    <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($match['white_sinif']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- VS / Result Badge -->
                <div class="flex flex-col items-center gap-2 flex-shrink-0 px-2">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">vs</span>
                    <div class="<?php echo $resultBadge; ?> px-4 py-2 rounded-xl text-base font-black shadow-lg">
                        <?php echo $resultDisplay; ?>
                    </div>
                    <?php if (!empty($match['played_at'])): ?>
                    <span class="text-[10px] text-gray-400 mt-1"><?php echo date('d.m.Y', strtotime($match['played_at'])); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Black Player -->
                <div class="flex-1 flex flex-col items-center text-center">
                    <div class="relative mb-2">
                        <div class="w-16 h-16 rounded-full border-2 overflow-hidden <?php echo $blackBg; ?> <?php echo $blackRing; ?> flex items-center justify-center shadow-sm">
                            <?php if (!empty($match['black_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($match['black_photo']); ?>" alt="<?php echo htmlspecialchars($match['black_name'] ?? 'Siyah'); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-2xl" title="Siyah">&#9818;</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($blackWon): ?>
                        <div class="absolute -top-1 -right-1 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-md border-2 border-white">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <?php endif; ?>
                        <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[10px] font-bold bg-gray-800 text-white border border-gray-700 rounded-full px-1.5 py-0 shadow-sm">Siyah</span>
                    </div>
                    <p class="text-sm font-semibold <?php echo $blackText; ?> mt-2 leading-tight">
                        <?php echo htmlspecialchars($match['black_name'] ?? 'Bilinmiyor'); ?>
                    </p>
                    <?php if (!empty($match['black_sinif'])): ?>
                    <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($match['black_sinif']); ?></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Summary Stats -->
<div class="card p-5 mb-8">
    <div class="flex flex-wrap items-center justify-center gap-6 text-sm text-gray-600">
        <?php
        $totalMatches = count($matches);
        $whiteWins = 0;
        $blackWins = 0;
        $draws = 0;
        foreach ($matches as $m) {
            if ($m['result'] === '1-0') $whiteWins++;
            elseif ($m['result'] === '0-1') $blackWins++;
            else $draws++;
        }
        ?>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-gray-300 border border-gray-400 inline-block"></span>
            <span>Toplam: <strong class="text-gray-900"><?php echo $totalMatches; ?></strong> maç</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-white border-2 border-gray-400 inline-block"></span>
            <span>Beyaz Galip: <strong class="text-gray-900"><?php echo $whiteWins; ?></strong></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-gray-800 border border-gray-900 inline-block"></span>
            <span>Siyah Galip: <strong class="text-gray-900"><?php echo $blackWins; ?></strong></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-400 border border-amber-500 inline-block"></span>
            <span>Berabere: <strong class="text-gray-900"><?php echo $draws; ?></strong></span>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include 'footer.php'; ?>
