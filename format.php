<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Format Nomor WA - 62</title>
    
    <!-- Tailwind CSS untuk styling responsif & cepat -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Background Soft & Clean Gradient */
        body {
            /* Perpaduan warna pastel yang sangat lembut (soft pink ke soft blue) */
            background: linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%);
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Efek Glassmorphism Clean */
        .glass-panel {
            background: rgba(255, 255, 255, 0.45);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 1.5rem;
        }

        /* Efek Glass untuk Input/Textarea */
        .glass-input {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.6);
            color: #1e293b; /* Warna teks gelap agar terbaca jelas */
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }
        
        .glass-input::placeholder {
            color: #64748b; /* Warna placeholder soft gray */
        }

        .glass-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.8);
            border-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
        }

        /* Scrollbar kustom soft */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.25); }
    </style>
</head>
<body class="flex items-center justify-center p-4 md:p-8">

    <div class="glass-panel w-full max-w-4xl p-6 md:p-8 animate-[fadeIn_0.5s_ease-out]">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-slate-800 mb-2">
                <i class="fab fa-whatsapp text-green-500 mr-2"></i>+62
            </h1>
            <p class="text-slate-600"></p>
        </div>

        <!-- Grid Layout untuk Textarea -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
            
            <!-- Kolom Input -->
            <div class="flex flex-col">
                <label class="text-slate-700 font-bold mb-2 flex justify-between items-center">
                    <span><i class="fas fa-file-import text-blue-500 mr-2"></i>Input</span>
                    <span class="text-xs font-semibold text-slate-500 bg-white/50 px-2 py-1 rounded border border-white/60"></span>
                </label>
                <textarea id="inputNumbers" rows="12" 
                    class="glass-input w-full rounded-xl p-4 resize-none"
                    placeholder=""></textarea>
            </div>

            <!-- Tombol Panah (Desktop) / Bawah (Mobile) -->
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 hidden md:flex items-center justify-center w-12 h-12 rounded-full glass-panel z-10 text-slate-600 shadow-sm border border-white/80">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="flex justify-center md:hidden -my-2 z-10 relative">
                <div class="w-10 h-10 rounded-full glass-panel flex items-center justify-center text-slate-600 shadow-sm border border-white/80">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>

            <!-- Kolom Output -->
            <div class="flex flex-col">
                <label class="text-slate-700 font-bold mb-2 flex justify-between items-center">
                    <span><i class="fas fa-file-export text-emerald-500 mr-2"></i>Hasil</span>
                    <span id="countBadge" class="text-xs font-semibold text-blue-600 bg-blue-100/60 px-2 py-1 rounded border border-blue-200 hidden">0 Nomor</span>
                </label>
                <textarea id="outputNumbers" rows="12" readonly
                    class="glass-input w-full rounded-xl p-4 resize-none cursor-text"
                    placeholder=""></textarea>
            </div>
            
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex flex-wrap gap-4 justify-center">
            <button onclick="clearAll()" class="px-6 py-3 rounded-xl font-semibold text-slate-600 bg-white/40 hover:bg-white/70 border border-white/60 transition-all shadow-sm">
                <i class="fas fa-trash-alt mr-2"></i>Bersihkan
            </button>
            <button onclick="formatNumbers()" class="px-8 py-3 rounded-xl font-bold text-white bg-blue-500 hover:bg-blue-600 hover:-translate-y-1 transition-all shadow-md">
                <i class="fas fa-magic mr-2"></i>Format Sekarang
            </button>
            <button onclick="copyToClipboard()" id="copyBtn" class="px-6 py-3 rounded-xl font-semibold text-white bg-emerald-500 hover:bg-emerald-600 transition-all shadow-md">
                <i class="fas fa-copy mr-2"></i>Salin Hasil
            </button>
        </div>

    </div>

    <!-- Script Logika -->
    <script>
        function formatNumbers() {
            const input = document.getElementById('inputNumbers').value;
            if (!input.trim()) return;

            // Pisahkan berdasarkan baris baru
            const lines = input.split('\n');
            let validCount = 0;
            
            const formatted = lines.map(line => {
                // 1. Bersihkan semua karakter selain angka (menghapus spasi, strip, tanda +)
                let num = line.replace(/\D/g, '');
                
                if (!num) return null; // Abaikan baris kosong

                // 2. Logika perbaikan nomor
                if (num.startsWith('0')) {
                    // Jika mulai dari 0, ubah 0 jadi 62
                    num = '62' + num.substring(1);
                } else if (num.startsWith('8')) {
                    // Jika mulai dari 8, tambahkan 62 di depannya
                    num = '62' + num;
                }
                
                // Jika sudah berawalan 62, biarkan saja
                validCount++;
                return num;
            }).filter(n => n !== null); // Hapus hasil null/kosong

            // Tampilkan hasil
            document.getElementById('outputNumbers').value = formatted.join('\n');
            
            // Update badge jumlah
            const badge = document.getElementById('countBadge');
            badge.textContent = `${validCount} Nomor`;
            badge.classList.remove('hidden');
        }

        function copyToClipboard() {
            const output = document.getElementById('outputNumbers');
            const copyBtn = document.getElementById('copyBtn');
            
            if (!output.value) {
                alert("Tidak ada hasil untuk disalin!");
                return;
            }

            output.select();
            output.setSelectionRange(0, 99999); // Untuk mobile devices

            navigator.clipboard.writeText(output.value).then(() => {
                // Ubah tombol jadi status "Tersalin"
                const originalHTML = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Tersalin!';
                copyBtn.classList.replace('bg-emerald-500', 'bg-slate-700');
                copyBtn.classList.replace('hover:bg-emerald-600', 'hover:bg-slate-800');
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalHTML;
                    copyBtn.classList.replace('bg-slate-700', 'bg-emerald-500');
                    copyBtn.classList.replace('hover:bg-slate-800', 'hover:bg-emerald-600');
                }, 2000);
            });
        }

        function clearAll() {
            document.getElementById('inputNumbers').value = '';
            document.getElementById('outputNumbers').value = '';
            document.getElementById('countBadge').classList.add('hidden');
            document.getElementById('inputNumbers').focus();
        }
    </script>
</body>
</html>