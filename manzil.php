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
        $stmt_get = $conn->prepare("SELECT peserta_id, tanggal FROM manzil_data WHERE id = ?");
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
        
        $avg_ketuk = $total_murojaah > 0 ? $total_ketuk / $total_murojaah : 0;
        $avg_tuntun = $total_murojaah > 0 ? $total_tuntun / $total_murojaah : 0;
        
        // LOGIKA KUALITAS BARU: Patokan nilai terbesar antara ketuk & tuntun
        $ketuk_score = 0;
        if ($avg_ketuk > 4) $ketuk_score = 2;      // Tidak Lancar
        elseif ($avg_ketuk > 3) $ketuk_score = 1;   // Cukup
        
        $tuntun_score = 0;
        if ($avg_tuntun > 3) $tuntun_score = 2;    // Tidak Lancar
        elseif ($avg_tuntun > 2) $tuntun_score = 1; // Cukup
        
        $final_score = max($ketuk_score, $tuntun_score);
        
        if ($final_score === 2) {
            $kualitas = 'Tidak Lancar';
        } elseif ($final_score === 1) {
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
// AJAX GET HISTORY
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
        
        $grouped_data = [];
        foreach ($raw_data as $item) {
            $tanggal = $item['tanggal'];
            if (!isset($grouped_data[$tanggal])) {
                $grouped_data[$tanggal] = [
                    'tanggal' => $tanggal,
                    'hari' => $item['hari'],
                    'total_juz' => 0,
                    'juz_list' => [],
                    'total_ketuk' => 0,
                    'total_tuntun' => 0,
                    'catatan_list' => [],
                    'ids' => []
                ];
            }
            $grouped_data[$tanggal]['total_juz']++;
            $grouped_data[$tanggal]['juz_list'][] = $item['juz'];
            $grouped_data[$tanggal]['total_ketuk'] += $item['ketuk'];
            $grouped_data[$tanggal]['total_tuntun'] += $item['tuntun'];
            if (!empty($item['catatan'])) {
                $grouped_data[$tanggal]['catatan_list'][] = "J{$item['juz']}: {$item['catatan']}";
            }
            $grouped_data[$tanggal]['ids'][] = $item['id'];
        }
        
        $history_grouped = [];
        foreach ($grouped_data as $tanggal => $group) {
            $juz_list_unique = array_unique($group['juz_list']);
            sort($juz_list_unique);
            
            $avg_ketuk = $group['total_juz'] > 0 ? $group['total_ketuk'] / $group['total_juz'] : 0;
            $avg_tuntun = $group['total_juz'] > 0 ? $group['total_tuntun'] / $group['total_juz'] : 0;
            
            // LOGIKA KUALITAS BARU
            $ketuk_score = 0;
            if ($avg_ketuk > 4) $ketuk_score = 2;
            elseif ($avg_ketuk > 3) $ketuk_score = 1;
            
            $tuntun_score = 0;
            if ($avg_tuntun > 3) $tuntun_score = 2;
            elseif ($avg_tuntun > 2) $tuntun_score = 1;
            
            $final_score = max($ketuk_score, $tuntun_score);
            
            if ($final_score === 2) {
                $kualitas = 'Tidak Lancar';
                $badge_class = 'badge-tidak';
                $icon_class = 'xmark';
            } elseif ($final_score === 1) {
                $kualitas = 'Cukup';
                $badge_class = 'badge-cukup';
                $icon_class = 'minus';
            } else {
                $kualitas = 'Lancar';
                $badge_class = 'badge-lancar';
                $icon_class = 'check';
            }
            
            $history_grouped[] = [
                'tanggal' => $group['tanggal'],
                'hari' => $group['hari'],
                'total_juz' => $group['total_juz'],
                'juz_list' => implode(', ', $juz_list_unique),
                'total_ketuk' => $group['total_ketuk'],
                'total_tuntun' => $group['total_tuntun'],
                'catatan' => implode('; ', $group['catatan_list']),
                'kualitas' => $kualitas,
                'badge_class' => $badge_class,
                'icon_class' => $icon_class,
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
        $response['html'] = generateHistoryGroupedHTML($history_grouped, $response['nama_peserta'] ?? 'Santri');
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ==========================================
// FUNCTION GENERATE HTML HISTORY
// ==========================================
function generateHistoryGroupedHTML($history_data, $nama_peserta) {
    if (empty($history_data)) {
        return '<div class="glass-card p-6 text-center text-gray-400 text-sm italic">Belum ada data murojaah minggu ini</div>';
    }
    
    $html = '
    <div class="glass-card mt-7">
    <div class="px-5 py-4 history-section flex items-center justify-between">
    <div class="flex items-center gap-2">
    <i class="fas fa-clock-rotate-left text-indigo-400 text-xs"></i>
    <span class="text-sm font-medium text-gray-600 tracking-wide">
    RIWAYAT · ' . htmlspecialchars($nama_peserta) . '
    </span>
    </div>
    <button onclick="openHistoryModal()"
    class="text-xs text-indigo-500 hover:text-indigo-700 border border-indigo-200 px-4 py-2 bg-white/50 backdrop-blur-sm rounded-xl hover:bg-indigo-50 transition-all duration-200 shadow-sm">
    <i class="fas fa-eye mr-1"></i> Preview Riwayat
    </button>
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
                'total_juz' => 0,
                'juz_list' => [],
                'total_ketuk' => 0,
                'total_tuntun' => 0,
                'catatan_list' => [],
                'ids' => []
            ];
        }
        $grouped_data[$tanggal]['total_juz']++;
        $grouped_data[$tanggal]['juz_list'][] = $item['juz'];
        $grouped_data[$tanggal]['total_ketuk'] += $item['ketuk'];
        $grouped_data[$tanggal]['total_tuntun'] += $item['tuntun'];
        if (!empty($item['catatan'])) {
            $grouped_data[$tanggal]['catatan_list'][] = "J{$item['juz']}: {$item['catatan']}";
        }
        $grouped_data[$tanggal]['ids'][] = $item['id'];
    }
    
    foreach ($grouped_data as $tanggal => $group) {
        $juz_list_unique = array_unique($group['juz_list']);
        sort($juz_list_unique);
        
        $avg_ketuk = $group['total_juz'] > 0 ? $group['total_ketuk'] / $group['total_juz'] : 0;
        $avg_tuntun = $group['total_juz'] > 0 ? $group['total_tuntun'] / $group['total_juz'] : 0;
        
        // LOGIKA KUALITAS BARU
        $ketuk_score = 0;
        if ($avg_ketuk > 4) $ketuk_score = 2;
        elseif ($avg_ketuk > 3) $ketuk_score = 1;
        
        $tuntun_score = 0;
        if ($avg_tuntun > 3) $tuntun_score = 2;
        elseif ($avg_tuntun > 2) $tuntun_score = 1;
        
        $final_score = max($ketuk_score, $tuntun_score);
        
        if ($final_score === 2) {
            $kualitas = 'Tidak Lancar';
            $badge_class = 'badge-tidak';
            $icon_class = 'xmark';
        } elseif ($final_score === 1) {
            $kualitas = 'Cukup';
            $badge_class = 'badge-cukup';
            $icon_class = 'minus';
        } else {
            $kualitas = 'Lancar';
            $badge_class = 'badge-lancar';
            $icon_class = 'check';
        }
        
        $history_grouped[] = [
            'tanggal' => $group['tanggal'],
            'hari' => $group['hari'],
            'total_juz' => $group['total_juz'],
            'juz_list' => implode(', ', $juz_list_unique),
            'total_ketuk' => $group['total_ketuk'],
            'total_tuntun' => $group['total_tuntun'],
            'catatan' => implode('; ', $group['catatan_list']),
            'kualitas' => $kualitas,
            'badge_class' => $badge_class,
            'icon_class' => $icon_class,
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

/* GLASSMORPHISM & MODERN UI */
* {
    -webkit-tap-highlight-color: transparent;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--colors-canvas-parchment); /* Parchment */
    background-attachment: fixed;
    color: var(--colors-ink); /* Near-Black Ink */
    min-height: 100vh;
}

/* Glassmorphism Cards */
.glass-card {
    background: var(--colors-canvas);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--colors-hairline);
    border-radius: 20px;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.08),
        0 2px 8px rgba(148, 163, 184, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    transition: all 0.3s ease;
}

.glass-card:hover {
    box-shadow: 
        0 12px 40px rgba(0, 0, 0, 0.12),
        0 4px 12px rgba(148, 163, 184, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
    transform: translateY(-2px);
}

/* Modern Inputs */
.modern-input, .modern-select {
    background: var(--colors-canvas);
    backdrop-filter: blur(8px);
    border: 1px solid var(--colors-hairline);
    border-radius: 8px; /* rounded.sm */
    padding: 0.6rem 0.8rem; /* typography.caption */
    font-size: 0.875rem; /* typography.caption */
    transition: all 0.2s ease;
    color: var(--colors-ink);
} 

.modern-input:focus, .modern-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    background: rgba(255, 255, 255, 0.95);
}
.modern-input:hover, .modern-select:hover {
    border-color: #94a3b8;
}

/* Modern Buttons */
/* btn-apple-primary equivalent */
.btn-glass {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.9), rgba(79, 70, 229, 0.9));
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: white;
    padding: 0.75rem 1.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 14px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}

.btn-glass:hover {
    background: linear-gradient(135deg, rgba(79, 70, 229, 1), rgba(67, 56, 202, 1));
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45);
}

/* btn-apple-success equivalent */
.btn-glass-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.9), rgba(22, 163, 74, 0.9));
    border: 1px solid rgba(34, 197, 94, 0.3);
    box-shadow: 0 4px 14px rgba(34, 197, 94, 0.35);
}

.btn-glass-success:hover {
    background: linear-gradient(135deg, rgba(22, 163, 74, 1), rgba(21, 128, 61, 1));
    box-shadow: 0 6px 20px rgba(22, 163, 74, 0.45);
}

/* btn-apple-danger equivalent */
.btn-glass-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.9));
    border: 1px solid rgba(239, 68, 68, 0.3);
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
}

.btn-glass-danger:hover {
    background: linear-gradient(135deg, rgba(220, 38, 38, 1), rgba(185, 28, 28, 1));
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.45);
}

/* btn-apple-outline equivalent */
.btn-glass-outline {
    background: var(--colors-surface-pearl); /* Pearl Button */
    border: 1px solid var(--colors-hairline);
    color: var(--colors-ink-muted-80);
    border-radius: 11px; /* rounded.md */
    padding: 0.625rem 1.25rem; /* Adjusted padding */
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.btn-glass-outline:hover {
    background: rgba(255, 255, 255, 0.85);
    border-color: #6366f1;
    color: #4f46e5;
}

/* Badges */
.badge-lancar { /* badge-success */
    background: var(--badge-success-bg);
    color: var(--badge-success-text);
    border: 1px solid var(--badge-success-bg);
}

.badge-cukup { /* badge-warning */
    background: var(--badge-warning-bg);
    color: var(--badge-warning-text);
    border: 1px solid var(--badge-warning-bg);
}

.badge-tidak { /* badge-danger */
    background: var(--badge-danger-bg);
    color: var(--badge-danger-text);
    border: 1px solid var(--badge-danger-bg);
}

/* Table Header Styles */
.modern-table-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(148, 163, 184, 0.05));
    color: #475569;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(203, 213, 225, 0.5);
}

.history-section {
    background: var(--colors-canvas-parchment); /* Parchment */
    border-bottom: 1px solid rgba(203, 213, 225, 0.3);
}

/* Modal Glassmorphism */
.modal-backdrop {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.modal-content {
    background: var(--colors-canvas);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--colors-hairline);
    border-radius: 18px; /* rounded.lg */
    box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.1);
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: rgba(241, 245, 249, 0.5);
    border-radius: 3px;
}
::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #94a3b8, #64748b);
    border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #64748b, #475569);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .responsive-table {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .juz-container {
        min-width: 280px;
    }
}

/* Decorative Elements */
.decoration-blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.15;
    z-index: -1;
    pointer-events: none;
}
.blob-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    top: -100px;
    right: -100px;
}
.blob-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #22c55e, #14b8a6);
    bottom: -50px;
    left: -50px;
}
</style>
</head>
<body class="p-4 md:p-6 relative overflow-x-hidden">
<!-- Decorative Blobs -->
<div class="decoration-blob blob-1"></div>
<div class="decoration-blob blob-2"></div>

<div class="max-w-7xl mx-auto">
<!-- Modern Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div class="flex items-center gap-3" style="letter-spacing: -0.02em;">
        <div class="text-4xl text-indigo-500 drop-shadow-sm">﷽</div>
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 tracking-tight">MUROJA'AH</h1>
            <p class="text-xs text-gray-500 mt-0.5">MONITORING KUALITAS HAFALAN</p>
        </div>
    </div>
    <div class="text-left md:text-right glass-card px-4 py-3 inline-flex items-center gap-3">
        <div>
            <p class="text-sm font-medium text-gray-600">Minggu <?= $minggu_ini ?> · <?= $tahun_ini ?></p>
            <p class="text-xs text-gray-400"><?= date('d M Y') ?></p>
        </div>
        <i class="fas fa-calendar-week text-indigo-400 text-lg"></i>
    </div>
</div>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
<div class="mb-6 px-5 py-4 glass-card border-l-4 <?= $message_type === 'success' ? 'border-green-500 bg-green-50/50 text-green-700' : ($message_type === 'error' ? 'border-red-500 bg-red-50/50 text-red-700' : 'border-yellow-500 bg-yellow-50/50 text-yellow-700') ?>">
    <div class="flex items-center gap-3">
        <i class="fas fa-<?= $message_type === 'success' ? 'circle-check' : ($message_type === 'error' ? 'circle-exclamation' : 'circle-info') ?> text-lg"></i>
        <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Tombol Laporan -->
<?php if ($selected_peserta_id > 0): ?>
<div class="mb-7 flex justify-end">
    <a href="manzil-report.php?peserta_id=<?= $selected_peserta_id ?>"
        class="inline-flex items-center px-5 py-2.5 btn-glass-outline text-sm">
        <i class="fas fa-chart-simple mr-2 text-xs"></i> Laporan Mingguan
    </a>
</div>
<?php endif; ?>

<!-- Kelola Santri -->
<div class="glass-card mb-7 p-6">
    <div class="flex items-center gap-2 mb-5">
        <i class="fas fa-users text-blue-500 text-sm"></i>
        <span class="text-sm font-medium text-gray-700 tracking-wide">KELOLA SANTRI</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Tambah Santri -->
        <form method="POST" action="" class="flex flex-col sm:flex-row items-stretch gap-3">
            <input type="text" name="nama_baru"
                class="flex-1 modern-input text-sm"
                placeholder="Nama santri baru" required>
            <button type="submit" name="tambah_peserta"
                class="px-5 btn-glass-success text-sm flex items-center justify-center whitespace-nowrap">
                <i class="fas fa-plus mr-2 text-xs"></i> Tambah Santri
            </button>
        </form>
        <!-- Hapus Santri -->
        <form method="POST" action="" class="flex flex-col sm:flex-row items-stretch gap-3">
            <select name="peserta_id_hapus" class="flex-1 modern-select text-sm" required>
                <option value="">-- Pilih santri --</option>
                <?php foreach ($peserta_list as $peserta): ?>
                <option value="<?= $peserta['id'] ?>"><?= htmlspecialchars($peserta['nama']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="hapus_peserta"
                class="px-5 btn-glass-danger text-sm flex items-center justify-center whitespace-nowrap"
                onclick="return confirm('Yakin ingin menghapus santri ini?')">
                <i class="fas fa-trash-alt mr-2 text-xs"></i> Hapus Santri
            </button>
        </form>
    </div>
</div>

<!-- Form Input Muroja'ah -->
<div class="glass-card p-6">
    <div class="flex items-center gap-2 mb-6">
        <i class="fas fa-pen-to-square text-blue-500 text-sm"></i>
        <span class="text-sm font-medium text-gray-700 tracking-wide">INPUT MUROJA'AH · MINGGU INI</span>
    </div>
    <form method="POST" action="" id="form-murojaah">
        <!-- Select Santri -->
        <div class="mb-7 max-w-md">
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
            <table class="w-full text-sm border border-gray-200/60 rounded-xl overflow-hidden" style="border-radius: 18px;">
                <thead class="modern-table-header">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Hari</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-center font-semibold">Jumlah Juz</th>
                        <th class="px-4 py-3 text-left font-semibold">Detail Juz & Catatan</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/60">
                    <?php
                    $hari_list = ['senin'=>'Senin','selasa'=>'Selasa','rabu'=>'Rabu','kamis'=>'Kamis','jumat'=>'Jumat','sabtu'=>'Sabtu','minggu'=>'Minggu'];
                    $hari_map = ['senin'=>'monday','selasa'=>'tuesday','rabu'=>'wednesday','kamis'=>'thursday','jumat'=>'friday','sabtu'=>'saturday','minggu'=>'sunday'];
                    foreach ($hari_list as $key => $hari_nama):
                    $tanggal_hari = date('Y-m-d', strtotime($hari_map[$key] . ' this week'));
                    $tanggal_tampil = date('d M Y', strtotime($tanggal_hari));
                    ?>
                    <tr class="hover:bg-gray-50/50 transition-colors" style="border-radius: 18px;">
                        <td class="px-4 py-4 text-gray-600 font-medium whitespace-nowrap"><?= $hari_nama ?></td>
                        <td class="px-4 py-4 text-gray-400 text-xs whitespace-nowrap"><?= $tanggal_tampil ?></td>
                        <td class="px-4 py-4 text-center">
                            <select name="hari[<?= $key ?>][jumlah]"
                                class="jumlah-juz w-20 modern-select text-xs py-2 px-2"
                                data-hari="<?= $key ?>">
                                <?php for($j=0;$j<=5;$j++): ?>
                                <option value="<?= $j ?>"><?= $j ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td class="px-4 py-4">
                            <div class="juz-container min-w-[320px]" id="juz-container-<?= $key ?>">
                                <span class="text-gray-400 text-xs italic">Pilih jumlah juz terlebih dahulu</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="status-badge inline-flex items-center px-3 py-1.5 text-xs bg-gray-100/80 text-gray-500 border border-gray-200/60 rounded-full whitespace-nowrap">
                                <i class="fas fa-circle mr-1.5 text-[8px] text-gray-300"></i> Belum Diisi
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <hr class="my-7 border-gray-200/60">

        <!-- Tombol Simpan -->
        <div class="flex justify-center">
            <button type="submit" name="simpan_murojaah"
                class="px-10 py-3.5 btn-glass text-sm tracking-wide flex items-center gap-2">
                <i class="fas fa-check text-xs"></i> SIMPAN DATA
            </button>
        </div>
    </form>
</div>

<!-- ========== RIWAYAT - MODERN MODAL PREVIEW ========== -->
<div id="history-container" class="mt-7">
    <?php if ($selected_peserta_id > 0): ?>
    <div class="glass-card">
        <div class="px-6 py-5 history-section flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3" style="letter-spacing: -0.02em;">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                    <i class="fas fa-clock-rotate-left text-blue-500"></i>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-700 tracking-wide block">
                        RIWAYAT MUROJA'AH
                    </span>
                    <span class="text-xs text-gray-400"><?= htmlspecialchars($selected_peserta_nama) ?></span>
                </div>
            </div>
            <button onclick="openHistoryModal()"
                class="inline-flex items-center gap-2 px-5 py-2.5 btn-glass-outline text-sm transition-all duration-200 hover:shadow-md">
                <i class="fas fa-eye"></i> Lihat Riwayat
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card p-8 text-center text-gray-400 text-sm italic">
        <i class="fas fa-user text-2xl mb-3 opacity-50"></i><br>
        Pilih santri terlebih dahulu untuk melihat riwayat
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="mt-12 text-center pb-4">
    <p class="text-xs text-gray-400 tracking-wide" style="letter-spacing: -0.02em;">
        <i class="fas fa-heart mr-1 text-[10px] text-red-400"></i>
        Reqra by Han · <?= date('Y') ?>
    </p>
</div>
</div>

<!-- ========== MODAL HISTORY (MODERN POPUP) ========== -->
<div id="historyModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
    <div class="modal-content w-full max-w-5xl max-h-[90vh] overflow-hidden animate-fade-in">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-gray-200/60 flex items-center justify-between bg-gradient-to-r from-indigo-50/50 to-purple-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-book-open"></i>
                </div>
                <div style="letter-spacing: -0.02em;">
                    <h3 class="text-lg font-semibold text-gray-700">Riwayat Muroja'ah</h3>
                    <p class="text-xs text-gray-400" id="modalPesertaNama">Memuat data...</p>
                </div>
            </div>
            <button onclick="closeHistoryModal()" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]" id="historyModalContent">
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="fas fa-spinner fa-spin text-gray-400 text-xl"></i>
                    </div>
                    <p class="text-gray-500 text-sm">Memuat riwayat muroja'ah...</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200/60 bg-gray-50/50 flex justify-end gap-3">
            <button onclick="closeHistoryModal()" class="px-5 py-2.5 btn-glass-outline text-sm">
                <i class="fas fa-times mr-2"></i> Tutup
            </button>
            <button onclick="printHistory()" class="px-5 py-2.5 btn-glass text-sm">
                <i class="fas fa-print mr-2"></i> Cetak
            </button>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div id="editModal" class="fixed inset-0 modal-backdrop hidden z-50 flex items-center justify-center p-4">
    <div class="modal-content w-full max-w-md animate-fade-in">
        <div class="px-6 py-5 border-b border-gray-200/60 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-pencil-alt text-blue-500"></i> EDIT DATA
            </h3>

            <button onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <form method="POST" action="" id="form-edit" class="p-6">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="mb-5">
                <label class="block text-xs text-gray-500 mb-2 font-medium">Pilih Juz</label>
                <select id="edit_juz" name="edit_juz" class="w-full modern-select text-sm apple-select" required>
                    <?php for ($j = 1; $j <= 30; $j++): ?>
                    <option value="<?= $j ?>">Juz <?= $j ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs text-gray-500 mb-2 font-medium">Ketuk</label>
                    <input type="number" id="edit_ketuk" name="edit_ketuk" min="0" max="10" value="0" 
                        class="w-full modern-input text-sm text-center apple-input" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-2 font-medium">Tuntun</label>
                    <input type="number" id="edit_tuntun" name="edit_tuntun" min="0" max="10" value="0" 
                        class="w-full modern-input text-sm text-center apple-input">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-xs text-gray-500 mb-2 font-medium">Catatan Surat</label>
                <input type="text" id="edit_catatan" name="edit_catatan"
                    class="w-full modern-input text-sm" placeholder="Contoh: Al-Mulk, Al-Qalam">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeEditModal()"
                    class="flex-1 px-4 py-3 btn-glass-outline text-sm">
                    Batal
                </button>
                <button type="submit" name="edit_data"
                    class="flex-1 px-4 py-3 btn-glass text-sm flex items-center justify-center"> 
                    <i class="fas fa-check mr-2 text-xs"></i> Simpan
                </button>
            </div>
        </form>
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
            const statusCell = this.closest('tr').querySelector('.status-badge');
            
            if (jumlah === 0) {
                container.innerHTML = '<span class="text-gray-400 text-xs italic">pilih jumlah juz terlebih dahulu</span>';
                if (statusCell) {
                    statusCell.innerHTML = '<i class="fas fa-circle mr-1.5 text-[8px] text-gray-300"></i> Belum Diisi';
                    statusCell.className = 'status-badge inline-flex items-center px-3 py-1.5 text-xs bg-gray-100/80 text-gray-500 border border-gray-200/60 rounded-full whitespace-nowrap';
                }
                return;
            }
            
            let html = `<div class="space-y-2.5 max-w-lg">`;
            for (let i = 1; i <= jumlah; i++) {
                html += `
                <div class="flex flex-wrap items-center gap-2 p-3 bg-white/60 border border-gray-200/60 rounded-lg backdrop-blur-sm">
                    <span class="text-[11px] text-blue-600 font-medium min-w-[32px] bg-blue-50 px-2 py-1 rounded-lg">J${i}</span>
                    <input list="juzList-${hari}-${i}" name="hari[${hari}][juz_${i}]" 
                        class="flex-1 min-w-[90px] modern-input text-xs py-2 px-3 apple-input" 
                        placeholder="No. Juz" required>
                    <datalist id="juzList-${hari}-${i}">
                        ${generateJuzOptions()}
                    </datalist>
                    <input type="number" name="hari[${hari}][ketuk_${i}]" min="0" max="10" value="0" placeholder="K"
                        class="w-14 modern-input text-xs py-2 px-2 text-center apple-input" title="Jumlah Ketuk">
                    <input type="number" name="hari[${hari}][tuntun_${i}]" min="0" max="10" value="0" placeholder="T"
                        class="w-14 modern-input text-xs py-2 px-2 text-center apple-input" title="Jumlah Tuntun">
                    <input type="text" name="hari[${hari}][catatan_${i}]" placeholder="Catatan"
                        class="flex-1 min-w-[120px] modern-input text-xs py-2 px-3 apple-input">
                </div>`;
            }
            html += `</div>`;
            container.innerHTML = html;
            
            // Update status preview
            updateStatusPreview(hari, jumlah, statusCell);
        });
    });

    function generateJuzOptions() {
        let options = '';
        for (let j = 1; j <= 30; j++) options += `<option value="${j}">Juz ${j}</option>`;
        return options;
    }

    function updateStatusPreview(hari, jumlah, statusCell) {
        if (!statusCell) return;
        
        let totalKetuk = 0, totalTuntun = 0, count = 0;
        for (let i = 1; i <= jumlah; i++) {
            const ketukInput = document.querySelector(`input[name="hari[${hari}][ketuk_${i}]"]`);
            const tuntunInput = document.querySelector(`input[name="hari[${hari}][tuntun_${i}]"]`);
            if (ketukInput) {
                const ketuk = parseInt(ketukInput.value || 0);
                const tuntun = parseInt(tuntunInput?.value || 0);
                if (ketuk > 0 || tuntun > 0) {
                    totalKetuk += ketuk;
                    totalTuntun += tuntun;
                    count++;
                }
            }
        }
        
        if (count > 0) {
            const avgKetuk = totalKetuk / count;
            const avgTuntun = totalTuntun / count;
            
            // LOGIKA KUALITAS BARU
            let ketukScore = 0;
            if (avgKetuk > 4) ketukScore = 2;
            else if (avgKetuk > 3) ketukScore = 1;
            
            let tuntunScore = 0;
            if (avgTuntun > 3) tuntunScore = 2;
            else if (avgTuntun > 2) tuntunScore = 1;
            
            const finalScore = Math.max(ketukScore, tuntunScore);
            
            let statusText = 'Lancar', statusClass = 'badge-lancar', iconClass = 'fa-check';
            if (finalScore === 2) {
                statusText = 'Tidak Lancar';
                statusClass = 'badge-tidak';
                iconClass = 'fa-xmark';
            } else if (finalScore === 1) {
                statusText = 'Cukup';
                statusClass = 'badge-cukup';
                iconClass = 'fa-minus';
            }
            
            statusCell.innerHTML = `<i class="fas ${iconClass} mr-1.5 text-[10px]"></i>${statusText}`;
            statusCell.className = `status-badge inline-flex items-center px-3 py-1.5 text-xs border ${statusClass} rounded-full whitespace-nowrap`;
        }
    }

    // Real-time status update on input
    document.addEventListener('input', function(e) {
        if (e.target.name && (e.target.name.includes('[ketuk_') || e.target.name.includes('[tuntun_'))) {
            const hariMatch = e.target.name.match(/hari\[([^\]]+)\]/);
            if (hariMatch) {
                const hari = hariMatch[1];
                const jumlahSelect = document.querySelector(`.jumlah-juz[data-hari="${hari}"]`);
                if (jumlahSelect) {
                    const jumlah = parseInt(jumlahSelect.value);
                    const statusCell = jumlahSelect.closest('tr').querySelector('.status-badge');
                    updateStatusPreview(hari, jumlah, statusCell);
                }
            }
        }
    });

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
                <div class="glass-card p-8 text-center text-gray-400 text-sm italic">
                    <i class="fas fa-user text-2xl mb-3 opacity-50"></i><br>
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
                container.innerHTML = '<div class="glass-card p-6 text-center text-gray-400 text-sm italic">Gagal memuat data</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="glass-card p-6 text-center text-red-400 text-sm italic">Terjadi kesalahan</div>';
        })
        .finally(() => {
            container.classList.remove('loading');
        });
    }

    // ========== MODAL HISTORY FUNCTIONS ==========
    window.openHistoryModal = function() {
        const modal = document.getElementById('historyModal');
        const content = document.getElementById('historyModalContent');
        const pesertaId = document.getElementById('peserta_id')?.value;
        
        if (!pesertaId) {
            alert('Pilih santri terlebih dahulu!');
            return;
        }
        
        modal.classList.remove('hidden');
        document.getElementById('modalPesertaNama').textContent = 'Memuat data...';
        content.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-gray-400 text-xl"></i>
                </div>
                <p class="text-gray-500 text-sm">Memuat riwayat muroja'ah...</p>
            </div>
        </div>`;
        
        // Load history content
        fetch('<?= $_SERVER['PHP_SELF'] ?>?action=get_history&peserta_id=' + pesertaId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalPesertaNama').textContent = data.nama_peserta || 'Santri';
                content.innerHTML = generateHistoryModalContent(data.data);
            } else {
                content.innerHTML = '<div class="text-center py-8 text-gray-400">Gagal memuat data riwayat</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="text-center py-8 text-red-400">Terjadi kesalahan</div>';
        });
    };

    function generateHistoryModalContent(historyData) {
        if (!historyData || historyData.length === 0) {
            return `
            <div class="text-center py-12">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                </div>
                <p class="text-gray-500">Belum ada data murojaah minggu ini</p>
                <p class="text-gray-400 text-xs mt-1">Silakan input data terlebih dahulu</p>
            </div>`;
        }
        
        let html = `
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="modern-table-header rounded-t-xl">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold rounded-tl-xl">Hari</th>
                        <th class="px-4 py-3 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-3 text-left font-semibold">Juz</th>
                        <th class="px-4 py-3 text-center font-semibold">Jumlah Juz</th>
                        <th class="px-4 py-3 text-center font-semibold">Total Ketuk</th>
                        <th class="px-4 py-3 text-center font-semibold">Total Tuntun</th>
                        <th class="px-4 py-3 text-center font-semibold">Kualitas</th>
                        <th class="px-4 py-3 text-left font-semibold">Catatan</th>
                        <th class="px-4 py-3 text-center font-semibold rounded-tr-xl">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/60">`;
        
        historyData.forEach((data, index) => {
            const hariPendek = data.hari.substring(0, 3);
            const tanggalFormat = new Date(data.tanggal).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit' });
            
            html += `
            <tr class="hover:bg-gray-50/50 transition-colors animate-fade-in" style="animation-delay: ${index * 50}ms">
                <td class="px-4 py-4 text-gray-700 font-medium">${hariPendek}</td>
                <td class="px-4 py-4 text-gray-400 text-xs">${tanggalFormat}</td>
                <td class="px-4 py-4">
                    <span class="font-mono text-indigo-600 text-xs bg-indigo-50 px-2 py-1 rounded-lg">${data.juz_list}</span>
                </td>
                <td class="px-4 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-medium bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-700 rounded-full">
                        ${data.total_juz}
                    </span>
                </td>
                <td class="px-4 py-4 text-center text-emerald-600 font-semibold">${data.total_ketuk}</td>
                <td class="px-4 py-4 text-center text-amber-600 font-semibold">${data.total_tuntun}</td>
                <td class="px-4 py-4 text-center">
                    <span class="inline-flex items-center px-3 py-1.5 text-[11px] border rounded-full ${data.badge_class}">
                        <i class="fas fa-${data.icon_class} mr-1.5 text-[10px]"></i>
                        ${data.kualitas}
                    </span>
                </td>
                <td class="px-4 py-4 text-gray-400 italic text-xs max-w-[180px] truncate" title="${data.catatan}">
                    ${data.catatan || '-'}
                </td>
                <td class="px-4 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="openEditModal(${data.sample_id})"
                            class="w-8 h-8 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 flex items-center justify-center transition-colors"
                            title="Edit">
                            <i class="fas fa-pencil-alt text-xs"></i>
                        </button>
                        <form method="POST" action="" class="inline" onsubmit="return confirm('Yakin ingin menghapus semua data tanggal ${data.tanggal}?')">
                            <input type="hidden" name="delete_date" value="${data.tanggal}">
                            <input type="hidden" name="peserta_id" value="<?= $selected_peserta_id ?>">
                            <button type="submit" name="hapus_per_tanggal" 
                                class="w-8 h-8 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center transition-colors"
                                title="Hapus">
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>`;
        });
        
        html += `
                </tbody>
            </table>
            <p class="text-[11px] text-gray-400 mt-4 italic text-center">
                <i class="fas fa-info-circle mr-1"></i>
                Data digabung per tanggal. Jumlah Juz = sesi murojaah hari itu. Ketuk & Tuntun adalah total kumulatif.
            </p>
        </div>`;
        
        return html;
    }

    window.closeHistoryModal = function() {
        document.getElementById('historyModal').classList.add('hidden');
    };

    window.printHistory = function() {
        window.print();
    };

    // Close modal on backdrop click
    document.getElementById('historyModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeHistoryModal();
    });

    // ========== MODAL EDIT FUNCTIONS ==========
    window.openEditModal = function(id) {
        closeHistoryModal(); // Close history modal first
        document.getElementById('edit_id').value = id;
        document.getElementById('editModal').classList.remove('hidden');
        // Reset fields
        document.getElementById('edit_juz').value = '';
        document.getElementById('edit_ketuk').value = '0';
        document.getElementById('edit_tuntun').value = '0';
        document.getElementById('edit_catatan').value = '';
    };

    window.closeEditModal = function() {
        document.getElementById('editModal').classList.add('hidden');
    };

    document.getElementById('editModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
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
            <div class="glass-card p-8 text-center text-gray-400 text-sm italic">
                <i class="fas fa-user text-2xl mb-3 opacity-50"></i><br>
                Pilih santri terlebih dahulu untuk melihat riwayat
            </div>`;
        }
    });

    // Initialize with current participant if any
    <?php if ($selected_peserta_id > 0): ?>
    if (document.getElementById('peserta_id')) {
        document.getElementById('peserta_id').value = '<?= $selected_peserta_id ?>';
    }
    <?php endif; ?>
});
</script>
</body>
</html>