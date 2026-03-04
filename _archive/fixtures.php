<?php
require_once 'db.php';
include 'header.php';

$matches = $pdo->query("
    SELECT m.*, p1.name as player1_name, p1.class_name as player1_class,
           p2.name as player2_name, p2.class_name as player2_class
    FROM matches m
    LEFT JOIN players p1 ON m.player1_id = p1.id
    LEFT JOIN players p2 ON m.player2_id = p2.id
    ORDER BY m.round_number ASC, m.id ASC
")->fetchAll();

$rounds = [];
foreach ($matches as $match) {
    $rounds[$match['round_number']][] = $match;
}
?>

<div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
        <span class="text-3xl">♟</span>
        <h2 class="text-3xl font-bold text-gray-900">Fikstur</h2>
    </div>
    <p class="text-sm text-gray-500">Turnuva boyunca tum maclarin listesi ve sonuclari.</p>
</div>

<?php if (empty($rounds)): ?>
<div class="card p-12 text-center">
    <div class="text-4xl mb-4">&#9812;</div>
    <h3 class="text-base font-semibold text-gray-900 mb-2">Henuz eslestirme yok</h3>
    <p class="text-sm text-gray-500">Turnuva eslesmeleri hakem tarafindan olusturulmadi.</p>
</div>
<?php else: ?>
<div class="space-y-6 mb-8">
    <?php foreach (array_reverse($rounds, true) as $round_number => $round_matches):
        $completed = 0;
        $total = count($round_matches);
        foreach ($round_matches as $m) { if ($m['status'] === 'completed') $completed++; }
    ?>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">♞</span>
                <h3 class="font-bold text-gray-900 text-lg"><?php echo $round_number; ?>. Tur</h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500 font-medium"><?php echo $completed; ?>/<?php echo $total; ?> mac</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-sm
                    <?php echo $completed === $total ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-amber-100 text-amber-700 border border-amber-200'; ?>">
                    <?php if ($completed === $total): ?>
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php endif; ?>
                    <?php echo $completed === $total ? 'Tamamlandi' : 'Devam Ediyor'; ?>
                </span>
            </div>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($round_matches as $match):
                $p1_won = $match['status'] === 'completed' && $match['result'] === '1-0';
                $p2_won = $match['status'] === 'completed' && $match['result'] === '0-1';
                $is_draw = $match['status'] === 'completed' && $match['result'] === '0.5-0.5';
            ?>
            <div class="px-5 py-4 flex items-center hover:bg-gray-50/70 transition group <?php echo $match['status'] === 'completed' ? 'bg-green-50/20' : ''; ?>">
                <!-- Player 1 -->
                <div class="flex-1 text-right pr-4 flex items-center justify-end gap-2">
                    <div class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            <?php if ($p1_won): ?>
                                <span class="text-green-600 font-bold text-base">♔</span>
                            <?php endif; ?>
                            <span class="text-sm font-medium <?php echo $p1_won ? 'font-bold text-green-700' : ($is_draw ? 'font-semibold text-amber-700' : 'text-gray-500'); ?>">
                                <?php echo htmlspecialchars($match['player1_name'] ?? 'Bay'); ?>
                            </span>
                        </div>
                        <?php if ($match['player1_class']): ?>
                        <span class="text-xs text-gray-400 block mt-0.5"><?php echo htmlspecialchars($match['player1_class']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="w-6 h-6 rounded border-2 border-gray-300 bg-white hidden sm:block flex-shrink-0"></div>
                </div>

                <!-- Score -->
                <div class="px-4 flex-shrink-0">
                    <?php if ($match['status'] === 'completed'): ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-md transition-transform group-hover:scale-105
                            <?php echo $is_draw ? 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-300' : 'bg-gradient-to-r from-gray-800 to-gray-900 text-white'; ?>">
                            <?php echo $match['result'] === '0.5-0.5' ? '½-½' : htmlspecialchars($match['result']); ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-50 text-blue-600 border border-blue-200">VS</span>
                    <?php endif; ?>
                </div>

                <!-- Player 2 -->
                <div class="flex-1 pl-4 flex items-center gap-2">
                    <div class="w-6 h-6 rounded border-2 border-gray-700 bg-gray-800 hidden sm:block flex-shrink-0"></div>
                    <div class="text-left">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium <?php echo empty($match['player2_name']) ? 'text-gray-400 italic' : ($p2_won ? 'font-bold text-green-700' : ($is_draw ? 'font-semibold text-amber-700' : 'text-gray-500')); ?>">
                                <?php echo $match['player2_name'] ? htmlspecialchars($match['player2_name']) : 'Bay (Oynamadan Gecer)'; ?>
                            </span>
                            <?php if ($p2_won): ?>
                                <span class="text-green-600 font-bold text-base">♚</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($match['player2_class'])): ?>
                        <span class="text-xs text-gray-400 block mt-0.5"><?php echo htmlspecialchars($match['player2_class']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
