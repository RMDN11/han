<?php
session_start();
require_once 'config/connection.php';

// Buat tabel rekap_pekanan jika belum ada
$conn->query("
    CREATE TABLE IF NOT EXISTS `rekap_pekanan` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `peserta_id` int(11) NOT NULL,
      `bulan` varchar(20) NOT NULL,
      `tahun` int(11) NOT NULL,
      `juz_sekarang` varchar(20) DEFAULT NULL,
      `pekan_1` text DEFAULT NULL,
      `pekan_2` text DEFAULT NULL,
      `pekan_3` text DEFAULT NULL,
      `pekan_4` text DEFAULT NULL,
      `pekan_5` text DEFAULT NULL,
      `total_hafalan` varchar(50) DEFAULT NULL,
      `catatan` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `peserta_bulan_tahun` (`peserta_id`, `bulan`, `tahun`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ambil daftar peserta
$peserta_list = [];
try {
    $query_peserta = "SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC";
    $result_peserta = $conn->query($query_peserta);
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
} catch (Exception $e) {
    // Silent
}

$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulan_sekarang = $bulan_list[date('n') - 1];
$selected_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : $bulan_sekarang;
$selected_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Ambil data rekap untuk bulan yang dipilih
$rekap_data = [];
if (!empty($peserta_list)) {
    $peserta_ids = array_column($peserta_list, 'id');
    if (!empty($peserta_ids)) {
        $placeholders = implode(',', array_fill(0, count($peserta_ids), '?'));
        $types = str_repeat('i', count($peserta_ids));
        $stmt = $conn->prepare("SELECT * FROM rekap_pekanan WHERE peserta_id IN ($placeholders) AND bulan = ? AND tahun = ?");
        $params = array_merge($peserta_ids, [$selected_bulan, $selected_tahun]);
        $stmt->bind_param($types . "si", ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rekap_data[$row['peserta_id']] = $row;
        }
        $stmt->close();
    }
}

// ==========================================
// FUNGSI HELPER
// ==========================================
function formatNilaiPekan($nilai) {
    if (empty($nilai)) return '-';
    $class = '';
    if (stripos($nilai, 'UJIAN') !== false) {
        $class = 'text-emerald-600 font-semibold';
    } elseif (preg_match('/^(\d+)\s*Hal/i', $nilai, $matches)) {
        $angka = (int)$matches[1];
        if ($angka < 6) $class = 'text-red-500 font-semibold';
    }
    return "<span class=\"$class\">" . htmlspecialchars($nilai) . "</span>";
}

function extractAngka($nilai) {
    if (empty($nilai)) return 0;
    if (stripos($nilai, 'UJIAN') !== false) return 0; // UJIAN = 0 untuk perbandingan
    if (preg_match('/^(\d+)\s*Hal/i', $nilai, $matches)) return (int)$matches[1];
    return 0;
}

// ==========================================
// 🏆 MAHKOTA AKUMULATIF (SEMUA BULAN & PEKAN)
// ==========================================
$mahkota_total = []; // [peserta_id] => total mahkota seumur data

// Inisialisasi
foreach ($peserta_list as $p) {
    $mahkota_total[$p['id']] = 0;
}

// Ambil SEMUA data rekap dari database (tanpa filter bulan/tahun)
$all_rekap = [];
if (!empty($peserta_list)) {
    $peserta_ids = array_column($peserta_list, 'id');
    if (!empty($peserta_ids)) {
        $placeholders = implode(',', array_fill(0, count($peserta_ids), '?'));
        $types = str_repeat('i', count($peserta_ids));
        $stmt = $conn->prepare("SELECT peserta_id, bulan, tahun, pekan_1, pekan_2, pekan_3, pekan_4 FROM rekap_pekanan WHERE peserta_id IN ($placeholders)");
        $stmt->bind_param($types, ...$peserta_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $all_rekap[] = $row;
        }
        $stmt->close();
    }
}

// Hitung mahkota: untuk setiap kombinasi bulan+tahun+pekan, cari juara
// Group data by bulan+tahun
$grouped_by_period = [];
foreach ($all_rekap as $r) {
    $key = $r['bulan'] . '_' . $r['tahun'];
    if (!isset($grouped_by_period[$key])) {
        $grouped_by_period[$key] = [];
    }
    $grouped_by_period[$key][] = $r;
}

// Untuk setiap periode (bulan+tahun), cari juara per pekan
foreach ($grouped_by_period as $period => $records) {
    for ($pekan = 1; $pekan <= 4; $pekan++) {
        $field = 'pekan_' . $pekan;
        $top_value = -1;
        $top_id = null;
        
        foreach ($records as $rec) {
            $nilai_raw = $rec[$field] ?? '';
            // Skip jika UJIAN atau kosong
            if (empty($nilai_raw) || stripos($nilai_raw, 'UJIAN') !== false) continue;
            
            $angka = extractAngka($nilai_raw);
            if ($angka > $top_value) {
                $top_value = $angka;
                $top_id = $rec['peserta_id'];
            }
        }
        
        // Berikan +1 mahkota ke pemenang pekan ini
        if ($top_id !== null && $top_value > 0) {
            $mahkota_total[$top_id]++;
        }
    }
}

// ==========================================
// TOP SETORAN PEKAN TERAKHIR (BULAN INI SAJA)
// ==========================================
$top_setoran = null;
$pekan_terakhir = 0;
$pekan_fields = ['pekan_1', 'pekan_2', 'pekan_3', 'pekan_4', 'pekan_5'];

foreach ($pekan_fields as $index => $field) {
    $pekan_num = $index + 1;
    $ada_data = false;
    foreach ($rekap_data as $rekap) {
        $nilai = extractAngka($rekap[$field] ?? '');
        if ($nilai > 0) { $ada_data = true; break; }
    }
    if ($ada_data) $pekan_terakhir = $pekan_num;
    else break;
}

if ($pekan_terakhir == 0) {
    foreach ($rekap_data as $rekap) {
        for ($i = 1; $i <= 5; $i++) {
            $nilai = extractAngka($rekap['pekan_' . $i] ?? '');
            if ($nilai > 0 && $i > $pekan_terakhir) $pekan_terakhir = $i;
        }
    }
}
if ($pekan_terakhir == 0) $pekan_terakhir = 1;

$pekan_field = 'pekan_' . $pekan_terakhir;
$top_value = 0; $top_nama = ''; $top_peserta_id = '';

foreach ($rekap_data as $peserta_id => $rekap) {
    $nilai = extractAngka($rekap[$pekan_field] ?? '');
    if ($nilai > $top_value) {
        $top_value = $nilai;
        $top_peserta_id = $peserta_id;
        foreach ($peserta_list as $p) {
            if ($p['id'] == $peserta_id) { $top_nama = $p['nama']; break; }
        }
    }
}

if ($top_value > 0) {
    $top_setoran = ['nama' => $top_nama, 'nilai' => $top_value, 'pekan' => $pekan_terakhir, 'peserta_id' => $top_peserta_id];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Laporan Perkembangan Hafalan</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* Apple-inspired Design System Variables */
:root {
    /* Colors from DESIGN.md */
    --colors-primary: #0066cc; /* Action Blue */
    --colors-primary-focus: #0071e3;
    --colors-primary-on-dark: #2997ff;
    --colors-canvas: #ffffff; /* Pure White */
    --colors-canvas-parchment: #f5f5f7; /* Parchment */
    --colors-surface-pearl: #fafafc; /* Pearl Button */
    --colors-surface-tile-1: #272729; /* Near-Black Tile 1 */
    --colors-surface-tile-2: #2a2a2c; /* Near-Black Tile 2 */
    --colors-surface-tile-3: #252527; /* Near-Black Tile 3 */
    --colors-surface-black: #000000; /* Pure Black */
    --colors-ink: #1d1d1f; /* Near-Black Ink */
    --colors-body-on-dark: #ffffff;
    --colors-body-muted: #cccccc;
    --colors-ink-muted-80: #333333;
    --colors-ink-muted-48: #7a7a7a;
    --colors-divider-soft: rgba(0, 0, 0, 0.04);
    --colors-hairline: #e0e0e0;

    /* Custom colors for badges based on existing logic, adapted to Apple's aesthetic */
    --badge-success-bg: #dcfce7; /* Light Green */
    --badge-success-text: #166534; /* Dark Green */
    --badge-danger-bg: #fee2e2; /* Light Red */
    --badge-danger-text: #991b1b; /* Dark Red */
    --badge-warning-bg: #fef3c7; /* Light Yellow */
    --badge-warning-text: #92400e; /* Dark Yellow */

    /* Spacing tokens (using 8px base unit) */
    --spacing-xs: 8px; --spacing-sm: 12px; --spacing-md: 16px; --spacing-lg: 24px;
}

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* GLASSMORPHISM MODERN UI */
:root {
    --glass-bg: rgba(255, 255, 255, 0.92);
    --glass-border: rgba(255, 255, 255, 0.6);
    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    --primary: #3b82f6;
    --gold: #f59e0b;
}
body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--colors-canvas-parchment); /* Parchment */
    background-attachment: fixed;
    color: var(--colors-ink); /* Near-Black Ink */
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    line-height: 1.47; /* typography.body line-height */
}
.glass-card {
    background: var(--colors-canvas); /* Pure White */
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--colors-hairline); /* Hairline */
    border-radius: 1.25rem;
    box-shadow: var(--glass-shadow);
    transition: all 0.3s ease;
}
.glass-card:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border-color: rgba(255, 255, 255, 0.9);
}
.apple-select {
    background: var(--colors-canvas);
    border: 1px solid var(--colors-hairline);
    border-radius: 8px; /* rounded.sm */
    padding: 0.6rem 0.8rem; /* typography.caption */
    font-size: 0.875rem; /* typography.caption */
    width: 100%;
    appearance: none;
    color: var(--colors-ink);
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}
.modern-select:focus {
    outline: none;
    border-color: var(--colors-primary); /* Action Blue */
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2); /* Focus Blue effect */
    background: var(--colors-canvas);
}
.btn-apple-primary {
    background: var(--colors-primary); /* Action Blue */
    border: none;
    color: var(--colors-canvas);
    padding: 0.75rem 1.75rem; /* button-primary padding */
    font-size: 0.875rem; /* typography.body */
    font-weight: 500; /* Adjusted to 500 for buttons */
    border-radius: 9999px; /* rounded.pill */
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(0, 102, 204, 0.35);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-apple-primary:hover {
    transform: translateY(-1px);
}
.btn-apple-primary:active {
    transform: scale(0.95);
}
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 18px; /* rounded.lg */
    background: var(--colors-canvas);
    border: 1px solid var(--colors-hairline);
}
.table-header {
    background: var(--colors-canvas-parchment);
    color: var(--colors-ink-muted-80);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: sticky;
    top: 0;
    z-index: 10;
}
.table-row:hover {
    background-color: rgba(59, 130, 246, 0.04);
}
.crown-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.7rem;
    color: var(--gold);
    font-weight: 600;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    border: 1px solid #fcd34d;
    animation: pulse 2s infinite;
}
.top-winner-card {
    /* This card style is quite different from Apple's typical flat design.
       Adapting it to a dark tile style from DESIGN.md. */
    background: var(--colors-surface-tile-1); /* Near-Black Tile 1 */
    color: var(--colors-body-on-dark);
    background: linear-gradient(135deg, #1e3c3f 0%, #2a4a4d 100%);
    border-radius: 1.25rem;
    padding: 1rem 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
}
.top-winner-card::before {
    content: ""; /* Remove crown icon as it's not in Apple's style */
    position: absolute; /* Keep for potential subtle background pattern */
    right: 0;
    top: 0;
    font-size: 0;
    opacity: 0.08;
    pointer-events: none;
}
.winner-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}
.winner-medal {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
}
.winner-info {
    text-align: left;
}
.winner-name {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.winner-value { /* typography.display-md */
    font-size: 34px;
    font-weight: 600;
    color: var(--colors-primary-on-dark); /* Sky Link Blue for highlight on dark */
}
.winner-pekan {
    background: rgba(255,255,255,0.1); /* Subtle background on dark tile */
    padding: 0.2rem 0.6rem; /* typography.caption */
    border-radius: 9999px;
    font-size: 0.7rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6); /* Darker backdrop */
    backdrop-filter: blur(4px);
}
.modal-content {
    background: var(--colors-canvas); /* Pure White */
    margin: 5% auto;
    padding: 1.5rem;
    border-radius: 18px; /* rounded.lg */
    width: 90%;
    max-width: 520px;
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-40px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start; /* Align close button to top-right */
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.8rem;
    margin-bottom: 1rem;
}
.close-modal {
    background: #f1f5f9;
    border: none;
    width: 32px; /* Adjusted for touch target */
    height: 32px; /* Adjusted for touch target */
    border-radius: 50%;
    font-size: 1rem;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.close-modal:hover {
    background: #ef4444;
    color: var(--colors-canvas);
    transform: rotate(90deg) scale(1.05);
}
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
@media (max-width: 768px) {
    .glass-card { border-radius: 1rem; }
    .btn-modern { width: 100%; justify-content: center; }
    .winner-content { flex-direction: column; text-align: center; gap: 0.5rem; }
    .winner-info { text-align: center; }
}
@media print {
    .btn-modern, .top-winner-card, .footer-laporan { display: none !important; }
    body { background: white; }
    .glass-card { box-shadow: none; border: 1px solid #ddd; }
}
.kop-container { /* Adapted to Apple card style */
    background: var(--colors-canvas);
    border-radius: 18px; /* rounded.lg */
    padding: 1rem;
    margin-bottom: var(--spacing-lg); /* 24px */
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.kop-container img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
}
</style>
</head>
<body class="p-4 md:p-6">
<div class="max-w-6xl mx-auto">

    <!-- KOP SURAT (WAJIB) -->
    <div class="kop-container">
        <img src="KOP.png" alt="Kop Surat" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <div style="display:none; text-align:center; color:#64748b; font-size:0.9rem;">
            <i class="fas fa-image mr-2"></i>KOP.png tidak ditemukan
        </div>
    </div>

    <!-- Header -->
    <div class="glass-card p-5 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3" style="letter-spacing: -0.02em;">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-file-alt text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-semibold text-gray-800 tracking-tight">LAPORAN HAFALAN</h1>
                    <p class="text-xs text-gray-500 mt-0.5" style="letter-spacing: -0.02em;">PERKEMBANGAN HAFALAN SANTRI</p>
                </div>
            </div>
            <div class="text-left sm:text-right">
                <p class="text-sm font-medium text-gray-700">PROGRAM ASRAMA TAHFIZH</p>
                <p class="text-xs text-gray-400">MAHAD IMAM SYATHBY BOGOR</p>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="glass-card p-4 mb-6">
        <form method="GET" action="" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1 uppercase tracking-wide">Bulan</label>
                <select name="bulan" class="apple-select text-sm" onchange="this.form.submit()">
                    <?php foreach ($bulan_list as $bln): ?>
                    <option value="<?= $bln ?>" <?= $selected_bulan == $bln ? 'selected' : '' ?>><?= $bln ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[120px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1 uppercase tracking-wide">Tahun</label>
                <select name="tahun" class="apple-select text-sm" onchange="this.form.submit()">
                    <?php for ($y = 2024; $y <= 2027; $y++): ?>
                    <option value="<?= $y ?>" <?= $selected_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-1 mt-5 md:mt-0 md:ml-auto flex justify-end">
                <a href="kualitas.php" class="btn-apple-primary w-full sm:w-auto justify-center">
                    <i class="fas fa-chart-simple text-xs"></i> Nilai Muroja'ah
                </a>
            </div>
        </form>
    </div>

    <!-- Info Periode -->
    <div class="flex flex-wrap items-center gap-4 mb-5 px-2">
        <span class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-calendar-alt text-blue-500"></i> <?= $selected_bulan ?> <?= $selected_tahun ?>
        </span>
        <span class="hidden sm:block w-px h-5 bg-gray-300"></span>
        <span class="text-sm text-gray-500 flex items-center gap-2">
            <i class="fas fa-chalkboard-user text-indigo-500"></i> Pengampu: Farhan Ramadhan
        </span>
    </div>

    <!-- TOP SETORAN -->
    <div class="top-winner-card mb-6">
        <div class="text-center mb-2">
            <span class="text-xs uppercase tracking-wider opacity-80" style="letter-spacing: 0.05em;"><i class="fas fa-star mr-1"></i> Top Setoran Pekan Ini</span>
        </div>
        <?php if ($top_setoran && $top_setoran['nilai'] > 0): ?>
            <div class="winner-content">
                <div class="winner-medal">✨</div>
                <div class="winner-info">
                    <div class="winner-name"><?= htmlspecialchars($top_setoran['nama']) ?></div>
                    <div class="winner-value"><?= $top_setoran['nilai'] ?> <span class="text-sm font-normal">Hal</span></div>
                    <div class="winner-pekan"><i class="fas fa-calendar-week"></i> Pekan ke-<?= $top_setoran['pekan'] ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-3 opacity-80 text-sm">
                <i class="fas fa-info-circle mr-1"></i> Belum ada data setoran untuk bulan ini
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabel Data -->
    <div class="glass-card overflow-hidden" style="border-radius: 18px;">
        <div class="p-4 border-b border-gray-200/60 bg-gray-50/50">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-users text-blue-500"></i> Data Santri
                <span class="text-xs font-normal text-gray-400">(<?= count($peserta_list) ?>)</span>
            </h3>
        </div>
        <div class="table-container">
            <table class="w-full text-sm">
                <thead class="table-header" style="border-radius: 18px 18px 0 0;">
                    <tr>
                        <th class="px-3 py-3 text-left w-10">No</th>
                        <th class="px-3 py-3 text-left">Nama</th>
                        <th class="px-3 py-3 text-center">Jenjang</th>
                        <th class="px-3 py-3 text-center">Juz</th>
                        <th class="px-3 py-3 text-center">P1</th>
                        <th class="px-3 py-3 text-center">P2</th>
                        <th class="px-3 py-3 text-center">P3</th>
                        <th class="px-3 py-3 text-center">P4</th>
                        <th class="px-3 py-3 text-center">Total</th>
                        <th class="px-3 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($peserta_list)): ?>
                    <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400 italic">Belum ada data peserta</td></tr>
                    <?php else: ?>
                        <?php foreach ($peserta_list as $index => $peserta): 
                            $rekap = $rekap_data[$peserta['id']] ?? ['jenjang' => '1 SMP']; // Default jenjang
                            $p1 = extractAngka($rekap['pekan_1'] ?? '');
                            $p2 = extractAngka($rekap['pekan_2'] ?? '');
                            $p3 = extractAngka($rekap['pekan_3'] ?? '');
                            $p4 = extractAngka($rekap['pekan_4'] ?? '');
                            $mahkota = $mahkota_total[$peserta['id']] ?? 0;
                        ?>
                        <tr class="table-row transition-colors">
                            <td class="px-3 py-3 text-gray-500"><?= $index + 1 ?></td>
                            <td class="px-3 py-3">
                                <div class="font-medium text-gray-800 flex items-center gap-2 flex-wrap">
                                    <?= htmlspecialchars($peserta['nama']) ?>
                                    <?php if ($mahkota > 0): ?>
                                        <span class="crown-badge" title="Juara <?= $mahkota ?>x di semua periode">
                                            <?php for ($i = 0; $i < min($mahkota, 5); $i++): ?>
                                                <i class="fas fa-crown text-[10px]"></i>
                                            <?php endfor; ?>
                                            <?php if ($mahkota > 5): ?><span class="ml-1">+<?= $mahkota - 5 ?></span><?php endif; ?>
                                            <span class="ml-1 font-bold"><?= $mahkota ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center text-gray-600"><?= htmlspecialchars($rekap['jenjang'] ?? '1 SMP') ?></td>
                            <td class="px-3 py-3 text-center font-mono text-gray-700"><?= htmlspecialchars($rekap['juz_sekarang'] ?? '-') ?></td>
                            <td class="px-3 py-3 text-center"><?= formatNilaiPekan($rekap['pekan_1'] ?? '') ?></td>
                            <td class="px-3 py-3 text-center"><?= formatNilaiPekan($rekap['pekan_2'] ?? '') ?></td>
                            <td class="px-3 py-3 text-center"><?= formatNilaiPekan($rekap['pekan_3'] ?? '') ?></td>
                            <td class="px-3 py-3 text-center"><?= formatNilaiPekan($rekap['pekan_4'] ?? '') ?></td>
                            <td class="px-3 py-3 text-center font-semibold text-gray-700"><?= htmlspecialchars($rekap['total_hafalan'] ?? '-') ?></td>
                            <td class="px-3 py-3 text-center">
                                <button class="btn-modern" onclick="showGrafik('<?= addslashes(htmlspecialchars($peserta['nama'])) ?>', <?= $p1 ?>, <?= $p2 ?>, <?= $p3 ?>, <?= $p4 ?>)">
                                    <i class="fas fa-chart-bar text-xs"></i> Lihat Grafik
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Legend - Apple-style -->
        <div class="p-4 flex flex-wrap gap-4 text-xs text-gray-500 border-t border-gray-100">
            <span class="flex items-center gap-1"><i class="fas fa-circle text-emerald-500"></i> Ujian</span>
            <span class="flex items-center gap-1"><i class="fas fa-circle text-red-500"></i> &lt;6 Halaman</span>
            <span class="flex items-center gap-1"><i class="fas fa-crown text-amber-500"></i> Mahkota = Total Juara Pekan (Semua Bulan)</span>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center pb-4">
        <p class="text-xs text-gray-400">Reqra by Han · <?= date('Y') ?></p>
    </div>

</div>

<!-- Modal Grafik -->
<div id="grafikModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="font-semibold text-gray-800"><i class="fas fa-chart-bar mr-2 text-blue-500"></i>Grafik Perkembangan</h3>
            <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="h-64">
                <canvas id="perkembanganChart"></canvas>
            </div>
            <div class="mt-3 text-xs text-gray-500 text-center italic">
                <i class="fas fa-info-circle mr-1"></i> Ujian tidak ditampilkan dalam grafik
            </div>
        </div>
    </div>
</div>

<script>
let chartInstance = null;

function showGrafik(nama, p1, p2, p3, p4) {
    const modal = document.getElementById('grafikModal');
    document.getElementById('modalTitle').innerHTML = `<i class="fas fa-chart-bar mr-2 text-blue-500"></i>${nama}`;
    
    const ctx = document.getElementById('perkembanganChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();
    
    // Data: jika 0 (UJIAN/kosong), gunakan null agar tidak muncul di grafik
    const dataValues = [p1 > 0 ? p1 : null, p2 > 0 ? p2 : null, p3 > 0 ? p3 : null, p4 > 0 ? p4 : null];
    const validValues = dataValues.filter(v => v !== null);
    const maxVal = validValues.length > 0 ? Math.max(...validValues) : 10;
    
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['P1', 'P2', 'P3', 'P4'],
            datasets: [{
                label: 'Halaman',
                data: dataValues,
                backgroundColor: ['rgba(59,130,246,0.8)','rgba(16,185,129,0.8)','rgba(245,158,11,0.8)','rgba(239,68,68,0.8)'],
                borderColor: ['#3b82f6','#10b981','#f59e0b','#ef4444'],
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.7,
                categoryPercentage: 0.85,
                skipNull: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.95)',
                    titleColor: '#fff',
                    bodyColor: '#e2e8f0',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(ctx) {
                            return ctx.raw !== null ? `📖 ${ctx.raw} Halaman` : '🚫 Ujian / Tidak Disetor';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: Math.ceil(maxVal * 1.3),
                    grid: { color: '#e2e8f0', borderDash: [4,4] },
                    ticks: { 
                        font: { size: 10 },
                        callback: function(v) { return v + ' Hal'; }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10, weight: '600' } }
                }
            },
            animation: { duration: 600, easing: 'easeOutQuart' }
        }
    });
    
    // Tambahkan label angka di atas bar
    setTimeout(() => {
        const meta = chartInstance.getDatasetMeta(0);
        const ctx = chartInstance.ctx;
        ctx.save();
        ctx.font = 'bold 11px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        meta.data.forEach((bar, i) => {
            const val = dataValues[i];
            if (val !== null) {
                ctx.fillStyle = '#1e293b';
                ctx.fillText(val + ' Hal', bar.x, bar.y - 6);
            }
        });
        ctx.restore();
    }, 100);
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('grafikModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
}

document.getElementById('grafikModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>