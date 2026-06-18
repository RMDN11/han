<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Profesional - Jawwada</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-hover: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
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
        .shape { position: absolute; filter: blur(100px); opacity: 0.4; border-radius: 50%; }
        .shape-1 { width: 500px; height: 500px; background: #3b82f6; top: -10%; left: -10%; }
        .shape-2 { width: 600px; height: 600px; background: #8b5cf6; bottom: -10%; right: -10%; }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        /* Glassmorphism Panels */
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            margin-bottom: 30px;
        }

        h1, h2, h3 { color: #ffffff; margin-bottom: 15px; }
        p { color: var(--text-muted); margin-bottom: 15px; }

        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #cbd5e1; }
        input, select, textarea {
            width: 100%; padding: 14px; background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--glass-border); border-radius: 10px;
            color: white; font-size: 1rem; transition: 0.3s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); background: rgba(0, 0, 0, 0.6); }
        textarea { resize: vertical; min-height: 100px; }
        option { background: var(--bg-dark); color: white; }
        
        button {
            width: 100%; padding: 15px; background: var(--accent); color: white;
            border: none; border-radius: 10px; font-size: 1.1rem; font-weight: bold;
            cursor: pointer; transition: 0.3s;
        }
        button:hover { background: var(--accent-hover); transform: translateY(-2px); }
        button.btn-secondary { background: transparent; border: 1px solid var(--accent); color: var(--accent); }
        button.btn-secondary:hover { background: rgba(59, 130, 246, 0.1); }
        button.btn-danger { background: transparent; border: 1px solid var(--danger); color: var(--danger); margin-top: 10px;}
        button.btn-danger:hover { background: rgba(239, 68, 68, 0.1); }

        /* Banner Promosi */
        .banner-img {
            width: 100%; height: 250px; border-radius: 12px; margin-bottom: 25px;
            background: url('lms.jpeg') center/cover no-repeat;
            border: 1px solid var(--glass-border);
            display: flex; align-items: flex-end; padding: 20px;
            box-shadow: inset 0 -80px 80px -20px rgba(0,0,0,0.8);
        }
        .banner-img h2 { margin: 0; font-size: 2rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); }

        /* LMS Workspace Layout */
        #lms-workspace { display: none; animation: fadeIn 0.8s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .lms-grid { display: grid; grid-template-columns: 300px 1fr; gap: 25px; }
        @media (max-width: 900px) { .lms-grid { grid-template-columns: 1fr; } }

        /* Sidebar Modul */
        .module-list { list-style: none; }
        .module-item {
            padding: 15px; margin-bottom: 10px; background: rgba(0,0,0,0.2);
            border: 1px solid var(--glass-border); border-radius: 10px;
            cursor: pointer; transition: 0.3s; display: flex; justify-content: space-between; align-items: center;
        }
        .module-item:hover { background: var(--glass-hover); border-color: var(--accent); }
        .module-item.active { background: rgba(59, 130, 246, 0.2); border-color: var(--accent); border-left: 4px solid var(--accent); }

        /* Video Area */
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; border: 1px solid var(--glass-border); background: #000; margin-bottom: 20px;}
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }

        /* Admin Modal (Hidden by default) */
        #admin-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 100;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-content { max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; }
        
        .header-flex { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div id="admin-modal">
        <div class="glass-panel modal-content">
            <div class="header-flex">
                <h2>Tambah Modul Pembelajaran</h2>
                <button onclick="toggleAdminModal()" style="width: auto; padding: 5px 15px; background: transparent; border: 1px solid white;">X</button>
            </div>
            <p>Tambahkan video materi dan deskripsi modul ke dalam sistem LMS.</p>
            
            <form id="adminForm">
                <div class="form-group">
                    <label>Judul Modul</label>
                    <input type="text" id="mod_title" required placeholder="Contoh: Bab 1 - Pengantar KBM">
                </div>
                <div class="form-group">
                    <label>Link Embed Video (YouTube)</label>
                    <input type="url" id="mod_video" required placeholder="Contoh: https://www.youtube.com/embed/dQw4w9WgXcQ">
                    <small style="color: var(--text-muted);">Gunakan URL embed, bukan URL tonton biasa.</small>
                </div>
                <div class="form-group">
                    <label>Konten / Deskripsi Modul (Mendukung HTML)</label>
                    <textarea id="mod_content" required placeholder="Tuliskan materi atau instruksi untuk siswa di sini..."></textarea>
                </div>
                <button type="submit">Simpan Modul</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div id="promo-section" class="glass-panel">
            <div class="banner-img">
                <h2>Program Eksklusif Jawwada</h2>
            </div>
            <div style="text-align: center; margin-bottom: 30px;">
                <p>Tingkatkan kapasitas manajemen dan pembelajaran Anda. Isi formulir untuk mengakses modul interaktif.</p>
            </div>

            <form id="promoForm">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" id="nama_peserta" required placeholder="Masukkan nama Anda...">
                </div>
                <div class="form-group">
                    <label>Asal Instansi</label>
                    <input type="text" required placeholder="Nama sekolah/perusahaan...">
                </div>
                <button type="submit">Mulai Pembelajaran</button>
            </form>
        </div>

        <div id="lms-workspace">
            <div class="glass-panel header-flex">
                <div>
                    <h2>Selamat Belajar, <span id="user-display" style="color: var(--accent);">Peserta</span>!</h2>
                    <p style="margin:0;">Pilih modul di sebelah kiri untuk mulai mempelajari materi.</p>
                </div>
                <button onclick="toggleAdminModal()" class="btn-secondary" style="width: auto; padding: 10px 20px;">+ Tambah Modul (Admin)</button>
            </div>

            <div class="lms-grid">
                <div class="glass-panel">
                    <h3>Daftar Modul</h3>
                    <ul class="module-list" id="module-list-container">
                        </ul>
                    <button onclick="resetModules()" class="btn-danger" style="font-size: 0.9rem;">Reset Modul ke Default</button>
                </div>

                <div class="glass-panel">
                    <h2 id="content-title">Pilih Modul</h2>
                    <div class="video-container" id="content-video-container">
                        <iframe id="content-video" src="" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <div id="content-text" style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.8;">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- 1. DATA MANAJEMEN MODUL (Menyimpan di Browser LocalStorage) ---
        const defaultModules = [
            {
                id: 1,
                title: "1. Pengenalan Sistem Jawwada",
                video: "https://www.youtube.com/embed/tgbNymZ7vqY",
                content: "Selamat datang di Jawwada LMS. Pada modul pertama ini, kita akan mempelajari navigasi dasar, cara mengakses materi, dan pentingnya adaptasi digital dalam pendidikan modern."
            },
            {
                id: 2,
                title: "2. Manajemen KBM & Evaluasi",
                video: "https://www.youtube.com/embed/dQw4w9WgXcQ",
                content: "Modul ini membahas tentang proses Kegiatan Belajar Mengajar (KBM) dan bagaimana ujian bulanan diintegrasikan ke dalam sistem. Pastikan Anda menonton video hingga selesai."
            }
        ];

        // Ambil modul dari LocalStorage, jika kosong gunakan default
        let modules = JSON.parse(localStorage.getItem('jawwada_modules')) || defaultModules;
        let currentModuleId = null;

        // --- 2. RENDER SISTEM LMS ---
        function renderSidebar() {
            const container = document.getElementById('module-list-container');
            container.innerHTML = ''; // Bersihkan list

            modules.forEach(mod => {
                const li = document.createElement('li');
                li.className = `module-item ${mod.id === currentModuleId ? 'active' : ''}`;
                li.innerHTML = `<span>${mod.title}</span>`;
                li.onclick = () => loadModule(mod.id);
                container.appendChild(li);
            });
        }

        function loadModule(id) {
            currentModuleId = id;
            renderSidebar(); // Update UI list aktif

            const mod = modules.find(m => m.id === id);
            if(mod) {
                document.getElementById('content-title').innerText = mod.title;
                document.getElementById('content-video').src = mod.video;
                document.getElementById('content-text').innerHTML = mod.content;
            }
        }

        // --- 3. ALUR PENGGUNA (Form Promosi -> LMS) ---
        document.getElementById('promoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const nama = document.getElementById('nama_peserta').value;
            document.getElementById('user-display').innerText = nama;

            // Transisi Tampilan
            document.getElementById('promo-section').style.display = 'none';
            document.getElementById('lms-workspace').style.display = 'block';

            // Muat modul pertama secara otomatis jika ada
            if(modules.length > 0) {
                loadModule(modules[0].id);
            }
        });

        // --- 4. FITUR ADMIN (KUSTOMISASI MODUL) ---
        function toggleAdminModal() {
            const modal = document.getElementById('admin-modal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }

        document.getElementById('adminForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Buat objek modul baru
            const newModule = {
                id: Date.now(), // Gunakan timestamp sebagai ID unik
                title: document.getElementById('mod_title').value,
                video: document.getElementById('mod_video').value,
                content: document.getElementById('mod_content').value
            };

            // Simpan ke array dan LocalStorage
            modules.push(newModule);
            localStorage.setItem('jawwada_modules', JSON.stringify(modules));

            // Reset form dan tutup modal
            this.reset();
            toggleAdminModal();
            renderSidebar();
            
            alert('Modul baru berhasil ditambahkan!');
        });

        function resetModules() {
            if(confirm("Apakah Anda yakin ingin mereset semua modul kembali ke pengaturan awal (default)? Modul yang Anda tambahkan akan terhapus.")) {
                localStorage.removeItem('jawwada_modules');
                modules = defaultModules;
                renderSidebar();
                loadModule(modules[0].id);
            }
        }

        // Inisialisasi awal (Render sidebar meskipun belum login)
        renderSidebar();
    </script>
</body>
</html>