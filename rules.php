<?php
require_once 'db.php';
include 'header.php';

$deadline = get_setting('deadline', '4 Mart 2026');
?>

<div class="max-w-3xl mx-auto mb-8">
    <!-- Header -->
    <div class="card overflow-hidden mb-6">
        <div class="bg-gray-900 px-8 py-10 text-center">
            <div class="text-4xl mb-3">&#9812;</div>
            <h2 class="text-2xl font-bold text-white">Turnuva Kuralları ve İşleyiş</h2>
            <p class="text-gray-400 mt-2 text-sm">2025-2026 Okul Satranç Turnuvası &middot; İsviçre Sistemi (6 Tur) &middot; 68 Oyuncu</p>
        </div>
    </div>

    <!-- Kurallar -->
    <div class="space-y-4">
        <!-- Genel Kurallar -->
        <div class="card p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 text-sm font-bold">1</span>
                Genel Kurallar
            </h3>
            <ul class="space-y-2.5 text-sm text-gray-600">
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Turnuva okulumuz ogrencileri arasinda duzenlenmektedir ve katilim ucretsizdir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Maclar ilan edilen gun ve saatte, belirlenen sinifta/salonda oynanacaktir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Mazeretsiz olarak mac saatinde hazir bulunmayan oyuncu, maci <strong>hukmen kaybetmis</strong> sayilir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Sportmenlik disi davranista bulunan oyuncular hakem karariyla ihrac edilebilir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Dokunulan tas oynanir, birakilan tas geri alinamaz.</li>
            </ul>
        </div>

        <!-- Puanlama -->
        <div class="card p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center text-green-600 text-sm font-bold">2</span>
                Puanlama Sistemi
            </h3>
            <p class="text-sm text-gray-600 mb-4">Standart satranc puanlama sistemi uygulanir:</p>
            <div class="grid grid-cols-3 gap-3">
                <div class="text-center p-4 rounded-xl bg-green-50 border border-green-100">
                    <div class="text-2xl font-bold text-green-600">1</div>
                    <div class="text-xs text-green-700 font-medium mt-1">Galibiyet</div>
                </div>
                <div class="text-center p-4 rounded-xl bg-yellow-50 border border-yellow-100">
                    <div class="text-2xl font-bold text-yellow-600">0.5</div>
                    <div class="text-xs text-yellow-700 font-medium mt-1">Beraberlik</div>
                </div>
                <div class="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                    <div class="text-2xl font-bold text-red-600">0</div>
                    <div class="text-xs text-red-700 font-medium mt-1">Maglubiyet</div>
                </div>
            </div>
        </div>

        <!-- Eşleştirme -->
        <div class="card p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600 text-sm font-bold">3</span>
                Eslestirme Sistemi
            </h3>
            <ul class="space-y-2.5 text-sm text-gray-600">
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span><strong>1. Tur:</strong> Tum katilimcilar arasinda tamamen rastgele eslestirme yapilir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span><strong>Sonraki Turlar:</strong> Isvicre Sistemi ile birbirine yakin puanli oyuncular eslestirilir.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Ayni iki oyuncu birden fazla kez birbirleriyle eslestirilmez.</li>
                <li class="flex gap-2"><span class="text-gray-400 mt-0.5">&#8226;</span>Tek sayi katilimcida en dusuk puanli oyuncu BAY gecer ve otomatik 1 puan alir.</li>
            </ul>
        </div>

        <!-- İtiraz -->
        <div class="card p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center text-red-600 text-sm font-bold">4</span>
                Itiraz ve Anlaşmazliklar
            </h3>
            <p class="text-sm text-gray-600">
                Oyun sirasinda her turlu kural ihlali veya anlasmazlik durumunda oyunu durdurup
                <strong>Gorevli Hakemi</strong> cagirmaniz gerekmektedir. Hakem karari nihaidir.
            </p>
        </div>

        <!-- Başvuru Bilgisi -->
        <div class="rounded-xl bg-blue-50 border border-blue-200 p-5">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h4 class="text-sm font-semibold text-blue-800 mb-1">Basvuru Bilgisi</h4>
                    <p class="text-sm text-blue-700">
                        Basvurular icin son tarih <strong><?php echo htmlspecialchars($deadline); ?></strong>'dir.
                        Basvurular okul idaresine veya ilgili kulup danisman ogretmenine sahsen yapilmalidir.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
