<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
function navActive($page) {
    global $current_page;
    if (is_array($page)) return in_array($current_page, $page);
    return $current_page === $page;
}
function navClass($page) {
    return navActive($page)
        ? 'text-gray-900 border-b-2 border-amber-600 font-semibold'
        : 'text-gray-500 hover:text-gray-900 font-medium';
}
function mobileNavClass($page) {
    return navActive($page)
        ? 'bg-amber-50 text-amber-700 border-l-4 border-amber-600 font-semibold'
        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BYAL Satranç Turnuvası</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>♔</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        chess: {
                            light: '#f0f0f0',
                            dark: '#1a1a2e',
                            accent: '#d4af37',
                            gold: '#d4af37',
                            silver: '#c0c0c0',
                            bronze: '#cd7f32',
                            board: { light: '#f0d9b5', dark: '#b58863' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
            color: #111827;
        }
        .chess-pattern {
            background-image:
                linear-gradient(45deg, rgba(181,136,99,0.06) 25%, transparent 25%, transparent 75%, rgba(181,136,99,0.06) 75%),
                linear-gradient(45deg, rgba(181,136,99,0.06) 25%, transparent 25%, transparent 75%, rgba(181,136,99,0.06) 75%);
            background-size: 50px 50px;
            background-position: 0 0, 25px 25px;
        }
        .glass {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(181,136,99,0.15);
        }
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(181,136,99,0.12);
            box-shadow: 0 1px 3px rgba(181,136,99,0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card:hover {
            box-shadow: 0 8px 24px rgba(181,136,99,0.15);
            transform: translateY(-2px);
        }
        .podium-gold { background: linear-gradient(135deg, #f6e05e 0%, #d4af37 100%); box-shadow: 0 10px 30px rgba(212,175,55,0.3); }
        .podium-silver { background: linear-gradient(135deg, #e2e8f0 0%, #c0c0c0 100%); box-shadow: 0 6px 20px rgba(192,192,192,0.25); }
        .podium-bronze { background: linear-gradient(135deg, #f6ad55 0%, #cd7f32 100%); box-shadow: 0 6px 20px rgba(205,127,50,0.25); }
        .seed-badge {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .winner-glow { box-shadow: 0 0 20px rgba(34, 197, 94, 0.3); }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .chess-piece-float { animation: float 3s ease-in-out infinite; }
        .match-card { transition: all 0.3s ease; }
        .match-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.12); }
        .seed-row { background: linear-gradient(90deg, rgba(251,191,36,0.08), rgba(251,191,36,0.03)); }
    </style>
</head>
<body class="flex flex-col min-h-screen chess-pattern">

<!-- Navbar -->
<nav class="glass sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="index.php" class="flex items-center gap-3 group">
                    <img class="h-10 w-10 object-contain rounded-lg transition-transform group-hover:scale-105" src="logo.png" alt="BYAL Logo">
                    <div class="hidden md:block">
                        <h1 class="text-base font-bold text-gray-900 leading-tight">Sultangazi BYAL</h1>
                        <p class="text-xs text-gray-500 leading-tight flex items-center gap-1">
                            <span class="text-amber-600">♔</span>
                            <span>Satranç Turnuvası</span>
                        </p>
                    </div>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden sm:flex sm:items-center sm:gap-1">
                <a href="index.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass('index.php'); ?> transition rounded-lg">
                    <?php if (navActive('index.php')): ?><span class="text-xs">♔</span><?php endif; ?>
                    <span>Ana Sayfa</span>
                </a>
                <a href="round.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass('round.php'); ?> transition rounded-lg">
                    <?php if (navActive('round.php')): ?><span class="text-xs">♟</span><?php endif; ?>
                    <span>Fikstür</span>
                </a>
                <a href="results.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass('results.php'); ?> transition rounded-lg">
                    <?php if (navActive('results.php')): ?><span class="text-xs">♜</span><?php endif; ?>
                    <span>Sonuçlar</span>
                </a>
                <a href="standings.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass('standings.php'); ?> transition rounded-lg">
                    <?php if (navActive('standings.php')): ?><span class="text-xs">♚</span><?php endif; ?>
                    <span>Puan Durumu</span>
                </a>
                <a href="players.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass(['players.php', 'player.php']); ?> transition rounded-lg">
                    <?php if (navActive(['players.php', 'player.php'])): ?><span class="text-xs">♞</span><?php endif; ?>
                    <span>Katılımcılar</span>
                </a>
                <a href="rules.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm <?php echo navClass('rules.php'); ?> transition rounded-lg">
                    <?php if (navActive('rules.php')): ?><span class="text-xs">♝</span><?php endif; ?>
                    <span>Kurallar</span>
                </a>

                <?php if (isset($_SESSION['admin_logged_in'])): ?>
                    <div class="w-px h-6 bg-gray-200 mx-2"></div>
                    <a href="admin.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Yönetim
                    </a>
                    <a href="logout.php" class="text-sm text-red-500 hover:text-red-700 font-medium px-2 transition" title="Çıkış Yap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </a>
                <?php else: ?>
                    <div class="w-px h-6 bg-gray-200 mx-2"></div>
                    <a href="login.php" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                        Giriş
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <div class="flex items-center sm:hidden">
                <button type="button" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                        class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="sm:hidden hidden" id="mobile-menu">
        <div class="py-2 space-y-1 bg-white border-b border-gray-200 shadow-lg">
            <a href="index.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('index.php'); ?>"><span class="text-base">♔</span><span>Ana Sayfa</span></a>
            <a href="round.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('round.php'); ?>"><span class="text-base">♟</span><span>Fikstür</span></a>
            <a href="results.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('results.php'); ?>"><span class="text-base">♜</span><span>Sonuçlar</span></a>
            <a href="standings.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('standings.php'); ?>"><span class="text-base">♚</span><span>Puan Durumu</span></a>
            <a href="players.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass(['players.php', 'player.php']); ?>"><span class="text-base">♞</span><span>Katılımcılar</span></a>
            <a href="rules.php" class="flex items-center gap-2 pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('rules.php'); ?>"><span class="text-base">♝</span><span>Kurallar</span></a>
            <?php if (isset($_SESSION['admin_logged_in'])): ?>
                <a href="admin.php" class="block pl-4 pr-4 py-3 text-sm <?php echo mobileNavClass('admin.php'); ?>">Yönetim Paneli</a>
                <a href="logout.php" class="block pl-4 pr-4 py-3 text-sm text-red-600 border-l-4 border-transparent">Çıkış Yap</a>
            <?php else: ?>
                <a href="login.php" class="block pl-4 pr-4 py-3 text-sm text-amber-600 border-l-4 border-transparent">Görevli Girişi</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
