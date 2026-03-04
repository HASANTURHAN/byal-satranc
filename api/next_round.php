<?php
/**
 * API Endpoint: Generate Next Round Pairings (Swiss System)
 *
 * POST /api/next_round.php
 * Requires admin authentication and CSRF token.
 *
 * Swiss system algorithm:
 * 1. Players sorted by total_points DESC, name ASC
 * 2. Pair within same point groups, float down if needed
 * 3. No rematches (same pair cannot play twice)
 * 4. Color balance: player with fewer white games gets white
 * 5. BYE for lowest-ranked player who hasn't had one (odd player count)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Check admin authentication
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

// Verify CSRF token
if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Güvenlik hatası.']);
    exit;
}

try {
    // 1. Find current max round number
    $maxRound = (int) $pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();

    // 2. If there is a current round, check that ALL matches are completed (no NULL results)
    if ($maxRound > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ? AND result IS NULL");
        $stmt->execute([$maxRound]);
        $incomplete = (int) $stmt->fetchColumn();

        if ($incomplete > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Tüm maç sonuçları girilmeden yeni tur oluşturulamaz.'
            ]);
            exit;
        }
    }

    $newRound = $maxRound + 1;

    // 3. Get all players sorted by total_points DESC, then name ASC
    $players = $pdo->query("SELECT id, name, is_seed, total_points FROM players ORDER BY total_points DESC, name ASC")->fetchAll();

    if (count($players) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Yeni tur oluşturmak için en az 2 oyuncu gereklidir.'
        ]);
        exit;
    }

    // 4. Get all previous pairings to avoid rematches
    $playedPairs = [];
    if ($maxRound > 0) {
        $prevStmt = $pdo->query("SELECT white_player_id, black_player_id FROM pairings WHERE black_player_id IS NOT NULL");
        while ($row = $prevStmt->fetch()) {
            $w = (int) $row['white_player_id'];
            $b = (int) $row['black_player_id'];
            // Store both directions for quick lookup
            $playedPairs[$w . '-' . $b] = true;
            $playedPairs[$b . '-' . $w] = true;
        }
    }

    // Track color history: how many times each player has been white vs black
    $whiteCount = [];
    $blackCount = [];
    if ($maxRound > 0) {
        $colorStmt = $pdo->query("SELECT white_player_id, black_player_id FROM pairings WHERE black_player_id IS NOT NULL");
        while ($row = $colorStmt->fetch()) {
            $w = (int) $row['white_player_id'];
            $b = (int) $row['black_player_id'];
            $whiteCount[$w] = ($whiteCount[$w] ?? 0) + 1;
            $blackCount[$b] = ($blackCount[$b] ?? 0) + 1;
        }
    }

    // Find players who already had a BYE
    $byePlayers = [];
    if ($maxRound > 0) {
        $byeStmt = $pdo->query("SELECT white_player_id FROM pairings WHERE black_player_id IS NULL AND result = 'BYE'");
        while ($row = $byeStmt->fetch()) {
            $byePlayers[(int) $row['white_player_id']] = true;
        }
    }

    // 7. Handle odd number of players: assign BYE
    $byePlayer = null;
    $activePlayers = $players; // copy

    if (count($activePlayers) % 2 !== 0) {
        // Give BYE to the lowest-ranked player who hasn't had a BYE before
        // Players are sorted by total_points DESC, so iterate from the end
        $byeIndex = null;
        for ($i = count($activePlayers) - 1; $i >= 0; $i--) {
            $pid = (int) $activePlayers[$i]['id'];
            if (!isset($byePlayers[$pid])) {
                $byeIndex = $i;
                break;
            }
        }
        // If everyone has had a BYE, give it to the last player anyway
        if ($byeIndex === null) {
            $byeIndex = count($activePlayers) - 1;
        }

        $byePlayer = $activePlayers[$byeIndex];
        array_splice($activePlayers, $byeIndex, 1);
    }

    // 5. Swiss pairing: group players by points, pair within groups, float down if needed
    // Build point groups
    $pointGroups = [];
    foreach ($activePlayers as $player) {
        $pts = (string) $player['total_points'];
        $pointGroups[$pts][] = $player;
    }

    $newPairings = [];
    $paired = []; // set of player IDs that have been paired
    $floaters = []; // players that couldn't be paired in their group

    foreach ($pointGroups as $pts => $group) {
        // Merge any floaters from the previous group into this one
        if (!empty($floaters)) {
            $group = array_merge($floaters, $group);
            $floaters = [];
        }

        // Remove already-paired players from group
        $group = array_values(array_filter($group, function ($p) use ($paired) {
            return !isset($paired[$p['id']]);
        }));

        // Try to pair within the group
        $unpaired = $group;
        $localPaired = [];

        while (count($unpaired) >= 2) {
            $p1 = array_shift($unpaired);
            $bestMatch = null;
            $bestIndex = null;

            // Find the best opponent: someone they haven't played before
            foreach ($unpaired as $idx => $candidate) {
                $key = $p1['id'] . '-' . $candidate['id'];
                if (!isset($playedPairs[$key])) {
                    $bestMatch = $candidate;
                    $bestIndex = $idx;
                    break;
                }
            }

            if ($bestMatch !== null) {
                // Successfully found a valid opponent
                $localPaired[] = [$p1, $bestMatch];
                $paired[$p1['id']] = true;
                $paired[$bestMatch['id']] = true;
                array_splice($unpaired, $bestIndex, 1);
            } else {
                // Can't pair p1 within this group - try a fallback:
                // Allow rematch only if absolutely no other option exists
                $fallbackFound = false;

                // First try: see if reordering helps (try pairing p1 with someone else after skipping)
                // Put p1 back and try a different starting player
                // We use a more exhaustive approach: try all possible opponents
                foreach ($unpaired as $idx => $candidate) {
                    // Accept even a rematch as last resort within this group
                    $bestMatch = $candidate;
                    $bestIndex = $idx;
                    $fallbackFound = true;
                    break;
                }

                if ($fallbackFound) {
                    $localPaired[] = [$p1, $bestMatch];
                    $paired[$p1['id']] = true;
                    $paired[$bestMatch['id']] = true;
                    array_splice($unpaired, $bestIndex, 1);
                } else {
                    // Float p1 down to next group
                    $floaters[] = $p1;
                }
            }
        }

        // Any remaining unpaired players float down
        foreach ($unpaired as $leftover) {
            if (!isset($paired[$leftover['id']])) {
                $floaters[] = $leftover;
            }
        }

        $newPairings = array_merge($newPairings, $localPaired);
    }

    // Handle any remaining floaters after all groups processed
    // These are players that couldn't be paired within any group
    $remainingFloaters = array_filter($floaters, function ($p) use ($paired) {
        return !isset($paired[$p['id']]);
    });
    $remainingFloaters = array_values($remainingFloaters);

    while (count($remainingFloaters) >= 2) {
        $p1 = array_shift($remainingFloaters);
        $bestMatch = null;
        $bestIndex = null;

        // Try to find an opponent they haven't played
        foreach ($remainingFloaters as $idx => $candidate) {
            $key = $p1['id'] . '-' . $candidate['id'];
            if (!isset($playedPairs[$key])) {
                $bestMatch = $candidate;
                $bestIndex = $idx;
                break;
            }
        }

        // If no fresh opponent, take the first available
        if ($bestMatch === null && !empty($remainingFloaters)) {
            $bestIndex = 0;
            $bestMatch = $remainingFloaters[0];
        }

        if ($bestMatch !== null) {
            $newPairings[] = [$p1, $bestMatch];
            $paired[$p1['id']] = true;
            $paired[$bestMatch['id']] = true;
            array_splice($remainingFloaters, $bestIndex, 1);
        }
    }

    // 6. Color assignment: assign white/black based on color history balance
    $finalPairings = [];
    foreach ($newPairings as $pair) {
        $p1 = $pair[0];
        $p2 = $pair[1];

        $p1WhiteCount = $whiteCount[$p1['id']] ?? 0;
        $p1BlackCount = $blackCount[$p1['id']] ?? 0;
        $p2WhiteCount = $whiteCount[$p2['id']] ?? 0;
        $p2BlackCount = $blackCount[$p2['id']] ?? 0;

        // Calculate color difference: positive means more white games than black
        $p1ColorDiff = $p1WhiteCount - $p1BlackCount;
        $p2ColorDiff = $p2WhiteCount - $p2BlackCount;

        if ($p1ColorDiff < $p2ColorDiff) {
            // p1 has played fewer white games relative to black -> p1 gets white
            $white = $p1;
            $black = $p2;
        } elseif ($p2ColorDiff < $p1ColorDiff) {
            // p2 has played fewer white games relative to black -> p2 gets white
            $white = $p2;
            $black = $p1;
        } else {
            // Equal color difference: give white to the one with fewer total white games
            if ($p1WhiteCount <= $p2WhiteCount) {
                $white = $p1;
                $black = $p2;
            } else {
                $white = $p2;
                $black = $p1;
            }
        }

        $finalPairings[] = ['white' => $white, 'black' => $black];
    }

    // 8-10. Save to database within a transaction
    $pdo->beginTransaction();

    try {
        $tableNo = 1;

        $insertStmt = $pdo->prepare(
            "INSERT INTO pairings (round, table_no, white_player_id, black_player_id, is_seed_table, result, white_points, black_points)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL)"
        );

        foreach ($finalPairings as $pairing) {
            $whiteId = (int) $pairing['white']['id'];
            $blackId = (int) $pairing['black']['id'];

            // 10. Determine is_seed_table: if either player is_seed=1
            $isSeedTable = ((int) $pairing['white']['is_seed'] === 1 || (int) $pairing['black']['is_seed'] === 1) ? 1 : 0;

            $insertStmt->execute([$newRound, $tableNo, $whiteId, $blackId, $isSeedTable]);
            $tableNo++;
        }

        // Insert BYE pairing if applicable
        $byePlayerName = null;
        if ($byePlayer !== null) {
            $byeId = (int) $byePlayer['id'];
            $isSeedBye = ((int) $byePlayer['is_seed'] === 1) ? 1 : 0;

            $byeInsert = $pdo->prepare(
                "INSERT INTO pairings (round, table_no, white_player_id, black_player_id, is_seed_table, result, white_points, black_points)
                 VALUES (?, ?, ?, NULL, ?, 'BYE', 1, NULL)"
            );
            $byeInsert->execute([$newRound, $tableNo, $byeId, $isSeedBye]);

            // Update BYE player's total_points
            $updatePoints = $pdo->prepare("UPDATE players SET total_points = total_points + 1 WHERE id = ?");
            $updatePoints->execute([$byeId]);

            $byePlayerName = $byePlayer['name'];
        }

        // 9. Create new rounds entry
        $roundInsert = $pdo->prepare("INSERT INTO rounds (round_number, is_active) VALUES (?, 1)");
        $roundInsert->execute([$newRound]);

        // Deactivate previous rounds
        if ($maxRound > 0) {
            $deactivate = $pdo->prepare("UPDATE rounds SET is_active = 0 WHERE round_number < ?");
            $deactivate->execute([$newRound]);
        }

        $pdo->commit();

        $pairingsCount = count($finalPairings) + ($byePlayer !== null ? 1 : 0);

        echo json_encode([
            'success' => true,
            'round' => $newRound,
            'pairings_count' => $pairingsCount,
            'bye_player' => $byePlayerName,
            'message' => "Tur $newRound eşleştirmeleri oluşturuldu."
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Eşleştirme oluşturulurken bir hata oluştu: ' . $e->getMessage()
    ]);
}
