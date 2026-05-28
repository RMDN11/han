<?php
session_start();
require_once 'config/connection.php';

// Ambil parameter
$peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : 0;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Ambil daftar peserta
$peserta_list = [];
$query_peserta = "SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC";
$result_peserta = $conn->query($query_peserta);
while ($row = $result_peserta->fetch_assoc()) {
    $peserta_list[] = $row;
}

// Ambil nama peserta yang dipilih
$nama_peserta = '';
if ($peserta_id > 0) {
    $stmt = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
    $stmt->bind_param("i", $peserta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nama_peserta = $row['nama'];
    }
    $stmt->close();
}

// Tentukan tanggal awal dan akhir bulan
$tanggal_awal_bulan = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir_bulan = date('Y-m-t', strtotime("$tahun-$bulan-01"));

// Ambil semua minggu dalam bulan ini
$minggu_list = [];
for ($i = 1; $i <= 5; $i++) {
    $minggu_ke = (int)date('W', strtotime($tanggal_awal_bulan . ' + ' . ($i-1) . ' weeks'));
    if ($minggu_ke > 0) {
        $minggu_list[$i] = $minggu_ke;
    }
}

// Ambil data rangkuman per minggu
$minggu_data = [];
$detail_juz_per_minggu = [];

if ($peserta_id > 0) {
    foreach ($minggu_list as $minggu_index => $minggu_ke) {
        // Ambil data rangkuman
        $stmt = $conn->prepare("
            SELECT 
                mr.*,
                pm.nama
            FROM manzil_rangkuman mr
            JOIN peserta_manzil pm ON mr.peserta_id = pm.id
            WHERE mr.peserta_id = ? 
                AND mr.minggu_ke = ?
                AND mr.tahun = ?
        ");
        $stmt->bind_param("iii", $peserta_id, $minggu_ke, $tahun);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $minggu_data[$minggu_index] = $row;
        } else {
            $minggu_data[$minggu_index] = [
                'total_juz' => 0,
                'total_murojaah' => 0,
                'rata_ketuk' => 0,
                'rata_tuntun' => 0,
                'kualitas' => '-',
                'minggu_ke' => $minggu_ke
            ];
        }
        $stmt->close();
        
        // Hitung tanggal awal minggu
        $tanggal_awal = date('Y-m-d', strtotime($tahun . 'W' . str_pad($minggu_ke, 2, '0', STR_PAD_LEFT) . '-1'));
        
        // Pastikan tanggal masih dalam bulan yang sama
        if (date('m', strtotime($tanggal_awal)) == $bulan || 
            date('m', strtotime($tanggal_awal . ' +6 days')) == $bulan) {
            
            $tanggal_akhir = date('Y-m-d', strtotime($tanggal_awal . ' +6 days'));
            
            // Hitung total juz (COUNT semua record)
            $stmt_juz = $conn->prepare("
                SELECT COUNT(*) as total_juz
                FROM manzil_data 
                WHERE peserta_id = ? 
                    AND tanggal >= ? 
                    AND tanggal <= ?
            ");
            $stmt_juz->bind_param("iss", $peserta_id, $tanggal_awal, $tanggal_akhir);
            $stmt_juz->execute();
            $result_juz = $stmt_juz->get_result();
            $juz_row = $result_juz->fetch_assoc();
            $minggu_data[$minggu_index]['total_juz'] = $juz_row['total_juz'] ?? 0;
            $stmt_juz->close();
            
            // Ambil detail juz per minggu dengan frekuensi
            $stmt_detail = $conn->prepare("
                SELECT 
                    juz,
                    COUNT(*) as frekuensi,
                    AVG(ketuk) as rata_ketuk,
                    AVG(tuntun) as rata_tuntun
                FROM manzil_data 
                WHERE peserta_id = ? 
                    AND tanggal >= ? 
                    AND tanggal <= ?
                GROUP BY juz
                ORDER BY juz ASC
            ");
            $stmt_detail->bind_param("iss", $peserta_id, $tanggal_awal, $tanggal_akhir);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            
            $detail_juz = [];
            while ($row = $result_detail->fetch_assoc()) {
                // Tentukan kualitas per juz
                $rata_ketuk = $row['rata_ketuk'] ?? 0;
                $rata_tuntun = $row['rata_tuntun'] ?? 0;
                
                if ($rata_ketuk > 3 || $rata_tuntun > 2) {
                    $kualitas_juz = 'Tidak Lancar';
                    $badge_class = 'badge-tidak';
                } elseif ($rata_ketuk >= 2 || $rata_tuntun >= 1) {
                    $kualitas_juz = 'Cukup Lancar';
                    $badge_class = 'badge-cukup';
                } else {
                    $kualitas_juz = 'Lancar';
                    $badge_class = 'badge-lancar';
                }
                
                $row['kualitas'] = $kualitas_juz;
                $row['badge_class'] = $badge_class;
                $detail_juz[] = $row;
            }
            $detail_juz_per_minggu[$minggu_index] = $detail_juz;
            $stmt_detail->close();
        } else {
            $detail_juz_per_minggu[$minggu_index] = [];
            $minggu_data[$minggu_index]['total_juz'] = 0;
        }
    }
}

// Hitung total dan rata-rata bulanan
$total_juz_bulan = 0;
$total_ketuk = 0;
$total_tuntun = 0;
$count_minggu = 0;

foreach ($minggu_data as $data) {
    if ($data['total_juz'] > 0) {
        $total_juz_bulan += $data['total_juz'];
        $total_ketuk += $data['rata_ketuk'];
        $total_tuntun += $data['rata_tuntun'];
        $count_minggu++;
    }
}

$rata_ketuk_bulan = $count_minggu > 0 ? round($total_ketuk / $count_minggu, 2) : 0;
$rata_tuntun_bulan = $count_minggu > 0 ? round($total_tuntun / $count_minggu, 2) : 0;

// Tentukan kualitas bulanan
if ($rata_ketuk_bulan > 3 || $rata_tuntun_bulan > 2) {
    $kualitas_bulan = 'Tidak Lancar';
    $badge_color = 'bg-[#f8edec] border border-[#eddbda] text-[#8f5e5e]';
} elseif ($rata_ketuk_bulan >= 2 || $rata_tuntun_bulan >= 1) {
    $kualitas_bulan = 'Cukup Lancar';
    $badge_color = 'bg-[#f9f3e9] border border-[#ece1d2] text-[#8a7a5c]';
} else {
    $kualitas_bulan = 'Lancar';
    $badge_color = 'bg-[#ecf3f0] border border-[#d1e0db] text-[#3f6a5c]';
}

$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai · <?= htmlspecialchars($nama_peserta ?: 'Murojaah') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--colors-canvas-parchment);
            color: var(--colors-ink);
            -webkit-font-smoothing: antialiased;
            line-height: 1.47; /* typography.body line-height */
        }
        
        .apple-card {
            background-color: var(--colors-canvas);
            border: 1px solid var(--colors-hairline);
            border-radius: 18px; /* rounded.lg */
            box-shadow: 0 1px 0 var(--colors-divider-soft);
        }
        
        .apple-select {
            background-color: var(--colors-canvas);
            border: 1px solid var(--colors-hairline);
            padding: 0.6rem 0.8rem; /* typography.caption */
            font-size: 0.875rem; /* typography.caption */
            border-radius: 8px; /* rounded.sm */
            color: var(--colors-ink);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .apple-select:focus {
            border-color: var(--colors-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2); /* Focus Blue effect */
        }
        
        .badge-lancar {
            background-color: var(--badge-success-bg);
            border: 1px solid var(--badge-success-bg); /* Use same color for border */
            color: var(--badge-success-text);
        }
        
        .badge-cukup {
            background-color: var(--badge-warning-bg);
            border: 1px solid var(--badge-warning-bg);
            color: var(--badge-warning-text);
        }
        
        .badge-tidak {
            background-color: var(--badge-danger-bg);
            border: 1px solid var(--badge-danger-bg);
            color: var(--badge-danger-text);
        }
        
        .kualitas-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 1.1rem; /* status-badge padding */
            font-size: 0.875rem; /* typography.caption */
            font-weight: 600; /* typography.caption-strong */
            border-width: 1px;
            border-radius: 9999px; /* rounded.pill */
        }

        .rotate-180 {
            transform: rotate(180deg);
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-6xl mx-auto">
        
        <!-- Header - Adapted to Apple's style -->
        <div class="apple-card p-5 mb-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-semibold text-gray-800 tracking-tight">NILAI MUROJA'AH</h1>
                        <p class="text-xs text-gray-500 mt-0.5" style="letter-spacing: -0.02em;">KUALITAS MUROJA'AH · <?= $bulan_list[$bulan-1] ?> <?= $tahun ?></p>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-sm font-medium text-gray-700">PROGRAM ASRAMA TAHFIZH INTENSIF</p>
                    <p class="text-xs text-gray-400">MAHAD IMAM SYATHBY BOGOR</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="apple-card p-5 mb-7">
            <form method="GET" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1 tracking-wide">NAMA SANTRI</label>
                        <select name="peserta_id" class="w-full apple-select text-sm" onchange="this.form.submit()">
                            <option value="">-- Pilih Nama --</option>
                            <?php foreach ($peserta_list as $peserta): ?>
                            <option value="<?= $peserta['id'] ?>" <?= $peserta_id == $peserta['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($peserta['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1 tracking-wide">BULAN</label>
                        <select name="bulan" class="w-full apple-select text-sm" onchange="this.form.submit()">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                                <?= $bulan_list[$i-1] ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1 tracking-wide">TAHUN</label>
                        <select name="tahun" class="w-full apple-select text-sm" onchange="this.form.submit()">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($peserta_id > 0): ?>
        
        <!-- KUALITAS BULANAN -->
        <div class="apple-card p-6 mb-7">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Kualitas Bulan Ini</span>
                    <div class="flex items-center gap-4 mt-2">
                        <span class="text-3xl font-bold text-gray-800"><?= $kualitas_bulan ?></span>
                        <span class="kualitas-badge <?= $badge_color ?> rounded">
                            <i class="fas fa-<?= $kualitas_bulan === 'Lancar' ? 'check' : ($kualitas_bulan === 'Cukup Lancar' ? 'minus' : 'xmark') ?> mr-2"></i>
                            <?= $bulan_list[$bulan-1] ?> <?= $tahun ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PERBANDINGAN PER MINGGU -->
        <div class="apple-card p-6 mb-7">
            <div class="flex items-center gap-3">
                <i class="fas fa-chart-simple text-gray-500 text-sm"></i>
                <span class="text-sm font-medium text-gray-700 tracking-wide">PERBANDINGAN PER MINGGU</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pekan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Juz</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Kualitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $minggu_ditampilkan = 0;
                        foreach ($minggu_data as $minggu_index => $data):
                            $minggu_ke = $data['minggu_ke'];
                            $tanggal_awal = date('Y-m-d', strtotime($tahun . 'W' . str_pad($minggu_ke, 2, '0', STR_PAD_LEFT) . '-1'));
                            
                            if (date('m', strtotime($tanggal_awal)) != $bulan && 
                                date('m', strtotime($tanggal_awal . ' +6 days')) != $bulan) {
                                continue;
                            }
                            $minggu_ditampilkan++;
                            
                            $kualitas_status = $data['kualitas'];
                            $badge_class = '';
                            $icon_class = '';
                            if ($kualitas_status === 'Lancar') {
                                $badge_class = 'badge-lancar';
                                $icon_class = 'check';
                            } elseif ($kualitas_status === 'Cukup Lancar') {
                                $badge_class = 'badge-cukup';
                                $icon_class = 'minus';
                            } elseif ($kualitas_status === 'Tidak Lancar') {
                                $badge_class = 'badge-tidak';
                                $icon_class = 'xmark';
                            } else {
                                $badge_class = 'bg-gray-100 text-gray-500 border border-gray-200';
                                $icon_class = 'circle';
                            }
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                            <td class="px-4 py-4 text-[#5d6e6f] font-medium">Pekan <?= $minggu_index ?></td>
                            <td class="px-4 py-4 text-[#7c8c8d] text-xs">
                                <?= date('d M', strtotime($tanggal_awal)) ?> - <?= date('d M', strtotime($tanggal_awal . ' +6 days')) ?>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="font-mono text-[#6a8e8c] font-medium"><?= $data['total_juz'] ?></span>
                                <span class="text-xs text-[#9aa6a7] ml-1">juz</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <?php if ($data['total_juz'] > 0): ?>
                                <span class="kualitas-badge text-xs <?= $badge_class ?>">
                                    <i class="fas fa-<?= $icon_class ?> mr-1.5 text-[10px]"></i>
                                    <?= $kualitas_status ?>
                                </span>
                                <?php else: ?>
                                <span class="kualitas-badge text-xs bg-gray-100 text-gray-500 border border-gray-200">
                                    <i class="fas fa-circle mr-1.5 text-[8px] text-[#b7aa99]"></i>
                                    Tidak ada data
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if ($minggu_ditampilkan == 0): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm italic">
                                Belum ada data murojaah pada bulan <?= $bulan_list[$bulan-1] ?> <?= $tahun ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Legend -->
            <div class="mt-5 pt-4 border-t border-gray-200 flex flex-wrap gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-emerald-500"></i> Lancar</span>
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-amber-500"></i> Cukup Lancar</span>
                <span class="flex items-center gap-1.5"><i class="fas fa-circle text-red-500"></i> Tidak Lancar</span>
            </div>
        </div>

        <!-- TOMBOL DESKTOP -->
        <div class="hidden md:block mb-4 text-right">
            <a href="laporanhan.php?bulan=<?= urlencode($bulan_list[$bulan-1]) ?>&tahun=<?= $tahun ?>" 
               class="inline-flex items-center px-4 py-2 btn-apple-outline text-sm">
                <i class="fas fa-file-alt mr-2"></i>
                <span>Laporan Hafalan</span>
            </a>
        </div>

        <!-- DETAIL JUZ PER MINGGU - DROPDOWN -->
        <div class="apple-card mb-7">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <i class="fas fa-table-list text-gray-500 text-sm"></i>
                    <span class="text-sm font-medium text-gray-700 tracking-wide">
                        DETAIL JUZ · <?= strtoupper($bulan_list[$bulan-1]) ?> <?= $tahun ?>
                    </span>
                </div>
            </div>
            
            <?php 
            $minggu_ditampilkan = 0;
            foreach ($minggu_data as $minggu_index => $data):
                $minggu_ke = $data['minggu_ke'];
                $tanggal_awal = date('Y-m-d', strtotime($tahun . 'W' . str_pad($minggu_ke, 2, '0', STR_PAD_LEFT) . '-1'));
                
                if (date('m', strtotime($tanggal_awal)) != $bulan && 
                    date('m', strtotime($tanggal_awal . ' +6 days')) != $bulan) {
                    continue;
                }
                $minggu_ditampilkan++;
                $total_juz_minggu = $data['total_juz'] ?? 0;
            ?>
            <div class="border-b border-[#f0ebe5] last:border-b-0">
                <!-- Dropdown Header -->
                <div class="p-4 bg-gray-50/50 hover:bg-gray-100/50 cursor-pointer transition-colors duration-150" 
                     onclick="toggleDropdown('dropdown-<?= $minggu_index ?>')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-3">
                                <span class="text-base font-semibold text-gray-800">Pekan ke-<?= $minggu_index ?></span>
                                <span class="text-xs text-gray-500">
                                    <?= date('d M', strtotime($tanggal_awal)) ?> - 
                                    <?= date('d M', strtotime($tanggal_awal . ' +6 days')) ?>
                                </span>
                            </div>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full text-gray-600">
                                <?= $total_juz_minggu ?> Juz
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if ($total_juz_minggu > 0):
                                $kualitas = $data['kualitas'];
                                $icon = ($kualitas == 'Lancar') ? 'check' : (($kualitas == 'Cukup Lancar') ? 'minus' : 'xmark');
                                $bg_color = ($kualitas == 'Lancar') ? 'bg-[#ecf3f0]' : (($kualitas == 'Cukup Lancar') ? 'bg-[#f9f3e9]' : 'bg-[#f8edec]');
                            ?>
                            <span class="w-6 h-6 <?= $bg_color ?> rounded-full flex items-center justify-center border border-[#d1e0db]">
                                <i class="fas fa-<?= $icon ?> text-[10px] text-[#5d6e6f]"></i>
                            </span>
                            <?php endif; ?> 
                            <i class="fas fa-chevron-down text-gray-400 text-sm transition-transform duration-200" id="icon-<?= $minggu_index ?>"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Dropdown Content -->
                <div id="dropdown-<?= $minggu_index ?>" class="hidden p-4 pt-2 bg-white border-t border-gray-200">
                    <?php if (!empty($detail_juz_per_minggu[$minggu_index])): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                        <?php foreach ($detail_juz_per_minggu[$minggu_index] as $juz): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50/50 border border-gray-200 rounded-lg hover:border-blue-300 transition-colors">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-gray-800 font-semibold">Juz <?= $juz['juz'] ?></span>
                                <span class="text-gray-500 text-xs">·</span>
                                <span class="text-xs text-gray-600"><?= $juz['frekuensi'] ?>x</span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 text-[10px] border rounded-full <?= $juz['badge_class'] ?>">
                                <i class="fas fa-<?= $juz['kualitas'] == 'Lancar' ? 'check' : ($juz['kualitas'] == 'Cukup Lancar' ? 'minus' : 'xmark') ?> mr-1"></i>
                                <?= $juz['kualitas'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-xs italic text-gray-400 py-4 text-center">
                        <i class="far fa-frown mr-1"></i> Tidak ada data murojaah di pekan ini
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if ($minggu_ditampilkan == 0): ?>
            <div class="p-10 text-center text-gray-400 text-sm italic">
                <i class="far fa-folder-open mb-2 text-2xl block"></i>
                Belum ada data murojaah pada bulan <?= $bulan_list[$bulan-1] ?> <?= $tahun ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- FLOATING BUTTON MOBILE - BISA DI-TOGGLE -->
        <div id="floatingButton" class="fixed bottom-6 right-6 z-50 md:hidden">
            <div id="floatingContent" class="flex items-center bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg transition-all duration-300 overflow-hidden cursor-pointer"
                style="box-shadow: 0 4px 14px rgba(0, 102, 204, 0.35);">
                <a href="laporanhan.php?bulan=<?= urlencode($bulan_list[$bulan-1]) ?>&tahun=<?= $tahun ?>" 
                   class="flex items-center gap-2 px-5 py-3 text-white no-underline w-full h-full" id="floatingLink">
                    <i class="fas fa-file-alt text-lg"></i>
                    <span id="floatingText" class="text-sm font-medium whitespace-nowrap">Lihat Laporan Hafalan</span>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-10 text-center">
            <p class="text-xs text-gray-400 tracking-wide">
                <i class="fas fa-circle mr-1 text-[6px] align-middle"></i>
                Reqra by Han · <?= date('Y') ?>
            </p>
        </div>
        
        <?php else: ?>
        <!-- Empty State - Apple-style -->
        <div class="apple-card p-10 text-center">
            <div class="w-20 h-20 bg-blue-50/50 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-blue-500">📖</div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Pilih Nama Santri</h3>
            <p class="text-gray-500 max-w-md mx-auto">Silakan pilih nama santri pada filter di atas untuk melihat laporan kualitas muroja'ah.</p>
        </div>
        <?php endif; ?>
        
    </div>

    <script>
    // Toggle dropdown
    function toggleDropdown(id) {
        const content = document.getElementById(id);
        const icon = document.getElementById('icon-' + id.split('-')[1]);
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    // Buka dropdown pertama secara default
    document.addEventListener('DOMContentLoaded', function() {
        const firstDropdown = document.getElementById('dropdown-1');
        if (firstDropdown) {
            firstDropdown.classList.remove('hidden');
            const firstIcon = document.getElementById('icon-1');
            if (firstIcon) firstIcon.classList.add('rotate-180');
        }
        
        // Inisialisasi floating button
        initFloatingButton();
    });

    // Floating button toggle
    let isExpanded = localStorage.getItem('fabExpanded') !== 'false';

    function initFloatingButton() {
        updateFloatingButton();
        
        // Klik pada area kosong untuk toggle
        document.getElementById('floatingContent').addEventListener('click', function(e) {
            // Jika yang diklik adalah link atau icon di dalam link, jangan toggle
            if (e.target.closest('a') && !e.target.closest('a').id === 'floatingLink') {
                return;
            }
            
            // Jika klik langsung pada link, tetap lanjut ke href
            if (e.target.closest('a')) {
                return;
            }
            
            // Toggle ukuran
            toggleFloatingButton();
        });
    }

    function toggleFloatingButton() {
        isExpanded = !isExpanded;
        localStorage.setItem('fabExpanded', isExpanded);
        updateFloatingButton();
    }

    function updateFloatingButton() {
        const floatingContent = document.getElementById('floatingContent');
        const floatingText = document.getElementById('floatingText');
        const floatingLink = document.getElementById('floatingLink');
        
        if (isExpanded) {
            // Mode expanded: dengan teks
            floatingContent.classList.remove('w-14', 'justify-center');
            floatingContent.classList.add('w-auto');
            floatingLink.classList.remove('justify-center', 'px-3');
            floatingLink.classList.add('px-5', 'gap-2');
            floatingText.classList.remove('hidden', 'w-0', 'opacity-0');
            floatingText.classList.add('inline');
            floatingText.style.display = 'inline';
        } else {
            // Mode collapsed: hanya icon
            floatingContent.classList.add('w-14', 'justify-center');
            floatingContent.classList.remove('w-auto');
            floatingLink.classList.add('justify-center', 'px-0');
            floatingLink.classList.remove('px-5', 'gap-2');
            floatingText.classList.add('hidden');
            floatingText.style.display = 'none';
        }
    }

    // Animasi floating
    const style = document.createElement('style');
    style.textContent = `
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
        
        #floatingContent {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            animation: float 3s ease-in-out infinite;
        }
        
        #floatingContent.w-14 {
            width: 3.5rem;
        }
        
        #floatingText {
            transition: opacity 0.2s ease;
        }
        
        #floatingText.hidden {
            opacity: 0;
            width: 0;
            margin: 0;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>