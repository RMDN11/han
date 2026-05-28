<?php
// Default display settings
$display_settings = [
    'month' => 12,
    'year' => 2025,
    'display_text' => 'Top Performers - December 2025',
    'last_updated' => '2025-12-18 10:00:00',
    'updated_by' => 'admin'
];

<?php
// File: config/display_settings.php
// Pastikan path ini sesuai dengan struktur folder Anda

function save_display_settings($month, $year, $updated_by = 'admin') {
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $settings = [
        'month' => (int)$month,
        'year' => (int)$year,
        'display_text' => 'Top Performers - ' . $bulan_indonesia[(int)$month] . ' ' . $year,
        'last_updated' => date('Y-m-d H:i:s'),
        'updated_by' => $updated_by
    ];
    
    // Simpan ke file JSON di folder yang sama
    $settings_file = dirname(__FILE__) . '/display_settings.json';
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    
    return $settings;
}

function load_display_settings() {
    $settings_file = dirname(__FILE__) . '/display_settings.json';
    
    $default = [
        'month' => date('n'),
        'year' => date('Y'),
        'display_text' => 'Top Performers - ' . date('F Y'),
        'last_updated' => date('Y-m-d H:i:s'),
        'updated_by' => 'system'
    ];
    
    if (file_exists($settings_file)) {
        $saved = json_decode(file_get_contents($settings_file), true);
        if (is_array($saved) && !empty($saved)) {
            return array_merge($default, $saved);
        }
    }
    
    // Buat file default jika belum ada
    save_display_settings($default['month'], $default['year'], 'system');
    return $default;
}
?>