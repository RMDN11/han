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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Flat Minimalist Design dengan Responsive Mobile */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #faf8f5;
            color: #4a4a4a;
        }
        
        .flat-card {
            background-color: #ffffff;
            border: 1px solid #eae2d7;
        }
        
        .flat-input {
            border: 1px solid #e2dcd5;
            background-color: #ffffff;
            padding: 0.5rem 0.5rem;
            font-size: 0.8rem;
            width: 100%;
            border-radius: 0;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .flat-input:focus {
            border-color: #b7aa99;
            outline: none;
            background-color: #fffdfa;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .btn-soft-primary {
            background-color: #d9e2e8;
            border: 1px solid #c0d0d9;
            color: #476b7a;
        }
        
        .btn-soft-primary:hover {
            background-color: #c0d0d9;
        }
        
        .btn-flat {
            background-color: #a5978b;
            border: 1px solid #8e8277;
            color: white;
            padding: 0.625rem 1.5rem;
            font-size: 0.875rem;
        }
        
        .btn-flat:hover {
            background-color: #8e8277;
        }
        
        .table-header {
            background-color: #f5f2ee;
            color: #5f5b57;
            font-weight: 500;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 1px 0 #e2dcd5;
        }
        
        /* RESPONSIVE MOBILE OPTIMIZATION */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -1rem;
            padding: 0 1rem;
            width: calc(100% + 2rem);
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
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .btn-lihat-rekap {
                width: 100%;
                margin-top: 0.5rem;
            }
        }
        
        /* Untuk layar sangat kecil */
        @media (max-width: 480px) {
            .table-container table {
                min-width: 800px;
            }
            
            .flat-input {
                font-size: 0.7rem;
                padding: 0.4rem 0.3rem;
            }
            
            th {
                font-size: 0.65rem;
            }
            
            td {
                font-size: 0.7rem;
            }
        }
        
        .col-highlight {
            background-color: #e8e0d7 !important;
        }
        
        .juz-input {
            background-color: #f5f0ea;
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
            padding: 0.5rem;
            font-size: 0.8rem;
            text-align: center;
            box-sizing: border-box;
        }
        
        .pekan-input:focus {
            outline: none;
            background-color: #fffdfa;
        }
        
        .bg-red-100 { background-color: #fee2e2; }
        .bg-green-100 { background-color: #dcfce7; }
        
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
        
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-2 gap-2">
                <div>
                    <h1 class="text-xl md:text-2xl font-light tracking-wide text-[#5d6e6f]">LAPORAN PEKANAN</h1>
                    <p class="text-xs md:text-sm text-[#9aa6a7]">PERKEMBANGAN HAFALAN SANTRI</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium text-[#7c8c8d]">PROGRAM ASRAMA TAHFIZH INTENSIF</p>
                    <p class="text-xs text-[#a5b1b2]">MAHAD IMAM SYATHBY BOGOR</p>
                </div>
            </div>
            
            <!-- Info Pengampu - Responsive -->
            <div class="flex flex-wrap info-pengampu items-center justify-between gap-3 mt-3 p-3 bg-[#fcfbf9] border border-[#ece7e1] text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[#6a787a]">Nama Pengampu:</span>
                    <span class="font-medium text-[#5d6e6f]">Farhan Ramadhan</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[#6a787a]">Halaqah:</span>
                    <span class="font-medium text-[#5d6e6f]">1</span>
                </div>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="mb-4 px-4 py-3 border text-sm <?= $message_type === 'success' ? 'bg-[#ecf3f0] border-[#d1e0db] text-[#3f6a5c]' : 'bg-[#f8edec] border-[#eddbda] text-[#8f5e5e]' ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check' : 'exclamation' ?> mr-2"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Filter Bulan & Pekan - Responsive -->
        <div class="flat-card p-4 mb-5">
            <form method="GET" action="" class="filter-container flex flex-wrap sm:flex-nowrap items-end gap-4">
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs text-[#7c8c8d] mb-1">BULAN</label>
                    <select name="bulan" class="filter-select flat-input w-full sm:w-32 text-sm" onchange="this.form.submit()">
                        <?php foreach ($bulan_list as $bln): ?>
                        <option value="<?= $bln ?>" <?= $selected_bulan == $bln ? 'selected' : '' ?>>
                            <?= $bln ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs text-[#7c8c8d] mb-1">TAHUN</label>
                    <select name="tahun" class="filter-select flat-input w-full sm:w-20 text-sm" onchange="this.form.submit()">
                        <?php for ($y = 2025; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= $selected_tahun == $y ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item w-full sm:w-auto">
                    <label class="block text-xs text-[#7c8c8d] mb-1">PEKAN</label>
                    <select name="pekan" class="filter-select flat-input w-full sm:w-20 text-sm" onchange="this.form.submit()">
                        <?php for ($p = 1; $p <= 4; $p++): ?>
                        <option value="<?= $p ?>" <?= $selected_pekan == $p ? 'selected' : '' ?>>
                            Pekan <?= $p ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item btn-lihat-rekap w-full sm:w-auto sm:mt-4">
                    <a href="manzil-report.php" class="inline-flex w-full sm:w-auto items-center justify-center px-4 py-2 btn-soft-primary text-sm border">
                        <i class="fas fa-chart-simple mr-2 text-xs"></i> Lihat Rekap Muroja'ah
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
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span class="text-base font-medium text-[#5d6e6f]"><i class="fas fa-calendar-alt mr-2 text-[#9aa6a7]"></i>Bulan: <?= $selected_bulan ?> <?= $selected_tahun ?></span>
            <span class="hidden sm:inline w-px h-5 bg-[#e2dcd5]"></span>
            <span class="text-base font-medium text-[#5d6e6f]"><i class="fas fa-chart-line mr-2 text-[#9aa6a7]"></i>Pekan: <?= $selected_pekan ?></span>
        </div>
        
        <!-- Form Rekap - Responsive Table -->
        <form method="POST" action="" id="rekapForm">
            <input type="hidden" name="bulan" value="<?= $selected_bulan ?>">
            <input type="hidden" name="tahun" value="<?= $selected_tahun ?>">
            
            <div class="table-container border border-[#ece7e1] rounded overflow-hidden">
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
                            <td colspan="10" class="px-4 py-8 text-center text-[#a5b1b2] italic">
                                Belum ada data peserta. Silakan tambah peserta di halaman manzil.php
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($peserta_list as $index => $peserta): 
                                $rekap = $rekap_data[$peserta['id']] ?? [];
                            ?>
                            <tr class="border-b border-[#f0ebe5] hover:bg-[#fcfbf9]">
                                <td class="px-2 py-2 text-[#6a787a]"><?= $index + 1 ?></td>
                                <td class="px-2 py-2">
                                    <div class="font-medium text-[#5d6e6f]"><?= htmlspecialchars($peserta['nama']) ?></div>
                                </td>
                                <td class="px-2 py-2 text-center text-[#7c8c8d]">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][jenjang]" 
                                           value="<?= htmlspecialchars($rekap['jenjang'] ?? '1 SMP') ?>"
                                           class="flat-input text-xs text-center w-16 sm:w-20" 
                                           placeholder="">
                                </td>
                                
                                <!-- JUZ SEKARANG -->
                                <td class="px-2 py-2 text-center">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][juz_sekarang]" 
                                           value="<?= htmlspecialchars($rekap['juz_sekarang'] ?? '') ?>"
                                           class="flat-input text-xs text-center w-12 sm:w-16 font-mono juz-input" 
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
                                           class="flat-input text-xs text-center w-16 sm:w-20" 
                                           placeholder="">
                                </td>
                                
                                <!-- Catatan -->
                                <td class="px-1 py-2 catatan-cell">
                                    <input type="text" name="peserta[<?= $peserta['id'] ?>][catatan]" 
                                           value="<?= htmlspecialchars($rekap['catatan'] ?? '') ?>"
                                           class="flat-input text-xs w-24 sm:w-32" 
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
                        class="w-full sm:w-auto px-6 sm:px-8 py-3 btn-flat text-sm tracking-wide flex items-center justify-center">
                    <i class="fas fa-save mr-2 text-xs"></i> SIMPAN REKAP
                </button>
            </div>
        </form>
        
        <!-- Footer - Reqra by Han -->
        <div class="mt-8 text-center">
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