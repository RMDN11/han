<?php
/**
 * LMS - Materi Umum Manajemen Pendidikan Profesional
 * Single File PHP Application with SQLite Database
 */

// ============================================
// DATABASE INITIALIZATION
// ============================================
$dbFile = __DIR__ . '/lms_database.sqlite';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Create tables if not exists
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        email TEXT,
        role TEXT DEFAULT 'participant',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        video_url TEXT,
        thumbnail TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS quizzes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER,
        question TEXT NOT NULL,
        option_a TEXT NOT NULL,
        option_b TEXT NOT NULL,
        option_c TEXT NOT NULL,
        option_d TEXT NOT NULL,
        correct_answer TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS banners (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        image_url TEXT,
        link TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        quiz_id INTEGER,
        score INTEGER DEFAULT 0,
        total_questions INTEGER DEFAULT 0,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    );
");

// Seed default data if empty
$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount == 0) {
    // Admin user
    $db->exec("INSERT INTO users (username, password, name, email, role) VALUES 
        ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Administrator', 'admin@lms.com', 'admin')");
    // Participant user
    $db->exec("INSERT INTO users (username, password, name, email, role) VALUES 
        ('peserta', '" . password_hash('peserta123', PASSWORD_DEFAULT) . "', 'Peserta Didik', 'peserta@lms.com', 'participant')");

    // Sample courses
    $db->exec("INSERT INTO courses (title, description, video_url, thumbnail) VALUES 
        ('Pengantar Manajemen Pendidikan', 'Materi dasar tentang konsep manajemen pendidikan profesional', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'),
        ('Kepemimpinan dalam Pendidikan', 'Strategi kepemimpinan efektif di lingkungan pendidikan', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg'),
        ('Evaluasi Pembelajaran', 'Teknik evaluasi dan asesmen dalam proses pembelajaran', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg')");

    // Sample quizzes
    $db->exec("INSERT INTO quizzes (course_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES 
        (1, 'Apa yang dimaksud dengan manajemen pendidikan?', 'Proses pengelolaan sumber daya pendidikan', 'Proses pembelajaran di kelas', 'Evaluasi hasil belajar', 'Pengembangan kurikulum', 'A'),
        (1, 'Siapa yang bertanggung jawab utama dalam manajemen pendidikan?', 'Kepala Sekolah', 'Guru', 'Orang Tua', 'Siswa', 'A'),
        (2, 'Apa ciri utama kepemimpinan transformasional?', 'Memberikan inspirasi dan motivasi', 'Mempertahankan status quo', 'Fokus pada aturan', 'Menghindari perubahan', 'A'),
        (2, 'Kepemimpinan dalam pendidikan sangat penting untuk...', 'Meningkatkan kualitas pembelajaran', 'Mengurangi biaya sekolah', 'Memperbanyak siswa', 'Membangun gedung', 'A'),
        (3, 'Apa tujuan utama evaluasi pembelajaran?', 'Mengukur pencapaian belajar', 'Memberi nilai siswa', 'Membandingkan sekolah', 'Memenuhi administrasi', 'A'),
        (3, 'Teknik evaluasi yang melibatkan siswa dalam menilai dirinya sendiri disebut...', 'Self-assessment', 'Peer-assessment', 'Teacher-assessment', 'Portfolio', 'A')");

    // Sample banners
    $db->exec("INSERT INTO banners (title, image_url, link, is_active) VALUES 
        ('Promo Pendaftaran', 'https://via.placeholder.com/1200x400/4F46E5/FFFFFF?text=Pendaftaran+Dibuka', '#', 1),
        ('Webinar Gratis', 'https://via.placeholder.com/1200x400/7C3AED/FFFFFF?text=Webinar+Manajemen+Pendidikan', '#', 1),
        ('E-book Terbaru', 'https://via.placeholder.com/1200x400/2563EB/FFFFFF?text=E-book+Manajemen+Professional', '#', 1)");
}

// ============================================
// SESSION & AUTHENTICATION
// ============================================
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isParticipant() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'participant';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ============================================
// REQUEST HANDLING
// ============================================
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        redirect('?page=dashboard');
    } else {
        $loginError = 'Username atau password salah!';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('?page=login');
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'participant';

    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, $role]);
        $registerSuccess = 'Registrasi berhasil! Silakan login.';
    } catch (PDOException $e) {
        $registerError = 'Username sudah digunakan!';
    }
}

// ============================================
// ADMIN ACTIONS
// ============================================
if (isAdmin()) {
    // Add course
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $video_url = $_POST['video_url'] ?? '';
        $thumbnail = $_POST['thumbnail'] ?? '';
        if ($title && $description) {
            $stmt = $db->prepare("INSERT INTO courses (title, description, video_url, thumbnail) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $video_url, $thumbnail]);
            $successMsg = 'Kursus berhasil ditambahkan!';
        }
    }

    // Delete course
    if (isset($_GET['delete_course'])) {
        $id = (int)$_GET['delete_course'];
        $db->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
        redirect('?page=courses');
    }

    // Add quiz
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quiz'])) {
        $course_id = $_POST['course_id'] ?? 0;
        $question = $_POST['question'] ?? '';
        $option_a = $_POST['option_a'] ?? '';
        $option_b = $_POST['option_b'] ?? '';
        $option_c = $_POST['option_c'] ?? '';
        $option_d = $_POST['option_d'] ?? '';
        $correct_answer = $_POST['correct_answer'] ?? '';
        if ($question && $course_id) {
            $stmt = $db->prepare("INSERT INTO quizzes (course_id, question, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer]);
            $successMsg = 'Pertanyaan berhasil ditambahkan!';
        }
    }

    // Delete quiz
    if (isset($_GET['delete_quiz'])) {
        $id = (int)$_GET['delete_quiz'];
        $db->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$id]);
        redirect('?page=quizzes');
    }

    // Add banner
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
        $title = $_POST['title'] ?? '';
        $image_url = $_POST['image_url'] ?? '';
        $link = $_POST['link'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($title) {
            $stmt = $db->prepare("INSERT INTO banners (title, image_url, link, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $image_url, $link, $is_active]);
            $successMsg = 'Banner berhasil ditambahkan!';
        }
    }

    // Delete banner
    if (isset($_GET['delete_banner'])) {
        $id = (int)$_GET['delete_banner'];
        $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
        redirect('?page=banners');
    }

    // Toggle banner
    if (isset($_GET['toggle_banner'])) {
        $id = (int)$_GET['toggle_banner'];
        $banner = $db->prepare("SELECT is_active FROM banners WHERE id = ?")->execute([$id]);
        // Fix: properly fetch
        $stmt = $db->prepare("SELECT is_active FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        if ($banner) {
            $new = $banner['is_active'] ? 0 : 1;
            $db->prepare("UPDATE banners SET is_active = ? WHERE id = ?")->execute([$new, $id]);
        }
        redirect('?page=banners');
    }

    // Manage users - delete
    if (isset($_GET['delete_user'])) {
        $id = (int)$_GET['delete_user'];
        if ($id != $_SESSION['user_id']) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
        redirect('?page=users');
    }

    // Update user role
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_role'])) {
        $user_id = $_POST['user_id'] ?? 0;
        $role = $_POST['role'] ?? 'participant';
        if ($user_id != $_SESSION['user_id']) {
            $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $user_id]);
        }
        redirect('?page=users');
    }
}

// ============================================
// PARTICIPANT ACTIONS
// ============================================
if (isParticipant()) {
    // Submit quiz
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
        $quiz_id = (int)$_POST['quiz_id'];
        $user_id = $_SESSION['user_id'];
        $answers = $_POST['answers'] ?? [];

        // Get correct answers
        $stmt = $db->prepare("SELECT id, correct_answer FROM quizzes WHERE id = ?");
        $stmt->execute([$quiz_id]);
        $quiz = $stmt->fetch();
        if (!$quiz) {
            redirect('?page=quizzes');
        }

        // Get all questions for this quiz to count total
        $totalStmt = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE id = ?");
        $totalStmt->execute([$quiz_id]);
        $totalQuestions = $totalStmt->fetchColumn();

        // Calculate score
        $correctCount = 0;
        // For a single quiz, we check the submitted answers
        // But since we have multiple questions per quiz, we need to handle it differently
        // Actually, each quiz entry is a single question. So we need to group by course_id
        // Let's simplify: each quiz entry is a question, and we submit answers for all questions of a course

        // Get all questions for this course
        $stmt = $db->prepare("SELECT * FROM quizzes WHERE course_id = (SELECT course_id FROM quizzes WHERE id = ?)");
        $stmt->execute([$quiz_id]);
        $questions = $stmt->fetchAll();

        $totalQuestions = count($questions);
        $correctCount = 0;
        foreach ($questions as $q) {
            $userAnswer = $answers[$q['id']] ?? '';
            if ($userAnswer === $q['correct_answer']) {
                $correctCount++;
            }
        }

        $score = round(($correctCount / max($totalQuestions, 1)) * 100);

        // Save grade
        $stmt = $db->prepare("INSERT INTO grades (user_id, quiz_id, score, total_questions) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $quiz_id, $score, $totalQuestions]);

        // Store result in session for display
        $_SESSION['quiz_result'] = ['score' => $score, 'total' => $totalQuestions, 'correct' => $correctCount];
        redirect('?page=quiz_result');
    }
}

// ============================================
// PAGE ROUTING
// ============================================
// Public pages
if ($page === 'login' || $page === 'register') {
    // Show login/register page
} elseif (!isLoggedIn()) {
    redirect('?page=login');
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function getCourses($db) {
    return $db->query("SELECT * FROM courses ORDER BY created_at DESC")->fetchAll();
}

function getQuizzes($db) {
    return $db->query("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.id ORDER BY q.created_at DESC")->fetchAll();
}

function getBanners($db) {
    return $db->query("SELECT * FROM banners ORDER BY created_at DESC")->fetchAll();
}

function getUsers($db) {
    return $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
}

function getGrades($db, $userId = null) {
    if ($userId) {
        $stmt = $db->prepare("SELECT g.*, u.name as user_name, q.question, c.title as course_title 
            FROM grades g 
            JOIN users u ON g.user_id = u.id 
            JOIN quizzes q ON g.quiz_id = q.id 
            JOIN courses c ON q.course_id = c.id 
            WHERE g.user_id = ? 
            ORDER BY g.completed_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } else {
        return $db->query("SELECT g.*, u.name as user_name, q.question, c.title as course_title 
            FROM grades g 
            JOIN users u ON g.user_id = u.id 
            JOIN quizzes q ON g.quiz_id = q.id 
            JOIN courses c ON q.course_id = c.id 
            ORDER BY g.completed_at DESC")->fetchAll();
    }
}

function getQuizQuestions($db, $courseId) {
    $stmt = $db->prepare("SELECT * FROM quizzes WHERE course_id = ?");
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

function getUserGradeForQuiz($db, $userId, $quizId) {
    $stmt = $db->prepare("SELECT * FROM grades WHERE user_id = ? AND quiz_id = ? ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$userId, $quizId]);
    return $stmt->fetch();
}

function hasTakenQuiz($db, $userId, $quizId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM grades WHERE user_id = ? AND quiz_id = ?");
    $stmt->execute([$userId, $quizId]);
    return $stmt->fetchColumn() > 0;
}

// ============================================
// HTML TEMPLATES
// ============================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Manajemen Pendidikan Profesional</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================
           RESET & BASE
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        img {
            max-width: 100%;
            height: auto;
        }

        /* ============================================
           LOGIN / REGISTER PAGE
           ============================================ */
        .auth-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            padding: 20px;
        }
        .auth-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 35px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .auth-card h2 {
            text-align: center;
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .auth-card .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .auth-card .form-group {
            margin-bottom: 18px;
        }
        .auth-card label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            color: #334155;
        }
        .auth-card input,
        .auth-card select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: border-color 0.3s;
            background: #f8fafc;
        }
        .auth-card input:focus,
        .auth-card select:focus {
            outline: none;
            border-color: #4F46E5;
            background: #fff;
        }
        .auth-card .btn-primary {
            width: 100%;
            padding: 14px;
            background: #4F46E5;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s;
        }
        .auth-card .btn-primary:hover {
            background: #4338CA;
        }
        .auth-card .auth-link {
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
            color: #64748b;
        }
        .auth-card .auth-link a {
            color: #4F46E5;
            font-weight: 600;
        }
        .auth-card .auth-link a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* ============================================
           LAYOUT - SIDEBAR + MAIN
           ============================================ */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        .sidebar .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid #1e293b;
            margin-bottom: 16px;
        }
        .sidebar .brand h3 {
            font-size: 18px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .brand h3 i {
            color: #818CF8;
        }
        .sidebar .brand small {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .sidebar .nav-section {
            padding: 0 12px;
        }
        .sidebar .nav-section .nav-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 12px 10px 6px;
            font-weight: 700;
        }
        .sidebar .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: 10px;
            color: #cbd5e1;
            transition: all 0.2s;
            font-size: 15px;
            margin-bottom: 2px;
        }
        .sidebar .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        .sidebar .nav-item:hover {
            background: #1e293b;
            color: #fff;
        }
        .sidebar .nav-item.active {
            background: #4F46E5;
            color: #fff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
        .sidebar .nav-item.logout {
            margin-top: auto;
            border-top: 1px solid #1e293b;
            padding-top: 16px;
            color: #f87171;
        }
        .sidebar .nav-item.logout:hover {
            background: #1e293b;
            color: #fca5a5;
        }
        .sidebar .user-info {
            padding: 16px 20px;
            border-top: 1px solid #1e293b;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4F46E5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        .sidebar .user-info .user-detail {
            flex: 1;
            min-width: 0;
        }
        .sidebar .user-info .user-detail .name {
            font-weight: 600;
            color: #fff;
            font-size: 14px;
        }
        .sidebar .user-info .user-detail .role {
            font-size: 12px;
            color: #94a3b8;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* HEADER */
        .top-header {
            background: #fff;
            padding: 16px 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .top-header .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .top-header .page-title i {
            color: #4F46E5;
            margin-right: 10px;
        }
        .top-header .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .top-header .header-actions .user-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 6px 14px 6px 10px;
            border-radius: 30px;
            font-size: 14px;
        }
        .top-header .header-actions .user-badge i {
            color: #4F46E5;
        }
        .top-header .header-actions .btn-mobile-menu {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #0f172a;
            cursor: pointer;
        }

        /* PAGE CONTENT */
        .page-content {
            padding: 25px 30px;
            flex: 1;
        }

        /* FOOTER */
        .footer {
            background: #fff;
            padding: 16px 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            margin-top: auto;
        }

        /* ============================================
           COMPONENTS
           ============================================ */
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
        }
        .card .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card .card-title i {
            color: #4F46E5;
            margin-right: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid #eef2f6;
            text-align: center;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
        }
        .stat-card .stat-label {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .stat-card .stat-icon {
            font-size: 28px;
            color: #4F46E5;
            margin-bottom: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #4F46E5;
            color: #fff;
        }
        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-1px);
        }
        .btn-success {
            background: #22c55e;
            color: #fff;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-warning {
            background: #f59e0b;
            color: #fff;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table th {
            background: #f8fafc;
            text-align: left;
            padding: 12px 14px;
            font-weight: 700;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
        }
        table tr:hover td {
            background: #fafbfc;
        }
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-admin {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-participant {
            background: #dcfce7;
            color: #166534;
        }
        .badge-active {
            background: #dcfce7;
            color: #166534;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-score {
            background: #fef3c7;
            color: #92400e;
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            background: #000;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        .banner-slider {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            margin-bottom: 24px;
            background: #0f172a;
        }
        .banner-slider .banner-track {
            display: flex;
            transition: transform 0.5s ease;
        }
        .banner-slider .banner-slide {
            min-width: 100%;
            position: relative;
        }
        .banner-slider .banner-slide img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        .banner-slider .banner-slide .banner-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px 30px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            color: #fff;
        }
        .banner-slider .banner-slide .banner-overlay h3 {
            font-size: 20px;
            font-weight: 700;
        }
        .banner-slider .banner-dots {
            position: absolute;
            bottom: 12px;
            right: 20px;
            display: flex;
            gap: 8px;
            z-index: 5;
        }
        .banner-slider .banner-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s;
        }
        .banner-slider .banner-dots span.active {
            background: #fff;
            transform: scale(1.2);
        }

        .quiz-option {
            display: block;
            padding: 12px 16px;
            margin-bottom: 8px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: #fafbfc;
        }
        .quiz-option:hover {
            border-color: #4F46E5;
            background: #f1f4ff;
        }
        .quiz-option input[type="radio"] {
            margin-right: 12px;
            accent-color: #4F46E5;
            transform: scale(1.1);
        }
        .quiz-option.selected {
            border-color: #4F46E5;
            background: #eef2ff;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            color: #334155;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: #f8fafc;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4F46E5;
            background: #fff;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
        .empty-state h4 {
            color: #475569;
            margin-bottom: 4px;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .top-header .header-actions .btn-mobile-menu {
                display: block;
            }
            .top-header .header-actions .user-badge span {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .page-content {
                padding: 16px;
            }
            .top-header {
                padding: 12px 16px;
            }
            .top-header .page-title {
                font-size: 17px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .stat-card .stat-number {
                font-size: 24px;
            }
            .card {
                padding: 16px;
            }
            .banner-slider .banner-slide img {
                height: 140px;
            }
            .banner-slider .banner-slide .banner-overlay h3 {
                font-size: 16px;
            }
            .banner-slider .banner-slide .banner-overlay {
                padding: 12px 16px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .auth-card {
                padding: 28px 20px;
            }
            table {
                font-size: 13px;
            }
            table th,
            table td {
                padding: 8px 10px;
            }
            .btn {
                font-size: 13px;
                padding: 8px 14px;
            }
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 99;
        }
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

<?php if ($page === 'login' || $page === 'register'): ?>
    <!-- ==========================================
    AUTH PAGES
    ========================================== -->
    <div class="auth-page">
        <div class="auth-card">
            <?php if ($page === 'login'): ?>
                <h2><i class="fas fa-graduation-cap" style="color:#4F46E5;"></i> LMS</h2>
                <p class="subtitle">Masuk ke akun Anda</p>
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Masukkan username">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Masukkan password">
                    </div>
                    <button type="submit" name="login" class="btn-primary">Masuk</button>
                </form>
                <div class="auth-link">
                    Belum punya akun? <a href="?page=register">Daftar sekarang</a>
                </div>
                <div style="margin-top:12px;font-size:13px;color:#94a3b8;text-align:center;">
                    Demo: admin / admin123 &nbsp;|&nbsp; peserta / peserta123
                </div>
            <?php else: ?>
                <h2><i class="fas fa-user-plus" style="color:#4F46E5;"></i> Daftar</h2>
                <p class="subtitle">Buat akun baru</p>
                <?php if (isset($registerSuccess)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($registerSuccess) ?></div>
                <?php endif; ?>
                <?php if (isset($registerError)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($registerError) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="name" required placeholder="Nama lengkap">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Username unik">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@contoh.com">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Minimal 6 karakter">
                    </div>
                    <div class="form-group">
                        <label>Daftar sebagai</label>
                        <select name="role">
                            <option value="participant">Peserta</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="register" class="btn-primary">Daftar</button>
                </form>
                <div class="auth-link">
                    Sudah punya akun? <a href="?page=login">Masuk</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php exit; ?>
<?php endif; ?>

<!-- ==========================================
    APP LAYOUT
========================================== -->
<div class="app-wrapper">

    <!-- SIDEBAR OVERLAY (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <h3><i class="fas fa-graduation-cap"></i> LMS Profesional</h3>
            <small>Manajemen Pendidikan</small>
        </div>

        <div class="nav-section">
            <div class="nav-label">Navigasi</div>
            <a href="?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="?page=courses" class="nav-item <?= $page === 'courses' ? 'active' : '' ?>">
                <i class="fas fa-video"></i> Materi Video
            </a>
            <a href="?page=quizzes" class="nav-item <?= $page === 'quizzes' || $page === 'quiz_result' ? 'active' : '' ?>">
                <i class="fas fa-question-circle"></i> Kuis
            </a>
            <a href="?page=grades" class="nav-item <?= $page === 'grades' ? 'active' : '' ?>">
                <i class="fas fa-star"></i> Nilai
            </a>

            <?php if (isAdmin()): ?>
                <div class="nav-label" style="margin-top:8px;">Admin</div>
                <a href="?page=banners" class="nav-item <?= $page === 'banners' ? 'active' : '' ?>">
                    <i class="fas fa-image"></i> Banner Promosi
                </a>
                <a href="?page=users" class="nav-item <?= $page === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Kelola Peserta
                </a>
                <a href="?page=all_grades" class="nav-item <?= $page === 'all_grades' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Semua Nilai
                </a>
            <?php endif; ?>
        </div>

        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-detail">
                <div class="name"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
                <div class="role"><?= ucfirst($_SESSION['role'] ?? '') ?></div>
            </div>
        </div>
        <a href="?logout=1" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i> Keluar
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- HEADER -->
        <header class="top-header">
            <div class="page-title">
                <i class="fas fa-<?= $page === 'dashboard' ? 'th-large' : ($page === 'courses' ? 'video' : ($page === 'quizzes' ? 'question-circle' : ($page === 'grades' ? 'star' : ($page === 'banners' ? 'image' : ($page === 'users' ? 'users' : ($page === 'all_grades' ? 'chart-bar' : 'book')))))) ?>"></i>
                <?= ucfirst(str_replace('_', ' ', $page)) ?>
            </div>
            <div class="header-actions">
                <div class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
                </div>
                <button class="btn-mobile-menu" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <div class="page-content">

            <?php if (isset($successMsg)): ?>
                <div class="alert alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <?php
            // ============================================
            // PAGE RENDERER
            // ============================================
            switch ($page):
                // ==========================================
                case 'dashboard':
                    $totalCourses = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
                    $totalQuizzes = $db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
                    $totalBanners = $db->query("SELECT COUNT(*) FROM banners WHERE is_active = 1")->fetchColumn();
                    $myGrades = [];
                    if (isParticipant()) {
                        $myGrades = getGrades($db, $_SESSION['user_id']);
                    }
                    $totalGrades = isParticipant() ? count($myGrades) : $db->query("SELECT COUNT(*) FROM grades")->fetchColumn();
                    $avgScore = 0;
                    if (isParticipant() && count($myGrades) > 0) {
                        $sum = array_sum(array_column($myGrades, 'score'));
                        $avgScore = round($sum / count($myGrades));
                    } elseif (isAdmin()) {
                        $avgScore = round($db->query("SELECT AVG(score) FROM grades")->fetchColumn() ?: 0);
                    }

                    // Active banners for slider
                    $activeBanners = $db->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
                    ?>
                    <!-- Banner Slider -->
                    <?php if (count($activeBanners) > 0): ?>
                        <div class="banner-slider" id="bannerSlider">
                            <div class="banner-track" id="bannerTrack">
                                <?php foreach ($activeBanners as $idx => $b): ?>
                                    <div class="banner-slide">
                                        <img src="<?= htmlspecialchars($b['image_url'] ?: 'https://via.placeholder.com/1200x400/4F46E5/FFFFFF?text=' . urlencode($b['title'])) ?>" alt="<?= htmlspecialchars($b['title']) ?>">
                                        <div class="banner-overlay">
                                            <h3><?= htmlspecialchars($b['title']) ?></h3>
                                            <?php if ($b['link'] && $b['link'] !== '#'): ?>
                                                <a href="<?= htmlspecialchars($b['link']) ?>" target="_blank" style="color:#fff;text-decoration:underline;">Lihat Detail →</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="banner-dots">
                                <?php for ($i = 0; $i < count($activeBanners); $i++): ?>
                                    <span class="<?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" onclick="goToSlide(<?= $i ?>)"></span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-video"></i></div>
                            <div class="stat-number"><?= $totalCourses ?></div>
                            <div class="stat-label">Total Materi Video</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                            <div class="stat-number"><?= $totalQuizzes ?></div>
                            <div class="stat-label">Total Soal Kuis</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-star"></i></div>
                            <div class="stat-number"><?= $totalGrades ?></div>
                            <div class="stat-label">Nilai Tercatat</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-number"><?= $avgScore ?>%</div>
                            <div class="stat-label">Rata-rata Nilai</div>
                        </div>
                    </div>

                    <!-- Recent Courses -->
                    <div class="card">
                        <div class="card-title">
                            <span><i class="fas fa-video"></i> Materi Terbaru</span>
                            <a href="?page=courses" class="btn btn-secondary btn-sm">Lihat Semua</a>
                        </div>
                        <?php
                        $recentCourses = $db->query("SELECT * FROM courses ORDER BY created_at DESC LIMIT 3")->fetchAll();
                        if (count($recentCourses) > 0): ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                                <?php foreach ($recentCourses as $c): ?>
                                    <div style="background:#f8fafc;border-radius:12px;overflow:hidden;border:1px solid #eef2f6;">
                                        <img src="<?= htmlspecialchars($c['thumbnail'] ?: 'https://via.placeholder.com/400x225/4F46E5/FFFFFF?text=' . urlencode($c['title'])) ?>" alt="<?= htmlspecialchars($c['title']) ?>" style="width:100%;height:140px;object-fit:cover;">
                                        <div style="padding:12px 16px;">
                                            <h4 style="font-size:15px;font-weight:700;"><?= htmlspecialchars($c['title']) ?></h4>
                                            <p style="font-size:13px;color:#64748b;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($c['description']) ?></p>
                                            <a href="?page=courses" class="btn btn-primary btn-sm" style="margin-top:8px;">Tonton</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-video"></i><h4>Belum ada materi</h4></div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Grades (for participant) -->
                    <?php if (isParticipant() && count($myGrades) > 0): ?>
                        <div class="card">
                            <div class="card-title">
                                <span><i class="fas fa-star"></i> Nilai Terbaru</span>
                                <a href="?page=grades" class="btn btn-secondary btn-sm">Lihat Semua</a>
                            </div>
                            <div class="table-wrapper">
                                <table>
                                    <thead><tr><th>Kuis</th><th>Skor</th><th>Tanggal</th></tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($myGrades, 0, 5) as $g): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($g['question'] ?? 'Kuis') ?></td>
                                                <td><span class="badge badge-score"><?= $g['score'] ?>%</span></td>
                                                <td><?= date('d M Y', strtotime($g['completed_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php break; ?>

                <!-- ==========================================
                COURSES PAGE
                ========================================== -->
                case 'courses':
                    $courses = getCourses($db);
                    if (isAdmin()):
                        // Show add course form
                        ?>
                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-plus-circle"></i> Tambah Materi Video</span></div>
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group"><label>Judul</label><input type="text" name="title" required placeholder="Judul materi"></div>
                                    <div class="form-group"><label>URL Video (YouTube embed)</label><input type="url" name="video_url" placeholder="https://www.youtube.com/embed/..."></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Deskripsi</label><textarea name="description" placeholder="Deskripsi materi"></textarea></div>
                                    <div class="form-group"><label>URL Thumbnail</label><input type="url" name="thumbnail" placeholder="https://..."></div>
                                </div>
                                <button type="submit" name="add_course" class="btn btn-primary">Simpan</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-title"><span><i class="fas fa-list"></i> Daftar Materi</span></div>
                        <?php if (count($courses) > 0): ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
                                <?php foreach ($courses as $c): ?>
                                    <div style="background:#f8fafc;border-radius:14px;overflow:hidden;border:1px solid #eef2f6;transition:box-shadow 0.2s;" onmouseenter="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseleave="this.style.boxShadow='none'">
                                        <div class="video-container" style="padding-bottom:56.25%;height:0;border-radius:12px 12px 0 0;">
                                            <iframe src="<?= htmlspecialchars($c['video_url'] ?: 'https://www.youtube.com/embed/dQw4w9WgXcQ') ?>" allowfullscreen loading="lazy"></iframe>
                                        </div>
                                        <div style="padding:16px 20px;">
                                            <h4 style="font-size:17px;font-weight:700;"><?= htmlspecialchars($c['title']) ?></h4>
                                            <p style="font-size:14px;color:#64748b;margin:6px 0;"><?= htmlspecialchars($c['description']) ?></p>
                                            <?php if (isAdmin()): ?>
                                                <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                                                    <a href="?delete_course=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus materi ini?')"><i class="fas fa-trash"></i> Hapus</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-video"></i><h4>Belum ada materi</h4></div>
                        <?php endif; ?>
                    </div>
                    <?php break; ?>

                <!-- ==========================================
                QUIZZES PAGE
                ========================================== -->
                case 'quizzes':
                    if (isAdmin()):
                        $courses = getCourses($db);
                        ?>
                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-plus-circle"></i> Tambah Pertanyaan Kuis</span></div>
                            <form method="POST">
                                <div class="form-group">
                                    <label>Pilih Materi</label>
                                    <select name="course_id" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($courses as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label>Pertanyaan</label><textarea name="question" required placeholder="Tulis pertanyaan..."></textarea></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Opsi A</label><input type="text" name="option_a" required placeholder="Opsi A"></div>
                                    <div class="form-group"><label>Opsi B</label><input type="text" name="option_b" required placeholder="Opsi B"></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Opsi C</label><input type="text" name="option_c" required placeholder="Opsi C"></div>
                                    <div class="form-group"><label>Opsi D</label><input type="text" name="option_d" required placeholder="Opsi D"></div>
                                </div>
                                <div class="form-group">
                                    <label>Jawaban Benar</label>
                                    <select name="correct_answer" required>
                                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                    </select>
                                </div>
                                <button type="submit" name="add_quiz" class="btn btn-primary">Simpan</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-title"><span><i class="fas fa-list"></i> Daftar Kuis</span></div>
                        <?php
                        if (isAdmin()) {
                            $quizzes = getQuizzes($db);
                        } else {
                            // For participant: show quizzes grouped by course
                            $courses = getCourses($db);
                        }
                        ?>
                        <?php if (isAdmin()): ?>
                            <?php if (count($quizzes) > 0): ?>
                                <div class="table-wrapper">
                                    <table>
                                        <thead><tr><th>Materi</th><th>Pertanyaan</th><th>Jawaban</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($quizzes as $q): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($q['course_title'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($q['question']) ?></td>
                                                    <td><span class="badge badge-active"><?= $q['correct_answer'] ?></span></td>
                                                    <td><a href="?delete_quiz=<?= $q['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus pertanyaan ini?')"><i class="fas fa-trash"></i></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-question-circle"></i><h4>Belum ada pertanyaan</h4></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Participant view: list courses with quiz -->
                            <?php if (count($courses) > 0): ?>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;">
                                    <?php foreach ($courses as $c): ?>
                                        <?php
                                        $questions = getQuizQuestions($db, $c['id']);
                                        $hasTaken = hasTakenQuiz($db, $_SESSION['user_id'], $questions[0]['id'] ?? 0);
                                        $grade = getUserGradeForQuiz($db, $_SESSION['user_id'], $questions[0]['id'] ?? 0);
                                        ?>
                                        <div style="background:#f8fafc;border-radius:14px;padding:18px 20px;border:1px solid #eef2f6;">
                                            <h4 style="font-size:16px;font-weight:700;"><?= htmlspecialchars($c['title']) ?></h4>
                                            <p style="font-size:13px;color:#64748b;"><?= count($questions) ?> pertanyaan</p>
                                            <?php if (count($questions) > 0): ?>
                                                <?php if ($hasTaken): ?>
                                                    <div style="margin-top:10px;">
                                                        <span class="badge badge-score">Nilai: <?= $grade['score'] ?? 0 ?>%</span>
                                                        <a href="?page=quiz_result" class="btn btn-secondary btn-sm" style="margin-left:8px;">Lihat</a>
                                                    </div>
                                                <?php else: ?>
                                                    <a href="?page=take_quiz&course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm" style="margin-top:10px;">Kerjakan Kuis</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p style="font-size:13px;color:#94a3b8;margin-top:8px;">Belum ada pertanyaan</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-book-open"></i><h4>Belum ada materi kuis</h4></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php break; ?>

                <!-- ==========================================
                TAKE QUIZ
                ========================================== -->
                case 'take_quiz':
                    $courseId = (int)($_GET['course_id'] ?? 0);
                    $questions = getQuizQuestions($db, $courseId);
                    $course = $db->prepare("SELECT * FROM courses WHERE id = ?");
                    $course->execute([$courseId]);
                    $course = $course->fetch();
                    if (!$course || count($questions) == 0) {
                        redirect('?page=quizzes');
                    }
                    // Check if already taken
                    if (hasTakenQuiz($db, $_SESSION['user_id'], $questions[0]['id'])) {
                        redirect('?page=quiz_result');
                    }
                    ?>
                    <div class="card">
                        <div class="card-title">
                            <span><i class="fas fa-pencil-alt"></i> Kuis: <?= htmlspecialchars($course['title']) ?></span>
                            <span style="font-size:14px;font-weight:400;color:#64748b;"><?= count($questions) ?> pertanyaan</span>
                        </div>
                        <form method="POST" onsubmit="return confirm('Yakin ingin mengirim jawaban?')">
                            <input type="hidden" name="quiz_id" value="<?= $questions[0]['id'] ?>">
                            <?php foreach ($questions as $idx => $q): ?>
                                <div style="background:#f8fafc;border-radius:12px;padding:16px 20px;margin-bottom:16px;border:1px solid #eef2f6;">
                                    <p style="font-weight:700;font-size:15px;margin-bottom:12px;"><?= ($idx + 1) ?>. <?= htmlspecialchars($q['question']) ?></p>
                                    <div class="quiz-option" onclick="selectOption(this)">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="A" id="q<?= $q['id'] ?>_a">
                                        <label for="q<?= $q['id'] ?>_a" style="cursor:pointer;">A. <?= htmlspecialchars($q['option_a']) ?></label>
                                    </div>
                                    <div class="quiz-option" onclick="selectOption(this)">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="B" id="q<?= $q['id'] ?>_b">
                                        <label for="q<?= $q['id'] ?>_b" style="cursor:pointer;">B. <?= htmlspecialchars($q['option_b']) ?></label>
                                    </div>
                                    <div class="quiz-option" onclick="selectOption(this)">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="C" id="q<?= $q['id'] ?>_c">
                                        <label for="q<?= $q['id'] ?>_c" style="cursor:pointer;">C. <?= htmlspecialchars($q['option_c']) ?></label>
                                    </div>
                                    <div class="quiz-option" onclick="selectOption(this)">
                                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="D" id="q<?= $q['id'] ?>_d">
                                        <label for="q<?= $q['id'] ?>_d" style="cursor:pointer;">D. <?= htmlspecialchars($q['option_d']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" name="submit_quiz" class="btn btn-success">Kirim Jawaban</button>
                            <a href="?page=quizzes" class="btn btn-secondary">Batal</a>
                        </form>
                    </div>
                    <?php break; ?>

                <!-- ==========================================
                QUIZ RESULT
                ========================================== -->
                case 'quiz_result':
                    $result = $_SESSION['quiz_result'] ?? null;
                    if ($result) {
                        $score = $result['score'];
                        $total = $result['total'];
                        $correct = $result['correct'];
                        unset($_SESSION['quiz_result']);
                    } else {
                        // Get latest grade
                        $stmt = $db->prepare("SELECT * FROM grades WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1");
                        $stmt->execute([$_SESSION['user_id']]);
                        $g = $stmt->fetch();
                        if ($g) {
                            $score = $g['score'];
                            $total = $g['total_questions'];
                            $correct = round(($score / 100) * $total);
                        } else {
                            redirect('?page=quizzes');
                        }
                    }
                    ?>
                    <div class="card" style="text-align:center;padding:40px;">
                        <div style="font-size:72px;margin-bottom:16px;">
                            <?php if ($score >= 80): ?>🎉
                            <?php elseif ($score >= 60): ?>😊
                            <?php else: ?>📚
                            <?php endif; ?>
                        </div>
                        <h2 style="font-size:28px;font-weight:800;">Nilai Anda: <?= $score ?>%</h2>
                        <p style="color:#64748b;font-size:16px;margin:8px 0;">
                            Benar <?= $correct ?> dari <?= $total ?> pertanyaan
                        </p>
                        <div style="width:100%;max-width:300px;height:12px;background:#e2e8f0;border-radius:30px;margin:16px auto;overflow:hidden;">
                            <div style="width:<?= $score ?>%;height:100%;background:<?= $score >= 80 ? '#22c55e' : ($score >= 60 ? '#f59e0b' : '#ef4444') ?>;border-radius:30px;transition:width 0.8s ease;"></div>
                        </div>
                        <div style="margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                            <a href="?page=quizzes" class="btn btn-primary">Kembali ke Kuis</a>
                            <a href="?page=grades" class="btn btn-secondary">Lihat Semua Nilai</a>
                        </div>
                    </div>
                    <?php break; ?>

                <!-- ==========================================
                GRADES (Participant)
                ========================================== -->
                case 'grades':
                    if (isParticipant()) {
                        $grades = getGrades($db, $_SESSION['user_id']);
                    } else {
                        $grades = getGrades($db);
                    }
                    ?>
                    <div class="card">
                        <div class="card-title"><span><i class="fas fa-star"></i> Daftar Nilai</span></div>
                        <?php if (count($grades) > 0): ?>
                            <div class="table-wrapper">
                                <table>
                                    <thead><tr>
                                        <?php if (isAdmin()): ?><th>Peserta</th><?php endif; ?>
                                        <th>Kuis</th><th>Skor</th><th>Tanggal</th>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach ($grades as $g): ?>
                                            <tr>
                                                <?php if (isAdmin()): ?>
                                                    <td><?= htmlspecialchars($g['user_name'] ?? '-') ?></td>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($g['question'] ?? 'Kuis') ?></td>
                                                <td><span class="badge badge-score"><?= $g['score'] ?>%</span></td>
                                                <td><?= date('d M Y H:i', strtotime($g['completed_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-star"></i><h4>Belum ada nilai</h4></div>
                        <?php endif; ?>
                    </div>
                    <?php break; ?>

                <!-- ==========================================
                ALL GRADES (Admin)
                ========================================== -->
                <?php if (isAdmin()): ?>
                    <?php case 'all_grades': ?>
                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-chart-bar"></i> Semua Nilai Peserta</span></div>
                            <?php
                            $allGrades = $db->query("
                                SELECT g.*, u.name as user_name, u.username, q.question, c.title as course_title 
                                FROM grades g 
                                JOIN users u ON g.user_id = u.id 
                                JOIN quizzes q ON g.quiz_id = q.id 
                                JOIN courses c ON q.course_id = c.id 
                                ORDER BY g.completed_at DESC
                            ")->fetchAll();
                            ?>
                            <?php if (count($allGrades) > 0): ?>
                                <div class="table-wrapper">
                                    <table>
                                        <thead><tr><th>Peserta</th><th>Materi</th><th>Skor</th><th>Tanggal</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($allGrades as $g): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($g['user_name'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($g['course_title'] ?? '-') ?></td>
                                                    <td><span class="badge badge-score"><?= $g['score'] ?>%</span></td>
                                                    <td><?= date('d M Y H:i', strtotime($g['completed_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>Belum ada nilai</h4></div>
                            <?php endif; ?>
                        </div>
                        <?php break; ?>
                <?php endif; ?>

                <!-- ==========================================
                BANNERS (Admin)
                ========================================== -->
                <?php if (isAdmin()): ?>
                    <?php case 'banners': ?>
                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-plus-circle"></i> Tambah Banner</span></div>
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group"><label>Judul</label><input type="text" name="title" required placeholder="Judul banner"></div>
                                    <div class="form-group"><label>URL Gambar</label><input type="url" name="image_url" placeholder="https://..."></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Link (opsional)</label><input type="url" name="link" placeholder="https://..."></div>
                                    <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:24px;">
                                        <label style="margin:0;"><input type="checkbox" name="is_active" checked> Aktif</label>
                                    </div>
                                </div>
                                <button type="submit" name="add_banner" class="btn btn-primary">Simpan</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-images"></i> Daftar Banner</span></div>
                            <?php $banners = getBanners($db); ?>
                            <?php if (count($banners) > 0): ?>
                                <div class="table-wrapper">
                                    <table>
                                        <thead><tr><th>Gambar</th><th>Judul</th><th>Status</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($banners as $b): ?>
                                                <tr>
                                                    <td><img src="<?= htmlspecialchars($b['image_url'] ?: 'https://via.placeholder.com/100x60/4F46E5/FFFFFF?text=' . urlencode($b['title'])) ?>" style="width:80px;height:50px;object-fit:cover;border-radius:8px;" alt=""></td>
                                                    <td><?= htmlspecialchars($b['title']) ?></td>
                                                    <td><span class="badge <?= $b['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                                                    <td>
                                                        <a href="?toggle_banner=<?= $b['id'] ?>" class="btn btn-warning btn-sm"><?= $b['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></a>
                                                        <a href="?delete_banner=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus banner?')"><i class="fas fa-trash"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-image"></i><h4>Belum ada banner</h4></div>
                            <?php endif; ?>
                        </div>
                        <?php break; ?>
                <?php endif; ?>

                <!-- ==========================================
                USERS (Admin)
                ========================================== -->
                <?php if (isAdmin()): ?>
                    <?php case 'users': ?>
                        <div class="card">
                            <div class="card-title"><span><i class="fas fa-users"></i> Daftar Peserta & Admin</span></div>
                            <?php $users = getUsers($db); ?>
                            <?php if (count($users) > 0): ?>
                                <div class="table-wrapper">
                                    <table>
                                        <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($users as $u): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($u['name']) ?></td>
                                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                                    <td>
                                                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-participant' ?>">
                                                            <?= ucfirst($u['role']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                                <select name="role" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;border:1px solid #e2e8f0;font-size:13px;">
                                                                    <option value="participant" <?= $u['role'] === 'participant' ? 'selected' : '' ?>>Participant</option>
                                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                </select>
                                                            </form>
                                                            <a href="?delete_user=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus user ini?')"><i class="fas fa-trash"></i></a>
                                                        <?php else: ?>
                                                            <span style="font-size:13px;color:#94a3b8;">(Anda)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-users"></i><h4>Belum ada user</h4></div>
                            <?php endif; ?>
                        </div>
                        <?php break; ?>
                <?php endif; ?>

            <?php endswitch; ?>

        </div><!-- /page-content -->

        <!-- FOOTER -->
        <footer class="footer">
            &copy; <?= date('Y') ?> LMS Manajemen Pendidikan Profesional. Dibangun dengan <i class="fas fa-heart" style="color:#ef4444;"></i> untuk pendidikan.
        </footer>

    </main>
</div>

<!-- ==========================================
JAVASCRIPT
========================================== -->
<script>
    // Toggle sidebar on mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    // Close sidebar on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
    });

    // Banner slider
    let currentSlide = 0;
    const track = document.getElementById('bannerTrack');
    const dots = document.querySelectorAll('.banner-dots span');

    function goToSlide(index) {
        if (!track) return;
        const slides = track.querySelectorAll('.banner-slide');
        if (index >= slides.length) index = 0;
        if (index < 0) index = slides.length - 1;
        currentSlide = index;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        dots.forEach((d, i) => {
            d.classList.toggle('active', i === index);
        });
    }

    // Auto slide
    let autoSlideInterval;
    function startAutoSlide() {
        if (document.getElementById('bannerTrack')) {
            const slides = document.querySelectorAll('.banner-slide');
            if (slides.length <= 1) return;
            autoSlideInterval = setInterval(() => {
                goToSlide(currentSlide + 1);
            }, 4000);
        }
    }

    // Pause on hover
    const slider = document.getElementById('bannerSlider');
    if (slider) {
        slider.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
        slider.addEventListener('mouseleave', startAutoSlide);
        // Initialize dots click
        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                clearInterval(autoSlideInterval);
                goToSlide(i);
                startAutoSlide();
            });
        });
        startAutoSlide();
    }

    // Quiz option selection
    function selectOption(el) {
        const radio = el.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            // Remove selected class from siblings
            const parent = el.parentElement;
            parent.querySelectorAll('.quiz-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');
        }
    }

    // Auto-select on load for radio clicks
    document.querySelectorAll('.quiz-option input[type="radio"]').forEach(input => {
        input.addEventListener('change', function() {
            const parent = this.closest('.quiz-option');
            if (parent) {
                const container = parent.parentElement;
                container.querySelectorAll('.quiz-option').forEach(opt => opt.classList.remove('selected'));
                parent.classList.add('selected');
            }
        });
        // If already checked on load
        if (input.checked) {
            const parent = input.closest('.quiz-option');
            if (parent) parent.classList.add('selected');
        }
    });

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500); }, 5000);
    });
</script>

</body>
</html>