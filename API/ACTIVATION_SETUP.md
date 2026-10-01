# Setup Aktivasi Email

1. Pilih database `dbpkp` di phpMyAdmin, lalu jalankan isi `activation_schema.sql` satu kali.
2. Jalankan `add_users_email.sql` satu kali untuk menambahkan kolom email ke tabel `users`.
3. Jalankan `composer install` di folder utama aplikasi untuk memasang PHPMailer.
4. Tambahkan konfigurasi pada `.env` menggunakan nama variabel di `.env.example`. Isi `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, dan `SMTP_ENCRYPTION` sesuai penyedia email. Gunakan `tls` untuk STARTTLS atau `ssl` untuk SMTPS.
5. Atur `PKP_BASE_URL` ke alamat aplikasi, misalnya `http://localhost/PKP`, dan `PKP_MAIL_FROM` ke alamat pengirim yang diizinkan oleh penyedia SMTP. Environment variable server yang sudah ditetapkan akan diprioritaskan.
6. Pastikan server dapat mengakses host SMTP dan alamat aplikasi dapat diakses penerima; tautan aktivasi berlaku 24 jam.

Kolom `pending_users.token` menyimpan token aktivasi mentah yang sama dengan token pada tautan email. Jika tabel lama masih menggunakan kolom `token_hash`, migrasikan namanya sebelum memakai versi aplikasi ini:

```sql
ALTER TABLE pending_users CHANGE token_hash token CHAR(64) NOT NULL;
```

Token yang sudah tersimpan sebagai hash tidak dapat dipulihkan menjadi token asli. Registrasi yang masih menunggu aktivasi saat migrasi perlu diulang setelah data tersebut kedaluwarsa.

Email akun baru disimpan di `users` setelah token aktivasi dikonfirmasi. Akun lama tetap aktif, tetapi email mereka tidak terisi otomatis.
