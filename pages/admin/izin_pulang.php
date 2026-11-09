<?php
/** @var mysqli $koneksi */

require_once __DIR__ . '/../../config/edoc_helper.php';
ensure_edoc_ready($koneksi);

// ==========================================
// PROSES EDIT DATA IZIN PULANG AWAL
// ==========================================
if (isset($_POST['aksi_edit_izin_pulang'])) {
    $id_izin_pulang = (int)($_POST['id_izin_pulang'] ?? 0);
    $id_atasan      = mysqli_real_escape_string($koneksi, $_POST['id_atasan'] ?? '');
    $tgl_izin       = mysqli_real_escape_string($koneksi, $_POST['tgl_izin'] ?? '');
    $jam_pulang     = mysqli_real_escape_string($koneksi, $_POST['jam_pulang'] ?? '');
    $keperluan      = mysqli_real_escape_string($koneksi, $_POST['keperluan'] ?? '');

    if (!empty($id_izin_pulang) && !empty($id_atasan) && !empty($tgl_izin) && !empty($jam_pulang) && !empty($keperluan)) {
        $query_edit = "UPDATE izin_pulang SET 
                        id_atasan = '$id_atasan',
                        tgl_izin = '$tgl_izin',
                        jam_pulang = '$jam_pulang',
                        keperluan = '$keperluan'
                       WHERE id_izin_pulang = '$id_izin_pulang'";

        if (mysqli_query($koneksi, $query_edit)) {
            $_SESSION['swal'] = [
                'icon' => 'success',
                'title' => 'Data Diperbarui',
                'text' => 'Data permohonan izin pulang awal berhasil diperbarui.'
            ];
        } else {
            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Gagal Update',
                'text' => 'Terjadi kesalahan: ' . mysqli_error($koneksi)
            ];
        }
    } else {
        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Data Belum Lengkap',
            'text' => 'Mohon lengkapi semua isian formulir.'
        ];
    }
    header("Location: index.php?page=izin_pulang");
    exit();
}

// ==========================================
// PROSES UPLOAD DOKUMEN KE E-DOC (OTOMATIS)
// ==========================================
if (isset($_POST['aksi_upload_edoc_pulang'])) {
    $id_izin_pulang = (int)($_POST['id_izin_pulang'] ?? 0);
    $id_user        = (int)($_POST['id_user'] ?? 0);
    $nomor_surat    = trim($_POST['nomor_surat'] ?? '');
    $nama_dokumen   = trim($_POST['nama_dokumen'] ?? '');
    $tgl_dokumen    = trim($_POST['tgl_dokumen'] ?? date('Y-m-d'));
    $keterangan     = trim($_POST['keterangan'] ?? '');
    $uploaded_by    = $_SESSION['id_user'] ?? null;

    if (!empty($id_izin_pulang) && !empty($id_user) && !empty($nama_dokumen) && isset($_FILES['file_edoc'])) {
        $hasil = upload_file_ke_edoc(
            $koneksi,
            $_FILES['file_edoc'],
            'keluar_awal',
            $id_user,
            $nomor_surat,
            $nama_dokumen,
            $tgl_dokumen,
            $keterangan,
            $id_izin_pulang,
            $uploaded_by
        );

        if ($hasil['success']) {
            $_SESSION['swal'] = [
                'icon' => 'success',
                'title' => 'Upload Berhasil!',
                'text' => 'Dokumen berhasil diupload dan otomatis terarsip di menu e-Doc Izin Pulang Awal.'
            ];
        } else {
            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Upload Gagal',
                'text' => $hasil['message']
            ];
        }
    } else {
        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Form Tidak Lengkap',
            'text' => 'Pastikan judul dokumen dan file berkas sudah dipilih.'
        ];
    }
    header("Location: index.php?page=izin_pulang");
    exit();
}

// Ambil list atasan untuk modal edit
$list_atasan_pulang = [];
$q_atasan_pulang = mysqli_query($koneksi, "SELECT id_user, nama_lengkap, jabatan FROM users WHERE is_atasan='1' ORDER BY nama_lengkap ASC");
if ($q_atasan_pulang) {
    while ($at = mysqli_fetch_assoc($q_atasan_pulang)) {
        $list_atasan_pulang[] = $at;
    }
}

$bulanIndo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$hariIni = date('d') . ' ' . $bulanIndo[date('F')] . ' ' . date('Y');

$batas   = 6;
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman = ($halaman < 1) ? 1 : $halaman;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$keyword = "";
$where_clause = "";

if (isset($_GET['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_GET['cari']);
    $where_clause = " AND (
        u.nama_lengkap LIKE '%$keyword%' OR
        a.nama_lengkap LIKE '%$keyword%' OR
        i.tgl_izin LIKE '%$keyword%' OR
        i.jam_pulang LIKE '%$keyword%' OR
        i.keperluan LIKE '%$keyword%'
    )";
}

$query_count_str = "
    SELECT COUNT(i.id_izin_pulang) AS jumlah
    FROM izin_pulang i
    JOIN users u ON i.id_user = u.id_user
    LEFT JOIN users a ON i.id_atasan = a.id_user
    WHERE 1=1 $where_clause
";
$query_count = mysqli_query($koneksi, $query_count_str);
$data_count  = mysqli_fetch_assoc($query_count);
$jumlah_data = (int)($data_count['jumlah'] ?? 0);
$total_halaman = ($jumlah_data > 0) ? (int)ceil($jumlah_data / $batas) : 1;

$query_utama = "
    SELECT i.*, u.nama_lengkap AS pemohon, a.nama_lengkap AS atasan
    FROM izin_pulang i
    JOIN users u ON i.id_user = u.id_user
    LEFT JOIN users a ON i.id_atasan = a.id_user
    WHERE 1=1 $where_clause
    ORDER BY i.id_izin_pulang DESC
    LIMIT $halaman_awal, $batas
";
$q_riwayat = mysqli_query($koneksi, $query_utama);
$no = $halaman_awal + 1;
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
    :root {
        --pn-green: #004d00;
        --pn-dark-green: #003300;
        --pn-gold: #F9A825;
        --pn-gold-dark: #F9A825;
        --border-color: #d1d3e2;
    }

    body { font-family: 'Poppins', sans-serif !important; background-color: #f4f6f9; }

    .flatpickr-calendar { border: none !important; box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important; }
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange,
    .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange,
    .flatpickr-day.endRange.inRange, .flatpickr-day.selected:focus,
    .flatpickr-day.startRange:focus, .flatpickr-day.endRange:focus,
    .flatpickr-day.selected:hover, .flatpickr-day.startRange:hover,
    .flatpickr-day.endRange:hover {
        background: var(--pn-green) !important;
        border-color: var(--pn-green) !important;
    }
    .flatpickr-months .flatpickr-month { background: var(--pn-green) !important; color: #fff !important; fill: #fff !important; padding: 10px 0; }
    .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month { background-color: var(--pn-green) !important; }
    span.flatpickr-weekday { background: var(--pn-green) !important; color: #fff !important; }

    .card-header-pn{
        background-color: var(--pn-green);
        color: white;
        border-bottom: 4px solid var(--pn-gold);
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 10px 10px 0 0;
        gap: 15px;
    }

    .card-header-pn.card-header-compact{
        padding: 10px 16px;
    }

    .page-header-title {
        border-left: 5px solid var(--pn-gold);
        padding-left: 15px;
        color: var(--pn-green);
        font-weight: 700;
        font-size: 1.6rem;
    }

    .form-label-pn { font-size: 0.85rem; font-weight: 600; color: var(--pn-green); margin-bottom: 0.5rem; display: block; }
    .input-group-clean{
        display: flex; align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: #fff;
        transition: all 0.3s ease;
        overflow: hidden;
        height: 48px; width: 100%;
        position: relative;
    }
    .input-group-clean:focus-within{
        border-color: var(--pn-green);
        box-shadow: 0 0 0 4px rgba(0, 77, 0, 0.1);
        transform: translateY(-1px);
    }
    .input-icon-clean{
        width: 45px; height: 100%;
        display: flex; align-items: center; justify-content: center;
        color: var(--pn-green);
        background-color: #f8f9fc;
        border-right: 1px solid #eaecf4;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* === FIX: INPUT JANGAN CENTER, RATA KIRI === */
    .form-control-clean{
        flex: 1; border: none; height: 100%; width: 100%;
        padding: 0 15px;
        font-size: 0.95rem;
        color: #495057;
        background-color: #fff !important;
        outline: none;
        text-align: left !important; /* <--- ini */
    }
    .form-control-clean::placeholder{
        text-align: left !important; /* <--- placeholder rata kiri */
    }
    .form-control-clean:focus{ box-shadow: none; }

    /* pastiin khusus jam juga rata kiri (flatpickr kadang inject style) */
    input.flatpickr-time.form-control-clean{
        text-align: left !important;
    }
    input.flatpickr-date, input.flatpickr-time { cursor: pointer; background-color: #fff !important; }

    .input-group-clean.textarea-group { height: auto !important; align-items: stretch; }
    .input-group-clean.textarea-group .input-icon-clean { height: auto; min-height: 80px; padding-top: 0; }
    textarea.form-control-clean { padding-top: 15px; padding-bottom: 15px; line-height: 1.5; }

    .btn-pn-solid{
        background: linear-gradient(45deg, var(--pn-green), var(--pn-dark-green));
        color: white; border: none; font-weight: 600; padding: 12px 20px;
        border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transition: 0.3s;
    }
    .btn-pn-solid:hover{ transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.2); color: var(--pn-gold); }

    .card-clean { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); overflow: hidden; }
    .section-divider { display: flex; align-items: center; margin: 30px 0 20px 0; }
    .section-divider span { background-color: var(--pn-green); color: white; padding: 6px 15px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border-bottom: 2px solid var(--pn-gold-dark); }
    .section-divider hr { flex-grow: 1; border-top: 2px solid #e3e6f0; margin-left: 15px; }

    .table-pn-head { background-color: var(--pn-green); color: #fff; font-weight: 600; border-top: none; }
    .table-pn-head th{
        border-bottom: 3px solid var(--pn-gold) !important;
        vertical-align: middle !important;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        text-align: center;
    }
    .table tbody td{ font-size: 0.85rem !important; vertical-align: middle; }
    .table-hover tbody tr:hover{ background-color: rgba(0, 77, 0, 0.03) !important; }

    .btn-circle-action { width: 33px; height: 33px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; cursor: pointer; margin: 0 2px; }
    .btn-print { background-color: #e3f2fd; color: #0d47a1; }
    .btn-print:hover { background-color: #bbdefb; transform: scale(1.1); }
    .btn-edit { background-color: #fff8e1; color: #f57f17; }
    .btn-edit:hover { background-color: #ffecb3; transform: scale(1.1); }
    .btn-upload { background-color: #e0f2f1; color: #00695c; }
    .btn-upload:hover { background-color: #b2dfdb; transform: scale(1.1); }
    .btn-edoc-done { background-color: #d1fae5; color: #047857; box-shadow: 0 0 0 2px #10b981 !important; }

    .search-wrapper { position: relative; width: 100%; max-width: 320px; }
    .card-header-pn.card-header-compact .search-wrapper { max-width: 300px; }

    .search-input-inside{
        width: 100%;
        height: 30px;
        padding: 4px 34px 4px 14px !important;
        border-radius: 20px !important;
        border: none;
        background-color: #fff;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        outline: none;
        box-sizing: border-box;
    }

    .card-header-pn.card-header-compact .search-input-inside{
        height: 28px;
        padding: 3px 32px 3px 14px !important;
        font-size: 0.82rem;
    }

    .search-input-inside:focus { box-shadow: 0 0 0 0.2rem rgba(0, 77, 0, 0.18); }
    .search-icon-inside { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #999; pointer-events: none; }

    .pagination { margin-top: 10px; }
    .pagination .page-item .page-link{
        padding: .4rem .9rem !important;
        margin-left: 6px !important;
        border-radius: 10px !important;
        border: 1px solid #e5e7eb !important;
        background: #fff !important;
        color: var(--pn-green) !important;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
    }
    .pagination .page-item.active .page-link{
        background: var(--pn-green) !important;
        color: #fff !important;
        border: 1px solid var(--pn-green) !important;
    }
    .pagination .page-item .page-link:hover{
        background: #f0fdf4 !important;
        border-color: var(--pn-green) !important;
        transform: translateY(-2px);
    }
    .pagination .page-item.disabled .page-link{
        color: #9ca3af !important;
        background: #f9fafb !important;
        border-color: #e5e7eb !important;
        transform: none;
    }
</style>

<div class="container-fluid mb-5 mt-4">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="page-header-title mb-2 mb-sm-0">Izin Pulang Awal</h1>
        <div class="bg-white py-2 px-3 rounded shadow-sm" style="border-left: 4px solid var(--pn-gold);">
            <span class="small font-weight-bold" style="color: var(--pn-green);">
                <i class="far fa-calendar-alt mr-2"></i><?php echo $hariIni; ?>
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card card-clean shadow-sm h-100">
                <div class="card-header-pn">
                    <div class="font-weight-bold"><i class="fas fa-plus-circle mr-2"></i> Buat Izin Baru</div>
                </div>
                <div class="card-body p-4">
                    <form action="pages/proses_izin_pulang.php" method="POST" autocomplete="off">

                        <div class="section-divider mt-0">
                            <span>A. Data Pegawai</span><hr>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-pn">Nama Pegawai <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="fas fa-search"></i></div>
                                <input class="form-control-clean" list="list_pegawai" id="input_pegawai" placeholder="Cari nama pegawai..." required>
                            </div>
                            <datalist id="list_pegawai">
                                <?php
                                $q_all = mysqli_query($koneksi, "SELECT * FROM users ORDER BY nama_lengkap ASC");
                                while($p = mysqli_fetch_array($q_all)){
                                    echo "<option data-id='".$p['id_user']."' value='".$p['nama_lengkap']."'>";
                                }
                                ?>
                            </datalist>
                            <input type="hidden" name="id_user_pemohon" id="id_user_hidden">
                        </div>

                        <div class="mb-3">
                            <label class="form-label-pn">Atasan Langsung <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="fas fa-user-tie"></i></div>
                                <input class="form-control-clean" list="list_atasan" id="input_atasan" placeholder="Cari atasan..." required>
                            </div>
                            <datalist id="list_atasan">
                                <?php
                                $q_atasan = mysqli_query($koneksi, "SELECT * FROM users WHERE is_atasan='1' ORDER BY nama_lengkap ASC");
                                while($at = mysqli_fetch_array($q_atasan)){
                                    echo "<option data-id='".$at['id_user']."' value='".$at['nama_lengkap']." (".$at['jabatan'].")'>";
                                }
                                ?>
                            </datalist>
                            <input type="hidden" name="id_atasan" id="id_atasan_hidden">
                        </div>

                        <div class="section-divider mt-4">
                            <span>B. Detail Izin</span><hr>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-pn">Tanggal Pulang <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="far fa-calendar-alt"></i></div>
                                <input type="text" name="tgl_izin" class="form-control-clean flatpickr-date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-pn">Jam Pulang <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="far fa-clock"></i></div>
                                <input type="text" name="jam_pulang" class="form-control-clean flatpickr-time" required placeholder="Contoh: 14:00">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-pn">Keperluan <span class="text-danger">*</span></label>
                            <div class="input-group-clean textarea-group">
                                <div class="input-icon-clean"><i class="fas fa-align-left"></i></div>
                                <textarea name="keperluan" class="form-control-clean" rows="2" placeholder="Alasan..." required></textarea>
                            </div>
                        </div>

                        <button type="submit" name="simpan_izin_pulang" class="btn btn-pn-solid btn-block py-2">
                            <i class="fas fa-save mr-2"></i> Simpan Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-clean shadow-sm mb-4">
                <div class="card-header-pn card-header-compact">
                    <div class="font-weight-bold">
                        <i class="fas fa-history mr-2"></i>Riwayat Izin Pulang Awal
                    </div>

                    <div class="search-wrapper">
                        <input type="text" id="keyword" class="search-input-inside"
                               placeholder="Cari pegawai / tanggal / keperluan..."
                               value="<?php echo htmlspecialchars($keyword); ?>"
                               autocomplete="off">
                        <i class="fas fa-search search-icon-inside"></i>
                    </div>
                </div>

                <div class="card-body">
                    <div id="area_tabel_admin">

                        <?php if(mysqli_num_rows($q_riwayat) == 0) { ?>
                            <div class="alert alert-light text-center border shadow-sm" style="border-radius: 12px;">
                                <h6 class="text-muted m-3"><i class="fas fa-info-circle mr-2"></i>Data tidak ditemukan.</h6>
                            </div>
                        <?php } else { ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead class="table-pn-head">
                                    <tr class="text-center">
                                        <th width="5%">No</th>
                                        <th>Pegawai</th>
                                        <th>Waktu & Keperluan</th>
                                        <th>Atasan Langsung</th>
                                        <th width="14%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_array($q_riwayat)): ?>
                                    <tr>
                                        <td class="text-center font-weight-bold align-middle"><?= $no++; ?></td>

                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark"><?= $row['pemohon']; ?></div>
                                            <!-- HAPUS atasan di bawah nama pemohon (sesuai request) -->
                                        </td>

<td class="align-middle text-center">

    <div class="font-weight-bold text-primary" style="font-size: 0.9rem;">
        <?= date('d/m/Y', strtotime($row['tgl_izin'])); ?>
    </div>

    <div class="font-weight-bold text-dark" style="font-size: 0.86rem; margin-top: 2px;">
        <?= date('H:i', strtotime($row['jam_pulang'])); ?> WIB
    </div>

    <div class="text-muted font-italic small mt-1" style="line-height: 1.25;">
        "<?= $row['keperluan']; ?>"
    </div>

</td>

                                        <td class="align-middle">
                                            <?= $row['atasan'] ? $row['atasan'] : '<span class="text-muted">-</span>'; ?>
                                        </td>

                                        <td class="text-center align-middle">
                                            <?php 
                                            $edoc_items = get_edoc_terkait($koneksi, 'keluar_awal', $row['id_izin_pulang']);
                                            $has_edoc = !empty($edoc_items);
                                            ?>
                                            <div class="d-flex justify-content-center align-items-center">
                                                <!-- Cetak -->
                                                <a href="pages/cetak_izin_pulang.php?id=<?= $row['id_izin_pulang']; ?>" target="_blank"
                                                   class="btn-circle-action btn-print shadow-sm mr-1" title="Cetak Surat">
                                                    <i class="fas fa-print fa-sm"></i>
                                                </a>

                                                <!-- Edit Data (Perbaiki Salah Ketik) -->
                                                <button type="button" class="btn-circle-action btn-edit shadow-sm mr-1 btn-modal-edit-pulang"
                                                        data-id="<?= $row['id_izin_pulang'] ?>"
                                                        data-iduser="<?= $row['id_user'] ?>"
                                                        data-nama="<?= htmlspecialchars($row['pemohon']) ?>"
                                                        data-idatasan="<?= $row['id_atasan'] ?>"
                                                        data-tgl="<?= $row['tgl_izin'] ?>"
                                                        data-jampulang="<?= date('H:i', strtotime($row['jam_pulang'])) ?>"
                                                        data-keperluan="<?= htmlspecialchars($row['keperluan']) ?>"
                                                        title="Edit Data Izin">
                                                    <i class="fas fa-pencil-alt fa-sm"></i>
                                                </button>

                                                <!-- Upload Dokumen (Otomatis ke e-Doc) -->
                                                <button type="button" class="btn-circle-action btn-upload shadow-sm mr-1 btn-modal-upload-pulang <?= $has_edoc ? 'btn-edoc-done' : '' ?>"
                                                        data-id="<?= $row['id_izin_pulang'] ?>"
                                                        data-iduser="<?= $row['id_user'] ?>"
                                                        data-nama="<?= htmlspecialchars($row['pemohon']) ?>"
                                                        data-tgl="<?= $row['tgl_izin'] ?>"
                                                        title="<?= $has_edoc ? 'Dokumen sudah diupload ke e-Doc (Klik untuk tambah dokumen baru)' : 'Upload Dokumen (Otomatis Masuk ke e-Doc)'; ?>">
                                                    <i class="fas fa-cloud-upload-alt fa-sm"></i>
                                                </button>
                                            </div>
                                            <?php if ($has_edoc): ?>
                                                <div class="mt-1">
                                                    <a href="uploads/edoc/<?= htmlspecialchars($edoc_items[0]['file_dokumen']) ?>" target="_blank" 
                                                       class="badge badge-success px-2 py-1 shadow-sm text-white" 
                                                       style="background-color: #059669; font-size: 0.70rem; font-weight: 600; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" 
                                                       title="Klik untuk melihat berkas e-Doc (<?= htmlspecialchars($edoc_items[0]['nama_dokumen']) ?>)">
                                                        <i class="fas fa-check-circle"></i> Berhasil Terupload
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3">
                            <div class="text-muted small mb-2 mb-md-0">
                                Halaman <?php echo $halaman; ?> dari <?php echo $total_halaman; ?>. total: <?php echo $jumlah_data; ?> data
                            </div>

                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php if($halaman <= 1) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($halaman > 1){ echo "?page=izin_pulang&hal=".($halaman-1)."&cari=".urlencode($keyword); } ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for($x = 1; $x <= $total_halaman; $x++): ?>
                                        <li class="page-item <?php if($halaman == $x) echo 'active'; ?>">
                                            <a class="page-link" href="?page=izin_pulang&hal=<?php echo $x; ?>&cari=<?php echo urlencode($keyword); ?>">
                                                <?php echo $x; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php if($halaman >= $total_halaman) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($halaman < $total_halaman){ echo "?page=izin_pulang&hal=".($halaman+1)."&cari=".urlencode($keyword); } ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>

                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>

<script>
    function setupAutocomplete(inputId, listId, hiddenId) {
        const inputEl = document.getElementById(inputId);
        const hiddenEl = document.getElementById(hiddenId);

        if(inputEl) {
            inputEl.addEventListener('input', function() {
                var inputVal = this.value;
                var listOptions = document.querySelectorAll('#' + listId + ' option');
                hiddenEl.value = "";
                for (var i = 0; i < listOptions.length; i++) {
                    if (listOptions[i].value === inputVal) {
                        hiddenEl.value = listOptions[i].getAttribute('data-id');
                        break;
                    }
                }
            });

            inputEl.addEventListener('change', function() {
                if(hiddenEl.value === "") {
                    var inputVal = this.value;
                    var listOptions = document.querySelectorAll('#' + listId + ' option');
                    for (var i = 0; i < listOptions.length; i++) {
                        if (listOptions[i].value === inputVal) {
                            hiddenEl.value = listOptions[i].getAttribute('data-id');
                            break;
                        }
                    }
                }
            });
        }
    }
    setupAutocomplete('input_pegawai', 'list_pegawai', 'id_user_hidden');
    setupAutocomplete('input_atasan', 'list_atasan', 'id_atasan_hidden');

    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".flatpickr-date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            locale: "id",
            allowInput: false,
            disableMobile: "true"
        });

        flatpickr(".flatpickr-time", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            minTime: "08:00",
            maxTime: "17:00",
            allowInput: false
        });
    });

    $(document).ready(function() {
        $('#keyword').on('keyup', function() {
            var keyword = $(this).val();
            $('#area_tabel_admin').load(
                'index.php?page=izin_pulang&cari=' + encodeURIComponent(keyword) + ' #area_tabel_admin'
            );
        });

        // Handler Modal Edit Izin Pulang Awal
        $(document).on('click', '.btn-modal-edit-pulang', function() {
            var btn = $(this);
            $('#edit_pulang_id_izin').val(btn.data('id'));
            $('#edit_pulang_nama_text').text(btn.data('nama'));
            $('#edit_pulang_id_atasan').val(btn.data('idatasan'));
            $('#edit_pulang_tgl_izin').val(btn.data('tgl'));
            $('#edit_pulang_jam_pulang').val(btn.data('jampulang'));
            $('#edit_pulang_keperluan').val(btn.data('keperluan'));

            $('#modalEditIzinPulang').modal('show');
        });

        // Handler Modal Upload Dokumen e-Doc
        $(document).on('click', '.btn-modal-upload-pulang', function() {
            var btn = $(this);
            var nama = btn.data('nama');
            var tgl = btn.data('tgl');

            $('#upload_pulang_id_izin').val(btn.data('id'));
            $('#upload_pulang_id_user').val(btn.data('iduser'));
            $('#upload_pulang_nama_text').text(nama);
            
            var defaultJudul = 'Surat Izin Pulang Awal - ' + nama;
            if (tgl) {
                defaultJudul += ' (' + tgl + ')';
            }
            $('#upload_pulang_nama_dokumen').val(defaultJudul);
            $('#upload_pulang_nomor_surat').val('');
            $('#upload_pulang_tgl_dokumen').val(new Date().toISOString().split('T')[0]);
            $('#upload_pulang_keterangan').val('');
            $('#upload_pulang_file_input').val('');
            $('#upload_pulang_file_label').text('Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');

            $('#modalUploadEdocPulang').modal('show');
        });

        // Update custom file label on change
        $(document).on('change', '#upload_pulang_file_input', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#upload_pulang_file_label').text(fileName ? fileName : 'Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');
        });
    });

    <?php if (isset($_SESSION['swal'])) { ?>
        Swal.fire({
            icon: '<?php echo $_SESSION['swal']['icon']; ?>',
            title: '<?php echo $_SESSION['swal']['title']; ?>',
            text: '<?php echo $_SESSION['swal']['text']; ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['swal']); ?>
    <?php } ?>
</script>

<!-- ==============================================================
     MODAL 1: EDIT DATA IZIN PULANG AWAL (PERBAIKI SALAH KETIK)
     ============================================================== -->
<div class="modal fade" id="modalEditIzinPulang" tabindex="-1" role="dialog" aria-labelledby="modalEditIzinPulangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #F9A825;">
                <h5 class="modal-title font-weight-bold" id="modalEditIzinPulangLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-pencil-alt mr-2 text-warning"></i> Edit Data Izin Pulang Awal
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=izin_pulang" method="POST" autocomplete="off">
                <input type="hidden" name="aksi_edit_izin_pulang" value="1">
                <input type="hidden" name="id_izin_pulang" id="edit_pulang_id_izin">

                <div class="modal-body p-4" style="background: #fdfdfd;">
                    
                    <!-- Info Pemohon -->
                    <div class="p-3 mb-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x text-success mr-3"></i>
                            <div>
                                <small class="text-muted d-block">Nama Pegawai / Pemohon:</small>
                                <div class="font-weight-bold text-dark" id="edit_pulang_nama_text" style="font-size: 1rem;">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Atasan Langsung <span class="text-danger">*</span></label>
                        <select name="id_atasan" id="edit_pulang_id_atasan" class="form-control" required>
                            <option value="">-- Pilih Atasan --</option>
                            <?php foreach ($list_atasan_pulang as $at): ?>
                                <option value="<?= $at['id_user'] ?>"><?= htmlspecialchars($at['nama_lengkap']) ?> (<?= htmlspecialchars($at['jabatan']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Tanggal Izin <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_izin" id="edit_pulang_tgl_izin" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Jam Pulang <span class="text-danger">*</span></label>
                            <input type="time" name="jam_pulang" id="edit_pulang_jam_pulang" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group mb-1">
                        <label class="font-weight-bold small text-dark">Keperluan <span class="text-danger">*</span></label>
                        <textarea name="keperluan" id="edit_pulang_keperluan" class="form-control" rows="3" required placeholder="Keperluan izin pulang awal..."></textarea>
                    </div>

                </div>
                
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning px-4 font-weight-bold text-dark" style="background:#F9A825; border-color:#F9A825;">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL 2: UPLOAD DOKUMEN IZIN PULANG AWAL (OTOMATIS MASUK E-DOC)
     ============================================================== -->
<div class="modal fade" id="modalUploadEdocPulang" tabindex="-1" role="dialog" aria-labelledby="modalUploadEdocPulangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold" id="modalUploadEdocPulangLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-cloud-upload-alt mr-2 text-warning"></i> Upload Dokumen Izin Pulang Awal (e-Doc)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=izin_pulang" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="aksi_upload_edoc_pulang" value="1">
                <input type="hidden" name="id_izin_pulang" id="upload_pulang_id_izin">
                <input type="hidden" name="id_user" id="upload_pulang_id_user">

                <div class="modal-body p-4" style="background: #fdfdfd;">
                    
                    <div class="alert alert-info border-0 shadow-sm p-3 mb-3 small" style="border-radius: 10px; background:#e0f2fe; color:#0369a1;">
                        <i class="fas fa-magic mr-1 font-weight-bold"></i> Berkas yang diunggah di sini <strong>otomatis tersimpan & terarsip ke menu e-Doc Izin Pulang Awal</strong> tanpa perlu input ulang atau upload manual.
                    </div>

                    <!-- Info Pemohon -->
                    <div class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <small class="text-muted d-block">Pemohon Izin:</small>
                        <div class="font-weight-bold text-dark" id="upload_pulang_nama_text">-</div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Nomor Surat / Bukti Izin (Opsional)</label>
                        <input type="text" name="nomor_surat" id="upload_pulang_nomor_surat" class="form-control" placeholder="Contoh: W13-U1/ /KP.05.2/XI/2026">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Judul Dokumen (di e-Doc) <span class="text-danger">*</span></label>
                        <input type="text" name="nama_dokumen" id="upload_pulang_nama_dokumen" class="form-control" required placeholder="Contoh: Surat Izin Pulang Awal - Nama Pegawai">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Tanggal Dokumen <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_dokumen" id="upload_pulang_tgl_dokumen" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Pilih Berkas Dokumen <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="file_edoc" id="upload_pulang_file_input" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            <label class="custom-file-label" id="upload_pulang_file_label" for="upload_pulang_file_input">Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...</label>
                        </div>
                        <small class="text-muted mt-1 d-block">Format diperbolehkan: PDF, JPG, PNG, DOC, DOCX. Maksimal 10 MB.</small>
                    </div>

                    <div class="form-group mb-1">
                        <label class="font-weight-bold small text-dark">Keterangan / Catatan Arsip (Opsional)</label>
                        <textarea name="keterangan" id="upload_pulang_keterangan" class="form-control" rows="2" placeholder="Catatan tambahan dokumen e-Doc..."></textarea>
                    </div>

                </div>
                
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success px-4 font-weight-bold" style="background:#059669; border-color:#059669;">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Upload ke e-Doc
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
