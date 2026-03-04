<?php
require_once 'db.php';

// Fetch all players sorted by total_points DESC, then name ASC
$players = $pdo->query("
    SELECT * FROM players
    ORDER BY total_points DESC, name ASC
")->fetchAll();

$totalPlayers = count($players);
$seedCount = 0;
foreach ($players as $p) {
    if ($p['is_seed']) $seedCount++;
}

// Group by sinif for summary
$bySinif = [];
foreach ($players as $p) {
    $sinif = $p['sinif'] ?: 'Belirtilmemis';
    if (!isset($bySinif[$sinif])) $bySinif[$sinif] = 0;
    $bySinif[$sinif]++;
}
ksort($bySinif);

include 'header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden rounded-2xl mb-8 shadow-2xl">
    <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-black rounded-2xl p-8 sm:p-12 relative border border-gray-700">
        <div class="absolute inset-0 opacity-10">
            <div class="chess-pattern w-full h-full"></div>
        </div>
        <div class="absolute top-8 right-12 text-7xl opacity-5 chess-piece-float hidden lg:block">&#9822;</div>
        <div class="absolute bottom-6 left-16 text-5xl opacity-5 chess-piece-float" style="animation-delay: 1.5s;">&#9820;</div>

        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-amber-500/20 to-yellow-500/20 border border-amber-500/30 text-amber-300 text-xs font-bold mb-5 shadow-lg">
                <span class="text-base">&#9822;</span>
                <span>Turnuva Kadrosu</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white mb-3 leading-tight">Katilimcilar</h1>
            <p class="text-gray-400 text-base sm:text-lg">
                <span class="text-amber-400 font-bold"><?php echo $totalPlayers; ?> Oyuncu</span>
                <span class="mx-2 text-gray-600">|</span>
                Turnuvaya kayitli tum oyuncular
            </p>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
    <div class="card p-5 text-center group">
        <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">&#9823;</div>
        <div class="text-3xl font-extrabold text-gray-900"><?php echo $totalPlayers; ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Toplam Oyuncu</div>
    </div>
    <div class="card p-5 text-center group">
        <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">&#9733;</div>
        <div class="text-3xl font-extrabold text-amber-600"><?php echo $seedCount; ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Seri Basi</div>
    </div>
    <div class="card p-5 text-center group col-span-2 sm:col-span-1">
        <div class="text-2xl mb-2 group-hover:scale-110 transition-transform">&#9814;</div>
        <div class="text-3xl font-extrabold text-gray-900"><?php echo count($bySinif); ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Farkli Sinif</div>
    </div>
</div>

<!-- Class Badges -->
<?php if (count($bySinif) > 1): ?>
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($bySinif as $sinif => $count): ?>
    <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap shadow-sm">
        <?php echo htmlspecialchars($sinif); ?>
        <span class="ml-1.5 bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full text-[10px] font-bold"><?php echo $count; ?></span>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($players)): ?>

<!-- Search / Filter -->
<div class="card p-4 mb-6">
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <input
            type="text"
            id="playerSearch"
            placeholder="Oyuncu ara (isim, sinif veya okul no)..."
            class="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-amber-400 focus:ring-2 focus:ring-amber-100 outline-none text-sm transition bg-gray-50 focus:bg-white"
        >
        <div id="searchCount" class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs text-gray-400 font-medium"></div>
    </div>
</div>

<!-- Players Table -->
<div class="card overflow-hidden mb-8">
    <div class="overflow-x-auto">
        <table class="min-w-full" id="playersTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 sm:px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-14">Sira</th>
                    <th class="px-4 sm:px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ad Soyad</th>
                    <th class="px-4 sm:px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Sinif</th>
                    <th class="px-4 sm:px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Okul No</th>
                    <th class="px-4 sm:px-5 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Toplam Puan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="playersBody">
                <?php foreach ($players as $i => $player): ?>
                <tr class="<?php echo $player['is_seed'] ? 'seed-row' : ''; ?> hover:bg-blue-50/50 transition group player-row"
                    data-name="<?php echo htmlspecialchars(mb_strtolower($player['name'], 'UTF-8')); ?>"
                    data-sinif="<?php echo htmlspecialchars(mb_strtolower($player['sinif'] ?? '', 'UTF-8')); ?>"
                    data-schoolno="<?php echo htmlspecialchars($player['school_no'] ?? ''); ?>"
                >
                    <td class="px-4 sm:px-5 py-4 text-sm font-medium text-gray-400">
                        <span class="player-rank"><?php echo ($i + 1); ?>.</span>
                    </td>
                    <td class="px-4 sm:px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full <?php echo $player['is_seed'] ? 'bg-gradient-to-br from-amber-400 to-amber-600' : 'bg-gray-900'; ?> flex items-center justify-center text-white font-semibold text-sm flex-shrink-0 transition-transform group-hover:scale-105">
                                <?php echo htmlspecialchars(mb_substr($player['name'], 0, 1, 'UTF-8')); ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($player['name']); ?></span>
                                    <?php if ($player['is_seed']): ?>
                                    <span class="seed-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap">
                                        &#9733; Seri Basi
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-400 sm:hidden block mt-0.5">
                                    <?php echo htmlspecialchars($player['sinif'] ?? ''); ?>
                                    <?php if ($player['school_no']): ?>
                                        <span class="mx-1">-</span>#<?php echo htmlspecialchars($player['school_no']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 sm:px-5 py-4 text-sm text-gray-600 hidden sm:table-cell">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-100 text-xs font-medium">
                            <?php echo htmlspecialchars($player['sinif'] ?? '-'); ?>
                        </span>
                    </td>
                    <td class="px-4 sm:px-5 py-4 text-sm text-gray-500 text-center hidden sm:table-cell">
                        <?php echo htmlspecialchars($player['school_no'] ?? '-'); ?>
                    </td>
                    <td class="px-4 sm:px-5 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm transition-transform group-hover:scale-105
                            <?php
                            if ($player['total_points'] > 0) echo 'bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 border border-green-200';
                            else echo 'bg-gray-50 text-gray-500 border border-gray-200';
                            ?>">
                            <?php echo number_format($player['total_points'], 1); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- No results message (hidden by default) -->
    <div id="noResults" class="hidden p-12 text-center">
        <div class="text-4xl mb-3 opacity-40">&#9822;</div>
        <p class="text-sm font-medium text-gray-500">Aramanizla eslesen oyuncu bulunamadi.</p>
    </div>
</div>

<?php else: ?>
<div class="card p-12 text-center">
    <div class="text-5xl mb-4 opacity-40">&#9812;</div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Henuz katilimci yok</h3>
    <p class="text-sm text-gray-500">Sisteme henuz oyuncu eklenmemis.</p>
</div>
<?php endif; ?>

<!-- Client-side search/filter script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('playerSearch');
    const searchCount = document.getElementById('searchCount');
    const noResults = document.getElementById('noResults');
    const tableEl = document.getElementById('playersTable');
    const rows = document.querySelectorAll('.player-row');
    const totalRows = rows.length;

    if (!searchInput || !rows.length) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(function(row) {
            const name = row.getAttribute('data-name') || '';
            const sinif = row.getAttribute('data-sinif') || '';
            const schoolNo = row.getAttribute('data-schoolno') || '';

            const matches = !query ||
                name.indexOf(query) !== -1 ||
                sinif.indexOf(query) !== -1 ||
                schoolNo.indexOf(query) !== -1;

            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update visible rank numbers
        let rank = 1;
        rows.forEach(function(row) {
            if (row.style.display !== 'none') {
                var rankEl = row.querySelector('.player-rank');
                if (rankEl) rankEl.textContent = rank + '.';
                rank++;
            }
        });

        // Show/hide no results message
        if (visibleCount === 0 && query) {
            noResults.classList.remove('hidden');
            if (tableEl) tableEl.style.display = 'none';
        } else {
            noResults.classList.add('hidden');
            if (tableEl) tableEl.style.display = '';
        }

        // Update search count indicator
        if (query) {
            searchCount.textContent = visibleCount + ' / ' + totalRows;
        } else {
            searchCount.textContent = '';
        }
    });
});
</script>

<?php include 'footer.php'; ?>
