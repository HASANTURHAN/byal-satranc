<?php
require_once 'db.php';

// ── Server-side data (initial render) ──────────────────────────────────────

// Max round
$maxRound = (int) $pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();

// Players
$players = $pdo->query("
    SELECT id, name, sinif, is_seed, total_points
    FROM players
    ORDER BY total_points DESC, name ASC
")->fetchAll();

// Per-round scores lookup
$allPairings = $pdo->query("
    SELECT round, white_player_id, black_player_id, white_points, black_points, result
    FROM pairings
    ORDER BY round ASC
")->fetchAll();

$roundScores = [];
foreach ($allPairings as $p) {
    $round   = (int) $p['round'];
    $hasResult = $p['result'] !== null && $p['result'] !== '';

    if ($p['white_player_id']) {
        $pid = (int) $p['white_player_id'];
        if (!isset($roundScores[$pid])) $roundScores[$pid] = [];
        if ($hasResult) {
            $roundScores[$pid][$round] = (float) $p['white_points'];
        } else {
            if (!isset($roundScores[$pid][$round])) $roundScores[$pid][$round] = 'pending';
        }
    }
    if ($p['black_player_id']) {
        $pid = (int) $p['black_player_id'];
        if (!isset($roundScores[$pid])) $roundScores[$pid] = [];
        if ($hasResult) {
            $roundScores[$pid][$round] = (float) $p['black_points'];
        } else {
            if (!isset($roundScores[$pid][$round])) $roundScores[$pid][$round] = 'pending';
        }
    }
}

$now = date('Y-m-d H:i:s');

include 'header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <span class="text-3xl">&#9818;</span>
                <h2 class="text-3xl font-bold text-gray-900">Puan Durumu</h2>
            </div>
            <p class="text-sm text-gray-500">Turnuva genel siralaması ve tur bazli puanlar.</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- Refresh indicator -->
            <div id="refreshIndicator" class="hidden items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-blue-600 text-xs font-medium">
                <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Guncelleniyor...</span>
            </div>
            <!-- Last update timestamp -->
            <div id="lastUpdate" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Son guncelleme: <strong id="updateTime"><?php echo date('H:i:s'); ?></strong></span>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($players)): ?>

<!-- Podium (Top 3) -->
<?php if (count($players) >= 3): ?>
<div class="grid grid-cols-3 gap-4 mb-10 max-w-3xl mx-auto items-end" id="podiumSection">
    <!-- 2nd Place - Silver -->
    <div class="relative transform hover:scale-105 transition-all duration-300">
        <div class="card p-6 text-center border-2 border-gray-300 bg-gradient-to-b from-gray-50 to-white podium-silver">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center shadow-lg border-2 border-white">
                    <span class="text-xl">&#9815;</span>
                </div>
            </div>
            <div class="mt-6 mb-3">
                <div class="text-4xl font-black text-gray-600 mb-1">2</div>
                <div class="w-8 h-1 bg-gray-400 mx-auto rounded-full"></div>
            </div>
            <p class="text-sm font-bold text-gray-900 truncate mb-1" id="podium-2-name"><?php echo htmlspecialchars($players[1]['name']); ?></p>
            <p class="text-xs text-gray-500 mb-3" id="podium-2-sinif"><?php echo htmlspecialchars($players[1]['sinif'] ?? ''); ?></p>
            <div class="bg-white/60 rounded-lg p-2 border border-gray-200">
                <p class="text-2xl font-extrabold text-gray-700" id="podium-2-points"><?php echo number_format($players[1]['total_points'], 1); ?></p>
                <p class="text-[10px] text-gray-500 uppercase font-semibold">puan</p>
            </div>
        </div>
    </div>

    <!-- 1st Place - Gold -->
    <div class="relative transform hover:scale-105 transition-all duration-300 -mt-6">
        <div class="card p-8 text-center border-2 border-amber-300 bg-gradient-to-b from-amber-50 to-yellow-50 podium-gold">
            <div class="absolute -top-5 left-1/2 transform -translate-x-1/2">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-yellow-300 to-amber-500 flex items-center justify-center shadow-2xl border-3 border-white animate-pulse">
                    <span class="text-3xl">&#9812;</span>
                </div>
            </div>
            <div class="mt-8 mb-4">
                <div class="text-5xl font-black text-amber-600 mb-2">1</div>
                <div class="w-12 h-1.5 bg-gradient-to-r from-yellow-400 to-amber-500 mx-auto rounded-full"></div>
            </div>
            <p class="text-base font-bold text-gray-900 truncate mb-1" id="podium-1-name"><?php echo htmlspecialchars($players[0]['name']); ?></p>
            <p class="text-xs text-gray-600 mb-4" id="podium-1-sinif"><?php echo htmlspecialchars($players[0]['sinif'] ?? ''); ?></p>
            <div class="bg-white/70 rounded-xl p-3 border-2 border-amber-200 shadow-inner">
                <p class="text-3xl font-black text-amber-600" id="podium-1-points"><?php echo number_format($players[0]['total_points'], 1); ?></p>
                <p class="text-[10px] text-amber-700 uppercase font-bold tracking-wide">puan</p>
            </div>
        </div>
    </div>

    <!-- 3rd Place - Bronze -->
    <div class="relative transform hover:scale-105 transition-all duration-300">
        <div class="card p-6 text-center border-2 border-orange-300 bg-gradient-to-b from-orange-50 to-white podium-bronze">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-300 to-orange-500 flex items-center justify-center shadow-lg border-2 border-white">
                    <span class="text-xl">&#9814;</span>
                </div>
            </div>
            <div class="mt-6 mb-3">
                <div class="text-4xl font-black text-orange-600 mb-1">3</div>
                <div class="w-8 h-1 bg-orange-400 mx-auto rounded-full"></div>
            </div>
            <p class="text-sm font-bold text-gray-900 truncate mb-1" id="podium-3-name"><?php echo htmlspecialchars($players[2]['name']); ?></p>
            <p class="text-xs text-gray-500 mb-3" id="podium-3-sinif"><?php echo htmlspecialchars($players[2]['sinif'] ?? ''); ?></p>
            <div class="bg-white/60 rounded-lg p-2 border border-orange-200">
                <p class="text-2xl font-extrabold text-orange-700" id="podium-3-points"><?php echo number_format($players[2]['total_points'], 1); ?></p>
                <p class="text-[10px] text-orange-600 uppercase font-semibold">puan</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Full Standings Table -->
<div class="card overflow-hidden mb-8">
    <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-xl">&#9822;</span>
            <h3 class="font-bold text-gray-900 text-lg">Genel Siralama</h3>
        </div>
        <span class="text-xs text-gray-400 font-medium" id="playerCountBadge"><?php echo count($players); ?> oyuncu</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full" id="standingsTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-14">Sira</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Oyuncu</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Sinif</th>
                    <?php for ($r = 1; $r <= min($maxRound, 6); $r++): ?>
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell w-14">T<?php echo $r; ?></th>
                    <?php endfor; ?>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider font-bold">Toplam</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="standingsBody">
                <?php foreach ($players as $i => $player):
                    $pid = (int) $player['id'];

                    // Row background
                    $rowBg = '';
                    if ($i === 0) $rowBg = 'bg-amber-50/50';
                    elseif ($i === 1) $rowBg = 'bg-gray-50/50';
                    elseif ($i === 2) $rowBg = 'bg-orange-50/30';
                ?>
                <tr class="<?php echo $rowBg; ?> <?php echo $player['is_seed'] ? 'seed-row' : ''; ?> hover:bg-blue-50/50 transition group">
                    <!-- Rank -->
                    <td class="px-4 py-4 text-sm font-medium <?php echo $i < 3 ? 'text-gray-900 font-bold' : 'text-gray-400'; ?>">
                        <div class="flex items-center gap-1">
                            <?php
                            if ($i === 0) echo '<span class="text-lg">&#9812;</span>';
                            elseif ($i === 1) echo '<span class="text-lg">&#9815;</span>';
                            elseif ($i === 2) echo '<span class="text-lg">&#9814;</span>';
                            else echo ($i + 1) . '.';
                            ?>
                        </div>
                    </td>

                    <!-- Player name -->
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full <?php echo $player['is_seed'] ? 'bg-gradient-to-br from-amber-400 to-amber-600' : 'bg-gray-900'; ?> flex items-center justify-center text-white font-semibold text-xs flex-shrink-0 transition-transform group-hover:scale-105">
                                <?php echo htmlspecialchars(mb_substr($player['name'], 0, 1, 'UTF-8')); ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium text-gray-900 <?php echo $i < 3 ? 'font-bold' : ''; ?>">
                                        <?php echo htmlspecialchars($player['name']); ?>
                                    </span>
                                    <?php if ($player['is_seed']): ?>
                                    <span class="seed-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap">
                                        &#9733; Seri Basi
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-400 sm:hidden block mt-0.5">
                                    <?php echo htmlspecialchars($player['sinif'] ?? ''); ?>
                                </span>
                            </div>
                        </div>
                    </td>

                    <!-- Sinif -->
                    <td class="px-4 py-4 text-sm text-gray-600 hidden sm:table-cell">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-100 text-xs font-medium">
                            <?php echo htmlspecialchars($player['sinif'] ?? '-'); ?>
                        </span>
                    </td>

                    <!-- Per-round scores -->
                    <?php for ($r = 1; $r <= min($maxRound, 6); $r++):
                        $score = null;
                        $isPending = false;
                        $hasPairing = false;
                        if (isset($roundScores[$pid]) && array_key_exists($r, $roundScores[$pid])) {
                            $hasPairing = true;
                            if ($roundScores[$pid][$r] === 'pending') {
                                $isPending = true;
                            } else {
                                $score = $roundScores[$pid][$r];
                            }
                        }
                    ?>
                    <td class="px-3 py-4 text-center hidden lg:table-cell">
                        <?php if (!$hasPairing): ?>
                            <span class="text-gray-300 text-sm">-</span>
                        <?php elseif ($isPending): ?>
                            <span class="text-gray-300 text-sm" title="Sonuc bekleniyor">...</span>
                        <?php elseif ($score >= 1): ?>
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                <?php echo ($score == (int)$score) ? (int)$score : number_format($score, 1); ?>
                            </span>
                        <?php elseif ($score > 0): ?>
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">
                                &#189;
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-50 text-red-400 text-xs font-bold">
                                0
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php endfor; ?>

                    <!-- Total points -->
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm transition-transform group-hover:scale-105
                            <?php
                            if ($i === 0) echo 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-200';
                            elseif ($i === 1) echo 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 border border-gray-300';
                            elseif ($i === 2) echo 'bg-gradient-to-r from-orange-100 to-orange-200 text-orange-700 border border-orange-200';
                            else echo 'bg-gray-50 text-gray-700 border border-gray-200';
                            ?>">
                            <?php echo number_format($player['total_points'], 1); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scoring Info -->
<div class="card p-6 mb-8">
    <h3 class="font-semibold text-gray-900 mb-4">Puanlama Sistemi</h3>
    <div class="grid grid-cols-3 gap-4 text-center">
        <div class="p-3 rounded-xl bg-green-50 border border-green-100">
            <div class="text-2xl font-bold text-green-600">1</div>
            <div class="text-xs text-green-700 font-medium mt-1">Galibiyet</div>
        </div>
        <div class="p-3 rounded-xl bg-yellow-50 border border-yellow-100">
            <div class="text-2xl font-bold text-yellow-600">0.5</div>
            <div class="text-xs text-yellow-700 font-medium mt-1">Beraberlik</div>
        </div>
        <div class="p-3 rounded-xl bg-red-50 border border-red-100">
            <div class="text-2xl font-bold text-red-600">0</div>
            <div class="text-xs text-red-700 font-medium mt-1">Maglubiyet</div>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="card p-4 mb-8">
    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Gosterge</h4>
    <div class="flex flex-wrap gap-4 text-xs text-gray-600">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-700 text-[10px] font-bold">1</span>
            <span>Galibiyet</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">&#189;</span>
            <span>Beraberlik</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-50 text-red-400 text-[10px] font-bold">0</span>
            <span>Maglubiyet</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-gray-300 text-sm font-bold">-</span>
            <span>Eslesmesi yok</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="seed-badge text-[9px] px-1.5 py-0.5 rounded font-bold">&#9733; Seri Basi</span>
            <span>Seri basli oyuncu</span>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card p-12 text-center">
    <div class="text-5xl mb-4 opacity-40">&#9812;</div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Henuz puan durumu olusmadi</h3>
    <p class="text-sm text-gray-500">Sisteme oyuncu eklenmediginde veya mac oynanmadiginda burada gorunecek.</p>
</div>
<?php endif; ?>

<!-- AJAX Auto-Refresh Script -->
<script>
(function() {
    var REFRESH_INTERVAL = 30000; // 30 seconds
    var indicator = document.getElementById('refreshIndicator');
    var updateTimeEl = document.getElementById('updateTime');
    var standingsBody = document.getElementById('standingsBody');
    var playerCountBadge = document.getElementById('playerCountBadge');
    var isRefreshing = false;

    function formatScore(score) {
        if (score === null || score === undefined) return null;
        var n = parseFloat(score);
        if (n >= 1) {
            return (n === Math.floor(n)) ? Math.floor(n).toString() : n.toFixed(1);
        } else if (n > 0) {
            return '\u00BD'; // 1/2 character
        } else {
            return '0';
        }
    }

    function scoreClass(score) {
        if (score === null || score === undefined) return '';
        var n = parseFloat(score);
        if (n >= 1) return 'bg-green-100 text-green-700';
        if (n > 0) return 'bg-amber-100 text-amber-700';
        return 'bg-red-50 text-red-400';
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function rowBgClass(rank) {
        if (rank === 1) return 'bg-amber-50/50';
        if (rank === 2) return 'bg-gray-50/50';
        if (rank === 3) return 'bg-orange-50/30';
        return '';
    }

    function rankIcon(rank) {
        if (rank === 1) return '<span class="text-lg">&#9812;</span>';
        if (rank === 2) return '<span class="text-lg">&#9815;</span>';
        if (rank === 3) return '<span class="text-lg">&#9814;</span>';
        return rank + '.';
    }

    function pointsBadgeClass(rank) {
        if (rank === 1) return 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-200';
        if (rank === 2) return 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 border border-gray-300';
        if (rank === 3) return 'bg-gradient-to-r from-orange-100 to-orange-200 text-orange-700 border border-orange-200';
        return 'bg-gray-50 text-gray-700 border border-gray-200';
    }

    function buildRoundCells(player, maxRound) {
        var html = '';
        var limit = Math.min(maxRound, 6);
        for (var r = 1; r <= limit; r++) {
            var key = r.toString();
            var val = player.rounds[key];
            html += '<td class="px-3 py-4 text-center hidden lg:table-cell">';
            if (val === null || val === undefined) {
                // Not paired in this round
                html += '<span class="text-gray-300 text-sm">-</span>';
            } else if (val === 'pending') {
                // Paired but result not entered yet
                html += '<span class="text-gray-300 text-sm" title="Sonuc bekleniyor">...</span>';
            } else {
                var display = formatScore(val);
                var cls = scoreClass(val);
                html += '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full ' + cls + ' text-xs font-bold">' + display + '</span>';
            }
            html += '</td>';
        }
        return html;
    }

    function updatePodium(players) {
        if (players.length < 3) return;

        // Podium 1
        var el;
        el = document.getElementById('podium-1-name');
        if (el) el.textContent = players[0].name;
        el = document.getElementById('podium-1-sinif');
        if (el) el.textContent = players[0].sinif || '';
        el = document.getElementById('podium-1-points');
        if (el) el.textContent = players[0].total_points.toFixed(1);

        // Podium 2
        el = document.getElementById('podium-2-name');
        if (el) el.textContent = players[1].name;
        el = document.getElementById('podium-2-sinif');
        if (el) el.textContent = players[1].sinif || '';
        el = document.getElementById('podium-2-points');
        if (el) el.textContent = players[1].total_points.toFixed(1);

        // Podium 3
        el = document.getElementById('podium-3-name');
        if (el) el.textContent = players[2].name;
        el = document.getElementById('podium-3-sinif');
        if (el) el.textContent = players[2].sinif || '';
        el = document.getElementById('podium-3-points');
        if (el) el.textContent = players[2].total_points.toFixed(1);
    }

    function updateTable(data) {
        if (!standingsBody) return;

        var players = data.players || [];
        var maxRound = data.max_round || 0;

        // Update podium
        updatePodium(players);

        // Update player count
        if (playerCountBadge) {
            playerCountBadge.textContent = players.length + ' oyuncu';
        }

        // Update round header columns if round count changed
        var thead = document.querySelector('#standingsTable thead tr');
        if (thead) {
            // Remove existing round headers and re-add
            var existingRoundThs = thead.querySelectorAll('.round-header-col');
            existingRoundThs.forEach(function(th) { th.remove(); });

            // Find the "Toplam" th (last one)
            var toplamTh = thead.lastElementChild;
            var limit = Math.min(maxRound, 6);
            for (var r = 1; r <= limit; r++) {
                var th = document.createElement('th');
                th.className = 'px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell w-14 round-header-col';
                th.textContent = 'T' + r;
                thead.insertBefore(th, toplamTh);
            }
        }

        // Build table rows
        var html = '';
        for (var i = 0; i < players.length; i++) {
            var p = players[i];
            var rank = p.rank;
            var isSeed = p.is_seed ? true : false;
            var isTopThree = rank <= 3;
            var initial = p.name ? p.name.charAt(0) : '?';

            html += '<tr class="' + rowBgClass(rank) + ' ' + (isSeed ? 'seed-row' : '') + ' hover:bg-blue-50/50 transition group">';

            // Rank
            html += '<td class="px-4 py-4 text-sm font-medium ' + (isTopThree ? 'text-gray-900 font-bold' : 'text-gray-400') + '">';
            html += '<div class="flex items-center gap-1">' + rankIcon(rank) + '</div>';
            html += '</td>';

            // Player name
            html += '<td class="px-4 py-4">';
            html += '<div class="flex items-center gap-2.5">';
            html += '<div class="w-8 h-8 rounded-full ' + (isSeed ? 'bg-gradient-to-br from-amber-400 to-amber-600' : 'bg-gray-900') + ' flex items-center justify-center text-white font-semibold text-xs flex-shrink-0 transition-transform group-hover:scale-105">' + escapeHtml(initial) + '</div>';
            html += '<div class="min-w-0">';
            html += '<div class="flex items-center gap-2 flex-wrap">';
            html += '<span class="text-sm font-medium text-gray-900 ' + (isTopThree ? 'font-bold' : '') + '">' + escapeHtml(p.name) + '</span>';
            if (isSeed) {
                html += '<span class="seed-badge inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap">&#9733; Seri Basi</span>';
            }
            html += '</div>';
            html += '<span class="text-xs text-gray-400 sm:hidden block mt-0.5">' + escapeHtml(p.sinif || '') + '</span>';
            html += '</div></div>';
            html += '</td>';

            // Sinif
            html += '<td class="px-4 py-4 text-sm text-gray-600 hidden sm:table-cell">';
            html += '<span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-gray-50 border border-gray-100 text-xs font-medium">' + escapeHtml(p.sinif || '-') + '</span>';
            html += '</td>';

            // Per-round scores
            html += buildRoundCells(p, maxRound);

            // Total points
            html += '<td class="px-4 py-4 text-center">';
            html += '<span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm transition-transform group-hover:scale-105 ' + pointsBadgeClass(rank) + '">';
            html += p.total_points.toFixed(1);
            html += '</span></td>';

            html += '</tr>';
        }

        standingsBody.innerHTML = html;
    }

    function refreshStandings() {
        if (isRefreshing) return;
        isRefreshing = true;

        // Show spinner
        if (indicator) indicator.classList.remove('hidden');
        if (indicator) indicator.classList.add('inline-flex');

        fetch('api/standings.php?_t=' + Date.now())
            .then(function(response) {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(function(data) {
                updateTable(data);

                // Update timestamp
                if (updateTimeEl && data.updated_at) {
                    var timePart = data.updated_at.split(' ')[1] || '';
                    updateTimeEl.textContent = timePart;
                }
            })
            .catch(function(err) {
                console.error('Standings refresh failed:', err);
            })
            .finally(function() {
                isRefreshing = false;
                if (indicator) indicator.classList.add('hidden');
                if (indicator) indicator.classList.remove('inline-flex');
            });
    }

    // Auto-refresh every 30 seconds
    setInterval(refreshStandings, REFRESH_INTERVAL);

    // Also mark existing round header <th> elements so they can be replaced
    var existingRoundThs = document.querySelectorAll('#standingsTable thead th');
    existingRoundThs.forEach(function(th) {
        var text = th.textContent.trim();
        if (/^T\d+$/.test(text)) {
            th.classList.add('round-header-col');
        }
    });
})();
</script>

<?php include 'footer.php'; ?>
