<?php
require_once 'db.php';

// Parametreleri al
$type = $_GET['type'] ?? '';
$round = isset($_GET['round']) ? (int)$_GET['round'] : 0;
$pairingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Turnuva bilgileri
$tournamentName = get_setting('tournament_name', '2025-2026 Okul Satranç Turnuvası');
$schoolName = 'Sultangazi Bahattin Yıldız Anadolu Lisesi';
$refereeName = get_setting('referee_name', '');

// Turkce tarih formatlama
function formatTurkishDatePrint($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return htmlspecialchars($dateStr);
    $aylar = ['', 'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    $gunler = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
    $gun = (int)date('j', $ts);
    $ay = (int)date('n', $ts);
    $yil = date('Y', $ts);
    $haftaGunu = (int)date('w', $ts);
    return $gun . ' ' . $aylar[$ay] . ' ' . $yil . ', ' . $gunler[$haftaGunu];
}

// Eşleşmeleri cek (tura gore veya tekil)
$pairings = [];

if ($type === 'pairings' && $round > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*,
               w.name AS white_name, w.sinif AS white_sinif, w.school_no AS white_school_no,
               b.name AS black_name, b.sinif AS black_sinif, b.school_no AS black_school_no
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.round = ?
        ORDER BY p.table_no ASC
    ");
    $stmt->execute([$round]);
    $pairings = $stmt->fetchAll();
} elseif ($type === 'form' && $round > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*,
               w.name AS white_name, w.sinif AS white_sinif, w.school_no AS white_school_no,
               b.name AS black_name, b.sinif AS black_sinif, b.school_no AS black_school_no
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.round = ?
        ORDER BY p.table_no ASC
    ");
    $stmt->execute([$round]);
    $pairings = $stmt->fetchAll();
} elseif ($type === 'form' && $pairingId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*,
               w.name AS white_name, w.sinif AS white_sinif, w.school_no AS white_school_no,
               b.name AS black_name, b.sinif AS black_sinif, b.school_no AS black_school_no
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pairingId]);
    $pairings = $stmt->fetchAll();
    $round = !empty($pairings) ? (int)$pairings[0]['round'] : 0;
}

// Sayfa basligi
$pageTitle = 'Yazdır';
if ($type === 'pairings' && $round > 0) {
    $pageTitle = $round . '. Tur Eşleşmeleri';
} elseif ($type === 'form' && ($round > 0 || $pairingId > 0)) {
    $pageTitle = 'Maç Formu';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($tournamentName) ?></title>
    <style>
        /* Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            color: #000;
            background: #f5f5f5;
            line-height: 1.4;
        }

        /* Screen-only controls */
        .no-print {
            text-align: center;
            padding: 15px;
            background: #fff;
            border-bottom: 2px solid #333;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .no-print a,
        .no-print button {
            display: inline-block;
            padding: 8px 20px;
            margin: 0 8px;
            font-size: 14px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #ccc;
        }

        .no-print .btn-print {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            font-weight: bold;
        }

        .no-print .btn-print:hover {
            background: #1d4ed8;
        }

        .no-print .btn-back {
            background: #fff;
            color: #333;
        }

        .no-print .btn-back:hover {
            background: #f0f0f0;
        }

        /* A4 container for screen preview */
        .page-container {
            max-width: 210mm;
            margin: 20px auto;
            background: #fff;
            padding: 15mm 15mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        /* ============================================
           MODE 1: PAIRINGS TABLE
           ============================================ */
        .pairings-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }

        .pairings-header .logo-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .pairings-header .logo-row img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .pairings-header .header-text {
            flex: 1;
            text-align: center;
        }

        .pairings-header .school-name {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .pairings-header .tournament-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .pairings-header .round-info {
            font-size: 12pt;
            font-weight: bold;
            color: #333;
        }

        /* Pairings table */
        .pairings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .pairings-table th {
            background: #1a1a2e;
            color: #fff;
            padding: 8px 6px;
            font-size: 10pt;
            text-align: center;
            border: 1px solid #000;
        }

        .pairings-table td {
            padding: 6px;
            border: 1px solid #333;
            font-size: 10pt;
            text-align: center;
        }

        .pairings-table tbody tr:nth-child(even) {
            background: #f0f0f0;
        }

        .pairings-table .col-masa { width: 8%; }
        .pairings-table .col-player { width: 25%; text-align: left; padding-left: 10px; }
        .pairings-table .col-sinif { width: 8%; }
        .pairings-table .col-vs { width: 6%; font-weight: bold; }
        .pairings-table .col-datetime { width: 20%; font-size: 9pt; }

        .pairings-table .seed-row {
            background: #fff8e1 !important;
        }

        .star-marker {
            color: #d4a017;
            font-size: 14pt;
        }

        .pairings-footer {
            margin-top: 15px;
            font-size: 9pt;
            color: #555;
            display: flex;
            justify-content: space-between;
        }

        .pairings-footer .seed-note .star-marker {
            font-size: 10pt;
        }

        /* ============================================
           MODE 2 & 3: MATCH FORMS
           ============================================ */
        .form-wrapper {
            /* Each form is half-page */
        }

        .match-form {
            border: 2px solid #000;
            padding: 12px 16px;
            margin-bottom: 0;
            page-break-inside: avoid;
        }

        .match-form .form-header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .match-form .form-header img {
            width: 45px;
            height: 45px;
            object-fit: contain;
            margin-right: 12px;
        }

        .match-form .form-header .header-info {
            flex: 1;
        }

        .match-form .form-header .header-info .school-name {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .match-form .form-header .header-info .tournament-name {
            font-size: 11pt;
            font-weight: bold;
        }

        .match-form .form-header .header-info .form-label {
            font-size: 9pt;
            color: #555;
            font-style: italic;
        }

        .match-form .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 10pt;
            border: 1px solid #999;
            border-radius: 3px;
        }

        .match-form .meta-row .meta-item {
            flex: 1;
            padding: 5px 8px;
            text-align: center;
            border-right: 1px solid #999;
        }

        .match-form .meta-row .meta-item:last-child {
            border-right: none;
        }

        .match-form .meta-row .meta-label {
            font-size: 8pt;
            color: #666;
            display: block;
        }

        .match-form .meta-row .meta-value {
            font-weight: bold;
            font-size: 11pt;
        }

        /* Player table in form */
        .form-player-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .form-player-table th {
            background: #1a1a2e;
            color: #fff;
            padding: 6px 8px;
            font-size: 9pt;
            text-align: center;
            border: 1px solid #000;
        }

        .form-player-table td {
            padding: 8px;
            border: 1px solid #333;
            font-size: 10pt;
            text-align: center;
        }

        .form-player-table .col-renk { width: 10%; }
        .form-player-table .col-ad { width: 30%; text-align: left; padding-left: 10px; }
        .form-player-table .col-sinif-form { width: 10%; }
        .form-player-table .col-no { width: 12%; }
        .form-player-table .col-imza { width: 22%; }

        .color-white {
            background: #fff;
            font-weight: bold;
        }

        .color-black {
            background: #333;
            color: #fff;
            font-weight: bold;
        }

        /* Result checkbox section */
        .result-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            padding: 8px;
            border: 1px solid #999;
            border-radius: 3px;
        }

        .result-section .result-label {
            font-weight: bold;
            font-size: 10pt;
            margin-right: 20px;
        }

        .result-section .result-option {
            display: inline-flex;
            align-items: center;
            margin: 0 15px;
            font-size: 12pt;
            font-weight: bold;
        }

        .result-section .result-option .checkbox {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid #000;
            margin-right: 6px;
            vertical-align: middle;
        }

        /* Referee / signature */
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 10px;
            padding-top: 8px;
            font-size: 9pt;
        }

        .form-footer .referee-section {
            flex: 1;
        }

        .form-footer .referee-label {
            font-size: 9pt;
            color: #666;
        }

        .form-footer .referee-name {
            font-weight: bold;
            font-size: 10pt;
        }

        .form-footer .signature-line {
            margin-top: 20px;
            border-top: 1px solid #000;
            width: 180px;
            text-align: center;
            padding-top: 3px;
            font-size: 8pt;
            color: #666;
        }

        /* Dashed separator between two forms on same page */
        .form-separator {
            border: none;
            border-top: 2px dashed #999;
            margin: 12px 0;
        }

        /* ============================================
           PRINT STYLES
           ============================================ */
        @media print {
            @page {
                size: A4;
                margin: 10mm 12mm;
            }

            body {
                background: #fff;
                font-size: 11pt;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .pairings-table th {
                background: #1a1a2e !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .pairings-table tbody tr:nth-child(even) {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .pairings-table .seed-row {
                background: #fff8e1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .form-player-table th {
                background: #1a1a2e !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .color-black {
                background: #333 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Page break for forms: 2 per page */
            .form-page-break {
                page-break-after: always;
                break-after: page;
            }

            .match-form {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Pairings list page break */
            .pairings-page-break {
                page-break-after: always;
                break-after: page;
            }
        }

        /* Error state */
        .error-box {
            max-width: 500px;
            margin: 60px auto;
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
        }

        .error-box h2 {
            color: #dc2626;
            margin-bottom: 10px;
        }

        .error-box p {
            color: #666;
            margin-bottom: 20px;
        }

        .error-box a {
            display: inline-block;
            padding: 8px 20px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="no-print">
    <button class="btn-print" onclick="window.print()">Yazdır</button>
    <a class="btn-back" href="admin.php<?= $round > 0 ? '?round=' . $round : '' ?>">Geri Dön</a>
</div>

<?php if (empty($type) || (empty($pairings) && $type !== '')): ?>
    <!-- Error / empty state -->
    <div class="error-box">
        <h2>Veri Bulunamadı</h2>
        <p>
            <?php if (empty($type)): ?>
                Geçerli bir yazdır tipi belirtilmedi. <br>
                Kullanım: <code>?type=pairings&amp;round=1</code> veya <code>?type=form&amp;round=1</code>
            <?php else: ?>
                Belirtilen tur veya eşleşme için veri bulunamadı.
            <?php endif; ?>
        </p>
        <a href="admin.php">Yönetim Paneline Dön</a>
    </div>

<?php elseif ($type === 'pairings'): ?>
    <!-- ============================================
         MODE 1: PAIRINGS LIST
         ============================================ -->
    <div class="page-container">
        <div class="pairings-header">
            <div class="logo-row">
                <img src="logo.png" alt="Logo">
                <div class="header-text">
                    <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
                    <div class="tournament-name"><?= htmlspecialchars($tournamentName) ?></div>
                    <div class="round-info"><?= $round ?>. Tur Eşleşmeleri</div>
                </div>
                <img src="logo.png" alt="Logo">
            </div>
        </div>

        <table class="pairings-table">
            <thead>
                <tr>
                    <th class="col-masa">Masa</th>
                    <th class="col-player">Beyaz</th>
                    <th class="col-sinif">Sınıf</th>
                    <th class="col-vs">vs</th>
                    <th class="col-player">Siyah</th>
                    <th class="col-sinif">Sınıf</th>
                    <th class="col-datetime">Tarih / Saat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pairings as $p): ?>
                    <?php
                        $isSeed = (int)($p['is_seed_table'] ?? 0);
                        $rowClass = $isSeed ? 'seed-row' : '';
                        $dateTimeStr = '';
                        if (!empty($p['match_date'])) {
                            $dateTimeStr = formatTurkishDatePrint($p['match_date']);
                            if (!empty($p['match_time'])) {
                                $dateTimeStr .= ' / ' . htmlspecialchars($p['match_time']);
                            }
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td>
                            <?= (int)$p['table_no'] ?>
                            <?php if ($isSeed): ?>
                                <span class="star-marker">&#9733;</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-player"><?= htmlspecialchars($p['white_name'] ?? 'BYE') ?></td>
                        <td><?= htmlspecialchars($p['white_sinif'] ?? '-') ?></td>
                        <td class="col-vs">vs</td>
                        <td class="col-player"><?= htmlspecialchars($p['black_name'] ?? 'BYE') ?></td>
                        <td><?= htmlspecialchars($p['black_sinif'] ?? '-') ?></td>
                        <td class="col-datetime"><?= $dateTimeStr ?: '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pairings-footer">
            <div class="seed-note">
                <span class="star-marker">&#9733;</span> Seri başı masası
            </div>
            <div>
                Toplam <?= count($pairings) ?> masa &bull; <?= $round ?>. Tur
            </div>
        </div>
    </div>

<?php elseif ($type === 'form'): ?>
    <!-- ============================================
         MODE 2 & 3: MATCH FORMS
         ============================================ -->
    <div class="page-container">
        <?php
        $totalForms = count($pairings);
        foreach ($pairings as $index => $p):
            $formNum = $index + 1;
            $isEven = ($formNum % 2 === 0);
            $isLast = ($formNum === $totalForms);

            $isSeed = (int)($p['is_seed_table'] ?? 0);
            $dateStr = '';
            if (!empty($p['match_date'])) {
                $dateStr = formatTurkishDatePrint($p['match_date']);
            }
            $timeStr = !empty($p['match_time']) ? htmlspecialchars($p['match_time']) : '-';
        ?>

        <div class="match-form">
            <!-- Form Header -->
            <div class="form-header">
                <img src="logo.png" alt="Logo">
                <div class="header-info">
                    <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
                    <div class="tournament-name"><?= htmlspecialchars($tournamentName) ?></div>
                    <div class="form-label">Maç Sonuç Formu</div>
                </div>
            </div>

            <!-- Meta info row -->
            <div class="meta-row">
                <div class="meta-item">
                    <span class="meta-label">Tur</span>
                    <span class="meta-value"><?= (int)$p['round'] ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Masa</span>
                    <span class="meta-value"><?= (int)$p['table_no'] ?><?php if ($isSeed): ?> <span class="star-marker">&#9733;</span><?php endif; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tarih</span>
                    <span class="meta-value" style="font-size:9pt;"><?= $dateStr ?: '-' ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Saat</span>
                    <span class="meta-value"><?= $timeStr ?></span>
                </div>
            </div>

            <!-- Player table -->
            <table class="form-player-table">
                <thead>
                    <tr>
                        <th class="col-renk">Renk</th>
                        <th class="col-ad">Ad Soyad</th>
                        <th class="col-sinif-form">Sınıf</th>
                        <th class="col-no">Okul No</th>
                        <th class="col-imza">İmza</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="color-white">&#9812; Beyaz</td>
                        <td style="text-align:left; padding-left:10px; font-weight:bold;">
                            <?= htmlspecialchars($p['white_name'] ?? 'BYE') ?>
                        </td>
                        <td><?= htmlspecialchars($p['white_sinif'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['white_school_no'] ?? '-') ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="color-black">&#9818; Siyah</td>
                        <td style="text-align:left; padding-left:10px; font-weight:bold;">
                            <?= htmlspecialchars($p['black_name'] ?? 'BYE') ?>
                        </td>
                        <td><?= htmlspecialchars($p['black_sinif'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['black_school_no'] ?? '-') ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- Result checkboxes -->
            <div class="result-section">
                <span class="result-label">Sonuç:</span>
                <span class="result-option">
                    <span class="checkbox"></span> 1-0
                </span>
                <span class="result-option">
                    <span class="checkbox"></span> &frac12;-&frac12;
                </span>
                <span class="result-option">
                    <span class="checkbox"></span> 0-1
                </span>
            </div>

            <!-- Referee / signature footer -->
            <div class="form-footer">
                <div class="referee-section">
                    <div class="referee-label">Hakem</div>
                    <?php if (!empty($refereeName)): ?>
                        <div class="referee-name"><?= htmlspecialchars($refereeName) ?></div>
                    <?php endif; ?>
                    <div class="signature-line">Hakem İmzası</div>
                </div>
                <div style="text-align:right;">
                    <div class="signature-line">Tarih</div>
                </div>
            </div>
        </div>

        <?php if (!$isLast): ?>
            <?php if ($isEven): ?>
                <!-- After every 2nd form: page break -->
                <div class="form-page-break"></div>
            <?php else: ?>
                <!-- Between 1st and 2nd form on same page: dashed separator -->
                <hr class="form-separator">
            <?php endif; ?>
        <?php endif; ?>

        <?php endforeach; ?>
    </div>

<?php endif; ?>

<script>
    // Auto-trigger print with a small delay (only if there's content to print)
    <?php if (!empty($pairings)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
    <?php endif; ?>
</script>

</body>
</html>
