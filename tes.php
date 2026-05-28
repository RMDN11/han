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
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// ==========================================
// FUNGSI: HITUNG KUALITAS PER JUZ (LOGIKA BARU)
// ==========================================
function hitungKualitasPerJuz($ketuk, $tuntun) {
    // LOGIKA KETUK:
    // < 3 → Lancar | = 3 → Cukup | > 3 → Tidak Lancar
    $status_ketuk = 'Lancar';
    if ($ketuk == 3) {
        $status_ketuk = 'Cukup';
    } elseif ($ketuk > 3) {
        $status_ketuk = 'Tidak Lancar';
    }
    
    // LOGIKA TUNTUN:
    // < 2 → Lancar | = 2 → Cukup | > 2 → Tidak Lancar
    $status_tuntun = 'Lancar';
    if ($tuntun == 2) {
        $status_tuntun = 'Cukup';
    } elseif ($tuntun > 2) {
        $status_tuntun = 'Tidak Lancar';
    }
    
    // Ambil status TERBURUK dari keduanya
    if ($status_ketuk === 'Tidak Lancar' || $status_tuntun === 'Tidak Lancar') {
        return ['status' => 'Tidak Lancar', 'class' => 'badge-danger', 'icon' => 'xmark'];
    } elseif ($status_ketuk === 'Cukup' || $status_tuntun === 'Cukup') {
        return ['status' => 'Cukup', 'class' => 'badge-warning', 'icon' => 'minus'];
    } else {
        return ['status' => 'Lancar', 'class' => 'badge-success', 'icon' => 'check'];
    }
}

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
// 3. PROSES HAPUS DATA MUROJAAH (SINGLE ID)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_data']) && !$is_ajax) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
        $stmt_get = $conn->prepare("SELECT peserta_id, tanggal, juz FROM manzil_data WHERE id = ?");
        $stmt_get->bind_param("i", $delete_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $record_info = $result_get->fetch_assoc();
        $stmt_get->close();
        
        if ($record_info) {
            $peserta_id_for_update = $record_info['peserta_id'];
            $tanggal = $record_info['tanggal'];
            $minggu_ke = date('W', strtotime($tanggal));
            $tahun_for_update = date('Y', strtotime($tanggal));
            
            $stmt = $conn->prepare("DELETE FROM manzil_data WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $message = "Data berhasil dihapus!";
                $message_type = 'success';
                updateRangkumanMingguan($conn, $peserta_id_for_update, $minggu_ke, $tahun_for_update);
            } else {
                $message = "Gagal menghapus data!";
                $message_type = 'error';
            }
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?peserta_id=" . $peserta_id_for_update);
            exit;
        }
    }
}

// ==========================================
// 4. PROSES EDIT DATA MUROJAAH
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_data']) && !$is_ajax) {
    $edit_id = (int)$_POST['edit_id'];
    $juz = (int)$_POST['edit_juz'];
    $ketuk = (int)$_POST['edit_ketuk'];
    $tuntun = (int)$_POST['edit_tuntun'];
    $catatan = trim($_POST['edit_catatan'] ?? '');
    
    if ($edit_id > 0 && $juz >= 1 && $juz <= 30) {
        $stmt = $conn->prepare("UPDATE manzil_data SET juz = ?, ketuk = ?, tuntun = ?, catatan = ? WHERE id = ?");
        $stmt->bind_param("iiisi", $juz, $ketuk, $tuntun, $catatan, $edit_id);
        if ($stmt->execute()) {
            $message = "Data berhasil diupdate!";
            $message_type = 'success';
            
            $stmt_get = $conn->prepare("SELECT peserta_id, tanggal FROM manzil_data WHERE id = ?");
            $stmt_get->bind_param("i", $edit_id);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            $record_info = $result_get->fetch_assoc();
            $stmt_get->close();
            
            if ($record_info) {
                $peserta_id_for_update = $record_info['peserta_id'];
                $tanggal = $record_info['tanggal'];
                $minggu_ke = date('W', strtotime($tanggal));
                $tahun_for_update = date('Y', strtotime($tanggal));
                updateRangkumanMingguan($conn, $peserta_id_for_update, $minggu_ke, $tahun_for_update);
            }
        } else {
            $message = "Gagal mengupdate data!";
            $message_type = 'error';
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF'] . "?peserta_id=" . ($record_info['peserta_id'] ?? $selected_peserta_id));
        exit;
    }
}

// ==========================================
// 5. AMBIL DAFTAR SANTRI
// ==========================================
$peserta_list = [];
try {
    $query_peserta = "SELECT id, nama FROM peserta_manzil WHERE aktif = 1 ORDER BY nama ASC";
    $result_peserta = $conn->query($query_peserta);
    while ($row = $result_peserta->fetch_assoc()) {
        $peserta_list[] = $row;
    }
} catch (Exception $e) {
    // Tabel belum ada
}

// ==========================================
// 6. PROSES TAMBAH SANTRI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_peserta']) && !$is_ajax) {
    $nama_baru = trim($_POST['nama_baru'] ?? '');
    if (!empty($nama_baru)) {
        $stmt_check = $conn->prepare("SELECT id FROM peserta_manzil WHERE nama = ?");
        $stmt_check->bind_param("s", $nama_baru);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $message = "Santri dengan nama tersebut sudah ada!";
            $message_type = 'warning';
        } else {
            $stmt = $conn->prepare("INSERT INTO peserta_manzil (nama, aktif) VALUES (?, 1)");
            $stmt->bind_param("s", $nama_baru);
            if ($stmt->execute()) {
                $message = "Santri berhasil ditambahkan!";
                $message_type = 'success';
            } else {
                $message = "Gagal menambahkan santri: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
        $stmt_check->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = "Nama santri tidak boleh kosong!";
        $message_type = 'error';
    }
}

// ==========================================
// 7. PROSES SIMPAN MUROJAAH
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_murojaah']) && !$is_ajax) {
    $peserta_id = (int)($_POST['peserta_id'] ?? 0);
    if ($peserta_id <= 0) {
        $message = 'Pilih santri terlebih dahulu!';
        $message_type = 'error';
    } else {
        $success_count = 0;
        $error_count = 0;
        $hari_list = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        
        foreach ($hari_list as $hari) {
            $jumlah_juz = isset($_POST['hari'][$hari]['jumlah']) ? (int)$_POST['hari'][$hari]['jumlah'] : 0;
            if ($jumlah_juz > 0 && $jumlah_juz <= 5) {
                $hari_map = [
                    'senin' => 'monday', 'selasa' => 'tuesday', 'rabu' => 'wednesday',
                    'kamis' => 'thursday', 'jumat' => 'friday', 'sabtu' => 'saturday', 'minggu' => 'sunday'
                ];
                $tanggal = date('Y-m-d', strtotime($hari_map[$hari] . ' this week'));
                
                for ($juz_ke = 1; $juz_ke <= $jumlah_juz; $juz_ke++) {
                    $juz = (int)($_POST['hari'][$hari]["juz_{$juz_ke}"] ?? 0);
                    $ketuk = isset($_POST['hari'][$hari]["ketuk_{$juz_ke}"]) ? (int)$_POST['hari'][$hari]["ketuk_{$juz_ke}"] : 0;
                    $tuntun = isset($_POST['hari'][$hari]["tuntun_{$juz_ke}"]) ? (int)$_POST['hari'][$hari]["tuntun_{$juz_ke}"] : 0;
                    $catatan = trim($_POST['hari'][$hari]["catatan_{$juz_ke}"] ?? '');
                    
                    if ($juz >= 1 && $juz <= 30 && $tanggal) {
                        $stmt_check = $conn->prepare("SELECT id FROM manzil_data WHERE peserta_id = ? AND tanggal = ? AND juz = ? AND juz_ke = ?");
                        $stmt_check->bind_param("issi", $peserta_id, $tanggal, $juz, $juz_ke);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        
                        if ($result_check->num_rows > 0) {
                            $row = $result_check->fetch_assoc();
                            $stmt_update = $conn->prepare("UPDATE manzil_data SET ketuk = ?, tuntun = ?, catatan = ? WHERE id = ?");
                            $stmt_update->bind_param("iisi", $ketuk, $tuntun, $catatan, $row['id']);
                            if ($stmt_update->execute()) $success_count++;
                            else $error_count++;
                            $stmt_update->close();
                        } else {
                            $stmt_insert = $conn->prepare("INSERT INTO manzil_data (peserta_id, tanggal, juz, juz_ke, ketuk, tuntun, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_insert->bind_param("issiiis", $peserta_id, $tanggal, $juz, $juz_ke, $ketuk, $tuntun, $catatan);
                            if ($stmt_insert->execute()) $success_count++;
                            else $error_count++;
                            $stmt_insert->close();
                        }
                        $stmt_check->close();
                    }
                }
            }
        }
        
        if ($success_count > 0) {
            $message = "Data berhasil disimpan! ($success_count data berhasil)";
            $message_type = 'success';
            updateRangkumanMingguan($conn, $peserta_id, $minggu_ini, $tahun_ini);
        } else {
            $message = "Tidak ada data yang disimpan!";
            $message_type = 'warning';
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
    
    $stmt = $conn->prepare("
    SELECT
    COUNT(DISTINCT juz) as total_juz,
    COUNT(*) as total_murojaah,
    SUM(ketuk) as total_ketuk,
    SUM(tuntun) as total_tuntun
    FROM manzil_data
    WHERE peserta_id = ?
    AND tanggal >= ?
    AND tanggal <= ?
    ");
    $stmt->bind_param("iss", $peserta_id, $tanggal_awal, $tanggal_akhir);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_juz = $row['total_juz'];
        $total_murojaah = $row['total_murojaah'];
        $total_ketuk = $row['total_ketuk'] ?? 0;
        $total_tuntun = $row['total_tuntun'] ?? 0;
        
        // Hitung kualitas rata-rata untuk rangkuman
        $avg_ketuk = $total_murojaah > 0 ? $total_ketuk / $total_murojaah : 0;
        $avg_tuntun = $total_murojaah > 0 ? $total_tuntun / $total_murojaah : 0;
        
        $status_ketuk = $avg_ketuk < 3 ? 'Lancar' : ($avg_ketuk == 3 ? 'Cukup' : 'Tidak Lancar');
        $status_tuntun = $avg_tuntun < 2 ? 'Lancar' : ($avg_tuntun == 2 ? 'Cukup' : 'Tidak Lancar');
        
        if ($status_ketuk === 'Tidak Lancar' || $status_tuntun === 'Tidak Lancar') {
            $kualitas = 'Tidak Lancar';
        } elseif ($status_ketuk === 'Cukup' || $status_tuntun === 'Cukup') {
            $kualitas = 'Cukup';
        } else {
            $kualitas = 'Lancar';
        }
        
        $stmt_check = $conn->prepare("SELECT id FROM manzil_rangkuman WHERE peserta_id = ? AND minggu_ke = ? AND tahun = ?");
        $stmt_check->bind_param("iii", $peserta_id, $minggu_ke, $tahun);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $row_check = $result_check->fetch_assoc();
            $stmt_update = $conn->prepare("UPDATE manzil_rangkuman SET total_juz = ?, total_murojaah = ?, rata_ketuk = ?, rata_tuntun = ?, kualitas = ? WHERE id = ?");
            $stmt_update->bind_param("iiddsi", $total_juz, $total_murojaah, $total_ketuk, $total_tuntun, $kualitas, $row_check['id']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO manzil_rangkuman (peserta_id, minggu_ke, tahun, total_juz, total_murojaah, rata_ketuk, rata_tuntun, kualitas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("iiiiidds", $peserta_id, $minggu_ke, $tahun, $total_juz, $total_murojaah, $total_ketuk, $total_tuntun, $kualitas);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $stmt->close();
}

// ==========================================
// AJAX GET HISTORY (INLINE TABLE - PER JUZ)
// ==========================================
if ($is_ajax && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $peserta_id = (int)$_GET['peserta_id'];
    $response = ['success' => false, 'data' => [], 'html' => ''];
    
    if ($peserta_id > 0) {
        $tanggal_awal_minggu = date('Y-m-d', strtotime('monday this week'));
        $tanggal_akhir_minggu = date('Y-m-d', strtotime('sunday this week'));
        
        $stmt_history = $conn->prepare("
        SELECT id, tanggal, DAYNAME(tanggal) as hari, juz, juz_ke, ketuk, tuntun, catatan
        FROM manzil_data
        WHERE peserta_id = ? AND tanggal >= ? AND tanggal <= ?
        ORDER BY tanggal ASC, juz_ke ASC
        ");
        $stmt_history->bind_param("iss", $peserta_id, $tanggal_awal_minggu, $tanggal_akhir_minggu);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        $raw_data = [];
        while ($row = $result_history->fetch_assoc()) {
            $raw_data[] = $row;
        }
        $stmt_history->close();
        
        // GROUPING PER TANGGAL DENGAN DETAIL PER-JUZ
        $grouped_data = [];
        foreach ($raw_data as $item) {
            $tanggal = $item['tanggal'];
            if (!isset($grouped_data[$tanggal])) {
                $grouped_data[$tanggal] = [
                    'tanggal' => $tanggal,
                    'hari' => $item['hari'],
                    'juz_details' => [],
                    'ids' => []
                ];
            }
            
            $kualitas_info = hitungKualitasPerJuz($item['ketuk'], $item['tuntun']);
            
            $grouped_data[$tanggal]['juz_details'][] = [
                'juz' => $item['juz'],
                'juz_ke' => $item['juz_ke'],
                'ketuk' => $item['ketuk'],
                'tuntun' => $item['tuntun'],
                'catatan' => $item['catatan'],
                'kualitas' => $kualitas_info['status'],
                'badge_class' => $kualitas_info['class'],
                'icon' => $kualitas_info['icon'],
                'id' => $item['id']
            ];
            $grouped_data[$tanggal]['ids'][] = $item['id'];
        }
        
        $history_grouped = [];
        foreach ($grouped_data as $tanggal => $group) {
            usort($group['juz_details'], function($a, $b) {
                return $a['juz_ke'] <=> $b['juz_ke'];
            });
            
            $history_grouped[] = [
                'tanggal' => $group['tanggal'],
                'hari' => $group['hari'],
                'juz_details' => $group['juz_details'],
                'total_juz' => count($group['juz_details']),
                'sample_id' => $group['ids'][0]
            ];
        }
        
        $stmt_nama = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
        $stmt_nama->bind_param("i", $peserta_id);
        $stmt_nama->execute();
        $result_nama = $stmt_nama->get_result();
        if ($nama_row = $result_nama->fetch_assoc()) {
            $response['nama_peserta'] = $nama_row['nama'];
        }
        $stmt_nama->close();
        
        $response['success'] = true;
        $response['data'] = $history_grouped;
        $response['html'] = generateHistoryTableHTML($history_grouped, $peserta_id);
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ==========================================
// FUNCTION GENERATE INLINE TABLE HTML (PER-JUZ)
// ==========================================
function generateHistoryTableHTML($history_data, $peserta_id) {
    if (empty($history_data)) {
        return '<div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">Belum ada data murojaah minggu ini</div>';
    }
    
    $html = '
    <div class="glass-card mt-7">
    <div class="px-5 py-4 border-b border-gray-200/60 flex items-center justify-between">
    <div class="flex items-center gap-2">
    <i class="fas fa-clock-rotate-left text-gray-500 text-xs"></i>
    <span class="text-sm font-medium text-gray-700 tracking-wide">RIWAYAT MUROJA\'AH</span>
    </div>
    </div>
    
    <div class="p-5">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
    <thead class="table-header rounded-t-xl">
    <tr>
    <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
    <th class="px-4 py-3 text-left font-semibold">Juz</th>
    <th class="px-4 py-3 text-center font-semibold">Ketuk</th>
    <th class="px-4 py-3 text-center font-semibold">Tuntun</th>
    <th class="px-4 py-3 text-center font-semibold">Status</th>
    <th class="px-4 py-3 text-left font-semibold">Catatan</th>
    <th class="px-4 py-3 text-center font-semibold">Aksi</th>
    </tr>
    </thead>
    <tbody class="text-gray-600">';
    
    $current_date = '';
    foreach ($history_data as $data) {
        foreach ($data['juz_details'] as $juz) {
            $tanggal_display = $current_date !== $data['tanggal'] 
                ? '<span class="font-medium text-gray-800">' . date('d M', strtotime($data['tanggal'])) . '</span>' 
                : '<span class="text-gray-400">〃</span>';
            $current_date = $data['tanggal'];
            
            $html .= '
            <tr class="border-b border-gray-100/60 hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-3 whitespace-nowrap">' . $tanggal_display . '</td>
            <td class="px-4 py-3">
            <span class="font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            ' . $juz['juz'] . '
            </span>
            </td>
            <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-50 text-orange-600 font-medium text-xs">
            ' . $juz['ketuk'] . 'x
            </span>
            </td>
            <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-pink-50 text-pink-600 font-medium text-xs">
            ' . $juz['tuntun'] . 'x
            </span>
            </td>
            <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center px-3 py-1.5 text-[11px] font-medium rounded-full border ' . $juz['badge_class'] . '">
            <i class="fas fa-' . $juz['icon'] . ' mr-1"></i>
            ' . $juz['kualitas'] . '
            </span>
            </td>
            <td class="px-4 py-3 text-gray-500 italic max-w-[180px] truncate" title="' . htmlspecialchars($juz['catatan']) . '">
            ' . (empty($juz['catatan']) ? '-' : htmlspecialchars($juz['catatan'])) . '
            </td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
            <button onclick="openEditModal(' . $juz['id'] . ')" 
            class="text-blue-500 hover:text-blue-700 mr-3 transition-colors" title="Edit">
            <i class="fas fa-pencil-alt text-xs"></i>
            </button>
            <form method="POST" action="" class="inline" onsubmit="return confirm(\'Yakin hapus data Juz ' . $juz['juz'] . ' ini?\')">
            <input type="hidden" name="delete_id" value="' . $juz['id'] . '">
            <button type="submit" name="hapus_data" class="text-red-400 hover:text-red-600 transition-colors" title="Hapus">
            <i class="fas fa-trash-alt text-xs"></i>
            </button>
            </form>
            </td>
            </tr>';
        }
    }
    
    $html .= '
    </tbody>
    </table>
    </div>
    <p class="text-[11px] text-gray-400 mt-4 italic pl-4">
    <i class="fas fa-info-circle mr-1"></i> 
    Status: Ketuk (≤2=Lancar, 3=Cukup, >3=Tidak Lancar) | Tuntun (≤1=Lancar, 2=Cukup, >2=Tidak Lancar)
    </p>
    </div>
    </div>';
    
    return $html;
}

// ==========================================
// LOAD DATA INITIAL (NON-AJAX)
// ==========================================
$selected_peserta_id = isset($_GET['peserta_id']) ? (int)$_GET['peserta_id'] : 0;
$history_grouped = [];
$selected_peserta_nama = '';

if ($selected_peserta_id > 0) {
    $tanggal_awal_minggu = date('Y-m-d', strtotime('monday this week'));
    $tanggal_akhir_minggu = date('Y-m-d', strtotime('sunday this week'));
    
    $stmt_history = $conn->prepare("
    SELECT id, tanggal, DAYNAME(tanggal) as hari, juz, juz_ke, ketuk, tuntun, catatan
    FROM manzil_data
    WHERE peserta_id = ? AND tanggal >= ? AND tanggal <= ?
    ORDER BY tanggal ASC, juz_ke ASC
    ");
    $stmt_history->bind_param("iss", $selected_peserta_id, $tanggal_awal_minggu, $tanggal_akhir_minggu);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    $raw_data = [];
    while ($row = $result_history->fetch_assoc()) {
        $raw_data[] = $row;
    }
    $stmt_history->close();
    
    $grouped_data = [];
    foreach ($raw_data as $item) {
        $tanggal = $item['tanggal'];
        if (!isset($grouped_data[$tanggal])) {
            $grouped_data[$tanggal] = [
                'tanggal' => $tanggal,
                'hari' => $item['hari'],
                'juz_details' => [],
                'ids' => []
            ];
        }
        
        $kualitas_info = hitungKualitasPerJuz($item['ketuk'], $item['tuntun']);
        
        $grouped_data[$tanggal]['juz_details'][] = [
            'juz' => $item['juz'],
            'juz_ke' => $item['juz_ke'],
            'ketuk' => $item['ketuk'],
            'tuntun' => $item['tuntun'],
            'catatan' => $item['catatan'],
            'kualitas' => $kualitas_info['status'],
            'badge_class' => $kualitas_info['class'],
            'icon' => $kualitas_info['icon'],
            'id' => $item['id']
        ];
        $grouped_data[$tanggal]['ids'][] = $item['id'];
    }
    
    foreach ($grouped_data as $tanggal => $group) {
        usort($group['juz_details'], function($a, $b) {
            return $a['juz_ke'] <=> $b['juz_ke'];
        });
        
        $history_grouped[] = [
            'tanggal' => $group['tanggal'],
            'hari' => $group['hari'],
            'juz_details' => $group['juz_details'],
            'total_juz' => count($group['juz_details']),
            'sample_id' => $group['ids'][0]
        ];
    }
    
    $stmt_nama = $conn->prepare("SELECT nama FROM peserta_manzil WHERE id = ?");
    $stmt_nama->bind_param("i", $selected_peserta_id);
    $stmt_nama->execute();
    $result_nama = $stmt_nama->get_result();
    if ($row_nama = $result_nama->fetch_assoc()) {
        $selected_peserta_nama = $row_nama['nama'];
    }
    $stmt_nama->close();
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
/* GLASSMORPHISM & MODERN UI */
* {
    -webkit-tap-highlight-color: transparent;
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
    background-attachment: fixed;
    color: #334155;
    min-height: 100vh;
}

/* Glassmorphism Card */
.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.glass-card:hover {
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}

/* Modern Input Style */
.modern-input, .modern-select {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.6);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    border-radius: 0.875rem;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.modern-input:focus, .modern-select:focus {
    border-color: rgba(59, 130, 246, 0.5);
    outline: none;
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.modern-input:hover, .modern-select:hover {
    border-color: rgba(148, 163, 184, 0.8);
}

/* Modern Button Styles */
.btn-modern {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 1rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.btn-modern:active {
    transform: translateY(0);
}

.btn-soft {
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid rgba(203, 213, 225, 0.6);
    color: #475569;
    border-radius: 1rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.btn-soft:hover {
    background: rgba(226, 232, 240, 0.9);
    border-color: rgba(148, 163, 184, 0.8);
}

.btn-soft-success {
    background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
    color: white;
    border: none;
    border-radius: 1rem;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.btn-soft-success:hover {
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
}

.btn-soft-danger {
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
    color: white;
    border: none;
    border-radius: 1rem;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
}

.btn-soft-danger:hover {
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45);
}

/* Badge Styles - Colorful */
.badge-success {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
    border: 1px solid #86efac;
}

.badge-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fcd34d;
}

.badge-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.table-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    color: #475569;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

hr.soft {
    border: 0;
    border-top: 1px solid rgba(226, 232, 240, 0.6);
    margin: 1.5rem 0;
}

#history-container {
    transition: opacity 0.2s ease;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

.animate-fade-in {
    animation: fadeIn 0.2s ease-out forwards;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-slide-up {
    animation: slideUp 0.3s ease-out forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .responsive-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    
    .juz-container {
        min-width: 200px;
    }
    
    .glass-card {
        border-radius: 1.25rem;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.5);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.6);
}
</style>
</head>
<body class="p-4 md:p-6">
<div class="max-w-7xl mx-auto">

<!-- Modern Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white shadow-lg">
            <i class="fas fa-book-quran text-lg"></i>
        </div>
        <div>
            <h1 class="text-2xl font-semibold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">muroja'ah</h1>
            <p class="text-xs text-gray-500 mt-0.5">monitoring kualitas hafalan</p>
        </div>
    </div>
    <div class="text-left md:text-right">
        <p class="text-sm font-medium text-gray-700">Minggu <?= $minggu_ini ?> · <?= $tahun_ini ?></p>
        <p class="text-xs text-gray-400"><?= date('d M Y') ?></p>
    </div>
</div>

<!-- Alert Messages - Modern Style -->
<?php if (!empty($message)): ?>
<div class="mb-6 px-5 py-4 rounded-2xl border text-sm animate-slide-up <?= 
    $message_type === 'success' ? 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200 text-green-800' : 
    ($message_type === 'error' ? 'bg-gradient-to-r from-red-50 to-rose-50 border-red-200 text-red-800' : 
    'bg-gradient-to-r from-amber-50 to-orange-50 border-amber-200 text-amber-800') ?>">
    <div class="flex items-center">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle') ?> mr-3 text-lg"></i>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Tombol Laporan -->
<?php if ($selected_peserta_id > 0): ?>
<div class="mb-7 flex justify-end">
    <a href="manzil-report.php?peserta_id=<?= $selected_peserta_id ?>"
        class="inline-flex items-center px-5 py-2.5 btn-soft text-sm border transition-all hover:shadow-md">
        <i class="fas fa-chart-simple mr-2 text-xs"></i> Laporan Mingguan
    </a>
</div>
<?php endif; ?>

<!-- Kelola Santri - Glass Card -->
<div class="glass-card mb-7 p-5 animate-slide-up">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white">
            <i class="fas fa-users text-xs"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 tracking-wide">KELOLA SANTRI</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Tambah Santri -->
        <form method="POST" action="" class="flex flex-col sm:flex-row items-stretch gap-2">
            <input type="text" name="nama_baru"
                class="flex-1 modern-input text-sm"
                placeholder="Nama santri baru..." required>
            <button type="submit" name="tambah_peserta"
                class="px-4 btn-soft-success text-sm flex items-center justify-center whitespace-nowrap">
                <i class="fas fa-plus mr-1 text-xs"></i> Tambah
            </button>
        </form>
        <!-- Hapus Santri -->
        <form method="POST" action="" class="flex flex-col sm:flex-row items-stretch gap-2">
            <select name="peserta_id_hapus" class="flex-1 modern-select text-sm" required>
                <option value="">-- Pilih santri untuk dihapus --</option>
                <?php foreach ($peserta_list as $peserta): ?>
                <option value="<?= $peserta['id'] ?>"><?= htmlspecialchars($peserta['nama']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="hapus_peserta"
                class="px-4 btn-soft-danger text-sm flex items-center justify-center whitespace-nowrap"
                onclick="return confirm('Yakin ingin menghapus santri ini?')">
                <i class="fas fa-trash-alt mr-1 text-xs"></i> Hapus
            </button>
        </form>
    </div>
</div>

<!-- Form Input Muroja'ah - Glass Card -->
<div class="glass-card p-5 animate-slide-up" style="animation-delay: 0.1s">
    <div class="flex items-center gap-2 mb-5">
        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center text-white">
            <i class="fas fa-pen-to-square text-xs"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 tracking-wide">INPUT MUROJA'AH · MINGGU INI</span>
    </div>
    <form method="POST" action="" id="form-murojaah">
        <!-- Pilih Santri -->
        <div class="mb-6 max-w-md">
            <label class="block text-xs text-gray-500 mb-2 font-medium">Pilih Santri</label>
            <select name="peserta_id" id="peserta_id" class="w-full modern-select text-sm" required>
                <option value="">-- Pilih santri --</option>
                <?php foreach ($peserta_list as $peserta): ?>
                <option value="<?= $peserta['id'] ?>" <?= $selected_peserta_id == $peserta['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($peserta['nama']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tabel Muroja'ah Harian - Responsive -->
        <div class="overflow-x-auto responsive-table">
            <table class="w-full text-sm border border-gray-200/60 rounded-xl overflow-hidden">
                <thead class="table-header">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Hari</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-center font-semibold">Jumlah Juz</th>
                        <th class="px-4 py-3 text-left font-semibold">Detail Juz & Catatan</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hari_list = ['senin'=>'Senin','selasa'=>'Selasa','rabu'=>'Rabu','kamis'=>'Kamis','jumat'=>'Jumat','sabtu'=>'Sabtu','minggu'=>'Minggu'];
                    $hari_map = ['senin'=>'monday','selasa'=>'tuesday','rabu'=>'wednesday','kamis'=>'thursday','jumat'=>'friday','sabtu'=>'saturday','minggu'=>'sunday'];
                    foreach ($hari_list as $key => $hari_nama):
                    $tanggal_hari = date('Y-m-d', strtotime($hari_map[$key] . ' this week'));
                    $tanggal_tampil = date('d M Y', strtotime($tanggal_hari));
                    ?>
                    <tr class="border-b border-gray-100/60 hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap"><?= $hari_nama ?></td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap"><?= $tanggal_tampil ?></td>
                        <td class="px-4 py-3 text-center">
                            <select name="hari[<?= $key ?>][jumlah]"
                                class="jumlah-juz w-20 modern-select text-xs py-2 px-2"
                                data-hari="<?= $key ?>">
                                <?php for($j=0;$j<=5;$j++): ?>
                                <option value="<?= $j ?>"><?= $j ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <div class="juz-container min-w-[300px]" id="juz-container-<?= $key ?>">
                                <span class="text-gray-400 text-xs italic">Pilih jumlah juz terlebih dahulu</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-3 py-1.5 text-xs bg-gray-100/80 text-gray-500 border border-gray-200/60 rounded-full whitespace-nowrap">
                                <i class="fas fa-circle mr-1.5 text-[8px] text-gray-400"></i> Belum diisi
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="soft my-6">

        <!-- Tombol Simpan -->
        <div class="flex justify-center">
            <button type="submit" name="simpan_murojaah"
                class="px-10 py-3 btn-modern text-sm tracking-wide flex items-center shadow-lg">
                <i class="fas fa-check mr-2 text-xs"></i> SIMPAN DATA
            </button>
        </div>
    </form>
</div>

<!-- ========== RIWAYAT - INLINE TABLE ========== -->
<div id="history-container" class="animate-slide-up" style="animation-delay: 0.2s">
    <?php if (!empty($history_grouped) && $selected_peserta_id > 0): ?>
        <?= generateHistoryTableHTML($history_grouped, $selected_peserta_id) ?>
    <?php elseif ($selected_peserta_id > 0): ?>
        <div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">
            Belum ada data murojaah minggu ini
        </div>
    <?php else: ?>
        <div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">
            Pilih santri terlebih dahulu untuk melihat riwayat
        </div>
    <?php endif; ?>
</div>

<!-- Modal Edit - Modern Style -->
<div id="editModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-gradient-to-br from-white/95 to-gray-50/95 border border-white/40 rounded-3xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center gap-2 mb-5 pb-3 border-b border-gray-200/60">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white">
                <i class="fas fa-pencil-alt text-xs"></i>
            </div>
            <span class="text-sm font-medium text-gray-700 tracking-wide">EDIT DATA MUROJA'AH</span>
        </div>
        <form method="POST" action="" id="form-edit">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-2 font-medium">Pilih Juz</label>
                <select id="edit_juz" name="edit_juz" class="w-full modern-select text-sm" required>
                    <?php for ($j = 1; $j <= 30; $j++): ?>
                    <option value="<?= $j ?>">Juz <?= $j ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-2 font-medium">Ketuk</label>
                    <input type="number" id="edit_ketuk" name="edit_ketuk" min="0" max="10" value="0"
                        class="w-full modern-input text-sm text-center font-medium" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-2 font-medium">Tuntun</label>
                    <input type="number" id="edit_tuntun" name="edit_tuntun" min="0" max="10" value="0"
                        class="w-full modern-input text-sm text-center font-medium">
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs text-gray-500 mb-2 font-medium">Catatan Surat</label>
                <input type="text" id="edit_catatan" name="edit_catatan"
                    class="w-full modern-input text-sm" placeholder="Contoh: Al-Mulk, Al-Qalam">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeEditModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-200/60 text-gray-600 text-sm hover:bg-gray-100/80 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" name="edit_data"
                    class="flex-1 px-4 py-2.5 btn-modern text-sm flex items-center justify-center">
                    <i class="fas fa-check mr-2 text-xs"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<div class="mt-10 text-center">
    <p class="text-xs text-gray-400 tracking-wide">
        <i class="fas fa-circle mr-1 text-[5px] align-middle text-blue-400"></i>
        Reqra by Han · <?= date('Y') ?> · <span class="text-gray-300">|</span> Muroja'ah System
    </p>
</div>
</div>

<script>
// Generate Juz Detail Forms dengan Suggest
document.addEventListener('DOMContentLoaded', function() {
    const jumlahJuzSelects = document.querySelectorAll('.jumlah-juz');
    
    jumlahJuzSelects.forEach(select => {
        select.addEventListener('change', function() {
            const hari = this.dataset.hari;
            const jumlah = parseInt(this.value);
            const container = document.getElementById(`juz-container-${hari}`);
            const statusCell = this.closest('tr').querySelector('td:last-child span');
            
            if (jumlah === 0) {
                container.innerHTML = '<span class="text-gray-400 text-xs italic">Pilih jumlah juz terlebih dahulu</span>';
                if (statusCell) {
                    statusCell.innerHTML = '<i class="fas fa-circle mr-1.5 text-[8px] text-gray-400"></i>Belum diisi';
                    statusCell.className = 'inline-flex items-center px-3 py-1.5 text-xs bg-gray-100/80 text-gray-500 border border-gray-200/60 rounded-full';
                }
                return;
            }
            
            let html = `<div class="space-y-2.5 max-w-lg">`;
            for (let i = 1; i <= jumlah; i++) {
                html += `
                <div class="flex flex-wrap items-center gap-2 p-3 bg-gradient-to-br from-gray-50/80 to-white/80 border border-gray-200/60 rounded-xl">
                    <span class="text-[11px] font-semibold text-gray-600 min-w-[32px] bg-gray-100/80 px-2 py-1 rounded-lg">J${i}</span>
                    <input list="juzList-${hari}-${i}" name="hari[${hari}][juz_${i}]" 
                        class="flex-1 min-w-[70px] modern-input text-xs py-2 px-2.5" 
                        placeholder="Juz" required>
                    <datalist id="juzList-${hari}-${i}">
                        ${generateJuzOptions()}
                    </datalist>
                    <input type="number" name="hari[${hari}][ketuk_${i}]" min="0" max="10" value="0" placeholder="K"
                        class="w-14 modern-input text-xs py-2 px-2 text-center" data-type="ketuk" data-hari="${hari}" data-idx="${i}">
                    <input type="number" name="hari[${hari}][tuntun_${i}]" min="0" max="10" value="0" placeholder="T"
                        class="w-14 modern-input text-xs py-2 px-2 text-center" data-type="tuntun" data-hari="${hari}" data-idx="${i}">
                    <input type="text" name="hari[${hari}][catatan_${i}]" placeholder="Catatan"
                        class="flex-1 min-w-[90px] modern-input text-xs py-2 px-2">
                </div>`;
            }
            html += `</div>`;
            container.innerHTML = html;
            
            // Update status setelah generate
            updateRowStatus(this.closest('tr'));
        });
    });

    // Event delegation untuk input ketuk/tuntun
    document.addEventListener('input', function(e) {
        if (e.target.dataset.type === 'ketuk' || e.target.dataset.type === 'tuntun') {
            const row = e.target.closest('tr');
            updateRowStatus(row);
        }
    });

    function updateRowStatus(row) {
        const jumlahSelect = row.querySelector('.jumlah-juz');
        const statusCell = row.querySelector('td:last-child span');
        if (!jumlahSelect || !statusCell) return;
        
        const jumlah = parseInt(jumlahSelect.value);
        if (jumlah === 0) return;
        
        const hari = jumlahSelect.dataset.hari;
        let worstStatus = 'Lancar';
        
        for (let i = 1; i <= jumlah; i++) {
            const ketukInput = document.querySelector(`input[name="hari[${hari}][ketuk_${i}]"]`);
            const tuntunInput = document.querySelector(`input[name="hari[${hari}][tuntun_${i}]"]`);
            if (ketukInput && tuntunInput) {
                const ketuk = parseInt(ketukInput.value || 0);
                const tuntun = parseInt(tuntunInput.value || 0);
                
                // Hitung kualitas per juz
                let status_ketuk = 'Lancar';
                if (ketuk == 3) status_ketuk = 'Cukup';
                else if (ketuk > 3) status_ketuk = 'Tidak Lancar';
                
                let status_tuntun = 'Lancar';
                if (tuntun == 2) status_tuntun = 'Cukup';
                else if (tuntun > 2) status_tuntun = 'Tidak Lancar';
                
                // Ambil worst
                let juzStatus = 'Lancar';
                if (status_ketuk === 'Tidak Lancar' || status_tuntun === 'Tidak Lancar') {
                    juzStatus = 'Tidak Lancar';
                } else if (status_ketuk === 'Cukup' || status_tuntun === 'Cukup') {
                    juzStatus = 'Cukup';
                }
                
                // Update worst status untuk row
                if (juzStatus === 'Tidak Lancar') {
                    worstStatus = 'Tidak Lancar';
                } else if (juzStatus === 'Cukup' && worstStatus !== 'Tidak Lancar') {
                    worstStatus = 'Cukup';
                }
            }
        }
        
        // Update badge
        let statusClass = 'badge-success', iconClass = 'fa-check';
        if (worstStatus === 'Tidak Lancar') {
            statusClass = 'badge-danger';
            iconClass = 'fa-xmark';
        } else if (worstStatus === 'Cukup') {
            statusClass = 'badge-warning';
            iconClass = 'fa-minus';
        }
        
        statusCell.innerHTML = `<i class="fas ${iconClass} mr-1.5 text-xs"></i>${worstStatus}`;
        statusCell.className = `inline-flex items-center px-3 py-1.5 text-xs border rounded-full ${statusClass}`;
    }

    function generateJuzOptions() {
        let options = '';
        for (let j = 1; j <= 30; j++) options += `<option value="${j}">Juz ${j}</option>`;
        return options;
    }

    // AUTO LOAD DATA SAAT PILIH SANTRI - VIA AJAX
    const pesertaSelect = document.getElementById('peserta_id');
    if (pesertaSelect) {
        pesertaSelect.addEventListener('change', function() {
            const pesertaId = this.value;
            if (pesertaId) {
                const url = new URL(window.location.href);
                url.searchParams.set('peserta_id', pesertaId);
                window.history.pushState({}, '', url);
                loadHistoryData(pesertaId);
            } else {
                const url = new URL(window.location.href);
                url.searchParams.delete('peserta_id');
                window.history.pushState({}, '', url);
                document.getElementById('history-container').innerHTML = `
                    <div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">
                        Pilih santri terlebih dahulu untuk melihat riwayat
                    </div>`;
            }
        });
    }

    // Fungsi Load History Data via AJAX
    function loadHistoryData(pesertaId) {
        const container = document.getElementById('history-container');
        container.classList.add('loading');
        
        fetch('<?= $_SERVER['PHP_SELF'] ?>?action=get_history&peserta_id=' + pesertaId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
            } else {
                container.innerHTML = '<div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">Gagal memuat data</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="glass-card mt-7 p-6 text-center text-red-500 text-sm italic">Terjadi kesalahan</div>';
        })
        .finally(() => {
            container.classList.remove('loading');
        });
    }

    // Modal Edit Functions
    window.openEditModal = function(id) {
        document.getElementById('edit_id').value = id;
        document.getElementById('editModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Reset fields
        document.getElementById('edit_juz').value = '';
        document.getElementById('edit_ketuk').value = '0';
        document.getElementById('edit_tuntun').value = '0';
        document.getElementById('edit_catatan').value = '';
    }

    window.closeEditModal = function() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    // Handle keyboard ESC to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });

    // Handle browser back/forward
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const pesertaId = urlParams.get('peserta_id');
        if (pesertaId) {
            document.getElementById('peserta_id').value = pesertaId;
            loadHistoryData(pesertaId);
        } else {
            document.getElementById('peserta_id').value = '';
            document.getElementById('history-container').innerHTML = `
                <div class="glass-card mt-7 p-6 text-center text-gray-400 text-sm italic">
                    Pilih santri terlebih dahulu untuk melihat riwayat
                </div>`;
        }
    });

    // Auto-load jika ada peserta_id di URL
    const urlParams = new URLSearchParams(window.location.search);
    const initialPesertaId = urlParams.get('peserta_id');
    if (initialPesertaId && pesertaSelect) {
        pesertaSelect.value = initialPesertaId;
        loadHistoryData(initialPesertaId);
    }
});
</script>
</body>
</html>