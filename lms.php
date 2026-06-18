<?php
session_start();
require_once 'config/connection.php'; // Menggunakan koneksi dari repository Anda

// Inisialisasi Pesan
$message = '';
$message_type = '';

// 1. PROSES LOGIN
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
        $message = "Login gagal: Username/Password salah.";
        $message_type = "error";
    }
}

// 2. PROSES KUIS
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
    $message = "Evaluasi selesai! Skor Anda: $skor";
    $message_type = "success";
}

$page = $_GET['page'] ?? 'dashboard';
$is_logged_in = isset($_SESSION['lms_user_id']);
$role = $_SESSION['lms_role'] ?? null;

if (!$is_logged_in && $page !== 'login') { $page = 'login'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>LMS Pendidikan Profesional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-900">

<?php if ($page == 'login'): ?>
    <div class="min-h-screen flex items-center justify-center bg-slate-900 text-slate-900">
        <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-sm">
            <h2 class="text-2xl font-bold mb-6 text-center">Login LMS</h2>
            <?php if($message): ?><p class="text-red-500 text-sm mb-4"><?= $message ?></p><?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" class="w-full p-3 border mb-3 rounded-lg" required>
                <input type="password" name="password" placeholder="Password" class="w-full p-3 border mb-4 rounded-lg" required>
                <button name="login" class="w-full bg-blue-600 text-white p-3 rounded-lg font-bold">MASUK</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Layout Header & Sidebar -->
    <header class="fixed w-full h-16 bg-white border-b flex items-center justify-between px-6 z-40">
        <span class="font-bold text-xl text-blue-700">LMS PRO</span>
        <a href="?logout=1" class="text-red-600 font-bold text-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </header>

    <div class="flex pt-16">
        <aside class="w-64 fixed h-full bg-white border-r p-6 hidden md:block">
            <nav class="space-y-4">
                <a href="?page=dashboard" class="block font-medium text-slate-600"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="?page=materi" class="block font-medium text-slate-600"><i class="fas fa-video mr-2"></i> Materi Video</a>
                <a href="?page=kuis" class="block font-medium text-slate-600"><i class="fas fa-tasks mr-2"></i> Kuis</a>
                <a href="?page=nilai" class="block font-medium text-slate-600"><i class="fas fa-chart-line mr-2"></i> Nilai</a>
            </nav>
        </aside>

        <main class="flex-1 md:ml-64 p-8">
            <?php if ($page == 'dashboard'): 
                $b = $conn->query("SELECT * FROM lms_banners WHERE aktif=1")->fetch_assoc();
            ?>
                <div class="bg-blue-800 text-white p-10 rounded-2xl mb-8">
                    <h1 class="text-3xl font-bold">Selamat Datang, <?= $_SESSION['lms_nama'] ?>!</h1>
                    <p class="opacity-80">Siap untuk melanjutkan materi Manajemen Pendidikan Anda hari ini?</p>
                </div>
            <?php elseif ($page == 'materi'):
                $m = $conn->query("SELECT * FROM lms_materi")->fetch_assoc();
            ?>
                <div class="bg-white p-6 rounded-xl shadow">
                    <h2 class="text-xl font-bold mb-4"><?= $m['judul'] ?></h2>
                    <video controls class="w-full rounded-lg bg-black mb-4"><source src="<?= $m['video_url'] ?>"></video>
                    <p><?= $m['deskripsi'] ?></p>
                </div>
            <?php elseif ($page == 'nilai'): 
                $sql = ($role=='admin') ? "SELECT * FROM lms_nilai JOIN lms_users ON lms_nilai.user_id = lms_users.id" : "SELECT * FROM lms_nilai JOIN lms_users ON lms_nilai.user_id = lms_users.id WHERE user_id=".$_SESSION['lms_user_id'];
                $data = $conn->query($sql);
            ?>
                <table class="w-full bg-white rounded-lg shadow p-4">
                    <tr class="bg-slate-100"><th class="p-3">Peserta</th><th class="p-3">Skor</th></tr>
                    <?php while($row = $data->fetch_assoc()): ?>
                    <tr><td class="p-3"><?= $row['nama_lengkap'] ?></td><td class="p-3 text-center"><?= $row['skor'] ?></td></tr>
                    <?php endwhile; ?>
                </table>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>
</body>
</html>