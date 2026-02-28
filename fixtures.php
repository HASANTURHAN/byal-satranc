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

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Fikstur</h2>
    <p class="text-sm text-gray-500 mt-1">Turnuva boyunca tum maclarin listesi.</p>
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
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900"><?php echo $round_number; ?>. Tur</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400"><?php echo $completed; ?>/<?php echo $total; ?></span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    <?php echo $completed === $total ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                    <?php echo $completed === $total ? 'Tamamlandi' : 'Devam Ediyor'; ?>
                </span>
            </div>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($round_matches as $match): ?>
            <div class="px-5 py-3 flex items-center hover:bg-gray-50 transition">
                <div class="flex-1 text-right pr-3">
                    <span class="text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($match['player1_name'] ?? 'Bay'); ?>
                    </span>
                    <?php if ($match['player1_class']): ?>
                    <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['player1_class']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="px-3 flex-shrink-0">
                    <?php if ($match['status'] === 'completed'): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold
                            <?php echo $match['result'] === '0.5-0.5' ? 'bg-amber-100 text-amber-700' : 'bg-gray-900 text-white'; ?>">
                            <?php echo $match['result'] === '0.5-0.5' ? '½-½' : htmlspecialchars($match['result']); ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-600">VS</span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 pl-3">
                    <span class="text-sm font-medium <?php echo empty($match['player2_name']) ? 'text-gray-400 italic' : 'text-gray-900'; ?>">
                        <?php echo $match['player2_name'] ? htmlspecialchars($match['player2_name']) : 'Bay (Oynamadan Gecer)'; ?>
                    </span>
                    <?php if (!empty($match['player2_class'])): ?>
                    <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($match['player2_class']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
