# Campus Menfess Platform

Platform Laravel untuk menerima kiriman anonim kampus, memoderasi konten, membuat gambar PNG dari template HTML, dan mengelola antrean publikasi. Identitas akun pengirim tidak ditampilkan pada desain publik.

## Stack dan status saat ini

- Laravel 13, PHP 8.3+, Blade, Livewire 4, Tailwind CSS, dan MySQL.
- Google OAuth melalui Laravel Socialite.
- Wallet berbasis ledger dan checkout Midtrans Snap; notifikasi settlement diverifikasi di backend.
- Renderer HTML/CSS ke PNG memakai Playwright Chromium melalui Node.
- Queue Laravel dengan driver database sebagai default.
- Adapter Instagram Graph API dan X API tersedia. `PUBLISHER_MODE=simulation` adalah default; simulasi tidak membuat post dan memberi status `needs_review`.
- Publikasi live tetap memerlukan kredensial Meta/X/Cloudinary yang sah dan lingkungan yang menjalankan Chromium. Token dipotong melalui ledger idempotent saat reservasi publikasi; hasil API yang tidak pasti ditahan untuk peninjauan.

## Menjalankan lokal

Persyaratan: PHP 8.3+, Composer, Node.js/npm, MySQL 8+, dan Chromium yang dapat dijalankan pada host aplikasi.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi nama database MySQL dan kredensial pada `.env`, kemudian:

```bash
php artisan migrate --seed
npm install --ignore-scripts
npx playwright install chromium
npm run build
```

Jalankan proses web dan worker di terminal terpisah:

```bash
php artisan serve
php artisan queue:work --queue=default --tries=1
```

Untuk perubahan frontend saat mengembangkan, gunakan `npm run dev`. Migrasi dan seed dapat diulang aman pada instalasi yang sudah berjalan: `php artisan migrate --seed`.

## Google OAuth

1. Buka [Google Cloud Console](https://console.cloud.google.com/), pilih atau buat project, lalu buka **Google Auth Platform → Branding** untuk mengisi nama aplikasi dan email dukungan. Set audience sesuai komunitas yang akan login.
2. Buka **Google Auth Platform → Clients → Create client**, pilih **Web application**, lalu tambahkan callback URI yang tepat. Untuk instalasi lokal gunakan `http://localhost:8000/auth/google/callback`; untuk production gunakan `https://<domain>/auth/google/callback`. Skema, host, port, path, dan trailing slash harus cocok persis dengan `GOOGLE_REDIRECT_URI`.
3. Salin Client ID dan Client Secret ke `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` di `.env`, lalu set `GOOGLE_REDIRECT_URI`. Nilai `.env.example` sengaja kosong; jangan commit kredensial asli atau file secret Google.
4. Setelah mengubah environment, jalankan `php artisan config:clear`. Pastikan domain production memakai HTTPS dan halaman branding/privasi aplikasi telah diisi sebelum membuka login ke publik.
5. Login awal memberi role `user`. Aplikasi meminta identitas dasar, menolak email yang tidak terverifikasi, tidak menggabungkan akun lama berdasarkan email saja, dan menolak akun nonaktif.

Panduan resmi: [membuat kredensial OAuth untuk web server](https://developers.google.com/identity/protocols/oauth2/web-server) dan [mengatur layar persetujuan](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid).

Tidak ada UI manajemen role pada versi ini. Untuk bootstrap super admin pertama setelah akun dibuat, jalankan `php artisan tinker`, lalu:

```php
$user = \App\Models\User::where('email', 'admin@kampus.edu')->firstOrFail();
$user->forceFill(['role' => \App\UserRole::SuperAdmin->value])->save();
```

Jalankan perubahan role hanya dari console tepercaya dan catat admin yang ditetapkan.

## Cloudinary untuk gambar

Semua PNG final hasil render diunggah ke Cloudinary melalui backend. Aplikasi menyimpan `public_id` dan `secure_url` Cloudinary sebagai metadata gambar; Instagram mengambil gambar dari URL HTTPS tersebut, dan unduhan aplikasi juga membaca aset dari Cloudinary. File render hanya dibuat sementara di direktori temporer server lalu dihapus setelah upload. Foto sumber yang diunggah pengguna disimpan pada disk `local` privat (`storage/app/private`) dan hanya disajikan melalui route terotorisasi; aplikasi tidak menyimpan render atau lampiran ke disk `public`.

1. Buat akun/cloud di [Cloudinary Console](https://cloudinary.com/console), lalu ambil **Cloud name**, **API Key**, dan **API Secret** dari dashboard/API Keys.
2. Isi `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, dan `CLOUDINARY_API_SECRET` di environment server. API Secret hanya boleh tersedia di backend atau secret manager; jangan masukkan ke Blade, JavaScript, atau repository.
3. Jalankan `php artisan config:clear` setelah mengubah `.env` (atau rebuild config cache pada deployment). Renderer dan preview memerlukan konfigurasi Cloudinary yang lengkap.
4. Tes memakai fake HTTP Cloudinary dan tidak mengunggah file sungguhan. Konfigurasi live harus diverifikasi dengan satu render uji setelah kredensial Cloudinary ditetapkan.

Preview diunggah sebagai aset `authenticated`, dikembalikan kepada browser sebagai data preview, lalu dihapus. PNG final yang dipakai untuk publikasi menggunakan delivery type `upload` agar API Meta dapat mengambil URL publik HTTPS. Jika penghapusan preview sementara gagal, aplikasi mencatat `public_id` untuk investigasi; bersihkan aset tersebut dari Media Library Cloudinary.

## Midtrans Snap

1. Atur `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, dan `MIDTRANS_IS_PRODUCTION=false` untuk sandbox.
2. Daftarkan notification URL `https://<domain>/webhooks/midtrans` pada dashboard Midtrans.
3. Harga dan jumlah token top-up berasal dari paket di database; redirect sukses tidak memberi saldo. Wallet hanya dikreditkan oleh notifikasi valid dengan order, nominal, dan signature yang cocok.
4. Jalankan transaksi sandbox sebelum mengaktifkan kredensial produksi. Kredensial Midtrans tidak disertakan dan live checkout belum diuji.

## Instagram Graph API dan X API

Mode awal aman adalah `PUBLISHER_MODE=simulation`. Di mode ini tidak ada permintaan publikasi live; status `needs_review` menandai perlunya pemeriksaan manusia.

Untuk Instagram, implementasi memakai **Instagram API with Instagram Login**: `https://graph.instagram.com/{META_GRAPH_VERSION}/{META_INSTAGRAM_ACCOUNT_ID}` dan Instagram User Access Token. Siapkan akun profesional dan berikan permission `instagram_business_basic` serta `instagram_business_content_publish` pada app/token. Alur ini tidak memerlukan Facebook Page tertaut. Isi `META_INSTAGRAM_ACCOUNT_ID` dengan **Instagram professional `user_id`** (bukan Facebook Page ID atau field `id` yang app-scoped) dari `GET /me?fields=user_id,username`; `META_INSTAGRAM_ACCESS_TOKEN` harus token mentah dari Instagram Login. Publisher memvalidasi token dan kecocokan `user_id` sebelum membuat container. Setelah pembuatan container, publisher memeriksa statusnya dan hanya memanggil `media_publish` saat `status_code=FINISHED`. Permintaan publikasi juga akan ditolak Meta jika permission publish tidak tersedia. Isi `META_GRAPH_VERSION` dalam format seperti `v25.0`; opsional, isi `META_INSTAGRAM_ACCESS_TOKEN_EXPIRES_AT` dengan waktu kedaluwarsa ISO-8601. Gambar PNG Cloudinary dikirim ke Meta melalui URL transformasi JPEG Cloudinary; aset asli tetap PNG.

Untuk X, siapkan app dengan akses API yang mendukung upload media dan membuat post; biaya/akses X mengikuti paket akun saat ini. Belum ada UI untuk mengelola akun sosial.

Setelah mengubah environment, jalankan `php artisan config:clear` (atau rebuild cache saat deployment) dan restart queue worker agar token/version baru dimuat. Periksa status permission/token di Meta Developer Dashboard dan dokumentasi resmi [Instagram API with Instagram Login](https://www.postman.com/meta/instagram/folder/6raa77c/instagram-api-with-instagram-login) serta [panduan publikasi konten Meta](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/content-publishing). Jangan aktifkan `PUBLISHER_MODE=live` sebelum token dan akun yang benar diuji, karena job yang antre dapat menerbitkan konten sungguhan. Adapter X memerlukan user access token dari `social_accounts`. Kode menandai hasil yang tidak terkonfirmasi sebagai `needs_review`; periksa status post di platform sebelum retry manual.

### Checklist production

- Deploy ke host dengan domain HTTPS; set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, dan `SESSION_SECURE_COOKIE=true` melalui secret/config host.
- Gunakan MySQL production dan disk storage yang memberi URL HTTPS stabil yang dapat diakses Meta untuk mengambil gambar. URL `localhost` atau storage privat tanpa URL akses tidak dapat dipakai untuk publikasi media.
- Simpan `APP_KEY` persisten dan rahasia. Jangan menggantinya setelah data terenkripsi disimpan.
- Atur Google OAuth callback ke `https://<domain>/auth/google/callback`, dan webhook Midtrans ke `https://<domain>/webhooks/midtrans`. Gunakan kredensial production provider masing-masing hanya setelah pengujian sandbox.
- Default publikasi tetap `PUBLISHER_MODE=simulation`. Sebelum `live`, cek antrean dan status job yang ada, izin Meta, domain media publik HTTPS, akun profesional yang terhubung, serta worker. `live` memproses publikasi nyata.
- Jalankan `php artisan migrate --force`, `php artisan config:cache`, dan `php artisan optimize`; pastikan queue worker dan backup MySQL/storage dikelola process supervisor/platform.

## Filter konten dan auto-publishing

- Kiriman non-draft menjalani filter di server. Kata/frasa aktif dalam `content_filter_rules` diganti dengan `****`; pengecualian aktif di `content_filter_exceptions` menghindari false positive. Teks asli kiriman tidak disimpan sebagai salinan kedua.
- Daftar awal berasal dari `ContentFilterRuleSeeder`. Super admin mengelola aturan dan pengecualian di **Admin → Filter konten otomatis**. Aksi `Sensor` mengganti teks; aksi `Review` menahan kiriman pada pemeriksaan keamanan. Perubahan aturan tercatat di audit log.
- Kiriman bersih membuat job terpisah untuk tiap platform tanpa persetujuan moderator. Email, nomor telepon, nomor identitas, frasa ancaman/doxing yang terdaftar, atau aturan beraksi `Review` tetap menunggu pemeriksaan manusia. Dashboard pengguna menampilkan status setiap platform.
- Job menandai publikasi berhasil hanya setelah adapter menerima ID post dari API. Mode `simulation` tidak menerbitkan konten dan menampilkan `needs review`; hasil yang tidak pasti tidak dicoba ulang otomatis untuk mencegah duplikasi.
- Kuota Instagram mengikuti `INSTAGRAM_FREE_POSTS_PER_DAY` dan `INSTAGRAM_FREE_COOLDOWN_MINUTES`, dengan perhitungan tanggal kalender `Asia/Jakarta`. Kuota dicatat berhasil hanya setelah konfirmasi publikasi Instagram; reservasi aktif mencegah submit paralel memakai kuota yang sama.

## Zona waktu, kuota gratis, dan biaya publikasi

- `APP_TIMEZONE=Asia/Jakarta` mengatur jam aplikasi dan `DB_TIMEZONE=+07:00` menjaga sesi koneksi MySQL tetap pada WIB. Atur variabel tersebut sebelum workers dijalankan dan restart workers setelah deploy.
- Kuota gratis berlaku pada komponen Instagram: dua publikasi sukses per hari WIB, jeda minimal satu jam. Setelah kuota atau jeda tidak memenuhi syarat, Instagram memakai `INSTAGRAM_PUBLICATION_TOKENS` (default 2). X selalu memakai `X_PUBLICATION_TOKENS` (default 1). Menfess Instagram + X menggunakan biaya yang dijumlahkan; bila komponen Instagram gratis, biaya X tetap dikenakan.
- Saldo didebit satu kali saat job publikasi dibuat di dalam transaksi dan dicatat di `wallet_transactions`; unique idempotency key dan row lock mencegah debit ganda atau saldo negatif. Mode `simulation` tidak memanggil API dan mengembalikan biaya yang telah dicadangkan. Job dengan hasil eksternal tidak pasti tetap `needs_review`, menahan biaya/kuota sampai hasil diperiksa; jangan retry sebelum memastikan post belum terbit.
- Paket top-up tetap berasal dari tabel `topup_packages`. Token hanya dikreditkan setelah webhook Midtrans terverifikasi; isi kredensial Midtrans sandbox/production secara terpisah.
- Renderer memilih warna secara acak dari `MENFESS_CANVAS_BACKGROUNDS` per komponen menfess dan menyimpan warna pilihan di metadata menfess agar render final memakai warna yang sama. `MENFESS_CANVAS_BACKGROUND` tetap menjadi fallback untuk kompatibilitas.
- Migration tambahan menyimpan metadata warna: jalankan `php artisan migrate --force` di lingkungan deploy setelah backup dan release kode. Tidak ada operasi penghapusan atau reset data.

## Queue, storage, dan rendering

- Queue tersimpan di MySQL secara default. Worker harus selalu hidup agar publikasi terjadwal dan pekerjaan antrean diproses.
- Foto sumber menfess disimpan pada disk `local` privat. PNG hasil render selalu diunggah ke Cloudinary; file PNG hanya sementara di direktori temp server sebelum dihapus.
- `MenfessImageRenderer` tetap menjadi entry point yang dipakai preview, penyimpanan gambar, dan `ProcessPublication`. Pilih engine melalui `IMAGE_RENDERER`: `playwright` mempertahankan renderer lama, sedangkan `imagick` memakai `ImagickMenfessImageRenderer`, `MenfessTextLayout`, dan `FontManager`. Renderer lama disimpan sebagai jalur rollback selama validasi hosting.
- Engine Imagick membuat layout langsung di PHP (tanpa Node, Chromium, `proc_open`, atau shell). Output portrait Instagram dipatok 1080×1350, square 1080×1080, X landscape 1200×675. Teks dibungkus dengan metrik font dan ukuran diturunkan sampai batas yang ditentukan; menfess panjang tetap dibagi ke slide seperti sebelumnya. Foto diputar mengikuti EXIF, di-crop ke area rounded, lalu hasil PNG diunggah melalui `CloudinaryMediaStorage`. PNG dapat dikonversi ke JPEG dengan `IMAGE_RENDERER_QUALITY` (default 95) bila consumer membutuhkan JPEG.
- `MENFESS_CANVAS_BACKGROUNDS` tetap menjadi palet random per Menfess; `MENFESS_CANVAS_BACKGROUND` fallback dan `MENFESS_CANVAS_ACCENT` accent terpisah. Warna terpilih dari `metadata.canvas_background` dipakai untuk preview/final render. Template/kategori tidak menentukan warna latar.
- Font yang diutamakan adalah Inter Regular/Bold di `resources/fonts/Inter-Regular.ttf` dan `resources/fonts/Inter-Bold.ttf`; quote memakai Georgia atau DejaVu Serif. Atur `MENFESS_FONT_REGULAR`, `MENFESS_FONT_BOLD`, dan `MENFESS_FONT_SERIF` ke path absolut jika diletakkan di lokasi lain. Inter berlisensi SIL Open Font License; ambil dari distribusi resmi dan sertakan file lisensi ketika menyalin font. Linux DejaVu Sans dan macOS Arial menjadi fallback lokal, bukan jaminan font tersedia di cPanel. Emoji hanya tampil jika font/ImageMagick host memiliki glyph terkait.

#### Aktivasi Imagick di hosting

Jangan set `IMAGE_RENDERER=imagick` sebelum semua poin ini dikonfirmasi untuk PHP web **dan** PHP CLI/worker yang digunakan:

1. PHP versi yang sama dengan aplikasi (PHP 8.3+), ekstensi `imagick` aktif (`php --ri imagick`) dan `Imagick::getVersion()` menunjukkan ImageMagick 6.5.3-10+; ImageMagick 7.x yang dipelihara lebih disarankan. Ekstensi harus dibangun untuk versi/API PHP tersebut, bukan sekadar binary `magick` yang tersedia.
2. ImageMagick mengizinkan baca JPEG/PNG/WebP, tulis PNG, font type/truetype, alpha channel, blur, dan operasi crop/composite. Minta provider memeriksa policy/resources untuk ukuran 1080×1350 dan foto maksimal 5 MB; tidak perlu mengaktifkan delegate/format berbahaya yang tidak digunakan.
3. Font Inter Regular/Bold terbaca oleh user PHP di path yang dikonfigurasi. Pastikan `sys_get_temp_dir()` writable dan kapasitas memory/process cukup untuk membuat layer canvas, shadow, dan foto.
4. Extension yang sama tersedia pada PHP-FPM/web, CLI, dan queue worker. Uji queue worker memakai binary PHP yang benar; `php --ri imagick` via SSH saja belum membuktikan PHP web memakai versi yang sama.
5. Cloudinary tetap menjadi storage hasil akhir dan harus dapat menerima PNG. Renderer hanya memakai temp file lokal selama render, kemudian menghapusnya.

Urutan cut-over: pasang extension + font, isi variabel font (jika perlu), jalankan tes/unit dan feature renderer di host staging, buat perbandingan screenshot pada konten dan foto yang sama, verifikasi upload Cloudinary serta satu publication job uji yang belum pernah terbit, baru set `IMAGE_RENDERER=imagick` dan bersihkan config cache. Jangan retry job berstatus published/needs_review yang mungkin sudah terkirim ke platform tanpa verifikasi status eksternal terlebih dulu.

#### Troubleshooting dan rollback renderer

- `PHP Imagick tidak tersedia`: aktifkan extension pada PHP web/CLI yang tepat, restart worker, lalu jalankan `php artisan config:clear` dan pemeriksaan `php --ri imagick`.
- `Font renderer tidak ditemukan`: unggah Inter Regular/Bold beserta lisensinya, atur `MENFESS_FONT_REGULAR`/`MENFESS_FONT_BOLD`, pastikan file readable oleh user PHP, dan clear config cache.
- Error format/policy atau memory: minta provider meninjau dukungan JPEG/PNG/WebP, ImageMagick policy, memory limit dan temp directory; jangan memperlebar policy secara umum tanpa kebutuhan.
- Untuk rollback sementara, ubah `IMAGE_RENDERER=playwright`, jalankan `php artisan config:clear`, dan pastikan worker memakai config baru. Jalur ini masih memerlukan Node/Playwright/Chromium. Kode/script lama belum dihapus.

Playwright runtime lama membutuhkan Node dan Chromium. Ia tetap ada hanya untuk rollback selama Imagick belum lulus visual comparison dan tes host-level; build frontend tetap menggunakan Node terpisah dari renderer backend.

### Catatan deploy di cPanel

cPanel berbeda antar-provider. Untuk jalur Imagick, minta provider mengonfirmasi ekstensi PHP Imagick dan library ImageMagick yang cocok dengan PHP web/CLI, font yang dapat dibaca, policy resource/format serta cara menjalankan queue worker. Playwright masih menjadi rollback sementara dan memerlukan Node/Chromium apabila dipilih.

- Simpan source Laravel di luar document root, misalnya `/home/<akun>/menfess`, dan arahkan document root domain ke `/home/<akun>/menfess/public`. Jangan menaruh `.env`, `vendor`, atau keseluruhan source di area publik. Untuk domain utama, cPanel mungkin tidak mengizinkan perubahan document root; minta provider mengatur vhost dengan aman. Lihat [panduan cPanel untuk document root domain](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/).
- Pilih PHP 8.3 atau lebih baru di MultiPHP Manager. Via SSH jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, dan `npm run build`; Node diperlukan untuk build frontend, sedangkan runtime rendering memakai Imagick setelah tervalidasi.
- Buat database/user MySQL di cPanel, isi `.env` di direktori aplikasi dengan nilai production termasuk tiga kredensial Cloudinary, lalu jalankan `php artisan migrate --force`, `php artisan config:cache`, dan `php artisan optimize`. `storage:link` tidak diperlukan untuk alur gambar menfess ini. Jangan salin `.env` ke `public_html`.
- Sebelum cut-over, jalankan tes renderer dari worker/CLI hosting dan bandingkan hasil dengan referensi visual. Keberadaan extension pada CLI saja tidak memastikan PHP-FPM memilikinya; jangan menganggap keberhasilan build Vite membuktikan renderer backend siap.
- Pastikan Cloudinary dikonfigurasi dan satu gambar uji berhasil diunggah sehingga Meta dapat mengambil `secure_url` Cloudinary melalui HTTPS. Jangan mengandalkan URL localhost atau disk `public` hosting untuk publikasi Meta.
- Jalankan queue worker persisten melalui supervisor/process manager yang disediakan provider. Jika paket hanya mengizinkan cron, sepakati command `queue:work --stop-when-empty` dengan interval dan lock anti-overlap dari provider; jadwalkan `php artisan schedule:run` setiap menit sesuai [dokumentasi cron cPanel](https://docs.cpanel.net/cpanel/advanced/cron-jobs/). Jangan menjalankan dua worker tanpa kontrol.
- Aktifkan HTTPS/SSL, set domain `APP_URL`, OAuth callback Google, dan webhook Midtrans. Simpan token Meta hanya di environment/secret cPanel, lalu verifikasi secara manual menggunakan akun uji sebelum mengubah `PUBLISHER_MODE=live`.

## Pengujian dan pemeriksaan

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Tes integrasi renderer membutuhkan Chromium. Tes API pembayaran dan publikasi eksternal menggunakan data sandbox/fake, bukan kredensial live. Jalankan `php artisan migrate:fresh --seed` hanya pada database development/test karena perintah itu menghapus data.

## Perintah operasional umum

```bash
php artisan migrate --force
php artisan queue:work --queue=default --tries=1
php artisan queue:failed
php artisan queue:retry <id>
php artisan optimize
php artisan optimize:clear
```

Untuk deployment, gunakan HTTPS, konfigurasi secret melalui secret manager/environment, `APP_DEBUG=false`, backup MySQL dan storage, worker yang dipantau, dan scheduler/queue worker yang dikelola process supervisor. Jalankan migrasi sebelum mengganti kode aplikasi.
