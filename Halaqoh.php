<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farhan Halaqoh · Reqra</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --colors-canvas-parchment: #f5f5f7;
            --colors-ink: #1d1d1f;
            --colors-primary: #0066cc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--colors-canvas-parchment);
            color: var(--colors-ink);
            overflow: hidden; /* Prevent body scroll, let iframe handle it */
        }

        /* Glassmorphism Sidebar */
        .glass-sidebar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-left: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: -8px 0 32px rgba(0, 0, 0, 0.05);
        }

        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        /* Nav Items Style */
        .nav-item {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .nav-item:hover {
            background: rgba(0, 102, 204, 0.04);
            transform: translateX(-4px);
        }

        .nav-item.active {
            background: #f0f7ff;
            color: var(--colors-primary);
            font-weight: 600;
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 10%;
            height: 80%;
            width: 4px;
            background: var(--colors-primary);
            border-radius: 4px 0 0 4px;
            box-shadow: -2px 0 8px rgba(0, 102, 204, 0.4);
        }

        /* Animations */
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-slide-in {
            animation: slideInRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .stagger-1 { animation-delay: 0.1s; opacity: 0; }
        .stagger-2 { animation-delay: 0.2s; opacity: 0; }
        .stagger-3 { animation-delay: 0.3s; opacity: 0; }

        /* Loader inside iframe wrapper */
        .iframe-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }

        #frameLoader {
            position: absolute;
            inset: 0;
            background: var(--colors-canvas-parchment);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 102, 204, 0.1);
            border-radius: 50%;
            border-top-color: var(--colors-primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex flex-col md:flex-row h-screen w-full">

    <header class="md:hidden glass-header flex items-center justify-between p-4 z-40 fixed top-0 w-full">
        <div class="flex items-center gap-2 animate-fade-in">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow">
                <i class="fas fa-chalkboard-user text-xs"></i>
            </div>
            <div class="font-semibold text-gray-800 text-sm tracking-wide">Portal Saye</div>
        </div>
        <button id="menuOpen" class="text-gray-600 hover:text-blue-600 p-2 focus:outline-none transition-colors">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </header>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 hidden md:hidden transition-opacity opacity-0"></div>

    <main class="flex-1 w-full h-full pt-[64px] md:pt-0 md:mr-72 relative transition-all duration-300 z-10">
        <div class="iframe-wrapper">
            <div id="frameLoader">
                <div class="flex flex-col items-center">
                    <div class="spinner mb-3"></div>
                    <div class="text-xs text-gray-400 font-medium tracking-wide">Memuat halaman...</div>
                </div>
            </div>
            
            <iframe id="mainFrame" name="mainFrame" src="tes.php" class="w-full h-full border-0" title="Main Content"></iframe>
        </div>
    </main>

    <aside id="sidebar" class="glass-sidebar w-72 h-screen fixed right-0 top-0 z-50 transform translate-x-full md:translate-x-0 transition-transform duration-400 ease-in-out flex flex-col">
        
        <div class="p-6 pb-4 flex items-center justify-between border-b border-gray-200/50">
            <div class="flex items-center gap-3 animate-slide-in">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-chalkboard-user text-lg"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-800 tracking-tight">Dashboard Farhan</h2>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-0.5">Halaqoh Ma'had</p>
                </div>
            </div>
            <button id="menuClose" class="md:hidden w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 mt-4 px-3 animate-fade-in">Menu Utama</div>
            
            <a href="tes.php" target="mainFrame" class="nav-item active animate-slide-in stagger-1 flex items-center gap-3.5 px-4 py-3.5 rounded-xl text-sm font-medium text-gray-600">
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center">
                    <i class="fas fa-pen-to-square"></i>
                </div>
                Input Muroja'ah
            </a>

            <a href="kualitas.php" target="mainFrame" class="nav-item animate-slide-in stagger-2 flex items-center gap-3.5 px-4 py-3.5 rounded-xl text-sm font-medium text-gray-600">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center">
                    <i class="fas fa-chart-line"></i>
                </div>
                Kualitas Hafalan
            </a>

            <a href="rekap.php" target="mainFrame" class="nav-item animate-slide-in stagger-3 flex items-center gap-3.5 px-4 py-3.5 rounded-xl text-sm font-medium text-gray-600">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                Rekap Pekanan
            </a>
        </nav>

        <div class="p-5 border-t border-gray-200/50 bg-white/30 backdrop-blur-md">
            <div class="flex items-center gap-3 animate-fade-in">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-gray-100 to-gray-200 border border-white shadow-inner flex items-center justify-center text-gray-400">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-700">Akun Pengajar</p>
                    <p class="text-[10px] text-gray-400 mt-0.5"><i class="fas fa-circle text-green-400 text-[6px] align-middle mr-1"></i> Online</p>
                </div>
            </div>
            <div class="mt-4 text-center">
                <p class="text-[10px] text-gray-400 tracking-wide">&copy; <?= date('Y') ?> Reqra by Han</p>
            </div>
        </div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuOpen = document.getElementById('menuOpen');
            const menuClose = document.getElementById('menuClose');
            const navItems = document.querySelectorAll('.nav-item');
            const mainFrame = document.getElementById('mainFrame');
            const frameLoader = document.getElementById('frameLoader');

            // --- Fungsi Toggle Sidebar Mobile ---
            function openSidebar() {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            }

            function closeSidebar() {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }

            menuOpen.addEventListener('click', openSidebar);
            menuClose.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);

            // --- Fungsi Active Link & Iframe Loader ---
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // Update class active
                    navItems.forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');

                    // Tampilkan loader saat iframe berpindah
                    frameLoader.style.opacity = '1';

                    // Tutup sidebar di versi mobile setelah link diklik
                    if (window.innerWidth < 768) {
                        closeSidebar();
                    }
                });
            });

            // Hilangkan loader saat iframe selesai dimuat
            mainFrame.addEventListener('load', function() {
                frameLoader.style.opacity = '0';
            });
        });
    </script>
</body>
</html>