<?php
session_start();
require_once 'config/connection.php';

// Buat tabel rekap_pekanan
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

// Cek kolom catatan
$result = $conn->query("SHOW COLUMNS FROM rekap_pekanan LIKE 'catatan'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `rekap_pekanan` ADD COLUMN `catatan` text DEFAULT NULL AFTER `total_hafalan`");
}

// Variabel untuk pesan
$message = '';
$message_type = '';

// Ambil daftar peserta dari DB manzil.php
$peserta_list = [];
try {
    $query_peserta = "SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC";
    $result_peserta = $conn->query($query_peserta);
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
} catch (Exception $e) {
    $message = "Tabel peserta_manzil belum ada. Jalankan manzil.php dulu.";
    $message_type = 'error';
}

// Ambil data bulan dan tahun yang dipilih
$selected_bulan = isset($_POST['bulan']) ? $_POST['bulan'] : (isset($_GET['bulan']) ? $_GET['bulan'] : 'Januari');
$selected_tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : (isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y'));
$selected_pekan = isset($_POST['pekan']) ? (int)$_POST['pekan'] : (isset($_GET['pekan']) ? (int)$_GET['pekan'] : 4);

// Array bulan
$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Proses simpan data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_rekap'])) {
    $bulan = $_POST['bulan'];
    $tahun = (int)$_POST['tahun'];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($_POST['peserta'] as $peserta_id => $data) {
        $juz_sekarang = $conn->real_escape_string($data['juz_sekarang'] ?? '');
        $pekan_1 = $conn->real_escape_string($data['pekan_1'] ?? '');
        $pekan_2 = $conn->real_escape_string($data['pekan_2'] ?? '');
        $pekan_3 = $conn->real_escape_string($data['pekan_3'] ?? '');
        $pekan_4 = $conn->real_escape_string($data['pekan_4'] ?? '');
        $total_hafalan = $conn->real_escape_string($data['total_hafalan'] ?? '');
        $catatan = $conn->real_escape_string($data['catatan'] ?? '');
        
        // Cek apakah sudah ada data untuk peserta, bulan, tahun ini
        $stmt_check = $conn->prepare("SELECT id FROM rekap_pekanan WHERE peserta_id = ? AND bulan = ? AND tahun = ?");
        $stmt_check->bind_param("isi", $peserta_id, $bulan, $tahun);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE rekap_pekanan SET 
                juz_sekarang = ?, 
                pekan_1 = ?, pekan_2 = ?, pekan_3 = ?, pekan_4 = ?,
                total_hafalan = ?, catatan = ? 
                WHERE peserta_id = ? AND bulan = ? AND tahun = ?");
            $stmt->bind_param("sssssssisi", 
                $juz_sekarang, 
                $pekan_1, $pekan_2, $pekan_3, $pekan_4,
                $total_hafalan, $catatan, 
                $peserta_id, $bulan, $tahun);
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO rekap_pekanan 
                (peserta_id, bulan, tahun, juz_sekarang, pekan_1, pekan_2, pekan_3, pekan_4, total_hafalan, catatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isisssssss", 
                $peserta_id, $bulan, $tahun, $juz_sekarang, 
                $pekan_1, $pekan_2, $pekan_3, $pekan_4,
                $total_hafalan, $catatan);
        }
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
        $stmt_check->close();
    }
    
    if ($success_count > 0) {
        $message = "Data berhasil disimpan! ($success_count peserta)";
        $message_type = 'success';
    } else {
        $message = "Gagal menyimpan data!";
        $message_type = 'error';
    }
    
    // Redirect untuk refresh
    header("Location: " . $_SERVER['PHP_SELF'] . "?bulan=" . urlencode($bulan) . "&tahun=" . $tahun . "&pekan=" . $selected_pekan);
    exit;
}

// Ambil data rekap yang sudah ada
$rekap_data = [];
if (!empty($peserta_list)) {
    $peserta_ids = array_column($peserta_list, 'id');
    if (!empty($peserta_ids)) {
        $placeholders = implode(',', array_fill(0, count($peserta_ids), '?'));
        $types = str_repeat('i', count($peserta_ids));
        
        $stmt = $conn->prepare("SELECT * FROM rekap_pekanan WHERE peserta_id IN ($placeholders) AND bulan = ? AND tahun = ?");
        
        // Gabungkan parameter
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

// Fungsi untuk menentukan warna berdasarkan teks
function getWarnaPekan($teks) {
    if (empty($teks)) {
        return '';
    }
    
    // Cek apakah mengandung kata "UJIAN" (case insensitive)
    if (stripos($teks, 'UJIAN') !== false) {
        return 'bg-green-100';
    }
    
    // Cek apakah angka di bawah 6 (format: "5 Hal", "3 Hal", dll)
    if (preg_match('/^(\d+)\s*Hal/i', $teks, $matches)) {
        $angka = (int)$matches[1];
        if ($angka < 6) {
            return 'bg-red-100';
        }
    }
    
    return '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Rekap Pekanan · Perkembangan Hafalan</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Apple-inspired Design System */
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
        
        .apple-input, .apple-select {
            background-color: var(--colors-canvas);
            border: 1px solid var(--colors-hairline);
            padding: 0.6rem 0.8rem; /* Adjusted from 0.5rem 0.5rem */
            font-size: 0.875rem; /* typography.caption */
            width: 100%;
            border-radius: 8px; /* rounded.sm */
            box-sizing: border-box;
            color: var(--colors-ink);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .apple-input:focus, .apple-select:focus {
            border-color: var(--colors-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2); /* Focus Blue effect */
        }
        
        .btn-apple-outline {
            background-color: var(--colors-surface-pearl); /* Pearl Button */
            border: 1px solid var(--colors-hairline);
            color: var(--colors-ink-muted-80);
            padding: 0.625rem 1.25rem; /* Adjusted from 0.625rem 1.5rem */
            font-size: 0.875rem; /* typography.caption */
            border-radius: 11px; /* rounded.md */
            transition: all 0.2s ease;
        }
        
        .btn-apple-outline:hover {
            background-color: var(--colors-canvas);
            border-color: var(--colors-primary);
            color: var(--colors-primary);
        }
        
        .btn-apple-primary {
            background-color: var(--colors-primary); /* Action Blue */
            border: none;
            color: var(--colors-canvas);
            padding: 0.75rem 1.75rem; /* button-primary padding */
            font-size: 0.875rem; /* typography.body */
            font-weight: 500; /* Adjusted to 500 for buttons */
            border-radius: 9999px; /* rounded.pill */
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 102, 204, 0.35);
        }
        
        .btn-apple-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.5);
        }
        
        .btn-apple-primary:active {
            transform: scale(0.95);
        }
        
        .table-header {
            background-color: var(--colors-canvas-parchment);
            color: var(--colors-ink-muted-80);
            font-weight: 600; /* typography.caption-strong */
            font-size: 0.75rem; /* typography.caption */
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1; /* Lower z-index for sticky header */
            box-shadow: 0 1px 0 #e2dcd5;
        }
        
        /* RESPONSIVE MOBILE OPTIMIZATION */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -1rem;
            padding: 0 1rem;
            width: calc(100% + 2rem); /* Adjust for full width on small screens */
        }
        
        .table-container table {
            min-width: 1000px; /* Tetap lebar untuk desktop */
        }
        
        @media (max-width: 768px) {
            .table-container table {
                min-width: 900px; /* Lebih kecil untuk mobile */
            }
            
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-item {
                width: 100%; /* Full width for filter items */
            }
            
            .filter-select {
                width: 100%; /* Full width for select */
            }
            
            .btn-lihat-rekap {
                width: 100%;
                margin-top: 0.5rem;
            }
        }
        
        /* Untuk layar sangat kecil */
        @media (max-width: 480px) {
            .table-container table {
                min-width: 700px; /* Further reduced for very small screens */
            }
            
            .apple-input {
                font-size: 0.75rem; /* typography.caption */
                padding: 0.5rem 0.6rem;
            }
            
            th {
                font-size: 0.7rem;
            }
            
            td {
                font-size: 0.7rem;
            }
        }
        
        .col-highlight {
            background-color: rgba(0, 102, 204, 0.1) !important; /* Light Action Blue */
        }
        
        .juz-input {
            background-color: var(--colors-canvas-parchment);
        }
        
        .catatan-cell {
            min-width: 150px;
        }
        
        /* Warna otomatis untuk teks (bukan seluruh kolom) */
        .pekan-cell {
            padding: 0 !important;
        }
        
        .pekan-input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 0.6rem; /* Adjusted padding */
            font-size: 0.8rem;
            text-align: center;
            box-sizing: border-box;
        }
        
        .pekan-input:focus {
            outline: none;
            background-color: var(--colors-canvas);
        }
        
        .bg-red-100 { background-color: var(--badge-danger-bg); color: var(--badge-danger-text); }
        .bg-green-100 { background-color: var(--badge-success-bg); color: var(--badge-success-text); }
        
        /* Card view untuk mobile saat tabel tidak muat */
        @media (max-width: 640px) {
            .table-container {
                overflow-x: auto;
            }
            
            .info-pengampu {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header - Adapted to Apple's style -->
        <div class="mb-8 apple-card p-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-semibold text-gray-800 tracking-tight">LAPORAN PEKANAN</h1>
                        <p class="text-xs text-gray-500 mt-0.5">PERKEMBANGAN HAFALAN SANTRI</p>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-sm font-medium text-gray-700">PROGRAM ASRAMA TAHFIZH INTENSIF</p>
                    <p class="text-xs text-gray-400">MAHAD IMAM SYATHBY BOGOR</p>
                </div>
            </div>
            
            <!-- Info Pengampu - Responsive -->
            <div class="mt-4 p-3 bg-blue-50/50 border border-blue-100 rounded-xl flex flex-wrap items-center gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-user-tie text-blue-500"></i>
                    <span class="text-gray-600">Pengampu:</span>
                    <span class="font-medium text-gray-800">Farhan Ramadhan</span>
                </div>
                <div>
                    <i class="fas fa-layer-group text-blue-500"></i>
                    <span class="text-gray-600">Halaqah:</span>
                    <span class="font-medium text-gray-800">1</span>
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="mb-4 px-4 py-3 border text-sm <?= $message_type === 'success' ? 'bg-[#ecf3f0] border-[#d1e0db] text-[#3f6a5c]' : 'bg-[#f8edec] border-[#eddbda] text-[#8f5e5e]' ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> mr-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Filter Bulan & Pekan - Responsive -->
        <div class="apple-card p-4 mb-6">
            <form method="GET" action="" class="filter-container flex flex-wrap sm:flex-nowrap items-end gap-4">
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">BULAN</label>
                    <select name="bulan" class="apple-select w-full sm:w-32 text-sm" onchange="this.form.submit()">
                        <?php foreach ($bulan_list as $bln): ?>
                        <option value="<?= $bln ?>" <?= $selected_bulan == $bln ? 'selected' : '' ?>>
                            <?= $bln ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">TAHUN</label>
                    <select name="tahun" class="apple-select w-full sm:w-20 text-sm" onchange="this.form.submit()">
                        <?php for ($y = 2025; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= $selected_tahun == $y ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">PEKAN</label>
                    <select name="pekan" class="apple-select w-full sm:w-20 text-sm" onchange="this.form.submit()">
                        <?php for ($p = 1; $p <= 4; $p++): ?>
                        <option value="<?= $p ?>" <?= $selected_pekan == $p ? 'selected' : '' ?>>
                            Pekan <?= $p ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item w-full sm:w-auto sm:mt-4 flex gap-2">
                    <a href="manzil-report.php" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2 btn-apple-outline text-sm">
                        <i class="fas fa-chart-simple mr-2 text-xs"></i> Rekap Muroja'ah
                    </a>
                </div>
				<div class="filter-item btn-lihat-rekap w-full sm:w-auto sm:mt-4">
                    <a href="laporanhan.php" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2 btn-soft-primary text-sm border">
                        <i class="fas fa-chart-simple mr-2 text-xs"></i> Kirim Laporan
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Info Bulan dan Pekan - Responsive -->
        <div class="mb-5 flex flex-wrap items-center gap-4 px-2">
            <span class="text-base font-semibold text-gray-700 flex items-center gap-2"><i class="fas fa-calendar-alt text-blue-500"></i> <?= $selected_bulan ?> <?= $selected_tahun ?></span>
            <span class="hidden sm:block w-px h-5 bg-gray-300"></span>
            <span class="text-base font-semibold text-gray-700 flex items-center gap-2"><i class="fas fa-bullseye text-indigo-500"></i> Fokus Pekan <?= $selected_pekan ?></span>
        </div>
        
        <!-- Form Rekap - Responsive Table -->
        <form method="POST" action="" id="rekapForm">
            <input type="hidden" name="bulan" value="<?= $selected_bulan ?>">
            <input type="hidden" name="tahun" value="<?= $selected_tahun ?>">
            
            <div class="table-container apple-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="table-header sticky-header">
                        <tr>
                            <th class="px-2 py-3 text-left w-12">No</th>
                            <th class="px-2 py-3 text-left">Nama Santri</th>
                            <th class="px-2 py-3 text-center">Jenjang</th>
                            <th class="px-2 py-3 text-center">Juz Skrg</th>
                            <th class="px-2 py-3 text-center">P1</th>
                            <th class="px-2 py-3 text-center">P2</th>
                            <th class="px-2 py-3 text-center">P3</th>
                            <th class="px-2 py-3 text-center">P4</th>
                            <th class="px-2 py-3 text-center">Total</th>
                            <th class="px-2 py-3 text-left">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($peserta_list)): ?>
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-400 italic">
                                Belum ada data peserta. Silakan tambah peserta di halaman manzil.php
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($peserta_list as $index => $peserta): 
                                $rekap = $rekap_data[$peserta['id']] ?? [];
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                                <td class="px-2 py-3 text-gray-500"><?= $index + 1 ?></td>
                                <td class="px-2 py-3">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($peserta['nama']) ?></div>
                                </td>
                                <td class="px-2 py-3 text-center text-gray-600">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][jenjang]" 
                                           value="<?= htmlspecialchars($rekap['jenjang'] ?? '1 SMP') ?>"
                                           class="apple-input text-xs text-center w-16 sm:w-20" 
                                           placeholder="">
                                </td>
                                
                                <!-- JUZ SEKARANG -->
                                <td class="px-2 py-2 text-center">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][juz_sekarang]" 
                                           value="<?= htmlspecialchars($rekap['juz_sekarang'] ?? '') ?>" 
                                           class="apple-input text-xs text-center w-12 sm:w-16 font-mono juz-input"
                                           placeholder="">
                                </td>
                                
                                <!-- Pekan 1 dengan warna otomatis -->
                                <td class="px-1 py-2 pekan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_1]" 
                                           value="<?= htmlspecialchars($rekap['pekan_1'] ?? '') ?>"
                                           class="pekan-input <?= getWarnaPekan($rekap['pekan_1'] ?? '') ?>" 
                                           placeholder="">
                                </td>
                                
                                <!-- Pekan 2 dengan warna otomatis -->
                                <td class="px-1 py-2 pekan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_2]" 
                                           value="<?= htmlspecialchars($rekap['pekan_2'] ?? '') ?>"
                                           class="pekan-input <?= getWarnaPekan($rekap['pekan_2'] ?? '') ?>" 
                                           placeholder="">
                                </td>
                                
                                <!-- Pekan 3 dengan warna otomatis -->
                                <td class="px-1 py-2 pekan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_3]" 
                                           value="<?= htmlspecialchars($rekap['pekan_3'] ?? '') ?>"
                                           class="pekan-input <?= getWarnaPekan($rekap['pekan_3'] ?? '') ?>" 
                                           placeholder="">
                                </td>
                                
                                <!-- Pekan 4 dengan warna otomatis -->
                                <td class="px-1 py-2 pekan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_4]" 
                                           value="<?= htmlspecialchars($rekap['pekan_4'] ?? '') ?>"
                                           class="pekan-input <?= getWarnaPekan($rekap['pekan_4'] ?? '') ?>" 
                                           placeholder="">
                                </td>
                                
                                <!-- Total Hafalan -->
                                <td class="px-1 py-2">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][total_hafalan]" 
                                           value="<?= htmlspecialchars($rekap['total_hafalan'] ?? '') ?>" 
                                           class="apple-input text-xs text-center w-16 sm:w-20"
                                           placeholder="">
                                </td>
                                
                                <!-- Catatan -->
                                <td class="px-1 py-2 catatan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][catatan]" 
                                           value="<?= htmlspecialchars($rekap['catatan'] ?? '') ?>" 
                                           class="apple-input text-xs w-24 sm:w-32"
                                           placeholder="Catatan">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tombol Simpan - Responsive -->
            <div class="flex justify-center mt-6">
                <button type="submit" name="simpan_rekap" 
                        class="w-full sm:w-auto px-6 sm:px-8 py-3 btn-apple-primary text-sm tracking-wide flex items-center justify-center">
                    <i class="fas fa-save mr-2 text-xs"></i> SIMPAN REKAP
                </button>
            </div>
        </form>
        
        <!-- Footer - Reqra by Han -->
        <div class="mt-10 text-center">
            <p class="text-xs text-[#a5b1b2] tracking-wide">
                <i class="fas fa-circle mr-1 text-[6px] align-middle"></i>
                Reqra by Han · <?= date('Y') ?>
            </p>
        </div>
        
    </div>
    
    <script>
        // Auto-highlight pekan yang dipilih
        document.addEventListener('DOMContentLoaded', function() {
            const pekan = <?= $selected_pekan ?>;
            const headers = document.querySelectorAll('thead tr th');
            
            // Hapus semua highlight sebelumnya
            headers.forEach(h => h.classList.remove('col-highlight'));
            
            // Highlight kolom pekan yang dipilih (kolom 4-7 adalah pekan 1-4)
            if (pekan >= 1 && pekan <= 4) {
                const kolomIndex = pekan + 3; // Pekan 1 = kolom 4, Pekan 2 = kolom 5, dst
                if (headers[kolomIndex]) {
                    headers[kolomIndex].classList.add('col-highlight');
                }
            }
        });
        
        // Update warna real-time
        document.addEventListener('input', function(e) {
            if (e.target.name && e.target.name.includes('pekan_')) {
                updateWarna(e.target);
            }
        });
        
        // Auto-add "Hal" hanya saat selesai mengetik (blur)
        document.addEventListener('blur', function(e) {
            if (e.target.name && e.target.name.includes('pekan_')) {
                const input = e.target;
                const value = input.value.trim();
                
                // Jika hanya angka (1-999), tambahkan " Hal"
                if (/^\d+$/.test(value)) {
                    const angka = parseInt(value);
                    if (angka >= 1 && angka <= 999) {
                        input.value = value + ' Hal';
                        updateWarna(input);
                    }
                }
            }
        }, true);
        
        // Function untuk update warna real-time
        function updateWarna(input) {
            const value = input.value;
            
            // Hapus semua class warna
            input.classList.remove('bg-red-100', 'bg-green-100');
            
            // Cek UJIAN
            if (value.toUpperCase().includes('UJIAN')) {
                input.classList.add('bg-green-100');
            }
            // Cek angka di bawah 6 dengan format "X Hal"
            else {
                const match = value.match(/^(\d+)\s*Hal/i);
                if (match) {
                    const angka = parseInt(match[1]);
                    if (angka < 6) {
                        input.classList.add('bg-red-100');
                    }
                }
            }
        }
        
        // Touch-friendly untuk mobile
        document.addEventListener('touchstart', function() {
            // Optimasi untuk touch events
        }, {passive: true});
    </script>
</body>
</html>