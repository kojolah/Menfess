/**
 * QRIS InterActive Helpers & Payment Timer
 * Handles QRIS copy, image download, expiration countdown, and check-status cooldown.
 */
(function () {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Copy QR string button
        const copyBtn = document.getElementById('copy-qris-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const text = copyBtn.dataset.content || '';
                if (text) {
                    navigator.clipboard.writeText(text).then(() => {
                        const originalText = copyBtn.innerText;
                        copyBtn.innerText = '✓ Berhasil Disalin';
                        setTimeout(() => { copyBtn.innerText = originalText; }, 2000);
                    }).catch(() => {
                        // Fallback prompt if clipboard API blocked
                        window.prompt('Salin string QRIS:', text);
                    });
                }
            });
        }

        // Download QR as image
        const downloadBtn = document.getElementById('download-qris-btn');
        const qrisImg = document.getElementById('qris-image');
        if (downloadBtn && qrisImg) {
            downloadBtn.addEventListener('click', async () => {
                const filename = (downloadBtn.dataset.filename || 'qris-payment') + '.png';
                try {
                    const response = await fetch(qrisImg.src, { mode: 'cors' });
                    const blob = await response.blob();
                    const objectUrl = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(objectUrl);
                } catch {
                    // Fallback: open image in new window/tab for user to save
                    window.open(qrisImg.src, '_blank');
                }
            });
        }

        // Countdown Timer for Expiration (30 minutes)
        const timerEl = document.getElementById('qris-countdown');
        if (timerEl && timerEl.dataset.expiresAt) {
            const expiresAt = new Date(timerEl.dataset.expiresAt).getTime();
            function updateTimer() {
                const now = Date.now();
                const diff = expiresAt - now;
                if (diff <= 0) {
                    timerEl.innerHTML = '<span class="text-rose-600 font-bold">Kedaluwarsa</span>';
                    const checkBtn = document.getElementById('btn-check-status');
                    if (checkBtn) {
                        checkBtn.disabled = true;
                        checkBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                    return;
                }
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                timerEl.textContent = `${minutes}m ${seconds < 10 ? '0' : ''}${seconds}s`;
            }
            updateTimer();
            setInterval(updateTimer, 1000);
        }

        // Check status button cooldown
        const checkBtn = document.getElementById('btn-check-status');
        const cooldownEl = document.getElementById('check-cooldown-text');
        if (checkBtn && cooldownEl) {
            let remaining = parseInt(cooldownEl.dataset.seconds || '0', 10);
            if (remaining > 0) {
                checkBtn.disabled = true;
                checkBtn.classList.add('opacity-50', 'cursor-not-allowed');
                const interval = setInterval(() => {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(interval);
                        checkBtn.disabled = false;
                        checkBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        cooldownEl.textContent = '';
                    } else {
                        cooldownEl.textContent = `Tunggu ${remaining}s sebelum cek ulang...`;
                    }
                }, 1000);
            }
        }
    });
})();
