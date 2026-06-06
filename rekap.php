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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
    --spacing-xs: 8px;
    --spacing-sm: 12px;
    --spacing-md: 16px; /* Adjusted from 17px for consistency with 8px grid */
    --spacing-lg: 24px;
}
/* Modern Glassmorphism & Clean Design */
:root {
    --glass-bg: rgba(255, 255, 255, 0.88);
    --glass-border: rgba(255, 255, 255, 0.6);
    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    --primary: #3b82f6;
    --primary-hover: #2563eb;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
}
body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--colors-canvas-parchment); /* Parchment */
    background-attachment: fixed;
    color: var(--colors-ink); /* Near-Black Ink */
    -webkit-font-smoothing: antialiased;
    line-height: 1.47; /* typography.body line-height */
}
.glass-card {
    background: var(--colors-canvas); /* Pure White */
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--colors-hairline); /* Hairline */
    /* box-shadow: 0 1px 0 var(--colors-divider-soft); */ /* Soft hairline */
    border-radius: 1.25rem;
    box-shadow: var(--glass-shadow);
    transition: all 0.3s ease;
}
.glass-card:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border-color: rgba(255, 255, 255, 0.9);
}
.apple-input, .apple-select {
    background: var(--colors-canvas);
    border: 1px solid var(--colors-hairline);
    border-radius: 8px; /* rounded.sm */
    padding: 0.6rem 0.8rem; /* typography.caption */
    font-size: 0.875rem; /* typography.caption */
    width: 100%;
    transition: all 0.2s ease;
    color: var(--colors-ink);
}
.apple-input:focus, .apple-select:focus {
    border-color: var(--colors-primary); /* Action Blue */
    outline: none;
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
    justify-content: center;
    gap: 0.5rem;
}
.btn-apple-primary:hover {
    transform: translateY(-1px);
}
.btn-apple-primary:active {
    transform: scale(0.95);
}
.modern-btn-secondary {
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.6rem 1.2rem;
    font-size: 0.875rem;
    border-radius: 0.875rem;
    transition: all 0.2s ease;
}
.modern-btn-secondary:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
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
    font-weight: 600; /* typography.caption-strong */
    font-size: 0.75rem; /* typography.caption */
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: sticky;
    top: 0;
    z-index: 1; /* Lower z-index for sticky header */
    box-shadow: 0 1px 0 var(--colors-divider-soft);
}
.table-row:hover {
    background-color: rgba(59, 130, 246, 0.04);
}
.bg-red-100 { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; }
.bg-green-100 { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; }
.pekan-input {
    width: 100%;
    border: none;
    background: transparent;
    padding: 0.5rem;
    font-size: 0.8rem;
    text-align: center;
    border-radius: 0.5rem;
    transition: background 0.2s;
    color: var(--colors-ink);
}
.pekan-input:focus {
    outline: none;
    background: var(--colors-canvas);
}
.col-highlight {
    background-color: rgba(0, 102, 204, 0.1) !important; /* Light Action Blue */
}
.juz-input {
    background: rgba(248, 250, 252, 0.8);
    font-family: monospace;
    font-weight: 600;
}
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--colors-hairline); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
@media (max-width: 768px) {
    .glass-card { border-radius: 1rem; }
    .modern-btn, .modern-btn-secondary { width: 100%; }
}
</style>
</head>
<body class="min-h-screen p-4 md:p-8">
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <?php
        $header_icon = 'fa-clipboard-list';
        $header_title = 'LAPORAN PEKANAN';
        $header_subtitle = 'REKAPITULASI HAFALAN SANTRI';
        include 'header_content.php';
        ?>

    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="mb-5 px-5 py-4 rounded-xl border text-sm animate-fade-in <?= $message_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> mr-2"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Filter & Actions -->
    <div class="glass-card p-4 mb-8">
        <form method="GET" action="" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">BULAN</label>
                <select name="bulan" class="apple-select text-sm" onchange="this.form.submit()">
                    <?php foreach ($bulan_list as $bln): ?>
                    <option value="<?= $bln ?>" <?= $selected_bulan == $bln ? 'selected' : '' ?>><?= $bln ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[100px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">TAHUN</label>
                <select name="tahun" class="apple-select text-sm" onchange="this.form.submit()">
                    <?php for ($y = 2025; $y <= 2027; $y++): ?>
                    <option value="<?= $y ?>" <?= $selected_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[100px]">
                <label class="block text-xs font-semibold text-gray-500 mb-1.5 ml-1">PEKAN</label>
                <select name="pekan" class="apple-select text-sm" onchange="this.form.submit()">
                    <?php for ($p = 1; $p <= 4; $p++): ?>
                    <option value="<?= $p ?>" <?= $selected_pekan == $p ? 'selected' : '' ?>>Pekan <?= $p ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex gap-2 mt-5 md:mt-0 md:ml-auto">
                <a href="kualitas.php" class="modern-btn-secondary whitespace-nowrap">
                    <i class="fas fa-chart-simple mr-1 text-xs"></i> Rekap Muroja'ah
                </a>
                <a href="laporanhan.php" class="modern-btn-secondary whitespace-nowrap">
                    <i class="fas fa-paper-plane mr-1 text-xs"></i> Kirim Laporan
                </a>
            </div>
        </form>
    </div>

    <!-- Info Bulan & Pekan -->
    <div class="flex flex-wrap items-center gap-4 mb-5 px-2">
        <span class="text-base font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-calendar-alt text-blue-500"></i> <?= $selected_bulan ?> <?= $selected_tahun ?>
        </span>
        <span class="hidden sm:block w-px h-5 bg-gray-300"></span>
        <span class="text-base font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-bullseye text-indigo-500"></i> Fokus Pekan <?= $selected_pekan ?>
        </span>
    </div>

    <!-- Form Rekap -->
    <form method="POST" action="" id="rekapForm">
        <input type="hidden" name="bulan" value="<?= $selected_bulan ?>">
        <input type="hidden" name="tahun" value="<?= $selected_tahun ?>">
        
        <div class="table-container mb-6">
            <table class="w-full text-sm">
                <thead class="table-header">
                    <tr>
                        <th class="px-3 py-3 text-left w-10">No</th>
                        <th class="px-3 py-3 text-left">Nama Santri</th>
                        <th class="px-3 py-3 text-center">Jenjang</th>
                        <th class="px-3 py-3 text-center">Juz Skrg</th>
                        <th class="px-3 py-3 text-center">P1</th>
                        <th class="px-3 py-3 text-center">P2</th>
                        <th class="px-3 py-3 text-center">P3</th>
                        <th class="px-3 py-3 text-center">P4</th>
                        <th class="px-3 py-3 text-center">Total</th>
                        <th class="px-3 py-3 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($peserta_list)): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-gray-400 italic">
                            <i class="fas fa-user-plus text-3xl mb-2 block text-gray-300"></i>
                            Belum ada data peserta. Silakan tambah peserta di halaman manzil.php
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($peserta_list as $index => $peserta):
                        $rekap = $rekap_data[$peserta['id']] ?? [];
                    ?>
                    <tr class="table-row transition-colors">
                        <td class="px-3 py-3 text-gray-500"><?= $index + 1 ?></td>
                        <td class="px-3 py-3">
                            <div class="font-medium text-gray-800"><?= htmlspecialchars($peserta['nama']) ?></div>
                        </td>
                        <td class="px-2 py-3 text-center">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][jenjang]"
                                value="<?= htmlspecialchars($rekap['jenjang'] ?? '1 SMP') ?>"
                                class="modern-input text-xs text-center w-full" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][juz_sekarang]"
                                value="<?= htmlspecialchars($rekap['juz_sekarang'] ?? '') ?>"
                                class="apple-input text-xs text-center w-full juz-input" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_1]"
                                value="<?= htmlspecialchars($rekap['pekan_1'] ?? '') ?>"
                                class="pekan-input <?= getWarnaPekan($rekap['pekan_1'] ?? '') ?>" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_2]"
                                value="<?= htmlspecialchars($rekap['pekan_2'] ?? '') ?>"
                                class="pekan-input <?= getWarnaPekan($rekap['pekan_2'] ?? '') ?>" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_3]"
                                value="<?= htmlspecialchars($rekap['pekan_3'] ?? '') ?>"
                                class="pekan-input <?= getWarnaPekan($rekap['pekan_3'] ?? '') ?>" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][pekan_4]"
                                value="<?= htmlspecialchars($rekap['pekan_4'] ?? '') ?>"
                                class="pekan-input <?= getWarnaPekan($rekap['pekan_4'] ?? '') ?>" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][total_hafalan]"
                                value="<?= htmlspecialchars($rekap['total_hafalan'] ?? '') ?>"
                                class="apple-input text-xs text-center w-full" placeholder="">
                        </td>
                        <td class="px-2 py-3">
                            <input type="text" name="peserta[<?= $peserta['id'] ?>][catatan]"
                                value="<?= htmlspecialchars($rekap['catatan'] ?? '') ?>"
                                class="apple-input text-xs w-full" placeholder="Catatan">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tombol Simpan -->
        <div class="flex justify-center">
            <button type="submit" name="simpan_rekap"
                class="w-full sm:w-auto px-8 py-3 btn-apple-primary text-sm tracking-wide shadow-lg">
                <i class="fas fa-save text-xs"></i> SIMPAN REKAP
            </button>
        </div>
    </form>

    <!-- Footer -->
    <?php include 'footer_content.php'; ?>
</div>

<script>
// Auto-highlight pekan yang dipilih
document.addEventListener('DOMContentLoaded', function() {
    const pekan = <?= $selected_pekan ?>;
    const headers = document.querySelectorAll('thead tr th');
    headers.forEach(h => h.classList.remove('col-highlight'));
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
    input.classList.remove('bg-red-100', 'bg-green-100');
    // Cek UJIAN
    if (value.toUpperCase().includes('UJIAN')) {
        input.classList.add('bg-green-100');
    } else {
        // Cek angka di bawah 6 dengan format "X Hal"
        const match = value.match(/^(\d+)\s*Hal/i);
        if (match) {
            const angka = parseInt(match[1]);
            if (angka < 6) {
                input.classList.add('bg-red-100');
            }
        }
    }
}
</script>
</body>
</html>