<?php
/** @var mysqli $koneksi */

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan tabel tbl_edoc sudah ada
$sql_check_tbl = "CREATE TABLE IF NOT EXISTS tbl_edoc (
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
    INDEX (tgl_dokumen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($koneksi, $sql_check_tbl);

// Pastikan folder uploads/edoc/ ada
$upload_dir = __DIR__ . '/../../uploads/edoc/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Inisialisasi variabel kategori
$current_kategori = $current_kategori ?? 'cuti';
$pageAktif = $_GET['page'] ?? 'edoc_cuti';

// Konfigurasi Kategori
$kategori_config = [
    'cuti' => [
        'title' => 'e-Doc Keluar Cuti',
        'sub' => 'Arsip & Dokumen Elektronik Pengajuan dan Surat Cuti Pegawai',
        'icon' => 'fa-file-contract',
        'color' => '#004d00',
        'badge' => 'badge-success',
        'page' => 'edoc_cuti',
        'label' => 'Keluar Cuti'
    ],
    'keluar_awal' => [
        'title' => 'e-Doc Keluar Awal',
        'sub' => 'Arsip & Dokumen Elektronik Surat Izin Pulang Lebih Awal',
        'icon' => 'fa-file-export',
        'color' => '#b37400',
        'badge' => 'badge-warning',
        'page' => 'edoc_pulang',
        'label' => 'Keluar Awal'
    ],
    'izin_keluar' => [
        'title' => 'e-Doc Izin Keluar Kantor',
        'sub' => 'Arsip & Dokumen Elektronik Surat Izin Keluar Kantor Pada Jam Kerja',
        'icon' => 'fa-file-signature',
        'color' => '#005b7f',
        'badge' => 'badge-info',
        'page' => 'edoc_keluar',
        'label' => 'Izin Keluar'
    ]
];

$conf = $kategori_config[$current_kategori] ?? $kategori_config['cuti'];

// Helper formatting bulan & tanggal Indonesia
$nama_bulan_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if (!function_exists('format_tgl_id')) {
    function format_tgl_id($tgl) {
        if (empty($tgl) || $tgl == '0000-00-00') return '-';
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $exp = explode('-', $tgl);
        if (count($exp) != 3) return $tgl;
        return (int)$exp[2] . ' ' . $bulan[(int)$exp[1]] . ' ' . $exp[0];
    }
}

if (!function_exists('format_bytes_id')) {
    function format_bytes_id($bytes, $precision = 1) {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// -------------------------------------------------------------
// PROSES FORM: SIMPAN / UPLOAD DOKUMEN E-DOC
// -------------------------------------------------------------
$error_swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_edoc'])) {
    $id_user      = (int)$_POST['id_user'];
    $kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori_edoc']);
    $nomor_surat  = mysqli_real_escape_string($koneksi, trim($_POST['nomor_surat'] ?? ''));
    $nama_dokumen = mysqli_real_escape_string($koneksi, trim($_POST['nama_dokumen']));
    $tgl_dokumen  = mysqli_real_escape_string($koneksi, $_POST['tgl_dokumen']);
    $keterangan   = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));
    $id_ref       = !empty($_POST['id_ref']) ? (int)$_POST['id_ref'] : 'NULL';
    $uploaded_by  = (int)($_SESSION['id_user'] ?? 1);

    if (empty($nama_dokumen) || empty($tgl_dokumen) || $id_user <= 0) {
        $error_swal = "Harap lengkapi semua kolom wajib (Pegawai, Judul Dokumen, Tanggal)!";
    } elseif (!isset($_FILES['file_edoc']) || $_FILES['file_edoc']['error'] !== UPLOAD_ERR_OK) {
        $error_swal = "Harap pilih berkas file dokumen untuk diupload!";
    } else {
        $file = $_FILES['file_edoc'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $max_size = 10 * 1024 * 1024; // 10MB

        if (!in_array($ext, $allowed)) {
            $error_swal = "Format file tidak didukung! Format yang diperbolehkan: PDF, JPG, PNG, DOC, DOCX.";
        } elseif ($file['size'] > $max_size) {
            $error_swal = "Ukuran file terlalu besar! Maksimal 10 MB.";
        } else {
            $safe_filename = 'edoc_' . $kategori . '_' . $id_user . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $upload_dir . $safe_filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $file_size = (int)$file['size'];
                $sql_ins = "INSERT INTO tbl_edoc (kategori_edoc, id_user, nomor_surat, nama_dokumen, tgl_dokumen, file_dokumen, file_ukuran, file_tipe, keterangan, id_ref, uploaded_by)
                            VALUES ('$kategori', $id_user, '$nomor_surat', '$nama_dokumen', '$tgl_dokumen', '$safe_filename', $file_size, '$ext', '$keterangan', $id_ref, $uploaded_by)";
                if (mysqli_query($koneksi, $sql_ins)) {
                    $_SESSION['swal'] = [
                        'icon' => 'success',
                        'title' => 'Berhasil!',
                        'text' => 'Dokumen e-Doc berhasil diunggah.'
                    ];
                    echo "<script>window.location.href = 'index.php?page=" . $pageAktif . "';</script>";
                    exit();
                } else {
                    $error_swal = "Gagal menyimpan database: " . mysqli_error($koneksi);
                }
            } else {
                $error_swal = "Gagal memindahkan file ke direktori uploads/edoc/. Periksa izin folder.";
            }
        }
    }
}

// -------------------------------------------------------------
// PROSES FORM: UPDATE / EDIT DOKUMEN E-DOC
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_edoc'])) {
    $id_edoc      = (int)$_POST['id_edoc'];
    $id_user      = (int)$_POST['id_user'];
    $nomor_surat  = mysqli_real_escape_string($koneksi, trim($_POST['nomor_surat'] ?? ''));
    $nama_dokumen = mysqli_real_escape_string($koneksi, trim($_POST['nama_dokumen']));
    $tgl_dokumen  = mysqli_real_escape_string($koneksi, $_POST['tgl_dokumen']);
    $keterangan   = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    $file_update_sql = "";
    if (isset($_FILES['file_edoc_update']) && $_FILES['file_edoc_update']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file_edoc_update'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (in_array($ext, $allowed) && $file['size'] <= 10 * 1024 * 1024) {
            $safe_filename = 'edoc_' . $current_kategori . '_' . $id_user . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_filename)) {
                // Hapus berkas lama
                $q_old = mysqli_query($koneksi, "SELECT file_dokumen FROM tbl_edoc WHERE id_edoc = $id_edoc");
                if ($r_old = mysqli_fetch_assoc($q_old)) {
                    if (!empty($r_old['file_dokumen']) && file_exists($upload_dir . $r_old['file_dokumen'])) {
                        unlink($upload_dir . $r_old['file_dokumen']);
                    }
                }
                $file_size = (int)$file['size'];
                $file_update_sql = ", file_dokumen='$safe_filename', file_ukuran=$file_size, file_tipe='$ext'";
            }
        }
    }

    $sql_upd = "UPDATE tbl_edoc 
                SET id_user = $id_user, nomor_surat = '$nomor_surat', nama_dokumen = '$nama_dokumen', 
                    tgl_dokumen = '$tgl_dokumen', keterangan = '$keterangan' $file_update_sql
                WHERE id_edoc = $id_edoc";
    if (mysqli_query($koneksi, $sql_upd)) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Diperbarui!',
            'text' => 'Data e-Doc berhasil diperbarui.'
        ];
        echo "<script>window.location.href = 'index.php?page=" . $pageAktif . "';</script>";
        exit();
    }
}

// -------------------------------------------------------------
// PROSES HAPUS E-DOC
// -------------------------------------------------------------
if (isset($_GET['hapus_edoc'])) {
    $id_edoc = (int)$_GET['hapus_edoc'];
    $q_del = mysqli_query($koneksi, "SELECT file_dokumen FROM tbl_edoc WHERE id_edoc = $id_edoc");
    if ($r_del = mysqli_fetch_assoc($q_del)) {
        if (!empty($r_del['file_dokumen']) && file_exists($upload_dir . $r_del['file_dokumen'])) {
            unlink($upload_dir . $r_del['file_dokumen']);
        }
    }
    mysqli_query($koneksi, "DELETE FROM tbl_edoc WHERE id_edoc = $id_edoc");
    $_SESSION['swal'] = [
        'icon' => 'success',
        'title' => 'Dihapus!',
        'text' => 'Dokumen e-Doc berhasil dihapus.'
    ];
    echo "<script>window.location.href = 'index.php?page=" . $pageAktif . "';</script>";
    exit();
}

// -------------------------------------------------------------
// LOGIKA FILTER: PER MINGGU, PER BULAN, PER TAHUN, SEMUA
// -------------------------------------------------------------
$filter_tipe = $_GET['filter_tipe'] ?? 'bulan';
if (!in_array($filter_tipe, ['minggu', 'bulan', 'tahun', 'semua'])) {
    $filter_tipe = 'bulan';
}

$cari = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, trim($_GET['cari'])) : '';

// 1. Setup Mingguan
$filter_tgl = isset($_GET['filter_tgl']) && !empty($_GET['filter_tgl']) ? $_GET['filter_tgl'] : date('Y-m-d');
$ts = strtotime($filter_tgl);
if (!$ts) { $ts = time(); $filter_tgl = date('Y-m-d'); }
$day_num = (int)date('N', $ts); // 1 = Senin, 7 = Minggu
$tgl_senin = date('Y-m-d', strtotime('-' . ($day_num - 1) . ' days', $ts));
$tgl_minggu = date('Y-m-d', strtotime('+' . (7 - $day_num) . ' days', $ts));
$tgl_prev_week = date('Y-m-d', strtotime('-7 days', strtotime($tgl_senin)));
$tgl_next_week = date('Y-m-d', strtotime('+7 days', strtotime($tgl_senin)));

// 2. Setup Bulanan
$filter_bulan = isset($_GET['filter_bulan']) ? str_pad((int)$_GET['filter_bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$filter_tahun = isset($_GET['filter_tahun']) ? (int)$_GET['filter_tahun'] : (int)date('Y');

// 3. Setup Tahunan
$filter_tahun_only = isset($_GET['filter_tahun_only']) ? (int)$_GET['filter_tahun_only'] : (int)date('Y');

// Bangun WHERE Clause
$where_parts = ["e.kategori_edoc = '$current_kategori'"];

if (!empty($cari)) {
    $where_parts[] = "(u.nama_lengkap LIKE '%$cari%' OR u.nip LIKE '%$cari%' OR e.nomor_surat LIKE '%$cari%' OR e.nama_dokumen LIKE '%$cari%' OR e.keterangan LIKE '%$cari%')";
}

$info_filter_text = "";
if ($filter_tipe == 'minggu') {
    $where_parts[] = "(e.tgl_dokumen BETWEEN '$tgl_senin' AND '$tgl_minggu')";
    $info_filter_text = "Minggu: <strong>" . format_tgl_id($tgl_senin) . "</strong> s/d <strong>" . format_tgl_id($tgl_minggu) . "</strong>";
} elseif ($filter_tipe == 'bulan') {
    $where_parts[] = "(MONTH(e.tgl_dokumen) = '$filter_bulan' AND YEAR(e.tgl_dokumen) = '$filter_tahun')";
    $info_filter_text = "Bulan: <strong>" . ($nama_bulan_id[$filter_bulan] ?? $filter_bulan) . " " . $filter_tahun . "</strong>";
} elseif ($filter_tipe == 'tahun') {
    $where_parts[] = "(YEAR(e.tgl_dokumen) = '$filter_tahun_only')";
    $info_filter_text = "Tahun: <strong>" . $filter_tahun_only . "</strong>";
} else {
    $info_filter_text = "Menampilkan <strong>Semua Dokumen</strong>";
}

$where_sql = implode(' AND ', $where_parts);

// Pagination
$limit = 10;
$hal = isset($_GET['hal']) ? max(1, (int)$_GET['hal']) : 1;
$offset = ($hal - 1) * $limit;

$q_count = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_edoc e JOIN users u ON e.id_user = u.id_user WHERE $where_sql");
$row_count = mysqli_fetch_assoc($q_count);
$total_data = (int)($row_count['total'] ?? 0);
$total_hal = max(1, (int)ceil($total_data / $limit));

// Query Data Utama
$q_main = mysqli_query($koneksi, "SELECT e.*, u.nama_lengkap, u.nip, u.jabatan, u.unit_kerja 
                                  FROM tbl_edoc e 
                                  JOIN users u ON e.id_user = u.id_user 
                                  WHERE $where_sql 
                                  ORDER BY e.tgl_dokumen DESC, e.id_edoc DESC 
                                  LIMIT $offset, $limit");

// Hitung Statistik (Semua, Minggu Ini, Bulan Ini, Tahun Ini)
$stat_all = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_edoc WHERE kategori_edoc = '$current_kategori'"))['total'] ?? 0;

$stat_minggu = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_edoc 
    WHERE kategori_edoc = '$current_kategori' 
      AND YEARWEEK(tgl_dokumen, 1) = YEARWEEK(CURDATE(), 1)"))['total'] ?? 0;

$stat_bulan = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_edoc 
    WHERE kategori_edoc = '$current_kategori' 
      AND MONTH(tgl_dokumen) = MONTH(CURDATE()) 
      AND YEAR(tgl_dokumen) = YEAR(CURDATE())"))['total'] ?? 0;

$stat_tahun = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tbl_edoc 
    WHERE kategori_edoc = '$current_kategori' 
      AND YEAR(tgl_dokumen) = YEAR(CURDATE())"))['total'] ?? 0;

// Ambil daftar Pegawai untuk Modal Upload/Edit
$q_pegawai = mysqli_query($koneksi, "SELECT id_user, nama_lengkap, nip, jabatan FROM users WHERE status_akun='aktif' ORDER BY nama_lengkap ASC");
$pegawai_list = [];
while ($p = mysqli_fetch_assoc($q_pegawai)) {
    $pegawai_list[] = $p;
}

// Ambil data referensi pengajuan yang disetujui (opsional untuk mempermudah upload)
$ref_list = [];
if ($current_kategori == 'cuti') {
    $q_ref = mysqli_query($koneksi, "SELECT p.id_pengajuan as id_ref, p.id_user, p.nomor_surat, p.tgl_mulai, p.tgl_selesai, p.lama_hari, p.alasan, u.nama_lengkap, j.nama_jenis 
                                     FROM pengajuan_cuti p 
                                     JOIN users u ON p.id_user = u.id_user 
                                     JOIN jenis_cuti j ON p.id_jenis = j.id_jenis 
                                     WHERE p.status = 'Disetujui' 
                                     ORDER BY p.tgl_mulai DESC LIMIT 60");
    while ($r = mysqli_fetch_assoc($q_ref)) {
        $ref_list[] = [
            'id_ref' => $r['id_ref'],
            'id_user' => $r['id_user'],
            'nomor_surat' => $r['nomor_surat'] ?? '',
            'tgl' => $r['tgl_mulai'],
            'label' => $r['nama_lengkap'] . ' - Cuti ' . $r['nama_jenis'] . ' (' . format_tgl_id($r['tgl_mulai']) . ' s/d ' . format_tgl_id($r['tgl_selesai']) . ')',
            'judul_saran' => 'Surat Izin Cuti ' . $r['nama_jenis'] . ' - ' . $r['nama_lengkap'],
            'ket_saran' => 'Alasan: ' . $r['alasan'] . ' | Lama: ' . $r['lama_hari'] . ' Hari'
        ];
    }
} elseif ($current_kategori == 'keluar_awal') {
    $q_ref = mysqli_query($koneksi, "SELECT p.id_izin_pulang as id_ref, p.id_user, p.tgl_izin, p.jam_pulang, p.keperluan, u.nama_lengkap 
                                     FROM izin_pulang p 
                                     JOIN users u ON p.id_user = u.id_user 
                                     WHERE p.status = 1 
                                     ORDER BY p.tgl_izin DESC LIMIT 60");
    while ($r = mysqli_fetch_assoc($q_ref)) {
        $ref_list[] = [
            'id_ref' => $r['id_ref'],
            'id_user' => $r['id_user'],
            'nomor_surat' => '',
            'tgl' => $r['tgl_izin'],
            'label' => $r['nama_lengkap'] . ' - Pulang Awal: ' . substr($r['jam_pulang'], 0, 5) . ' (' . format_tgl_id($r['tgl_izin']) . ')',
            'judul_saran' => 'Surat Izin Pulang Awal - ' . $r['nama_lengkap'],
            'ket_saran' => 'Keperluan: ' . $r['keperluan']
        ];
    }
} elseif ($current_kategori == 'izin_keluar') {
    $q_ref = mysqli_query($koneksi, "SELECT k.id_izin as id_ref, k.id_user, k.tgl_izin, k.jam_keluar, k.jam_kembali, k.keperluan, u.nama_lengkap 
                                     FROM izin_keluar k 
                                     JOIN users u ON k.id_user = u.id_user 
                                     WHERE k.status = 'Disetujui' 
                                     ORDER BY k.tgl_izin DESC LIMIT 60");
    while ($r = mysqli_fetch_assoc($q_ref)) {
        $ref_list[] = [
            'id_ref' => $r['id_ref'],
            'id_user' => $r['id_user'],
            'nomor_surat' => '',
            'tgl' => $r['tgl_izin'],
            'label' => $r['nama_lengkap'] . ' - Izin Keluar: ' . substr($r['jam_keluar'], 0, 5) . '-' . substr($r['jam_kembali'], 0, 5) . ' (' . format_tgl_id($r['tgl_izin']) . ')',
            'judul_saran' => 'Surat Izin Keluar Kantor - ' . $r['nama_lengkap'],
            'ket_saran' => 'Keperluan: ' . $r['keperluan']
        ];
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --pn-green: #004d00;
        --pn-green-dark: #003300;
        --pn-green-light: #e8f5e9;
        --pn-gold: #F9A825;
        --pn-soft-bg: #f8f9fc;
        --text-dark: #2c3e50;
    }

    body { font-family: 'Poppins', sans-serif !important; background-color: #f4f6f9; }

    /* Page Header */
    .page-header-title {
        border-left: 5px solid var(--pn-gold);
        padding-left: 15px;
        color: var(--pn-green) !important;
        font-weight: 700;
        font-size: 1.5rem;
    }

    /* Tab Navigasi e-Doc */
    .edoc-menu-tabs {
        background: #fff;
        border-radius: 14px;
        padding: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        border: 1px solid #e9ecef;
        overflow-x: auto;
    }
    .edoc-menu-tabs .nav-link {
        border-radius: 10px;
        color: #495057;
        font-weight: 600;
        padding: 9px 18px;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        border: none;
        white-space: nowrap;
    }
    .edoc-menu-tabs .nav-link i { margin-right: 8px; font-size: 1rem; }
    .edoc-menu-tabs .nav-link.active {
        background: var(--pn-green);
        color: #fff;
        box-shadow: 0 4px 10px rgba(0, 77, 0, 0.25);
    }
    .edoc-menu-tabs .nav-link:hover:not(.active) {
        background: #f1f8e9;
        color: var(--pn-green);
    }

    /* Stat Cards */
    .stat-card-edoc {
        background: #fff;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border: 1px solid #edf2f7;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card-edoc:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.08);
    }
    .stat-card-edoc .stat-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .stat-card-edoc .stat-val {
        font-size: 1.8rem;
        font-weight: 800;
        color: #2c3e50;
        line-height: 1.1;
    }
    .stat-card-edoc .stat-icon {
        position: absolute;
        right: 15px;
        bottom: 12px;
        font-size: 2.2rem;
        opacity: 0.12;
    }

    /* Filter Card */
    .filter-card-edoc {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #edf2f7;
        margin-bottom: 25px;
        overflow: hidden;
    }
    .filter-header-tabs {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 10px 16px 0;
    }
    .filter-header-tabs .nav-pills .nav-link {
        font-size: 0.82rem;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 8px 8px 0 0;
        color: #555;
        background: transparent;
        border: 1px solid transparent;
        border-bottom: none;
        margin-right: 5px;
        transition: all 0.2s ease;
    }
    .filter-header-tabs .nav-pills .nav-link.active {
        background: #fff;
        color: var(--pn-green);
        border-color: #e9ecef;
        border-top: 3px solid var(--pn-green);
        font-weight: 700;
    }
    .filter-header-tabs .nav-pills .nav-link:hover:not(.active) {
        color: var(--pn-green);
        background: #edf7ed;
    }

    /* Table Card */
    .table-card-edoc {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #edf2f7;
        overflow: hidden;
    }
    .thead-pn-edoc {
        background: var(--pn-green);
        color: #fff;
    }
    .thead-pn-edoc th {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px !important;
        vertical-align: middle !important;
        border-top: none;
        border-bottom: 3px solid var(--pn-gold) !important;
    }
    .table-edoc tbody tr:hover {
        background-color: #f7faf7;
    }
    .table-edoc td {
        padding: 12px 14px !important;
        vertical-align: middle !important;
        font-size: 0.85rem;
        color: #333;
        border-top: 1px solid #edf0f5;
    }

    /* File Type Icons */
    .file-icon-badge {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-right: 12px;
        flex-shrink: 0;
    }
    .icon-pdf { background: #ffebee; color: #d32f2f; }
    .icon-img { background: #e8f5e9; color: #2e7d32; }
    .icon-doc { background: #e3f2fd; color: #1565c0; }
    .icon-other { background: #f5f5f5; color: #616161; }

    /* Action Buttons */
    .btn-action-edoc {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 2px;
        font-size: 0.82rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        text-decoration: none !important;
    }
    .btn-action-edoc:hover {
        transform: translateY(-2px);
    }
    .btn-preview { background: #e8f5e9; color: #1b5e20; border-color: #c8e6c9; }
    .btn-preview:hover { background: #1b5e20; color: #fff; }
    .btn-download { background: #e3f2fd; color: #0d47a1; border-color: #bbdefb; }
    .btn-download:hover { background: #0d47a1; color: #fff; }
    .btn-edit { background: #fff8e1; color: #f57f17; border-color: #ffecb3; }
    .btn-edit:hover { background: #f57f17; color: #fff; }
    .btn-delete { background: #ffebee; color: #b71c1c; border-color: #ffcdd2; }
    .btn-delete:hover { background: #b71c1c; color: #fff; }

    /* Buttons Solid */
    .btn-pn-solid {
        background-color: var(--pn-green);
        color: #fff;
        font-weight: 600;
        border-radius: 10px;
        padding: 9px 18px;
        border: none;
        box-shadow: 0 4px 10px rgba(0, 77, 0, 0.2);
        transition: all 0.25s ease;
    }
    .btn-pn-solid:hover {
        background-color: var(--pn-green-dark);
        color: var(--pn-gold);
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0, 77, 0, 0.3);
    }

    /* Pagination */
    .pagination-edoc .page-item .page-link {
        padding: .4rem .85rem;
        margin-left: 5px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        color: var(--pn-green);
        font-weight: 600;
        font-size: 13px;
    }
    .pagination-edoc .page-item.active .page-link {
        background: var(--pn-green) !important;
        color: #fff !important;
        border-color: var(--pn-green) !important;
    }

    /* Modal Styling */
    .modal-header-pn {
        background: linear-gradient(135deg, var(--pn-green) 0%, #006600 100%);
        color: #fff;
        border-bottom: 4px solid var(--pn-gold);
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        padding: 16px 20px;
    }
    .modal-content-pn {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        overflow: hidden;
    }

    /* Upload Dropzone Box */
    .upload-dropzone {
        border: 2px dashed #cbd5e0;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .upload-dropzone:hover, .upload-dropzone.dragover {
        border-color: var(--pn-green);
        background: #f0fdf4;
    }
</style>

<div class="container-fluid mb-5">

    <!-- HEADER TITLE & ACTION -->
    <div class="d-sm-flex align-items-center justify-content-between mb-3 mt-4">
        <div>
            <h1 class="h3 mb-1 page-header-title"><?= $conf['title'] ?></h1>
            <p class="text-muted small mb-0 ml-3"><?= $conf['sub'] ?></p>
        </div>
        <div class="mt-3 mt-sm-0">
            <button class="btn btn-pn-solid" data-toggle="modal" data-target="#modalUploadEdoc">
                <i class="fas fa-cloud-upload-alt mr-2"></i> Upload <?= $conf['label'] ?> (e-Doc)
            </button>
        </div>
    </div>

    <!-- TABS NAVIGASI 3 MENU E-DOC -->
    <ul class="nav edoc-menu-tabs shadow-sm">
        <li class="nav-item">
            <a class="nav-link <?= ($current_kategori == 'cuti') ? 'active' : '' ?>" href="index.php?page=edoc_cuti">
                <i class="fas fa-file-contract"></i> e-Doc Keluar Cuti
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_kategori == 'keluar_awal') ? 'active' : '' ?>" href="index.php?page=edoc_pulang">
                <i class="fas fa-file-export"></i> e-Doc Keluar Awal
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($current_kategori == 'izin_keluar') ? 'active' : '' ?>" href="index.php?page=edoc_keluar">
                <i class="fas fa-file-signature"></i> e-Doc Izin Keluar Kantor
            </a>
        </li>
    </ul>

    <!-- 4 KARTU STATISTIK DOKUMEN -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card-edoc" style="border-left: 4px solid #36b9cc;">
                <div class="stat-title text-info">Total Semua Dokumen</div>
                <div class="stat-val"><?= number_format($stat_all) ?></div>
                <i class="fas fa-folder-open stat-icon text-info"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card-edoc" style="border-left: 4px solid var(--pn-green);">
                <div class="stat-title text-success">Minggu Ini</div>
                <div class="stat-val text-success"><?= number_format($stat_minggu) ?></div>
                <i class="fas fa-calendar-week stat-icon text-success"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card-edoc" style="border-left: 4px solid var(--pn-gold);">
                <div class="stat-title text-warning">Bulan Ini</div>
                <div class="stat-val text-warning"><?= number_format($stat_bulan) ?></div>
                <i class="fas fa-calendar-alt stat-icon text-warning"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card-edoc" style="border-left: 4px solid #6f42c1;">
                <div class="stat-title" style="color: #6f42c1;">Tahun Ini (<?= date('Y') ?>)</div>
                <div class="stat-val" style="color: #6f42c1;"><?= number_format($stat_tahun) ?></div>
                <i class="fas fa-calendar stat-icon" style="color: #6f42c1;"></i>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION (PER MINGGU, BULAN, TAHUN, SEMUA) -->
    <div class="filter-card-edoc shadow-sm">
        <div class="filter-header-tabs">
            <ul class="nav nav-pills" id="pillsFilter" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= ($filter_tipe == 'minggu') ? 'active' : '' ?>" 
                       href="index.php?page=<?= $pageAktif ?>&filter_tipe=minggu&filter_tgl=<?= urlencode($filter_tgl) ?>&cari=<?= urlencode($cari) ?>">
                        <i class="fas fa-calendar-week mr-1"></i> Per Minggu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($filter_tipe == 'bulan') ? 'active' : '' ?>" 
                       href="index.php?page=<?= $pageAktif ?>&filter_tipe=bulan&filter_bulan=<?= $filter_bulan ?>&filter_tahun=<?= $filter_tahun ?>&cari=<?= urlencode($cari) ?>">
                        <i class="fas fa-calendar-alt mr-1"></i> Per Bulan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($filter_tipe == 'tahun') ? 'active' : '' ?>" 
                       href="index.php?page=<?= $pageAktif ?>&filter_tipe=tahun&filter_tahun_only=<?= $filter_tahun_only ?>&cari=<?= urlencode($cari) ?>">
                        <i class="fas fa-calendar mr-1"></i> Per Tahun
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($filter_tipe == 'semua') ? 'active' : '' ?>" 
                       href="index.php?page=<?= $pageAktif ?>&filter_tipe=semua&cari=<?= urlencode($cari) ?>">
                        <i class="fas fa-list mr-1"></i> Semua Dokumen
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-3">
            <!-- 1. KONTROL FILTER DINAMIS -->
            <?php if ($filter_tipe == 'minggu'): ?>
                <form method="GET" action="index.php" class="row align-items-center mb-3">
                    <input type="hidden" name="page" value="<?= $pageAktif ?>">
                    <input type="hidden" name="filter_tipe" value="minggu">
                    <?php if (!empty($cari)): ?>
                        <input type="hidden" name="cari" value="<?= htmlspecialchars($cari) ?>">
                    <?php endif; ?>

                    <div class="col-lg-4 col-md-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Pilih Tanggal / Minggu:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=minggu&filter_tgl=<?= $tgl_prev_week ?>&cari=<?= urlencode($cari) ?>" 
                                   class="btn btn-outline-secondary btn-sm d-flex align-items-center" title="Minggu Sebelumnya">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </div>
                            <input type="date" name="filter_tgl" class="form-control form-control-sm text-center font-weight-bold" 
                                   value="<?= $filter_tgl ?>" onchange="this.form.submit()">
                            <div class="input-group-append">
                                <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=minggu&filter_tgl=<?= $tgl_next_week ?>&cari=<?= urlencode($cari) ?>" 
                                   class="btn btn-outline-secondary btn-sm d-flex align-items-center" title="Minggu Berikutnya">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 col-md-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Rentang Tanggal Minggu Terpilih:</label>
                        <div class="alert alert-success py-1 px-3 mb-0 small d-flex align-items-center" style="border-radius: 8px;">
                            <i class="fas fa-calendar-check mr-2 text-success"></i>
                            <span><?= format_tgl_id($tgl_senin) ?> s/d <?= format_tgl_id($tgl_minggu) ?></span>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-12 mb-2 d-flex align-items-end">
                        <div class="w-100">
                            <label class="small text-muted font-weight-bold mb-1 d-none d-lg-block">&nbsp;</label>
                            <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=minggu&filter_tgl=<?= date('Y-m-d') ?>&cari=<?= urlencode($cari) ?>" 
                               class="btn btn-outline-success btn-sm btn-block">
                                <i class="fas fa-history mr-1"></i> Kembali ke Minggu Ini
                            </a>
                        </div>
                    </div>
                </form>

            <?php elseif ($filter_tipe == 'bulan'): ?>
                <form method="GET" action="index.php" class="row align-items-center mb-3">
                    <input type="hidden" name="page" value="<?= $pageAktif ?>">
                    <input type="hidden" name="filter_tipe" value="bulan">
                    <?php if (!empty($cari)): ?>
                        <input type="hidden" name="cari" value="<?= htmlspecialchars($cari) ?>">
                    <?php endif; ?>

                    <div class="col-lg-4 col-md-5 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Pilih Bulan:</label>
                        <select name="filter_bulan" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php foreach ($nama_bulan_id as $b_num => $b_name): ?>
                                <option value="<?= $b_num ?>" <?= ($filter_bulan == $b_num) ? 'selected' : '' ?>>
                                    <?= $b_name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-4 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Pilih Tahun:</label>
                        <select name="filter_tahun" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php 
                            $curr_y = (int)date('Y');
                            for ($y = $curr_y + 1; $y >= $curr_y - 4; $y--): 
                            ?>
                                <option value="<?= $y ?>" <?= ($filter_tahun == $y) ? 'selected' : '' ?>>
                                    Tahun <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-3 mb-2 d-flex align-items-end">
                        <div class="w-100">
                            <label class="small text-muted font-weight-bold mb-1 d-none d-lg-block">&nbsp;</label>
                            <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=bulan&filter_bulan=<?= date('m') ?>&filter_tahun=<?= date('Y') ?>&cari=<?= urlencode($cari) ?>" 
                               class="btn btn-outline-success btn-sm btn-block">
                                <i class="fas fa-history mr-1"></i> Bulan Sekarang
                            </a>
                        </div>
                    </div>
                </form>

            <?php elseif ($filter_tipe == 'tahun'): ?>
                <form method="GET" action="index.php" class="row align-items-center mb-3">
                    <input type="hidden" name="page" value="<?= $pageAktif ?>">
                    <input type="hidden" name="filter_tipe" value="tahun">
                    <?php if (!empty($cari)): ?>
                        <input type="hidden" name="cari" value="<?= htmlspecialchars($cari) ?>">
                    <?php endif; ?>

                    <div class="col-lg-6 col-md-7 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Pilih Tahun Dokumen:</label>
                        <select name="filter_tahun_only" class="form-control form-control-sm" onchange="this.form.submit()">
                            <?php 
                            $curr_y = (int)date('Y');
                            for ($y = $curr_y + 1; $y >= $curr_y - 4; $y--): 
                            ?>
                                <option value="<?= $y ?>" <?= ($filter_tahun_only == $y) ? 'selected' : '' ?>>
                                    Tahun <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-lg-6 col-md-5 mb-2 d-flex align-items-end">
                        <div class="w-100">
                            <label class="small text-muted font-weight-bold mb-1 d-none d-lg-block">&nbsp;</label>
                            <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=tahun&filter_tahun_only=<?= date('Y') ?>&cari=<?= urlencode($cari) ?>" 
                               class="btn btn-outline-success btn-sm btn-block">
                                <i class="fas fa-history mr-1"></i> Tahun Ini (<?= date('Y') ?>)
                            </a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- 2. SEARCH & STATUS BAR -->
            <div class="d-flex justify-content-between align-items-center flex-wrap pt-2 border-top">
                <div class="text-secondary small mb-2 mb-md-0">
                    <i class="fas fa-info-circle mr-1 text-primary"></i> 
                    <?= $info_filter_text ?> &bull; Ditemukan: <strong><?= number_format($total_data) ?></strong> dokumen
                </div>

                <div style="min-width: 280px;" class="mb-2 mb-md-0">
                    <form method="GET" action="index.php" class="input-group input-group-sm">
                        <input type="hidden" name="page" value="<?= $pageAktif ?>">
                        <input type="hidden" name="filter_tipe" value="<?= $filter_tipe ?>">
                        <?php if ($filter_tipe == 'minggu'): ?>
                            <input type="hidden" name="filter_tgl" value="<?= htmlspecialchars($filter_tgl) ?>">
                        <?php elseif ($filter_tipe == 'bulan'): ?>
                            <input type="hidden" name="filter_bulan" value="<?= htmlspecialchars($filter_bulan) ?>">
                            <input type="hidden" name="filter_tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                        <?php elseif ($filter_tipe == 'tahun'): ?>
                            <input type="hidden" name="filter_tahun_only" value="<?= htmlspecialchars($filter_tahun_only) ?>">
                        <?php endif; ?>

                        <input type="text" name="cari" class="form-control" placeholder="Cari nama, NIP, no. surat..." 
                               value="<?= htmlspecialchars($cari) ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($cari)): ?>
                                <a href="index.php?page=<?= $pageAktif ?>&filter_tipe=<?= $filter_tipe ?>&filter_tgl=<?= urlencode($filter_tgl) ?>&filter_bulan=<?= $filter_bulan ?>&filter_tahun=<?= $filter_tahun ?>&filter_tahun_only=<?= $filter_tahun_only ?>" 
                                   class="btn btn-outline-secondary" title="Reset Pencarian">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL DATA E-DOC -->
    <div class="table-card-edoc shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover table-edoc mb-0">
                <thead class="thead-pn-edoc">
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th style="width: 250px;">Dokumen e-Doc</th>
                        <th style="width: 220px;">Pegawai</th>
                        <th style="width: 140px;">Tgl Dokumen</th>
                        <th style="width: 150px;">Berkas File</th>
                        <th>Keterangan</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (mysqli_num_rows($q_main) > 0): 
                        $no = $offset + 1;
                        while ($row = mysqli_fetch_assoc($q_main)): 
                            $ext = strtolower($row['file_tipe'] ?? pathinfo($row['file_dokumen'], PATHINFO_EXTENSION));
                            
                            $icon_class = 'icon-other';
                            $icon_name = 'fa-file';
                            if ($ext == 'pdf') {
                                $icon_class = 'icon-pdf';
                                $icon_name = 'fa-file-pdf';
                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                $icon_class = 'icon-img';
                                $icon_name = 'fa-file-image';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $icon_class = 'icon-doc';
                                $icon_name = 'fa-file-word';
                            }

                            $file_url = 'uploads/edoc/' . htmlspecialchars($row['file_dokumen']);
                    ?>
                        <tr>
                            <td class="text-center font-weight-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="font-weight-bold text-dark" style="font-size: 0.9rem;">
                                    <?= htmlspecialchars($row['nama_dokumen']) ?>
                                </div>
                                <?php if (!empty($row['nomor_surat'])): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-hashtag text-secondary mr-1"></i>
                                        <code><?= htmlspecialchars($row['nomor_surat']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">
                                    <?= htmlspecialchars($row['nama_lengkap']) ?>
                                </div>
                                <div class="small text-muted">
                                    NIP: <?= htmlspecialchars($row['nip']) ?>
                                </div>
                                <?php if (!empty($row['jabatan'])): ?>
                                    <div class="small text-muted font-italic" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($row['jabatan']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">
                                    <?= format_tgl_id($row['tgl_dokumen']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    Upload: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="file-icon-badge <?= $icon_class ?>">
                                        <i class="fas <?= $icon_name ?>"></i>
                                    </div>
                                    <div>
                                        <div class="badge badge-light border text-uppercase font-weight-bold">
                                            <?= $ext ?>
                                        </div>
                                        <div class="small text-muted" style="font-size: 0.72rem;">
                                            <?= format_bytes_id($row['file_ukuran']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['keterangan'])): ?>
                                    <div class="small text-dark"><?= nl2br(htmlspecialchars($row['keterangan'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <!-- Button Lihat / Preview -->
                                <button type="button" class="btn btn-action-edoc btn-preview btn-preview-doc" 
                                        title="Lihat / Preview Dokumen"
                                        data-url="<?= $file_url ?>"
                                        data-ext="<?= $ext ?>"
                                        data-title="<?= htmlspecialchars($row['nama_dokumen']) ?>"
                                        data-pegawai="<?= htmlspecialchars($row['nama_lengkap']) ?>">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <!-- Button Unduh Langsung -->
                                <a href="<?= $file_url ?>" download class="btn btn-action-edoc btn-download" title="Unduh Berkas">
                                    <i class="fas fa-download"></i>
                                </a>

                                <!-- Button Edit -->
                                <button type="button" class="btn btn-action-edoc btn-edit btn-edit-doc" 
                                        title="Edit Data Dokumen"
                                        data-id="<?= $row['id_edoc'] ?>"
                                        data-user="<?= $row['id_user'] ?>"
                                        data-surat="<?= htmlspecialchars($row['nomor_surat'] ?? '') ?>"
                                        data-nama="<?= htmlspecialchars($row['nama_dokumen']) ?>"
                                        data-tgl="<?= $row['tgl_dokumen'] ?>"
                                        data-ket="<?= htmlspecialchars($row['keterangan'] ?? '') ?>">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>

                                <!-- Button Hapus -->
                                <button type="button" class="btn btn-action-edoc btn-delete btn-delete-doc" 
                                        title="Hapus Dokumen"
                                        data-id="<?= $row['id_edoc'] ?>"
                                        data-title="<?= htmlspecialchars($row['nama_dokumen']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="py-4">
                                    <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                                    <h5 class="text-secondary font-weight-bold">Belum Ada Dokumen e-Doc</h5>
                                    <p class="text-muted small mb-3">
                                        Tidak ditemukan berkas e-Doc untuk kategori <strong><?= $conf['title'] ?></strong> pada filter terpilih.
                                    </p>
                                    <button class="btn btn-pn-solid btn-sm" data-toggle="modal" data-target="#modalUploadEdoc">
                                        <i class="fas fa-cloud-upload-alt mr-1"></i> Upload Dokumen Sekarang
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION & SUMMARY -->
        <?php if ($total_hal > 1): ?>
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap">
                <div class="small text-muted mb-2 mb-sm-0">
                    Menampilkan <strong><?= ($total_data > 0) ? ($offset + 1) : 0 ?></strong> - 
                    <strong><?= min($offset + $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong> dokumen
                </div>
                <ul class="pagination pagination-edoc mb-0 justify-content-end">
                    <?php 
                    $page_link_base = "index.php?page=" . $pageAktif . "&filter_tipe=" . $filter_tipe . 
                                      "&filter_tgl=" . urlencode($filter_tgl) . 
                                      "&filter_bulan=" . $filter_bulan . 
                                      "&filter_tahun=" . $filter_tahun . 
                                      "&filter_tahun_only=" . $filter_tahun_only . 
                                      "&cari=" . urlencode($cari) . "&hal=";
                    ?>
                    <li class="page-item <?= ($hal <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page_link_base . ($hal - 1) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_hal; $i++): ?>
                        <li class="page-item <?= ($hal == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $page_link_base . $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($hal >= $total_hal) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page_link_base . ($hal + 1) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- =============================================================
     MODAL 1: UPLOAD DOKUMEN E-DOC BARU
     ============================================================= -->
<div class="modal fade" id="modalUploadEdoc" tabindex="-1" role="dialog" aria-labelledby="modalUploadEdocLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content modal-content-pn">
            <div class="modal-header modal-header-pn">
                <h5 class="modal-title font-weight-bold" id="modalUploadEdocLabel">
                    <i class="fas fa-cloud-upload-alt mr-2 text-warning"></i> Upload <?= $conf['title'] ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="" method="POST" enctype="multipart/form-data" id="formUploadEdoc">
                <input type="hidden" name="simpan_edoc" value="1">
                <input type="hidden" name="kategori_edoc" value="<?= $current_kategori ?>">

                <div class="modal-body p-4">

                    <!-- Opsi Hubungkan dengan Pengajuan Sistem (Opsional) -->
                    <?php if (!empty($ref_list)): ?>
                        <div class="card bg-light border-0 mb-3" style="border-radius: 10px;">
                            <div class="card-body p-3">
                                <label class="font-weight-bold text-dark small mb-1">
                                    <i class="fas fa-link text-primary mr-1"></i> Tautkan dengan Data Pengajuan (Opsional):
                                </label>
                                <select id="selectRefPengajuan" name="id_ref" class="form-control form-control-sm">
                                    <option value="">-- Input Bebas / Manual (Tanpa Tautan) --</option>
                                    <?php foreach ($ref_list as $ref): ?>
                                        <option value="<?= $ref['id_ref'] ?>" 
                                                data-user="<?= $ref['id_user'] ?>" 
                                                data-surat="<?= htmlspecialchars($ref['nomor_surat']) ?>" 
                                                data-tgl="<?= $ref['tgl'] ?>" 
                                                data-judul="<?= htmlspecialchars($ref['judul_saran']) ?>" 
                                                data-ket="<?= htmlspecialchars($ref['ket_saran']) ?>">
                                            <?= htmlspecialchars($ref['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted font-italic">
                                    *Memilih data pengajuan di atas akan otomatis mengisi nama pegawai, nomor surat, dan judul dokumen.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Pegawai -->
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Pegawai <span class="text-danger">*</span></label>
                            <select name="id_user" id="upload_id_user" class="form-control" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($pegawai_list as $peg): ?>
                                    <option value="<?= $peg['id_user'] ?>">
                                        <?= htmlspecialchars($peg['nama_lengkap']) ?> (NIP: <?= htmlspecialchars($peg['nip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tanggal Dokumen -->
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">Tanggal Dokumen <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_dokumen" id="upload_tgl_dokumen" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Nomor Surat -->
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">Nomor Surat / Dokumen</label>
                            <input type="text" name="nomor_surat" id="upload_nomor_surat" class="form-control" 
                                   placeholder="Contoh: 001/KPN/W13.U1/KP.05.3/I/2026">
                        </div>

                        <!-- Judul Dokumen -->
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Judul / Nama Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="nama_dokumen" id="upload_nama_dokumen" class="form-control" 
                                   placeholder="Contoh: Formulir Izin <?= $conf['label'] ?> - [Nama Pegawai]" required>
                        </div>
                    </div>

                    <!-- Upload File Box -->
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Pilih Berkas File (e-Doc) <span class="text-danger">*</span></label>
                        <div class="upload-dropzone" id="dropzoneUpload" onclick="document.getElementById('file_edoc_input').click();">
                            <i class="fas fa-cloud-upload-alt fa-3x text-success mb-2"></i>
                            <div class="font-weight-bold text-dark" id="dropzoneText">Klik di sini untuk memilih file dokumen</div>
                            <div class="small text-muted mt-1">Format yang didukung: <strong>PDF, JPG, PNG, DOC, DOCX</strong> (Maksimal 10 MB)</div>
                        </div>
                        <input type="file" name="file_edoc" id="file_edoc_input" class="d-none" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-dark">Catatan / Keterangan Tambahan</label>
                        <textarea name="keterangan" id="upload_keterangan" class="form-control" rows="2" 
                                  placeholder="Tambahkan catatan opsional jika ada..."></textarea>
                    </div>

                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pn-solid btn-sm px-4">
                        <i class="fas fa-check mr-1"></i> Simpan & Upload Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================
     MODAL 2: EDIT DOKUMEN E-DOC
     ============================================================= -->
<div class="modal fade" id="modalEditEdoc" tabindex="-1" role="dialog" aria-labelledby="modalEditEdocLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content modal-content-pn">
            <div class="modal-header modal-header-pn">
                <h5 class="modal-title font-weight-bold" id="modalEditEdocLabel">
                    <i class="fas fa-pencil-alt mr-2 text-warning"></i> Edit Informasi Dokumen e-Doc
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="" method="POST" enctype="multipart/form-data" id="formEditEdoc">
                <input type="hidden" name="update_edoc" value="1">
                <input type="hidden" name="id_edoc" id="edit_id_edoc">

                <div class="modal-body p-4">
                    <div class="row">
                        <!-- Pegawai -->
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Pegawai <span class="text-danger">*</span></label>
                            <select name="id_user" id="edit_id_user" class="form-control" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($pegawai_list as $peg): ?>
                                    <option value="<?= $peg['id_user'] ?>">
                                        <?= htmlspecialchars($peg['nama_lengkap']) ?> (NIP: <?= htmlspecialchars($peg['nip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tanggal Dokumen -->
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">Tanggal Dokumen <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_dokumen" id="edit_tgl_dokumen" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Nomor Surat -->
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">Nomor Surat / Dokumen</label>
                            <input type="text" name="nomor_surat" id="edit_nomor_surat" class="form-control" 
                                   placeholder="Contoh: 001/KPN/W13.U1/KP.05.3/I/2026">
                        </div>

                        <!-- Judul Dokumen -->
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Judul / Nama Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="nama_dokumen" id="edit_nama_dokumen" class="form-control" required>
                        </div>
                    </div>

                    <!-- Ganti File Opsional -->
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Ganti Berkas Dokumen (Opsional)</label>
                        <input type="file" name="file_edoc_update" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted font-italic">
                            *Biarkan kosong jika tidak ingin mengubah berkas yang sudah ada.
                        </small>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-dark">Catatan / Keterangan</label>
                        <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pn-solid btn-sm px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================
     MODAL 3: PREVIEW DOKUMEN E-DOC
     ============================================================= -->
<div class="modal fade" id="modalPreviewDoc" tabindex="-1" role="dialog" aria-labelledby="modalPreviewDocLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content modal-content-pn">
            <div class="modal-header modal-header-pn py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-alt mr-2 text-warning"></i>
                    <div>
                        <div class="font-weight-bold" id="previewModalTitle">Lihat Dokumen</div>
                        <div class="small text-white-50" id="previewModalPegawai">Pegawai</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <a href="#" id="btnPreviewExternal" target="_blank" class="btn btn-sm btn-outline-light mr-2" title="Buka di Tab Baru">
                        <i class="fas fa-external-link-alt mr-1"></i> Tab Baru
                    </a>
                    <a href="#" id="btnPreviewDownload" download class="btn btn-sm btn-light mr-3 text-dark font-weight-bold" title="Download File">
                        <i class="fas fa-download mr-1"></i> Unduh
                    </a>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            
            <div class="modal-body p-0 bg-dark text-center" style="min-height: 550px; display: flex; align-items: center; justify-content: center;">
                <!-- Iframe untuk PDF -->
                <iframe id="previewPdfFrame" src="" style="width: 100%; height: 600px; border: none; display: none;"></iframe>
                
                <!-- Img untuk Gambar (JPG, PNG) -->
                <div id="previewImageContainer" style="display: none; padding: 20px; max-height: 600px; overflow: auto; width: 100%;">
                    <img id="previewImageSrc" src="" class="img-fluid rounded shadow-sm mx-auto" style="max-height: 560px;">
                </div>

                <!-- Fallback untuk DOC/DOCX/Lainnya -->
                <div id="previewFallbackContainer" style="display: none; padding: 60px 20px; color: #fff;">
                    <i class="fas fa-file-word fa-5x text-info mb-3"></i>
                    <h5 class="font-weight-bold mb-2">Pratinjau Langsung Tidak Tersedia</h5>
                    <p class="text-white-50 small mb-4">Berkas ini berformat Word (.doc/.docx) atau arsip yang harus diunduh untuk membukanya.</p>
                    <a href="#" id="btnFallbackDownload" download class="btn btn-primary px-4">
                        <i class="fas fa-download mr-2"></i> Unduh Berkas Sekarang
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    <?php if ($error_swal): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($error_swal) ?>',
            confirmButtonColor: '#004d00'
        });
    <?php endif; ?>

    // Feedback file input upload
    const fileInput = document.getElementById('file_edoc_input');
    const dropzoneText = document.getElementById('dropzoneText');
    if (fileInput && dropzoneText) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const f = this.files[0];
                const sizeKB = Math.round(f.size / 1024);
                dropzoneText.innerHTML = `<span class="text-success"><i class="fas fa-check-circle mr-1"></i> Terpilih: <strong>${f.name}</strong> (${sizeKB} KB)</span>`;
            }
        });
    }

    // Auto-fill dari dropdown referensi pengajuan
    const selectRef = document.getElementById('selectRefPengajuan');
    if (selectRef) {
        selectRef.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.value) {
                const user = opt.getAttribute('data-user');
                const surat = opt.getAttribute('data-surat');
                const tgl = opt.getAttribute('data-tgl');
                const judul = opt.getAttribute('data-judul');
                const ket = opt.getAttribute('data-ket');

                if (user) document.getElementById('upload_id_user').value = user;
                if (surat) document.getElementById('upload_nomor_surat').value = surat;
                if (tgl) document.getElementById('upload_tgl_dokumen').value = tgl;
                if (judul) document.getElementById('upload_nama_dokumen').value = judul;
                if (ket) document.getElementById('upload_keterangan').value = ket;
            }
        });
    }

    // Event Klik Preview Dokumen
    document.querySelectorAll('.btn-preview-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            const ext = this.getAttribute('data-ext').toLowerCase();
            const title = this.getAttribute('data-title');
            const pegawai = this.getAttribute('data-pegawai');

            document.getElementById('previewModalTitle').textContent = title;
            document.getElementById('previewModalPegawai').textContent = 'Pegawai: ' + pegawai;
            document.getElementById('btnPreviewExternal').setAttribute('href', url);
            document.getElementById('btnPreviewDownload').setAttribute('href', url);

            const iframe = document.getElementById('previewPdfFrame');
            const imgBox = document.getElementById('previewImageContainer');
            const imgSrc = document.getElementById('previewImageSrc');
            const fallback = document.getElementById('previewFallbackContainer');
            const fallbackBtn = document.getElementById('btnFallbackDownload');

            iframe.style.display = 'none';
            imgBox.style.display = 'none';
            fallback.style.display = 'none';

            if (ext === 'pdf') {
                iframe.src = url;
                iframe.style.display = 'block';
            } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                imgSrc.src = url;
                imgBox.style.display = 'block';
            } else {
                fallbackBtn.setAttribute('href', url);
                fallback.style.display = 'block';
            }

            $('#modalPreviewDoc').modal('show');
        });
    });

    // Reset iframe saat modal preview ditutup
    $('#modalPreviewDoc').on('hidden.bs.modal', function () {
        document.getElementById('previewPdfFrame').src = '';
        document.getElementById('previewImageSrc').src = '';
    });

    // Event Klik Edit Dokumen
    document.querySelectorAll('.btn-edit-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id_edoc').value = this.getAttribute('data-id');
            document.getElementById('edit_id_user').value = this.getAttribute('data-user');
            document.getElementById('edit_nomor_surat').value = this.getAttribute('data-surat');
            document.getElementById('edit_nama_dokumen').value = this.getAttribute('data-nama');
            document.getElementById('edit_tgl_dokumen').value = this.getAttribute('data-tgl');
            document.getElementById('edit_keterangan').value = this.getAttribute('data-ket');

            $('#modalEditEdoc').modal('show');
        });
    });

    // Event Klik Hapus Dokumen dengan Konfirmasi SweetAlert2
    document.querySelectorAll('.btn-delete-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');

            Swal.fire({
                title: 'Hapus Dokumen?',
                html: `Apakah Anda yakin ingin menghapus dokumen <strong>${title}</strong>?<br><small class="text-danger">File arsip yang dihapus tidak dapat dipulihkan kembali.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `index.php?page=<?= $pageAktif ?>&hapus_edoc=${id}&filter_tipe=<?= $filter_tipe ?>`;
                }
            });
        });
    });

});
</script>
