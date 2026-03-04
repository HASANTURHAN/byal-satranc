<?php
/**
 * API Endpoint: GET /api/standings.php
 * Returns JSON with player standings and per-round scores.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../db.php';

try {
    // Get total number of rounds from pairings
    $maxRound = (int) $pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();

    // Fetch all players sorted by total_points DESC, name ASC
    $players = $pdo->query("
        SELECT id, name, sinif, is_seed, total_points
        FROM players
        ORDER BY total_points DESC, name ASC
    ")->fetchAll();

    // Build per-round scores for each player in a single pass
    // Get all pairings that have results
    $allPairings = $pdo->query("
        SELECT round, white_player_id, black_player_id, white_points, black_points, result
        FROM pairings
        ORDER BY round ASC
    ")->fetchAll();

    // Build a lookup: player_id => [round => points]
    $roundScores = [];
    foreach ($allPairings as $p) {
        $round = (int) $p['round'];
        $hasResult = $p['result'] !== null && $p['result'] !== '';

        if ($p['white_player_id']) {
            $pid = (int) $p['white_player_id'];
            if (!isset($roundScores[$pid])) {
                $roundScores[$pid] = [];
            }
            if ($hasResult) {
                // Player played this round and has a result
                $roundScores[$pid][$round] = (float) $p['white_points'];
            } else {
                // Player is paired but no result yet - mark as "pending"
                if (!isset($roundScores[$pid][$round])) {
                    $roundScores[$pid][$round] = 'pending';
                }
            }
        }

        if ($p['black_player_id']) {
            $pid = (int) $p['black_player_id'];
            if (!isset($roundScores[$pid])) {
                $roundScores[$pid] = [];
            }
            if ($hasResult) {
                $roundScores[$pid][$round] = (float) $p['black_points'];
            } else {
                if (!isset($roundScores[$pid][$round])) {
                    $roundScores[$pid][$round] = 'pending';
                }
            }
        }
    }

    // Build output
    $output = [];
    $rank = 1;
    foreach ($players as $player) {
        $pid = (int) $player['id'];
        $rounds = [];

        for ($r = 1; $r <= $maxRound; $r++) {
            if (isset($roundScores[$pid]) && array_key_exists($r, $roundScores[$pid])) {
                $rounds[(string) $r] = $roundScores[$pid][$r]; // float or "pending"
            } else {
                $rounds[(string) $r] = null; // not paired in this round
            }
        }

        $output[] = [
            'rank'         => $rank,
            'name'         => $player['name'],
            'sinif'        => $player['sinif'],
            'is_seed'      => (int) $player['is_seed'],
            'total_points' => (float) $player['total_points'],
            'rounds'       => $rounds,
        ];
        $rank++;
    }

    echo json_encode([
        'players'    => $output,
        'max_round'  => $maxRound,
        'updated_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'      => 'Veri alinamadi',
        'updated_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
}
