<?php
session_start();

// Panggil koneksi database bawaan dari repository
require_once 'config/connection.php';
if (!isset($conn) || $conn->connect_error) {
    die("Koneksi gagal: Pastikan file config/connection.php sudah benar.");
}

$message = '';
$message_type = '';

// Proses Login
if (isset($_POST['login'])) {
    $uname = $conn->real_escape_string($_POST['username']);
    $upass = $conn->real_escape_string($_POST['password']);
    
    $stmt = $conn->prepare("SELECT * FROM lms_users WHERE username=? AND password=?");
    $stmt->bind_param("ss", $uname, $upass);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $_SESSION['lms_user_id'] = $row['id'];
        $_SESSION['lms_role'] = $row['role'];
        $_SESSION['lms_nama'] = $row['nama_lengkap'];
        header("Location: ?page=dashboard");
        exit;
    } else {
        $message = "Akses ditolak. Username atau password salah.";
        $message_type = "error";
    }
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?page=login");
    exit;
}

// Proses Kuis
if (isset($_POST['submit_kuis'])) {
    $materi_id = (int)$_POST['materi_id'];
    $user_id = $_SESSION['lms_user_id'];
    $jawaban = $_POST['jawaban'] ?? [];
    
    $q_soal = $conn->query("SELECT id, jawaban_benar FROM lms_kuis WHERE materi_id = $materi_id");
    $total_soal = $q_soal->num_rows;
    $benar = 0;
    
    while ($soal = $q_soal->fetch_assoc()) {
        if (isset($jawaban[$soal['id']]) && $jawaban[$soal['id']] == $soal['jawaban_benar']) {
            $benar++;
        }
    }
    
    $skor = $total_soal > 0 ? round(($benar / $total_soal) * 100) : 0;
    $conn->query("INSERT INTO lms_nilai (user_id, materi_id, skor) VALUES ($user_id, $materi_id, $skor)");
    $message = "Evaluasi selesai. Skor Akhir Anda: $skor";
    $message_type = "success";
}

$page = $_GET['page'] ?? 'dashboard';
$is_logged_in = isset($_SESSION['lms_user_id']);
$role = $_SESSION['lms_role'] ?? null;

if (!$is_logged_in && $page !== 'login') {
    $page = 'login';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Manajemen Pendidikan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .hide-scroll::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="text-slate-800 flex flex-col min-h-screen">

    <?php if ($page === 'login'): ?>
    <div class="flex-1 flex items-center justify-center bg-gradient-to-br from-slate-800 to-blue-900 p-4">
        <div class="glass-panel w-full max-w-md p-8 rounded-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-blue-500"></div>
            <div class="text-center mb-8">
                <i class="fas fa-layer-group text-4xl text-blue-600 mb-3"></i>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Portal LMS</h2>
                <p class="text-sm text-slate-500 mt-1">Silakan masuk untuk mengakses materi</p>
            </div>

            <?php if($message): ?>
                <div class="mb-4 p-3 rounded-lg text-sm font-medium <?= $message_type == 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" class="w-full px-4 py-2 bg-white/50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2 bg-white/50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                </div>
                <button type="submit" name="login" class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg shadow-md transition">Masuk</button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <header class="glass-panel fixed w-full top-0 z-50 h-16 flex items-center justify-between px-6 border-b border-slate-200">
        <div class="flex items-center gap-3">
            <i class="fas fa-layer-group text-xl text-blue-600"></i>
            <span class="font-bold text-lg text-slate-800 tracking-tight">LMS Portal</span>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-bold text-slate-800 leading-none"><?= $_SESSION['lms_nama'] ?></p>
                <p class="text-xs text-slate-500 capitalize mt-1"><?= $role ?></p>
            </div>
            <a href="?logout=1" class="w-8 h-8 flex items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-red-50 hover:text-red-600 transition">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <div class="flex flex-1 pt-16">
        <aside class="w-64 glass-panel hidden md:flex flex-col fixed h-[calc(100vh-4rem)] border-r border-slate-200">
            <div class="p-4 flex-1">
                <nav class="space-y-1">
                    <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page == 'dashboard' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="fas fa-home w-5"></i> Dashboard
                    </a>
                    <a href="?page=materi" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page == 'materi' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="fas fa-book-open w-5"></i> Ruang Materi
                    </a>
                    <a href="?page=kuis" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page == 'kuis' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="fas fa-tasks w-5"></i> Kuis Evaluasi
                    </a>
                    <a href="?page=nilai" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page == 'nilai' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <i class="fas fa-chart-line w-5"></i> Rekap Nilai
                    </a>
                </nav>
            </div>
        </aside>

        <main class="flex-1 md:ml-64 p-4 lg:p-8">
            <?php if($message): ?>
                <div class="mb-6 p-4 rounded-lg shadow-sm text-sm font-semibold <?= $message_type == 'error' ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php
            // HALAMAN DASHBOARD
            if ($page == 'dashboard'): 
                $banners = $conn->query("SELECT * FROM lms_banners WHERE aktif=1 ORDER BY id DESC");
            ?>
                <?php if($banners->num_rows > 0): ?>
                <div class="mb-6 flex gap-4 overflow-x-auto hide-scroll snap-x">
                    <?php while($b = $banners->fetch_assoc()): ?>
                    <div class="min-w-full md:min-w-[70%] snap-center shrink-0">
                        <div class="relative h-48 md:h-56 rounded-xl overflow-hidden shadow-lg">
                            <img src="<?= $b['image_url'] ?>" alt="Banner" class="absolute inset-0 w-full h-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                            <div class="absolute bottom-0 left-0 p-6">
                                <h3 class="text-xl md:text-2xl font-bold text-white"><?= $b['judul'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="glass-panel p-5 rounded-xl flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-xl"><i class="fas fa-video"></i></div>
                        <div><p class="text-sm text-slate-500 font-semibold">Total Materi</p><p class="text-2xl font-bold text-slate-800"><?= $conn->query("SELECT id FROM lms_materi")->num_rows ?></p></div>
                    </div>
                    <div class="glass-panel p-5 rounded-xl flex items-center gap-4">
                        <div class="w-12 h-12 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl"><i class="fas fa-users"></i></div>
                        <div><p class="text-sm text-slate-500 font-semibold">Peserta</p><p class="text-2xl font-bold text-slate-800"><?= $conn->query("SELECT id FROM lms_users WHERE role='peserta'")->num_rows ?></p></div>
                    </div>
                </div>

            <?php
            // HALAMAN MATERI
            elseif ($page == 'materi'): 
                $materi_list = $conn->query("SELECT * FROM lms_materi");
            ?>
                <h2 class="text-2xl font-bold mb-6 text-slate-800">Ruang Materi</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php while($m = $materi_list->fetch_assoc()): ?>
                    <div class="glass-panel rounded-xl overflow-hidden">
                        <div class="bg-black">
                            <video controls class="w-full h-56 object-cover"><source src="<?= $m['video_url'] ?>" type="video/mp4"></video>
                        </div>
                        <div class="p-5">
                            <h3 class="font-bold text-lg text-slate-800 mb-2"><?= $m['judul'] ?></h3>
                            <p class="text-slate-600 text-sm mb-4"><?= $m['deskripsi'] ?></p>
                            <a href="?page=kuis&materi_id=<?= $m['id'] ?>" class="text-sm font-bold text-blue-600 hover:text-blue-800"><i class="fas fa-pen-alt mr-1"></i> Kerjakan Kuis</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

            <?php
            // HALAMAN KUIS
            elseif ($page == 'kuis'): 
                $materi_id = $_GET['materi_id'] ?? 1; 
                $kuis = $conn->query("SELECT * FROM lms_kuis WHERE materi_id=$materi_id");
            ?>
                <h2 class="text-2xl font-bold mb-6 text-slate-800">Evaluasi Pemahaman</h2>
                <?php if($kuis->num_rows > 0): ?>
                <div class="glass-panel rounded-xl p-6 md:p-8">
                    <form method="POST">
                        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
                        <?php $no=1; while($k = $kuis->fetch_assoc()): ?>
                        <div class="mb-6 border-b border-slate-100 pb-6 last:border-0">
                            <p class="font-bold text-slate-800 mb-3"><?= $no++ ?>. <?= $k['pertanyaan'] ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach(['A'=>$k['opsi_a'], 'B'=>$k['opsi_b'], 'C'=>$k['opsi_c'], 'D'=>$k['opsi_d']] as $key => $opsi): ?>
                                <label class="flex items-center p-3 border border-slate-200 bg-white/50 rounded-lg cursor-pointer hover:bg-blue-50 transition">
                                    <input type="radio" name="jawaban[<?= $k['id'] ?>]" value="<?= $key ?>" required class="w-4 h-4 text-blue-600">
                                    <span class="ml-2 text-slate-700 text-sm font-medium"><?= $key ?>. <?= $opsi ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <button type="submit" name="submit_kuis" class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-6 py-2.5 rounded-lg transition shadow-md w-full md:w-auto">Kirim Jawaban</button>
                    </form>
                </div>
                <?php else: ?>
                    <div class="glass-panel p-8 rounded-xl text-center text-slate-500">Soal kuis belum tersedia.</div>
                <?php endif; ?>

            <?php
            // HALAMAN NILAI
            elseif ($page == 'nilai'): 
                $kondisi = $role == 'admin' ? "" : "WHERE n.user_id = ".$_SESSION['lms_user_id'];
                $nilai_list = $conn->query("SELECT n.skor, n.tanggal, u.nama_lengkap, m.judul FROM lms_nilai n JOIN lms_users u ON n.user_id = u.id JOIN lms_materi m ON n.materi_id = m.id $kondisi ORDER BY n.id DESC");
            ?>
                <h2 class="text-2xl font-bold mb-6 text-slate-800">Laporan Nilai</h2>
                <div class="glass-panel rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-xs uppercase text-slate-500 font-bold border-b border-slate-200">
                                    <th class="p-4">Tanggal</th>
                                    <th class="p-4">Peserta</th>
                                    <th class="p-4">Materi</th>
                                    <th class="p-4 text-center">Skor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/50">
                                <?php while($n = $nilai_list->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-4 text-sm text-slate-600"><?= date('d M Y', strtotime($n['tanggal'])) ?></td>
                                    <td class="p-4 text-sm font-bold text-slate-800"><?= $n['nama_lengkap'] ?></td>
                                    <td class="p-4 text-sm text-slate-600"><?= $n['judul'] ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-3 py-1 rounded-md text-xs font-bold <?= $n['skor'] >= 75 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $n['skor'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; if($nilai_list->num_rows == 0): ?>
                                <tr><td colspan="4" class="p-6 text-center text-slate-500 text-sm font-medium">Data laporan kosong.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <nav class="md:hidden glass-panel fixed bottom-0 w-full flex justify-around p-3 z-50 border-t border-slate-200">
        <a href="?page=dashboard" class="flex flex-col items-center text-xs <?= $page=='dashboard'?'text-blue-600 font-bold':'text-slate-500' ?>"><i class="fas fa-home text-lg mb-1"></i> Home</a>
        <a href="?page=materi" class="flex flex-col items-center text-xs <?= $page=='materi'?'text-blue-600 font-bold':'text-slate-500' ?>"><i class="fas fa-book-open text-lg mb-1"></i> Materi</a>
        <a href="?page=kuis" class="flex flex-col items-center text-xs <?= $page=='kuis'?'text-blue-600 font-bold':'text-slate-500' ?>"><i class="fas fa-tasks text-lg mb-1"></i> Kuis</a>
        <a href="?page=nilai" class="flex flex-col items-center text-xs <?= $page=='nilai'?'text-blue-600 font-bold':'text-slate-500' ?>"><i class="fas fa-chart-line text-lg mb-1"></i> Nilai</a>
    </nav>
    <?php endif; ?>

</body>
</html>