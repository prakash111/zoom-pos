import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

export function posCameraScanner() {
    return {
        scannerOpen: false,
        isCameraLoading: false,
        hasTorch: false,
        torchEnabled: false,
        cameraStatusText: 'Ready',
        html5QrCode: null,
        scanLocked: false,

        async startScanner() {
            if (this.scannerOpen || this.isCameraLoading) return;
            this.scannerOpen = true;
            this.isCameraLoading = true;
            this.scanLocked = false;
            this.cameraStatusText = 'Requesting camera permissions...';
            document.documentElement.classList.add('overflow-hidden');
            await this.$nextTick();

            try {
                this.html5QrCode ??= new Html5Qrcode('pos-interactive-video', {
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                    ],
                    verbose: false,
                });
                await this.html5QrCode.start(
                    { facingMode: { ideal: 'environment' } },
                    { fps: 15, qrbox: (width, height) => ({ width: Math.min(280, width * 0.78), height: Math.min(170, height * 0.52) }), aspectRatio: 1.7778 },
                    code => this.onScanSuccess(code),
                    () => {},
                );
                this.isCameraLoading = false;
                this.cameraStatusText = 'Camera active';
                const capabilities = this.html5QrCode.getRunningTrackCapabilities?.();
                this.hasTorch = Boolean(capabilities?.torch);
            } catch (error) {
                this.isCameraLoading = false;
                this.cameraStatusText = 'Camera unavailable or permission denied';
                console.error('Camera Scanner Error:', error);
            }
        },

        async onScanSuccess(code) {
            if (this.scanLocked) return;
            this.scanLocked = true;
            window.playAddToCartBeep?.();
            await this.stopScanner();
            window.Livewire?.dispatch('barcode-scanned', { code });
        },

        async toggleTorch() {
            if (!this.hasTorch || !this.html5QrCode?.isScanning) return;
            this.torchEnabled = !this.torchEnabled;
            try {
                await this.html5QrCode.applyVideoConstraints({ advanced: [{ torch: this.torchEnabled }] });
            } catch (error) {
                this.torchEnabled = false;
                this.hasTorch = false;
            }
        },

        async stopScanner() {
            try {
                if (this.html5QrCode?.isScanning) await this.html5QrCode.stop();
            } catch (error) {
                console.warn('Camera scanner cleanup failed:', error);
            } finally {
                this.scannerOpen = false;
                this.isCameraLoading = false;
                this.hasTorch = false;
                this.torchEnabled = false;
                this.cameraStatusText = 'Ready';
                document.documentElement.classList.remove('overflow-hidden');
            }
        },
    };
}

window.posCameraScanner = posCameraScanner;

document.addEventListener('livewire:navigating', () => {
    document.querySelector('[data-pos-camera-scanner]')?.dispatchEvent(new CustomEvent('close-pos-scanner'));
});
