<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/connection.php';

// ==========================================
// 1. PARAMETER & VALIDASI
// ==========================================
$peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

$bulan_list = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// ==========================================
// 2. FUNGSI HELPER - KUALITAS
// ==========================================
function hitungKualitas($ketuk, $tuntun) {
    $s_k = ($ketuk < 3) ? 0 : (($ketuk == 3) ? 1 : 2);
    $s_t = ($tuntun < 2) ? 0 : (($tuntun == 2) ? 1 : 2);
    $max = max($s_k, $s_t);
    
    if ($max == 2) return ['status' => 'Tidak Lancar', 'class' => 'badge-danger', 'icon' => 'xmark'];
    if ($max == 1) return ['status' => 'Cukup', 'class' => 'badge-warning', 'icon' => 'minus'];
    return ['status' => 'Lancar', 'class' => 'badge-success', 'icon' => 'check'];
}

// ==========================================
// 3. FUNGSI: HITUNG PEKAN BERDASARKAN MINGGU PERTAMA (Sesuai Kalender)
// ==========================================
/**
 * Pekan 1 = Minggu yang mengandung tanggal 1 bulan (Minggu-Sabtu)
 * Pekan 2 = Minggu berikutnya, dst.
 * Contoh Mei 2025: Pekan 1 = 4-10 Mei (karena 4 Mei adalah Minggu pertama)
 */
function getPekanBerdasarkanMinggu($tanggal, $bulan, $tahun) {
    $date_ts = strtotime($tanggal);
    
    // 1. Cari hari pertama bulan (0=Sunday, 1=Monday, ..., 6=Saturday)
    $first_day = strtotime("$tahun-$bulan-01");
    $first_dow = (int)date('w', $first_day);
    
    // 2. Hitung tanggal Minggu pertama di bulan ini
    if ($first_dow == 0) {
        // Jika tgl 1 adalah Minggu, itu Minggu pertama
        $first_sunday = $first_day;
    } else {
        // Jika tidak, maju ke Minggu berikutnya
        $first_sunday = strtotime("+".(7 - $first_dow)." days", $first_day);
    }
    
    // 3. Jika tanggal sebelum Minggu pertama, tetap Pekan 1
    if ($date_ts < $first_sunday) {
        return 1;
    }
    
    // 4. Hitung selisih hari dari Minggu pertama
    $diff_days = ($date_ts - $first_sunday) / (24 * 60 * 60);
    $pekan = (int)floor($diff_days / 7) + 1;
    
    // 5. Batasi 1-5
    return max(1, min(5, $pekan));
}

// ==========================================
// 4. AMBIL DATA PESERTA
// ==========================================
$peserta_list = [];
$res_p = $conn->query("SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC");
if ($res_p) while ($r = $res_p->fetch_assoc()) $peserta_list[] = $r;

$nama_peserta = '';
if ($peserta_id > 0) {
    $stmt = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
    $stmt->bind_param("i", $peserta_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $nama_peserta = $row['nama'];
    $stmt->close();
}

// ==========================================
// 5. PROSES DATA MUROJAAH
// ==========================================
$weeks = [];
for ($i = 1; $i <= 5; $i++) {
    $weeks[$i] = [
        'juz_list' => [],
        'ketuk' => 0,
        'tuntun' => 0,
        'sessions' => 0,
        'detail' => [],
        'has_data' => false,
        'date_start' => '',
        'date_end' => ''
    ];
}
$monthly_stats = ['total_juz' => 0, 'total_sessions' => 0, 'total_ketuk' => 0, 'total_tuntun' => 0];
$all_juz = [];

if ($peserta_id > 0) {
    $start_month = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    $end_month   = date('Y-m-t', strtotime("$tahun-$bulan-01"));

    $stmt = $conn->prepare("SELECT juz, ketuk, tuntun, tanggal FROM manzil_data WHERE peserta_id = ? AND tanggal >= ? AND tanggal <= ? ORDER BY tanggal ASC");
    $stmt->bind_param("iss", $peserta_id, $start_month, $end_month);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tanggal = $row['tanggal'];
        
        // ✅ PAKAI FUNGSI PEKAN BERDASARKAN MINGGU PERTAMA
        $wk = getPekanBerdasarkanMinggu($tanggal, $bulan, $tahun);

        if (isset($weeks[$wk])) {
            $weeks[$wk]['ketuk'] += $row['ketuk'];
            $weeks[$wk]['tuntun'] += $row['tuntun'];
            $weeks[$wk]['sessions']++;
            $weeks[$wk]['has_data'] = true;
            $weeks[$wk]['juz_list'][$row['juz']] = true;

            $j = $row['juz'];
            if (!isset($weeks[$wk]['detail'][$j])) {
                $weeks[$wk]['detail'][$j] = ['juz' => $j, 'count' => 0, 'ketuk' => 0, 'tuntun' => 0];
            }
            $weeks[$wk]['detail'][$j]['count']++;
            $weeks[$wk]['detail'][$j]['ketuk'] += $row['ketuk'];
            $weeks[$wk]['detail'][$j]['tuntun'] += $row['tuntun'];
            
            // Track tanggal untuk display range
            if (empty($weeks[$wk]['date_start']) || $tanggal < $weeks[$wk]['date_start']) {
                $weeks[$wk]['date_start'] = $tanggal;
            }
            if (empty($weeks[$wk]['date_end']) || $tanggal > $weeks[$wk]['date_end']) {
                $weeks[$wk]['date_end'] = $tanggal;
            }
        }

        $monthly_stats['total_ketuk'] += $row['ketuk'];
        $monthly_stats['total_tuntun'] += $row['tuntun'];
        $monthly_stats['total_sessions']++;
        $all_juz[$row['juz']] = true;
    }
    $stmt->close();
    $monthly_stats['total_juz'] = count($all_juz);
}

// ==========================================
// 6. PROSES UNTUK TAMPILAN
// ==========================================
$monthly_quality = ['status' => 'Lancar', 'class' => 'badge-success', 'icon' => 'check'];
if ($monthly_stats['total_sessions'] > 0) {
    $monthly_quality = hitungKualitas(
        $monthly_stats['total_ketuk'] / $monthly_stats['total_sessions'],
        $monthly_stats['total_tuntun'] / $monthly_stats['total_sessions']
    );
}

$processed_weeks = [];

for ($w = 1; $w <= 5; $w++) {
    $data = $weeks[$w];
    
    // Gunakan tanggal dari data aktual, atau hitung range fallback
    if (!empty($data['date_start']) && !empty($data['date_end'])) {
        $date_start = $data['date_start'];
        $date_end = $data['date_end'];
    } else {
        // Fallback: hitung range pekan berdasarkan Minggu
        $first_day = date('Y-m-01', strtotime("$tahun-$bulan-01"));
        $first_dow = (int)date('w', strtotime($first_day));
        $first_sunday = ($first_dow == 0) ? $first_day : date('Y-m-d', strtotime($first_day . ' + ' . (7 - $first_dow) . ' days'));
        
        $date_start = date('Y-m-d', strtotime($first_sunday . ' + ' . (($w-1)*7) . ' days'));
        $date_end = date('Y-m-d', strtotime($date_start . ' +6 days'));
        
        if (date('m', strtotime($date_start)) != $bulan) continue;
        if (date('m', strtotime($date_end)) != $bulan) {
            $date_end = date('Y-m-t', strtotime("$tahun-$bulan-01"));
        }
    }

    $juz_details = [];
    if (isset($data['detail']) && is_array($data['detail']) && !empty($data['detail'])) {
        foreach ($data['detail'] as $d) {
            $avg_k = $d['count'] > 0 ? $d['ketuk'] / $d['count'] : 0;
            $avg_t = $d['count'] > 0 ? $d['tuntun'] / $d['count'] : 0;
            $q = hitungKualitas($avg_k, $avg_t);
            $juz_details[] = [
                'juz' => $d['juz'],
                'freq' => $d['count'],
                'avg_k' => round($avg_k, 1),
                'avg_t' => round($avg_t, 1),
                'status' => $q['status'],
                'class' => $q['class'],
                'icon' => $q['icon']
            ];
        }
    }
    
    usort($juz_details, fn($a, $b) => $a['juz'] <=> $b['juz']);

    $week_quality = '-';
    $week_badge = 'bg-gray-100 text-gray-500';
    if ($data['sessions'] > 0) {
        $avg_k = $data['ketuk'] / $data['sessions'];
        $avg_t = $data['tuntun'] / $data['sessions'];
        $q = hitungKualitas($avg_k, $avg_t);
        $week_quality = $q['status'];
        $week_badge = $q['class'];
    }

    $processed_weeks[$w] = [
        'index' => $w,
        'date_range' => date('d M', strtotime($date_start)) . ' - ' . date('d M', strtotime($date_end)),
        'total_juz' => is_array($data['juz_list']) ? count($data['juz_list']) : 0,
        'sessions' => $data['sessions'],
        'quality' => $week_quality,
        'badge' => $week_badge,
        'juz_details' => $juz_details,
        'has_data' => $data['has_data']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kualitas Muroja'ah · <?= htmlspecialchars($nama_peserta ?: 'Reqra') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --colors-ink: #1d1d1f; /* Near-Black Ink */
            --colors-ink-muted-80: #333333;
            --colors-ink-muted-48: #7a7a7a;
            --colors-hairline: #e0e0e0;

            /* Custom colors for badges based on existing logic, adapted to Apple's aesthetic */
            --badge-success-bg: #dcfce7; /* Light Green */
            --badge-success-text: #166534; /* Dark Green */
            --badge-danger-bg: #fee2e2; /* Light Red */
            --badge-danger-text: #991b1b; /* Dark Red */
            --badge-warning-bg: #fef3c7; /* Light Yellow */
            --badge-warning-text: #92400e; /* Dark Yellow */

            /* Spacing tokens (using 8px base unit) */
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px; /* Adjusted from 17px for consistency with 8px grid */
            --spacing-lg: 24px;
            --spacing-section: 80px;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.47; /* typography.body line-height */
        }
    </style>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
            background-attachment: fixed;
            color: #334155;
        }
        .glass-card {
            background: var(--colors-canvas);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--colors-hairline);
            border-radius: 18px; /* rounded.lg */
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04), 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        } 
        .apple-select {
            background: var(--colors-canvas);
            border: 1px solid var(--colors-hairline);
            border-radius: 8px; /* rounded.sm */
            padding: 0.6rem 0.8rem; /* typography.caption */
            font-size: 0.875rem; /* typography.caption */
            width: 100%;
            color: var(--colors-ink);
            background-image: url("image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            transition: all 0.2s ease;
        }
        .glass-input:focus {
            border-color: var(--colors-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2); /* Focus Blue effect */
            background: var(--colors-canvas);
        }
        .badge-success { 
            background: var(--badge-success-bg);
            color: var(--badge-success-text);
            border: 1px solid var(--badge-success-bg);
            box-shadow: none; /* Apple's badges are flat */
        }
        .badge-warning { 
            background: var(--badge-warning-bg);
            color: var(--badge-warning-text);
            border: 1px solid var(--badge-warning-bg);
            box-shadow: none;
        }
        .badge-danger  { 
            background: var(--badge-danger-bg);
            color: var(--badge-danger-text);
            border: 1px solid var(--badge-danger-bg);
            box-shadow: none;
        }
        .status-badge {
            display: inline-flex; align-items: center; padding: 0.45rem 1.1rem; /* button-primary padding */
            font-size: 0.875rem; font-weight: 600; border-radius: 9999px; gap: 0.5rem;
            transition: transform 0.2s ease;
        }
        .status-badge:hover { transform: scale(1.03); }
        
        .summary-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.95), rgba(248,250,252,0.95));
            border: 1px solid var(--colors-hairline);
            border-radius: 18px; /* rounded.lg */
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255,255,255,0.8);
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: ''; /* Remove decorative gradient line */
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(59,130,246,0.3), transparent);
            opacity: 0.6;
        }
        .summary-card.quality-card::before {
            background: linear-gradient(90deg, transparent, rgba(16,185,129,0.4), transparent);
        }
        .summary-card.total-card::before {
            background: linear-gradient(90deg, transparent, rgba(139,92,246,0.4), transparent);
        }
        
        .accordion-content { /* Adapted for Apple's smooth transitions */
            max-height: 0; overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .accordion-content.open { max-height: 1000px; }
        .chevron { transition: transform 0.3s ease; }
        .chevron.rotated { transform: rotate(180deg); }
        
        .juz-item {
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border: 1px solid var(--colors-hairline);
            border-radius: 11px; /* rounded.md */
            transition: all 0.2s ease;
        }
        .juz-item:hover {
            border-color: var(--colors-primary);
            box-shadow: 0 0 0 1px rgba(0, 102, 204, 0.1);
            transform: translateY(-1px);
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
        .animate-float { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen pb-12">
    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <!-- Header - Adapted to Apple's style -->
        <?php
            $header_icon = 'fa-chart-line';
            $header_title = "KUALITAS MUROJA'AH";
            $header_subtitle = 'PERKEMBANGAN HAFALAN SANTRI';
            include 'header_content.php';
            ?>

        <!-- Filter Form (NO PRINT BUTTON) -->
        <div class="glass-card p-5 mb-8">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-5">
                    <label class="block text-xs font-semibold text-gray-500 mb-2 ml-1 uppercase tracking-wide">Nama Santri</label>
                    <select name="peserta_id" class="apple-select text-gray-700 cursor-pointer" onchange="this.form.submit()">
                        <option value="">-- Pilih Nama --</option>
                        <?php foreach ($peserta_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $peserta_id == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 mb-2 ml-1 uppercase tracking-wide">BULAN</label>
                    <select name="bulan" class="apple-select text-gray-700 cursor-pointer" onchange="this.form.submit()">
                        <?php for ($i=1; $i<=12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= $bulan_list[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-2 ml-1 uppercase tracking-wide">TAHUN</label>
                    <select name="tahun" class="apple-select text-gray-700 cursor-pointer" onchange="this.form.submit()">
                        <?php $cy = date('Y'); for ($y=$cy; $y>=$cy-3; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="md:col-span-2 flex items-end">
                    <div class="w-full h-12"></div>
                </div>
            </form>
        </div>

        <?php if ($peserta_id > 0): ?>
            <!-- DASHBOARD SUMMARY -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Card Kualitas Utama -->
                <div class="summary-card quality-card p-6 flex flex-col justify-center items-center text-center">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3" style="letter-spacing: 0.05em;">Kualitas Bulan Ini</span>
                    <div class="text-lg font-semibold text-gray-800 mb-2"><?= $nama_peserta ?></div>
                    <div class="status-badge text-lg px-7 py-4 <?= $monthly_quality['class'] ?> shadow-lg transform hover:scale-105 transition-transform">
                        <i class="fas <?= $monthly_quality['icon'] ?>"></i>
                        <?= $monthly_quality['status'] ?>
                    </div>
                    <div class="mt-5 w-full">
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 <?= 
                                $monthly_quality['status'] === 'Lancar' ? 'bg-gradient-to-r from-emerald-400 to-emerald-500 w-full' : 
                                ($monthly_quality['status'] === 'Cukup' ? 'bg-gradient-to-r from-amber-400 to-amber-500 w-2/3' : 
                                'bg-gradient-to-r from-red-400 to-red-500 w-1/3')
                            ?>"></div>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-3">Berdasarkan rata-rata ketuk & tuntun</p>
                </div>

                <!-- Card Total Muroja'ah -->
                <div class="summary-card total-card p-6 flex flex-col justify-center items-center text-center">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3" style="letter-spacing: 0.05em;">Total Muroja'ah</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-6xl font-bold bg-gradient-to-br from-gray-800 to-gray-600 bg-clip-text text-transparent">
                            <?= $monthly_stats['total_sessions'] ?>
                        </span>
                        <span class="text-2xl text-gray-400 font-medium">Juz</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Selama <span class="font-medium text-gray-600"><?= $bulan_list[$bulan] ?> <?= $tahun ?></span>
                    </p>
                    <div class="mt-4 flex items-center gap-2 text-[11px] text-gray-400">
                        <i class="fas fa-chart-pie"></i>
                        <span><?= count(array_filter($processed_weeks, fn($w) => $w['has_data'])) ?> pekan aktif</span>
                    </div>
                </div>
            </div>

            <!-- DETAIL PEKANAN -->
            <div class="glass-card overflow-hidden">
                <div class="p-5 border-b border-gray-200/60 bg-gray-50/50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-layer-group text-blue-500"></i> Rincian Per Pekan
                        <span class="text-xs font-normal text-gray-400 ml-2" style="letter-spacing: -0.02em;">(Minggu-Sabtu)</span>
                    </h2>
                </div>
                <div class="p-2 md:p-5 space-y-3">
                    <?php foreach ($processed_weeks as $w): ?>
                    <div class="border border-gray-200/60 rounded-xl overflow-hidden bg-white/80 hover:shadow-md transition-all backdrop-blur-sm">
                        <button onclick="toggleWeek(<?= $w['index'] ?>)" class="w-full flex items-center justify-between p-4 bg-white/60 hover:bg-gray-50/80 transition-colors cursor-pointer">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-blue-50/50 flex items-center justify-center text-blue-600 font-bold border border-blue-100">
                                    <?= $w['index'] ?>
                                </div>
                                <div class="text-left">
                                    <div class="font-bold text-gray-800">Pekan ke-<?= $w['index'] ?></div>
                                    <div class="text-xs text-gray-400"><?= $w['date_range'] ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <?php if ($w['has_data']): ?>
                                    <div class="hidden md:flex flex-col items-end mr-4 text-right">
                                        <span class="text-[10px] text-gray-400 font-semibold"><?= $w['total_juz'] ?> Juz</span>
                                        <span class="text-[10px] text-gray-400 font-semibold"><?= $w['sessions'] ?> Sesi</span>
                                    </div>
                                    <span class="status-badge <?= $w['badge'] ?> text-xs shadow-sm"><?= $w['quality'] ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">Tidak ada data</span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down text-gray-400 chevron" id="icon-<?= $w['index'] ?>"></i>
                            </div>
                        </button>

                        <div id="week-<?= $w['index'] ?>" class="accordion-content <?= $w['index'] == 1 && $w['has_data'] ? 'open' : '' ?>">
                            <div class="p-4 border-t border-gray-100/60 bg-gray-50/40">
                                <?php if (empty($w['juz_details'])): ?>
                                    <div class="text-center py-6 text-gray-400 italic text-sm">
                                        <i class="fas fa-mug-hot mb-2 text-lg block opacity-50"></i>Belum ada setoran di pekan ini.
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach ($w['juz_details'] as $juz): ?>
                                            <div class="juz-item p-3 flex items-center justify-between" style="border-radius: 11px;">
                                                <div>
                                                    <div class="text-sm font-bold text-gray-700">Juz <?= $juz['juz'] ?></div>
                                                    
                                                </div>
                                                <span class="status-badge <?= $juz['class'] ?> text-[10px] px-2 py-1 shadow-sm">
                                                    <?= $juz['status'] ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- LEGENDA -->
            <div class="mt-6 flex flex-wrap gap-4 justify-center text-xs text-gray-500 glass-card p-4">
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-emerald-500"></i> Lancar (Ketuk ≤2, Tuntun ≤1)</span>
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-amber-500"></i> Cukup (Ketuk =3, Tuntun =2)</span>
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-red-500"></i> Tidak Lancar (Ketuk >3, Tuntun >2)</span>
            </div>

        <?php else: ?>
            <div class="glass-card p-16 text-center">
                <div class="w-20 h-20 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl animate-float">📖</div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Pilih Nama Santri</h3>
                <p class="text-gray-500 max-w-md mx-auto">Silakan pilih nama santri pada filter di atas untuk melihat laporan kualitas muroja'ah.</p>
            </div>
        <?php endif; ?>

        <?php include 'footer_content.php'; ?>
    </div>

    <script>
        function toggleWeek(index) {
            const content = document.getElementById('week-' + index);
            const icon = document.getElementById('icon-' + index);
            if (content && icon) {
                content.classList.toggle('open');
                icon.classList.toggle('rotated');
            }
        }
    </script>
</body>
</html>