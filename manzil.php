<?php
session_start();
require_once 'config/connection.php';

// Variabel untuk pesan
$message = '';
$message_type = '';

// Ambil minggu dan tahun saat ini
$minggu_ini = date('W');
$tahun_ini = date('Y');

// CEK APAKAH INI REQUEST AJAX UNTUK LOAD DATA
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// ==========================================
// 1. PROSES HAPUS SANTRI (Non-Aktifkan)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_peserta']) && !$is_ajax) {
    $peserta_id = (int)$_POST['peserta_id_hapus'];
    if ($peserta_id > 0) {
        $stmt = $conn->prepare("UPDATE peserta_manzil SET aktif = 0 WHERE id = ?");
        $stmt->bind_param("i", $peserta_id);
        if ($stmt->execute()) {
            $message = "Santri berhasil dihapus!";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus santri!";
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ==========================================
// 2. PROSES HAPUS DATA MUROJAAH (PER TANGGAL)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_per_tanggal']) && !$is_ajax) {
    $peserta_id = (int)$_POST['peserta_id'];
    $tanggal = $_POST['delete_date'];
    
    if ($peserta_id > 0 && !empty($tanggal)) {
        $stmt = $conn->prepare("DELETE FROM manzil_data WHERE peserta_id = ? AND tanggal = ?");
        $stmt->bind_param("is", $peserta_id, $tanggal);
        
        if ($stmt->execute()) {
            $message = "Data riwayat tanggal tersebut berhasil dihapus!";
            $message_type = 'success';
            $minggu_ke = date('W', strtotime($tanggal));
            $tahun_for_update = date('Y', strtotime($tanggal));
            updateRangkumanMingguan($conn, $peserta_id, $minggu_ke, $tahun_for_update);
        } else {
            $message = "Gagal menghapus data!";
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF'] . "?peserta_id=" . $peserta_id);
        exit;
    }
}

// ==========================================
// 3. PROSES EDIT DATA MUROJAAH
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_data']) && !$is_ajax) {
    $edit_id = (int)$_POST['edit_id'];
    $juz = (int)$_POST['edit_juz'];
    $status = $_POST['edit_status'] ?? 'lancar';
    $catatan = trim($_POST['edit_catatan'] ?? '');
    
    // Konversi status ke angka di balik layar
    if ($status == 'tidak') { $ketuk = 5; $tuntun = 4; } 
    elseif ($status == 'cukup') { $ketuk = 4; $tuntun = 3; } 
    else { $ketuk = 0; $tuntun = 0; }
    
    if ($edit_id > 0 && $juz >= 1 && $juz <= 30) {
        $stmt = $conn->prepare("UPDATE manzil_data SET juz = ?, ketuk = ?, tuntun = ?, catatan = ? WHERE id = ?");
        $stmt->bind_param("iiisi", $juz, $ketuk, $tuntun, $catatan, $edit_id);
        if ($stmt->execute()) {
            $message = "Data berhasil diupdate!";
            $message_type = 'success';
            
            $stmt_get = $conn->prepare("SELECT peserta_id, tanggal FROM manzil_data WHERE id = ?");
            $stmt_get->bind_param("i", $edit_id);
            $stmt_get->execute();
            if ($record_info = $stmt_get->get_result()->fetch_assoc()) {
                $peserta_id_for_update = $record_info['peserta_id'];
                $tanggal = $record_info['tanggal'];
                updateRangkumanMingguan($conn, $peserta_id_for_update, date('W', strtotime($tanggal)), date('Y', strtotime($tanggal)));
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?peserta_id=" . ($peserta_id_for_update ?? ''));
        exit;
    }
}

// ==========================================
// 4. PROSES TAMBAH SANTRI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_peserta']) && !$is_ajax) {
    $nama_baru = trim($_POST['nama_baru'] ?? '');
    if (!empty($nama_baru)) {
        $stmt = $conn->prepare("INSERT INTO peserta_manzil (nama, aktif) VALUES (?, 1)");
        $stmt->bind_param("s", $nama_baru);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ==========================================
// 5. PROSES SIMPAN MUROJAAH (BULK HARI)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_murojaah']) && !$is_ajax) {
    $peserta_id = (int)$_POST['peserta_id'];
    if ($peserta_id > 0 && isset($_POST['hari'])) {
        $hari_map = ['senin'=>'monday','selasa'=>'tuesday','rabu'=>'wednesday','kamis'=>'thursday','jumat'=>'friday','sabtu'=>'saturday','minggu'=>'sunday'];
        $minggu_update = $tahun_update = null;

        foreach ($_POST['hari'] as $hari_key => $data_hari) {
            $jumlah = (int)($data_hari['jumlah'] ?? 0);
            if ($jumlah > 0) {
                $tanggal = date('Y-m-d', strtotime($hari_map[$hari_key] . ' this week'));
                $minggu_update = date('W', strtotime($tanggal));
                $tahun_update = date('Y', strtotime($tanggal));
                
                // Hapus data lama hari ini untuk reset (opsional agar tidak dobel)
                $stmt_del = $conn->prepare("DELETE FROM manzil_data WHERE peserta_id = ? AND tanggal = ?");
                $stmt_del->bind_param("is", $peserta_id, $tanggal);
                $stmt_del->execute();
                
                $stmt = $conn->prepare("INSERT INTO manzil_data (peserta_id, tanggal, juz, juz_ke, ketuk, tuntun, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                for ($i = 1; $i <= $jumlah; $i++) {
                    $juz = (int)($data_hari["juz_$i"] ?? 0);
                    $status = $data_hari["status_$i"] ?? 'lancar';
                    $catatan = trim($data_hari["catatan_$i"] ?? '');
                    
                    if ($juz > 0) {
                        // Terjemahkan status ke angka di balik layar
                        if ($status == 'tidak') { $ketuk = 5; $tuntun = 4; } 
                        elseif ($status == 'cukup') { $ketuk = 4; $tuntun = 3; } 
                        else { $ketuk = 0; $tuntun = 0; }
                        
                        $stmt->bind_param("isiiiis", $peserta_id, $tanggal, $juz, $i, $ketuk, $tuntun, $catatan);
                        $stmt->execute();
                    }
                }
            }
        }
        if ($minggu_update) {
            updateRangkumanMingguan($conn, $peserta_id, $minggu_update, $tahun_update);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?peserta_id=" . $peserta_id);
        exit;
    }
}

// ==========================================
// FUNGSI UPDATE RANGKUMAN MINGGUAN
// ==========================================
function updateRangkumanMingguan($conn, $peserta_id, $minggu_ke, $tahun) {
    $tanggal_awal = date('Y-m-d', strtotime($tahun . 'W' . str_pad($minggu_ke, 2, '0', STR_PAD_LEFT) . '-1'));
    $tanggal_akhir = date('Y-m-d', strtotime($tanggal_awal . ' +6 days'));
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT juz) as total_juz, COUNT(*) as total_murojaah, SUM(ketuk) as total_ketuk, SUM(tuntun) as total_tuntun FROM manzil_data WHERE peserta_id = ? AND tanggal >= ? AND tanggal <= ?");
    $stmt->bind_param("iss", $peserta_id, $tanggal_awal, $tanggal_akhir);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_juz = $row['total_juz'];
        $total_murojaah = $row['total_murojaah'];
        $total_ketuk = $row['total_ketuk'] ?? 0;
        $total_tuntun = $row['total_tuntun'] ?? 0;
        
        $avg_ketuk = $total_murojaah > 0 ? $total_ketuk / $total_murojaah : 0;
        $avg_tuntun = $total_murojaah > 0 ? $total_tuntun / $total_murojaah : 0;
        
        // LOGIKA KUALITAS
        $ketuk_score = 0;
        if ($avg_ketuk > 4) $ketuk_score = 2; elseif ($avg_ketuk > 3) $ketuk_score = 1;
        
        $tuntun_score = 0;
        if ($avg_tuntun > 3) $tuntun_score = 2; elseif ($avg_tuntun > 2) $tuntun_score = 1;
        
        $final_score = max($ketuk_score, $tuntun_score);
        $kualitas = ($final_score === 2) ? 'Tidak Lancar' : (($final_score === 1) ? 'Cukup' : 'Lancar');
        
        $stmt_check = $conn->prepare("SELECT id FROM manzil_rangkuman WHERE peserta_id = ? AND minggu_ke = ? AND tahun = ?");
        $stmt_check->bind_param("iii", $peserta_id, $minggu_ke, $tahun);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $row_check = $result_check->fetch_assoc();
            $stmt_update = $conn->prepare("UPDATE manzil_rangkuman SET total_juz=?, total_murojaah=?, rata_ketuk=?, rata_tuntun=?, kualitas=? WHERE id=?");
            $stmt_update->bind_param("iiddsi", $total_juz, $total_murojaah, $total_ketuk, $total_tuntun, $kualitas, $row_check['id']);
            $stmt_update->execute();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO manzil_rangkuman (peserta_id, minggu_ke, tahun, total_juz, total_murojaah, rata_ketuk, rata_tuntun, kualitas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("iiiiidds", $peserta_id, $minggu_ke, $tahun, $total_juz, $total_murojaah, $total_ketuk, $total_tuntun, $kualitas);
            $stmt_insert->execute();
        }
    }
}

// ==========================================
// AMBIL DAFTAR SANTRI
// ==========================================
$peserta_list = [];
$result_peserta = $conn->query("SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC");
if($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) $peserta_list[] = $row;
}

// ==========================================
// AJAX GET HISTORY & GENERATE HTML
// ==========================================
function getHistoryData($conn, $peserta_id) {
    $tanggal_awal_minggu = date('Y-m-d', strtotime('monday this week'));
    $tanggal_akhir_minggu = date('Y-m-d', strtotime('sunday this week'));
    
    $stmt = $conn->prepare("SELECT id, tanggal, DAYNAME(tanggal) as hari, juz, ketuk, tuntun, catatan FROM manzil_data WHERE peserta_id = ? AND tanggal >= ? AND tanggal <= ? ORDER BY tanggal ASC, juz_ke ASC");
    $stmt->bind_param("iss", $peserta_id, $tanggal_awal_minggu, $tanggal_akhir_minggu);
    $stmt->execute();
    $result = $stmt->get_result();
    $raw_data = [];
    while ($row = $result->fetch_assoc()) $raw_data[] = $row;
    
    $grouped_data = [];
    foreach ($raw_data as $item) {
        $tanggal = $item['tanggal'];
        if (!isset($grouped_data[$tanggal])) {
            $grouped_data[$tanggal] = ['tanggal' => $tanggal, 'hari' => $item['hari'], 'total_juz' => 0, 'juz_list' => [], 'total_ketuk' => 0, 'total_tuntun' => 0, 'catatan_list' => [], 'ids' => []];
        }
        $grouped_data[$tanggal]['total_juz']++;
        $grouped_data[$tanggal]['juz_list'][] = $item['juz'];
        $grouped_data[$tanggal]['total_ketuk'] += $item['ketuk'];
        $grouped_data[$tanggal]['total_tuntun'] += $item['tuntun'];
        if (!empty($item['catatan'])) $grouped_data[$tanggal]['catatan_list'][] = "J{$item['juz']}: {$item['catatan']}";
        $grouped_data[$tanggal]['ids'][] = $item['id'];
    }
    
    $history_grouped = [];
    foreach ($grouped_data as $group) {
        $juz_list_unique = array_unique($group['juz_list']);
        sort($juz_list_unique);
        
        $avg_ketuk = $group['total_juz'] > 0 ? $group['total_ketuk'] / $group['total_juz'] : 0;
        $avg_tuntun = $group['total_juz'] > 0 ? $group['total_tuntun'] / $group['total_juz'] : 0;
        
        $final_score = max(($avg_ketuk > 4 ? 2 : ($avg_ketuk > 3 ? 1 : 0)), ($avg_tuntun > 3 ? 2 : ($avg_tuntun > 2 ? 1 : 0)));
        
        if ($final_score === 2) { $kualitas = 'Tidak Lancar'; $badge_class = 'badge-tidak'; $icon_class = 'xmark'; } 
        elseif ($final_score === 1) { $kualitas = 'Cukup'; $badge_class = 'badge-cukup'; $icon_class = 'minus'; } 
        else { $kualitas = 'Lancar'; $badge_class = 'badge-lancar'; $icon_class = 'check'; }
        
        $history_grouped[] = [
            'tanggal' => $group['tanggal'], 'hari' => $group['hari'], 'total_juz' => $group['total_juz'],
            'juz_list' => implode(', ', $juz_list_unique), 'catatan' => implode('; ', $group['catatan_list']),
            'kualitas' => $kualitas, 'badge_class' => $badge_class, 'icon_class' => $icon_class, 'sample_id' => $group['ids'][0]
        ];
    }
    return $history_grouped;
}

if ($is_ajax && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $peserta_id = (int)$_GET['peserta_id'];
    $response = ['success' => false, 'data' => [], 'html' => ''];
    
    if ($peserta_id > 0) {
        $history_grouped = getHistoryData($conn, $peserta_id);
        
        $stmt_nama = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
        $stmt_nama->bind_param("i", $peserta_id);
        $stmt_nama->execute();
        $response['nama_peserta'] = $stmt_nama->get_result()->fetch_assoc()['nama'] ?? 'Santri';
        
        $response['success'] = true;
        $response['data'] = $history_grouped;
        
        $html = '<div class="glass-card mt-7 animate-fade-in"><div class="px-5 py-4 history-section flex items-center justify-between"><div class="flex items-center gap-2"><i class="fas fa-clock-rotate-left text-indigo-400 text-xs"></i><span class="text-sm font-medium text-gray-600 tracking-wide">RIWAYAT · ' . htmlspecialchars($response['nama_peserta']) . '</span></div><button onclick="openHistoryModal()" class="text-xs text-indigo-500 hover:text-indigo-700 border border-indigo-200 px-4 py-2 bg-white/50 backdrop-blur-sm rounded-xl hover:bg-indigo-50 transition-all duration-200 shadow-sm"><i class="fas fa-eye mr-1"></i> Preview Riwayat</button></div></div>';
        
        $response['html'] = empty($history_grouped) ? '<div class="glass-card p-6 text-center text-gray-400 text-sm italic">Belum ada data murojaah minggu ini</div>' : $html;
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : 0;
$selected_peserta_nama = '';
if ($selected_peserta_id > 0) {
    $stmt_nama = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
    $stmt_nama->bind_param("i", $selected_peserta_id);
    $stmt_nama->execute();
    $selected_peserta_nama = $stmt_nama->get_result()->fetch_assoc()['nama'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Muroja'ah · Reqra</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --colors-primary: #0066cc;
    --colors-canvas: #ffffff;
    --colors-canvas-parchment: #f5f5f7;
    --colors-surface-pearl: #fafafc;
    --colors-ink: #1d1d1f;
    --colors-hairline: #e0e0e0;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--colors-canvas-parchment);
    color: var(--colors-ink);
    min-height: 100vh;
}

/* Glassmorphism Cards */
.glass-card {
    background: var(--colors-canvas);
    backdrop-filter: blur(12px);
    border: 1px solid var(--colors-hairline);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.04), 0 2px 8px rgba(148,163,184,0.08);
    transition: all 0.3s ease;
}

/* Modern Inputs */
.modern-input, .modern-select {
    background: var(--colors-canvas);
    border: 1px solid var(--colors-hairline);
    border-radius: 10px;
    padding: 0.6rem 0.8rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}
.modern-input:focus, .modern-select:focus {
    outline: none; border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Buttons */
.btn-glass {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: white; border-radius: 12px; transition: all 0.25s ease;
}
.btn-glass:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(79,70,229,0.3); }

/* Badges - Apple Aesthetic (Soft Colors) */
.badge-lancar { background-color: #ecfdf5; color: #065f46; border-color: #d1fae5; }
.badge-cukup { background-color: #fffbeb; color: #92400e; border-color: #fef3c7; }
.badge-tidak { background-color: #fef2f2; color: #991b1b; border-color: #fee2e2; }

/* Modal & Tables */
.modern-table-header { background: #f8fafc; color: #64748b; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
.modal-backdrop { background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(5px); }
.modal-content { background: #fff; border-radius: 18px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.animate-fade-in { animation: fadeIn 0.3s ease forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Responsive adjustments */
@media (max-width: 768px) {
    .responsive-table { display: block; overflow-x: auto; white-space: nowrap; }
}
</style>
</head>
<body class="p-4 md:p-6">
<div class="max-w-7xl mx-auto">

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div class="flex items-center gap-3">
        <div class="text-4xl text-indigo-500">﷽</div>
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">MUROJA'AH</h1>
            <p class="text-xs text-gray-500">MONITORING KUALITAS HAFALAN</p>
        </div>
    </div>
    <div class="glass-card px-4 py-3 inline-flex items-center gap-3">
        <div class="text-right">
            <p class="text-sm font-medium text-gray-600">Minggu <?= $minggu_ini ?> · <?= $tahun_ini ?></p>
            <p class="text-xs text-gray-400"><?= date('d M Y') ?></p>
        </div>
        <i class="fas fa-calendar-week text-indigo-400 text-lg"></i>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="mb-6 px-5 py-4 glass-card border-l-4 <?= $message_type === 'success' ? 'border-green-500 bg-green-50 text-green-700' : 'border-red-500 bg-red-50 text-red-700' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="glass-card mb-7 p-6">
    <div class="flex items-center gap-2 mb-5">
        <i class="fas fa-users text-blue-500"></i><span class="text-sm font-medium">KELOLA SANTRI</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <form method="POST" class="flex gap-2">
            <input type="text" name="nama_baru" class="flex-1 modern-input" placeholder="Nama santri baru" required>
            <button type="submit" name="tambah_peserta" class="px-4 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition">Tambah</button>
        </form>
        <form method="POST" class="flex gap-2">
            <select name="peserta_id_hapus" class="flex-1 modern-select" required>
                <option value="">-- Pilih santri dihapus --</option>
                <?php foreach ($peserta_list as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="hapus_peserta" class="px-4 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600 transition" onclick="return confirm('Yakin hapus?')">Hapus</button>
        </form>
    </div>
</div>

<div class="glass-card p-6">
    <div class="flex items-center gap-2 mb-6">
        <i class="fas fa-pen-to-square text-blue-500"></i><span class="text-sm font-medium">INPUT MUROJA'AH · MINGGU INI</span>
    </div>
    <form method="POST" id="form-murojaah">
        <div class="mb-6 max-w-md">
            <label class="block text-xs text-gray-500 mb-2 font-medium">Pilih Santri</label>
            <select name="peserta_id" id="peserta_id" class="w-full modern-select" required>
                <option value="">-- Pilih santri --</option>
                <?php foreach ($peserta_list as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $selected_peserta_id == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="overflow-x-auto responsive-table">
            <table class="w-full text-sm border border-gray-200/60 rounded-xl overflow-hidden">
                <thead class="modern-table-header">
                    <tr>
                        <th class="px-4 py-3 text-left">Hari</th>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-center">Jumlah Juz</th>
                        <th class="px-4 py-3 text-left">Detail Juz & Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $hari_list = ['senin'=>'Senin','selasa'=>'Selasa','rabu'=>'Rabu','kamis'=>'Kamis','jumat'=>'Jumat','sabtu'=>'Sabtu','minggu'=>'Minggu'];
                    $hari_map = ['senin'=>'monday','selasa'=>'tuesday','rabu'=>'wednesday','kamis'=>'thursday','jumat'=>'friday','sabtu'=>'saturday','minggu'=>'sunday'];
                    foreach ($hari_list as $key => $nama):
                    $tgl = date('d M Y', strtotime($hari_map[$key] . ' this week'));
                    ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-4 font-medium"><?= $nama ?></td>
                        <td class="px-4 py-4 text-gray-400 text-xs"><?= $tgl ?></td>
                        <td class="px-4 py-4 text-center">
                            <select name="hari[<?= $key ?>][jumlah]" class="jumlah-juz w-20 modern-select text-xs py-2" data-hari="<?= $key ?>">
                                <?php for($j=0;$j<=5;$j++): ?><option value="<?= $j ?>"><?= $j ?></option><?php endfor; ?>
                            </select>
                        </td>
                        <td class="px-4 py-4">
                            <div class="juz-container flex flex-wrap gap-3" id="juz-container-<?= $key ?>">
                                <span class="text-gray-400 text-xs italic">Pilih jumlah juz</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-7 flex justify-center">
            <button type="submit" name="simpan_murojaah" class="px-10 py-3.5 btn-glass text-sm flex items-center gap-2"><i class="fas fa-check"></i> SIMPAN DATA</button>
        </div>
    </form>
</div>

<div id="history-container" class="mt-7">
    <div class="glass-card p-8 text-center text-gray-400 text-sm italic">Pilih santri terlebih dahulu untuk melihat riwayat</div>
</div>

</div> <div id="historyModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
    <div class="modal-content w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-semibold text-gray-700">Riwayat Muroja'ah <span id="modalPesertaNama" class="text-indigo-500 font-bold ml-2"></span></h3>
            <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto" id="historyModalContent"></div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
    <div class="modal-content w-full max-w-md">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-semibold text-gray-700">Edit Data Juz</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="mb-4">
                <label class="block text-xs mb-2">Juz</label>
                <select id="edit_juz" name="edit_juz" class="w-full modern-select" required>
                    <?php for ($j=1; $j<=30; $j++): ?><option value="<?= $j ?>">Juz <?= $j ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-xs mb-2">Kualitas</label>
                <select id="edit_status" name="edit_status" class="w-full modern-select">
                    <option value="lancar">Lancar</option>
                    <option value="cukup">Cukup</option>
                    <option value="tidak">Tidak Lancar</option>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-xs mb-2">Catatan</label>
                <input type="text" id="edit_catatan" name="edit_catatan" class="w-full modern-input" placeholder="Catatan...">
            </div>
            <button type="submit" name="edit_data" class="w-full py-3 btn-glass text-sm">Simpan Perubahan</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Generate Input Dinamis tanpa kolom angka
    document.querySelectorAll('.jumlah-juz').forEach(select => {
        select.addEventListener('change', function() {
            const hari = this.dataset.hari;
            const jumlah = parseInt(this.value);
            const container = document.getElementById(`juz-container-${hari}`);
            
            if (jumlah === 0) {
                container.innerHTML = '<span class="text-gray-400 text-xs italic">Pilih jumlah juz</span>';
                return;
            }
            
            let html = '';
            for (let i = 1; i <= jumlah; i++) {
                html += `
                <div class="flex flex-col gap-2 p-3 bg-gray-50/80 border border-gray-200 rounded-lg w-full sm:w-auto flex-1 min-w-[200px]">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold bg-indigo-100 text-indigo-600 px-2 py-1 rounded">J${i}</span>
                        <input list="juzList" name="hari[${hari}][juz_${i}]" class="flex-1 modern-input text-xs py-1.5" placeholder="Juz ke-" required>
                    </div>
                    <div class="flex items-center gap-2">
                        <select name="hari[${hari}][status_${i}]" class="w-1/2 modern-select text-xs py-1.5 bg-white">
                            <option value="lancar">Lancar</option>
                            <option value="cukup">Cukup</option>
                            <option value="tidak">Tidak Lancar</option>
                        </select>
                        <input type="text" name="hari[${hari}][catatan_${i}]" class="w-1/2 modern-input text-xs py-1.5" placeholder="Catatan">
                    </div>
                </div>`;
            }
            container.innerHTML = html;
        });
    });

    // Datalist untuk kemudahan ketik juz
    const datalist = document.createElement('datalist');
    datalist.id = 'juzList';
    for(let i=1; i<=30; i++) datalist.innerHTML += `<option value="${i}">Juz ${i}</option>`;
    document.body.appendChild(datalist);

    // 2. AJAX Load History Otomatis saat ganti santri
    const pesertaSelect = document.getElementById('peserta_id');
    if (pesertaSelect) {
        pesertaSelect.addEventListener('change', function() {
            const pid = this.value;
            if (pid) {
                // Update URL parameter tanpa reload halaman
                const url = new URL(window.location);
                url.searchParams.set('peserta_id', pid);
                window.history.pushState({}, '', url);
                
                loadHistoryData(pid);
            } else {
                document.getElementById('history-container').innerHTML = '<div class="glass-card p-8 text-center text-gray-400 text-sm italic">Pilih santri terlebih dahulu untuk melihat riwayat</div>';
            }
        });
    }

    function loadHistoryData(pid) {
        const hc = document.getElementById('history-container');
        hc.style.opacity = '0.5';
        fetch(`?action=get_history&peserta_id=${pid}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(res => res.json())
            .then(data => {
                if(data.success) hc.innerHTML = data.html;
                hc.style.opacity = '1';
            }).catch(() => hc.innerHTML = 'Error loading data');
    }

    // Modal History Generation
    window.openHistoryModal = function() {
        const pid = document.getElementById('peserta_id').value;
        if (!pid) return alert('Pilih santri dulu');
        
        document.getElementById('historyModal').classList.remove('hidden');
        document.getElementById('historyModalContent').innerHTML = 'Loading...';
        
        fetch(`?action=get_history&peserta_id=${pid}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(res => res.json())
            .then(data => {
                document.getElementById('modalPesertaNama').innerText = `- ${data.nama_peserta}`;
                if(!data.data || data.data.length===0) {
                    document.getElementById('historyModalContent').innerHTML = '<p class="text-center text-gray-500 py-10">Data Kosong</p>';
                    return;
                }
                
                let html = `<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="modern-table-header"><tr>
                    <th class="p-3 text-left">Hari/Tgl</th><th class="p-3 text-left">Juz</th><th class="p-3 text-center">Kualitas</th><th class="p-3 text-left">Catatan</th><th class="p-3 text-center">Aksi</th>
                </tr></thead><tbody class="divide-y divide-gray-100">`;
                
                data.data.forEach((d) => {
                    const tgl = new Date(d.tanggal).toLocaleDateString('id-ID', { day:'2-digit', month:'short' });
                    html += `<tr>
                        <td class="p-3"><div class="font-medium">${d.hari.substr(0,3)}</div><div class="text-[10px] text-gray-400">${tgl}</div></td>
                        <td class="p-3 text-indigo-600 font-mono text-xs">${d.juz_list}</td>
                        <td class="p-3 text-center"><span class="px-2 py-1 text-[10px] border rounded-full ${d.badge_class}">${d.kualitas}</span></td>
                        <td class="p-3 text-xs text-gray-500 max-w-[150px] truncate">${d.catatan || '-'}</td>
                        <td class="p-3 text-center">
                            <button onclick="openEditModal(${d.sample_id})" class="text-blue-500 mx-1"><i class="fas fa-edit"></i></button>
                            <form method="POST" class="inline" onsubmit="return confirm('Hapus riwayat hari ini?')">
                                <input type="hidden" name="delete_date" value="${d.tanggal}"><input type="hidden" name="peserta_id" value="${pid}">
                                <button type="submit" name="hapus_per_tanggal" class="text-red-500 mx-1"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>`;
                });
                document.getElementById('historyModalContent').innerHTML = html + `</tbody></table></div>`;
            });
    }

    window.closeHistoryModal = () => document.getElementById('historyModal').classList.add('hidden');
    
    // Modal Edit
    window.openEditModal = function(id) {
        closeHistoryModal();
        document.getElementById('edit_id').value = id;
        document.getElementById('editModal').classList.remove('hidden');
    }
    window.closeEditModal = () => document.getElementById('editModal').classList.add('hidden');

    // Inisiasi awal jika ada peserta_id di URL
    <?php if ($selected_peserta_id > 0): ?>
        loadHistoryData(<?= $selected_peserta_id ?>);
    <?php endif; ?>
});
</script>
</body>
</html>