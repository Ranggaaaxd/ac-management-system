<div
    x-data="{
        scanner: null,
        status: 'Memuat kamera...',
        successful: false,
        processing: false,
        lookupUrl: '',

        async start() {
            this.lookupUrl = $el.dataset.lookupUrl;

            try {
                await this.loadLibrary();
                this.scanner = new Html5Qrcode('air-conditioner-qr-reader');
                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (assetCode) => this.onDetected(assetCode),
                    () => {},
                );
                this.status = 'Arahkan kamera ke QR Code pada unit AC.';
            } catch (error) {
                this.status = 'Kamera tidak dapat diaktifkan. Pastikan izin kamera diberikan dan halaman dibuka melalui HTTPS atau localhost.';
            }
        },

        loadLibrary() {
            if (window.Html5Qrcode) {
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/html5-qrcode';
                script.onload = resolve;
                script.onerror = () => reject(new Error('Gagal memuat library QR scanner.'));
                document.head.appendChild(script);
            });
        },

        async onDetected(assetCode) {
            if (this.processing) {
                return;
            }

            this.processing = true;
            this.status = 'QR terdeteksi. Memeriksa kode aset...';

            try {
                const response = await fetch(this.lookupUrl.replace('__ASSET_CODE__', encodeURIComponent(assetCode)), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    this.status = response.status === 404
                        ? 'Kode QR tidak cocok dengan aset yang terdaftar.'
                        : 'Gagal membuka data aset. Silakan coba kembali.';
                    this.processing = false;
                    return;
                }

                const result = await response.json();
                this.successful = true;
                this.status = 'Unit ditemukan. Mengalihkan ke detail AC...';
                await this.stop();
                this.beep();
                window.location.assign(result.url);
            } catch (error) {
                this.status = 'Tidak dapat memeriksa QR Code. Periksa koneksi lalu coba kembali.';
                this.processing = false;
            }
        },

        async stop() {
            if (this.scanner?.isScanning) {
                await this.scanner.stop();
                this.scanner.clear();
            }
        },

        beep() {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;

            if (!AudioContextClass) {
                return;
            }

            const context = new AudioContextClass();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.frequency.value = 880;
            gain.gain.value = 0.08;
            oscillator.start();
            oscillator.stop(context.currentTime + 0.12);
            oscillator.onended = () => context.close();
        },

        destroy() {
            this.stop();
        },
    }"
    x-init="start()"
    data-lookup-url="{{ route('admin.air-conditioners.scan', ['assetCode' => '__ASSET_CODE__']) }}"
    class="space-y-4"
>
    <div id="air-conditioner-qr-reader" class="mx-auto max-w-xl overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"></div>
    <p
        x-text="status"
        x-bind:class="successful ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-300'"
        class="text-center text-sm"
        role="status"
        aria-live="polite"
    ></p>
</div>
