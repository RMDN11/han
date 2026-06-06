<div class="glass-card p-5 mb-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shrink-0">
                <i class="fas <?= $header_icon ?? 'fa-clipboard-list' ?> text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-800 tracking-tight"><?= $header_title ?? 'DASHBOARD' ?></h1>
                <p class="text-xs text-gray-500 mt-0.5" style="letter-spacing: -0.02em;"><?= $header_subtitle ?? 'PERKEMBANGAN HAFALAN SANTRI' ?></p>
            </div>
        </div>
        
        <div class="flex items-center gap-3 sm:justify-end mt-2 sm:mt-0 pt-3 sm:pt-0 border-t sm:border-0 border-gray-100 w-full sm:w-auto">
            <img src="SYTB.png" alt="Logo SYTB" class="h-10 w-auto object-contain shrink-0 drop-shadow-sm">
            
            <div class="text-left sm:text-right">
                <p class="text-sm font-medium text-gray-700" style="letter-spacing: -0.01em;">MAHAD IMAM SYATHBY BOGOR</p>
                <p class="text-xs text-gray-400">ASRAMA TAHFIDZ QURAN</p>
            </div>
        </div>

    </div>
</div>