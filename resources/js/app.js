const navigationToggle = document.querySelector('[data-nav-toggle]');
const mobileNavigation = document.querySelector('[data-mobile-nav]');

if (navigationToggle && mobileNavigation) {
    navigationToggle.addEventListener('click', () => {
        const isOpen = mobileNavigation.dataset.state === 'open';

        mobileNavigation.dataset.state = isOpen ? 'closed' : 'open';
        navigationToggle.setAttribute('aria-expanded', String(! isOpen));
    });
}

const currencyInputs = document.querySelectorAll('[data-currency-input]');

const formatRupiahInput = (input) => {
    const digits = input.value.replace(/\D/g, '');

    input.value = digits === '' ? '' : new Intl.NumberFormat('id-ID').format(Number(digits));
};

currencyInputs.forEach((input) => {
    formatRupiahInput(input);
    input.addEventListener('input', () => formatRupiahInput(input));
    input.form?.addEventListener('submit', () => {
        input.value = input.value.replace(/\D/g, '');
    });
});

const importForm = document.querySelector('[data-import-form]');

if (importForm) {
    const progressPanel = importForm.querySelector('[data-import-progress]');
    const progressTitle = importForm.querySelector('[data-import-progress-title]');
    const progressMessage = importForm.querySelector('[data-import-progress-message]');
    const progressPercent = importForm.querySelector('[data-import-progress-percent]');
    const progressBar = importForm.querySelector('[data-import-progress-bar]');
    const submitButton = importForm.querySelector('[data-import-submit]');

    const showProgress = (percentage, title, message) => {
        progressPanel.classList.remove('hidden');
        progressTitle.textContent = title;
        progressMessage.textContent = message;
        progressPercent.textContent = `${percentage}%`;
        progressBar.style.width = `${percentage}%`;
    };

    const watchImport = (statusUrl) => {
        const poll = async () => {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            const batch = await response.json();
            const message = batch.message || (batch.status === 'uploaded'
                ? 'File sudah tersimpan. Menunggu worker memulai pemrosesan.'
                : 'Data sedang divalidasi dan disimpan.');

            showProgress(batch.progress, batch.status === 'ready' ? 'Impor selesai' : 'Memproses data…', message);

            if (batch.status === 'ready') {
                progressMessage.textContent = `${batch.accepted_rows ?? 0} data diterima, ${batch.rejected_rows ?? 0} data ditolak. Memuat ulang daftar impor…`;
                window.setTimeout(() => window.location.reload(), 1600);

                return true;
            }

            if (batch.status === 'failed') {
                progressTitle.textContent = 'Impor gagal';
                submitButton.disabled = false;
                submitButton.textContent = 'Upload dan validasi';

                return true;
            }

            return false;
        };

        const interval = window.setInterval(async () => {
            try {
                if (await poll()) {
                    window.clearInterval(interval);
                }
            } catch {
                showProgress(55, 'Menunggu koneksi…', 'Status impor belum dapat diperiksa. Sistem akan mencoba kembali.');
            }
        }, 2500);

        poll().catch(() => showProgress(55, 'Menunggu worker…', 'File tersimpan. Status akan diperbarui otomatis.'));
    };

    importForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        submitButton.textContent = 'Mengunggah…';
        showProgress(5, 'Mengunggah file…', 'File sedang dikirim dan disimpan secara aman.');

        const request = new XMLHttpRequest();
        request.open('POST', importForm.action);
        request.setRequestHeader('Accept', 'application/json');
        request.upload.addEventListener('progress', (progress) => {
            if (progress.lengthComputable) {
                showProgress(Math.max(5, Math.round((progress.loaded / progress.total) * 35)), 'Mengunggah file…', 'File sedang dikirim ke server.');
            }
        });
        request.addEventListener('load', () => {
            if (request.status < 200 || request.status >= 300) {
                submitButton.disabled = false;
                submitButton.textContent = 'Upload dan validasi';
                showProgress(100, 'Unggah gagal', 'Periksa koneksi, Google Drive, dan format file lalu coba kembali.');

                return;
            }

            const result = JSON.parse(request.responseText);
            showProgress(45, 'File tersimpan', result.message);
            submitButton.textContent = 'Sedang diproses…';
            watchImport(result.status_url);
        });
        request.addEventListener('error', () => {
            submitButton.disabled = false;
            submitButton.textContent = 'Upload dan validasi';
            showProgress(100, 'Unggah gagal', 'Koneksi terputus sebelum file selesai dikirim.');
        });
        request.send(new FormData(importForm));
    });
}
