CREATE TABLE IF NOT EXISTS pengaturan_bulan_kinerja (
    bulan CHAR(2) NOT NULL PRIMARY KEY,
    nama VARCHAR(16) NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO pengaturan_bulan_kinerja (bulan, nama, aktif) VALUES
    ('01', 'Januari', 1),
    ('02', 'Februari', 1),
    ('03', 'Maret', 1),
    ('04', 'April', 1),
    ('05', 'Mei', 1),
    ('06', 'Juni', 1),
    ('07', 'Juli', 1),
    ('08', 'Agustus', 1),
    ('09', 'September', 1),
    ('10', 'Oktober', 1),
    ('11', 'November', 1),
    ('12', 'Desember', 1);