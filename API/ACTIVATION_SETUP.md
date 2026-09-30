# Setup Aktivasi Email

1. Pilih database `dbpkp` di phpMyAdmin, lalu jalankan isi `activation_schema.sql` satu kali.
2. Jalankan `add_users_email.sql` satu kali untuk menambahkan kolom email ke tabel `users`.
3. Atur environment variable `PKP_BASE_URL` ke alamat aplikasi, misalnya `http://localhost/PKP` untuk XAMPP lokal.
4. Atur `PKP_MAIL_FROM` ke alamat pengirim yang valid.
5. Konfigurasikan SMTP/sendmail untuk PHP `mail()` di XAMPP dan restart Apache. Uji pengiriman dari server sebelum membuka registrasi.
6. Pastikan server dapat diakses pengguna melalui alamat pada `PKP_BASE_URL`; tautan aktivasi berlaku 24 jam.

Email akun baru disimpan di `users` setelah token aktivasi dikonfirmasi. Akun lama tetap aktif, tetapi email mereka tidak terisi otomatis.
