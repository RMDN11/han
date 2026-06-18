<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Interaktif Promosi - Jawwada</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* 3D Background Shapes */
        .bg-shapes { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        .shape { position: absolute; filter: blur(90px); opacity: 0.4; border-radius: 50%; }
        .shape-1 { width: 400px; height: 400px; background: #3b82f6; top: -10%; left: -5%; }
        .shape-2 { width: 500px; height: 500px; background: #8b5cf6; bottom: -10%; right: -10%; }

        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }

        /* Glassmorphism Classes */
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            transition: all 0.4s ease;
        }

        h1, h2, h3 { color: #ffffff; margin-bottom: 15px; }
        p { color: var(--text-muted); margin-bottom: 20px; }

        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #cbd5e1; }
        input, select {
            width: 100%; padding: 14px; background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border); border-radius: 10px;
            color: white; font-size: 1rem; transition: 0.3s;
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); background: rgba(0, 0, 0, 0.5); }
        option { background: var(--bg-dark); color: white; }
        
        button {
            width: 100%; padding: 15px; background: var(--accent); color: white;
            border: none; border-radius: 10px; font-size: 1.1rem; font-weight: bold;
            cursor: pointer; transition: 0.3s;
        }
        button:hover { background: #2563eb; transform: translateY(-2px); }

        /* LMS Workspace (Hidden Initially) */
        #lms-workspace { display: none; animation: fadeIn 0.8s ease forwards; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Grid Layout for LMS */
        .lms-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        @media (max-width: 768px) { .lms-grid { grid-template-columns: 1fr; } }

        /* Video Container */
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; border: 1px solid var(--glass-border); background: #000; }
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

        /* Module Content */
        .module-content { max-height: 400px; overflow-y: auto; padding-right: 10px; }
        .module-content::-webkit-scrollbar { width: 6px; }
        .module-content::-webkit-scrollbar-thumb { background: var(--glass-border); border-radius: 10px; }

        /* Quiz Styles */
        .quiz-question { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--glass-border); }
        .quiz-question:last-child { border-bottom: none; }
        .quiz-option { display: block; margin: 8px 0; cursor: pointer; color: var(--text-muted); }
        .quiz-option input { width: auto; margin-right: 10px; accent-color: var(--accent); }
        #quiz-result { display: none; margin-top: 20px; padding: 15px; border-radius: 10px; background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #34d399; font-weight: bold; text-align: center; }

        /* Banner Placeholder */
        .banner-img { width: 100%; height: 200px; background: linear-gradient(45deg, #1e293b, #0f172a); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px dashed var(--glass-border); margin-bottom: 25px; }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="container">
        
        <div id="promo-section" class="glass-panel">
            <div class="banner-img">
                <h2 style="color: var(--text-muted);">Area Banner Promosi (Gambar/KOP)</h2>
            </div>
            <div style="text-align: center; margin-bottom: 30px;">
                <h1>Akses Premium LMS Jawwada</h1>
                <p>Isi formulir di bawah ini untuk membuka akses langsung ke modul materi, video pembelajaran, dan simulasi kuis tanpa perlu mendaftar akun baru.</p>
            </div>

            <form id="promoForm">
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" required placeholder="Masukkan nama Anda...">
                </div>
                <div class="form-group">
                    <label for="instansi">Asal Instansi</label>
                    <input type="text" id="instansi" required placeholder="Nama sekolah atau perusahaan...">
                </div>
                <div class="form-group">
                    <label for="program">Program Diminati</label>
                    <select id="program" required>
                        <option value="" disabled selected>Pilih Program...</option>
                        <option value="KBM">KBM Batch 2026</option>
                        <option value="Ujian">Ujian Muroja'ah Bulanan</option>
                    </select>
                </div>
                <button type="submit">Buka Akses LMS Sekarang</button>
            </form>
        </div>

        <div id="lms-workspace">
            <div class="glass-panel" style="text-align: center; border-bottom: 3px solid var(--accent);">
                <h2>Selamat Datang, <span id="user-name-display" style="color: var(--accent);">Peserta</span>!</h2>
                <p>Akses premium Anda telah aktif. Silakan pelajari materi dan kerjakan kuis di bawah ini.</p>
            </div>

            <div class="lms-grid">
                <div>
                    <div class="glass-panel">
                        <h3>1. Video Pembelajaran</h3>
                        <p>Simak instruksi dan materi pengantar berikut.</p>
                        <div class="video-container">
                            <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video Pembelajaran" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    </div>

                    <div class="glass-panel">
                        <h3>2. Modul Materi Utama</h3>
                        <div class="module-content">
                            <h4>Bab 1: Pengenalan Sistem</h4>
                            <p>Sistem Manajemen Pembelajaran (LMS) adalah tulang punggung dari pendidikan digital modern. Di Jawwada, kami mengutamakan kemudahan akses, pelacakan progres real-time, dan UI/UX yang profesional.</p>
                            <h4>Bab 2: Manajemen Evaluasi</h4>
                            <p>Evaluasi dilakukan melalui kuis otomatis dan ujian terjadwal. Instruktur dapat melihat analitik langsung dari dashboard utama tanpa perlu mengolah data secara manual.</p>
                            <h4>Bab 3: Implementasi Glassmorphism</h4>
                            <p>Desain antarmuka saat ini menggunakan pendekatan Glassmorphism dengan kontras warna monokrom. Hal ini mengurangi kelelahan mata (eye-strain) dan menonjolkan konten pembelajaran.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="glass-panel">
                        <h3>3. Kuis Evaluasi</h3>
                        <p>Kerjakan kuis berikut untuk menguji pemahaman Anda dari modul.</p>
                        
                        <form id="quizForm">
                            <div class="quiz-question">
                                <strong>1. Apa fungsi utama LMS?</strong>
                                <label class="quiz-option"><input type="radio" name="q1" value="a" required> Media sosial</label>
                                <label class="quiz-option"><input type="radio" name="q1" value="b"> Manajemen pembelajaran digital</label>
                                <label class="quiz-option"><input type="radio" name="q1" value="c"> Editing video</label>
                            </div>
                            
                            <div class="quiz-question">
                                <strong>2. Gaya desain UI apa yang digunakan halaman ini?</strong>
                                <label class="quiz-option"><input type="radio" name="q2" value="a"> Skeuomorphism</label>
                                <label class="quiz-option"><input type="radio" name="q2" value="b"> Flat Design</label>
                                <label class="quiz-option"><input type="radio" name="q2" value="c" required> Glassmorphism</label>
                            </div>

                            <button type="submit" style="margin-top: 15px;">Kumpulkan Jawaban</button>
                        </form>

                        <div id="quiz-result"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // 1. Menangani Form Promosi
        document.getElementById('promoForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Mencegah reload halaman
            
            // Ambil nama peserta
            const nama = document.getElementById('nama').value;
            document.getElementById('user-name-display').innerText = nama;

            // Sembunyikan form promosi, tampilkan ruang LMS
            document.getElementById('promo-section').style.display = 'none';
            document.getElementById('lms-workspace').style.display = 'block';

            // Scroll ke atas dengan halus
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // 2. Menangani Kuis Interaktif
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Kunci Jawaban (Hardcoded di JS)
            const answers = { q1: 'b', q2: 'c' };
            let score = 0;
            const totalQuestions = 2;

            // Cek jawaban user
            const formData = new FormData(this);
            for (let [question, answer] of formData.entries()) {
                if (answers[question] === answer) {
                    score++;
                }
            }

            // Hitung nilai (0 - 100)
            const finalScore = (score / totalQuestions) * 100;

            // Tampilkan hasil
            const resultDiv = document.getElementById('quiz-result');
            resultDiv.style.display = 'block';
            
            if (finalScore === 100) {
                resultDiv.style.borderColor = "var(--success)";
                resultDiv.style.color = "#34d399";
                resultDiv.innerHTML = `Luar Biasa! Nilai Anda: ${finalScore}/100. Anda telah memahami materi dengan sempurna.`;
            } else {
                resultDiv.style.borderColor = "#fbbf24"; // Warna peringatan (kuning)
                resultDiv.style.color = "#fcd34d";
                resultDiv.innerHTML = `Nilai Anda: ${finalScore}/100. Silakan tinjau kembali modul pembelajaran di sebelah kiri.`;
            }
        });
    </script>
</body>
</html>