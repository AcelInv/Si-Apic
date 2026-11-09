<?php
/**
 * Helper Otomatisasi Dokumen e-Doc
 * SI-APIC Pengadilan Negeri Yogyakarta
 */

if (!function_exists('ensure_edoc_ready')) {
    function ensure_edoc_ready($koneksi) {
        $sql = "CREATE TABLE IF NOT EXISTS tbl_edoc (
            id_edoc INT AUTO_INCREMENT PRIMARY KEY,
            kategori_edoc VARCHAR(50) NOT NULL COMMENT 'cuti, keluar_awal, izin_keluar',
            id_user INT NOT NULL,
            nomor_surat VARCHAR(150) NULL,
            nama_dokumen VARCHAR(255) NOT NULL,
            tgl_dokumen DATE NOT NULL,
            file_dokumen VARCHAR(255) NOT NULL,
            file_ukuran INT DEFAULT 0,
            file_tipe VARCHAR(50) DEFAULT '',
            keterangan TEXT NULL,
            id_ref INT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (kategori_edoc),
            INDEX (id_user),
            INDEX (tgl_dokumen),
            INDEX (id_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($koneksi, $sql);

        $dir = __DIR__ . '/../uploads/edoc/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

if (!function_exists('upload_file_ke_edoc')) {
    function upload_file_ke_edoc($koneksi, $kategori, $id_user, $nomor_surat, $nama_dokumen, $tgl_dokumen, $file, $keterangan = '', $id_ref = null, $uploaded_by = null) {
        ensure_edoc_ready($koneksi);

        if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['status' => false, 'pesan' => 'Berkas file dokumen wajib dipilih!'];
        }

        $id_user = (int)$id_user;
        if ($id_user <= 0) {
            return ['status' => false, 'pesan' => 'Data pegawai/pemohon tidak valid.'];
        }

        $nama_dokumen = trim($nama_dokumen ?? '');
        if (empty($nama_dokumen)) {
            return ['status' => false, 'pesan' => 'Judul / nama dokumen wajib diisi!'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($ext, $allowed)) {
            return ['status' => false, 'pesan' => 'Format file tidak didukung! Format yang diperbolehkan: PDF, JPG, PNG, DOC, DOCX.'];
        }

        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return ['status' => false, 'pesan' => 'Ukuran file terlalu besar! Maksimal 10 MB.'];
        }

        $dir = __DIR__ . '/../uploads/edoc/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $safe_filename = 'edoc_' . $kategori . '_' . $id_user . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . $safe_filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['status' => false, 'pesan' => 'Gagal mengunggah berkas ke direktori uploads/edoc/. Periksa izin folder.'];
        }

        $file_size = (int)$file['size'];
        $kategori_esc = mysqli_real_escape_string($koneksi, $kategori);
        $nomor_surat_esc = mysqli_real_escape_string($koneksi, trim($nomor_surat ?? ''));
        $nama_dokumen_esc = mysqli_real_escape_string($koneksi, $nama_dokumen);
        $tgl_dokumen_esc = mysqli_real_escape_string($koneksi, $tgl_dokumen ?: date('Y-m-d'));
        $keterangan_esc = mysqli_real_escape_string($koneksi, trim($keterangan ?? ''));
        $id_ref_val = $id_ref ? (int)$id_ref : 'NULL';
        $uploaded_by_val = $uploaded_by ? (int)$uploaded_by : ((int)($_SESSION['id_user'] ?? 1));

        $sql_ins = "INSERT INTO tbl_edoc (kategori_edoc, id_user, nomor_surat, nama_dokumen, tgl_dokumen, file_dokumen, file_ukuran, file_tipe, keterangan, id_ref, uploaded_by)
                    VALUES ('$kategori_esc', $id_user, '$nomor_surat_esc', '$nama_dokumen_esc', '$tgl_dokumen_esc', '$safe_filename', $file_size, '$ext', '$keterangan_esc', $id_ref_val, $uploaded_by_val)";

        if (mysqli_query($koneksi, $sql_ins)) {
            $inserted_id = mysqli_insert_id($koneksi);
            return [
                'status' => true,
                'pesan' => 'Dokumen berhasil diunggah dan otomatis tersimpan ke e-Doc.',
                'id_edoc' => $inserted_id,
                'filename' => $safe_filename
            ];
        } else {
            return ['status' => false, 'pesan' => 'Gagal menyimpan ke database e-Doc: ' . mysqli_error($koneksi)];
        }
    }
}

if (!function_exists('get_edoc_terkait')) {
    function get_edoc_terkait($koneksi, $kategori, $id_ref) {
        ensure_edoc_ready($koneksi);
        $kategori_esc = mysqli_real_escape_string($koneksi, $kategori);
        $id_ref = (int)$id_ref;
        $q = mysqli_query($koneksi, "SELECT * FROM tbl_edoc WHERE kategori_edoc='$kategori_esc' AND id_ref=$id_ref ORDER BY id_edoc DESC");
        $res = [];
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $res[] = $row;
            }
        }
        return $res;
    }
}

