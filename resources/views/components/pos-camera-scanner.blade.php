<div x-data="posCameraScanner()"
     x-show="scannerOpen"
     x-cloak
     data-pos-camera-scanner
     @open-pos-scanner.window="startScanner()"
     @close-pos-scanner.window="stopScanner()"
     @keydown.escape.window="stopScanner()"
     class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-md"
     role="dialog" aria-modal="true" aria-label="{{ __('Barcode & QR Scanner') }}"
     style="display: none;">
    <div class="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col"
         @click.outside="stopScanner()">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-white dark:bg-slate-900 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold shrink-0">📷</span>
                <div class="min-w-0"><h3 class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">{{ __('Barcode & QR Scanner') }}</h3><p class="text-[10px] text-slate-400 truncate">{{ __('Point camera at product barcode or package QR') }}</p></div>
            </div>
            <button type="button" @click="stopScanner()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-600 flex items-center justify-center text-sm font-bold shrink-0">✕</button>
        </div>
        <div class="relative w-full bg-black aspect-square sm:aspect-video flex items-center justify-center overflow-hidden isolate">
            <div id="pos-interactive-video" class="absolute inset-0 w-full h-full overflow-hidden [&_video]:!absolute [&_video]:!inset-0 [&_video]:!w-full [&_video]:!h-full [&_video]:!object-cover [&_canvas]:!absolute [&_canvas]:!inset-0 [&_canvas]:!w-full [&_canvas]:!h-full"></div>
            <div class="absolute inset-0 z-10 pointer-events-none flex items-center justify-center p-8 overflow-hidden">
                <div class="relative w-[min(16rem,80vw)] h-40 border-2 border-blue-500/80 rounded-2xl shadow-[0_0_0_9999px_rgba(0,0,0,0.45)] overflow-hidden">
                    <div class="absolute inset-x-0 top-1/2 h-0.5 bg-gradient-to-r from-transparent via-red-500 to-transparent animate-pulse"></div>
                </div>
            </div>
            <div x-show="isCameraLoading" class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-slate-900 text-white gap-2 text-xs"><span class="animate-spin text-xl">⏳</span><span>{{ __('Starting camera video feed...') }}</span></div>
        </div>
        <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs bg-slate-50 dark:bg-slate-900 shrink-0">
            <span class="text-[11px] text-slate-500" x-text="cameraStatusText"></span>
            <button type="button" @click="toggleTorch()" x-show="hasTorch" class="px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 font-semibold text-[11px]">🔦 {{ __('Flashlight') }}</button>
        </div>
    </div>
</div>
