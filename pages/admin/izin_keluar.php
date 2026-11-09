<?php
/** @var mysqli $koneksi */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/edoc_helper.php';

// 1. PROSES FORM: EDIT DATA IZIN KELUAR KANTOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_edit_izin_keluar'])) {
    $id_izin     = (int)$_POST['id_izin'];
    $id_atasan   = (int)$_POST['id_atasan'];
    $tgl_izin    = mysqli_real_escape_string($koneksi, $_POST['tgl_izin']);
    $jam_keluar  = mysqli_real_escape_string($koneksi, $_POST['jam_keluar']);
    $jam_kembali = mysqli_real_escape_string($koneksi, $_POST['jam_kembali']);
    $keperluan   = mysqli_real_escape_string($koneksi, trim($_POST['keperluan']));
    $status      = mysqli_real_escape_string($koneksi, $_POST['status'] ?? 'Disetujui');

    $sql_upd = "UPDATE izin_keluar SET 
                    id_atasan = $id_atasan,
                    tgl_izin = '$tgl_izin',
                    jam_keluar = '$jam_keluar',
                    jam_kembali = '$jam_kembali',
                    keperluan = '$keperluan',
                    status = '$status'
                WHERE id_izin = $id_izin";

    if (mysqli_query($koneksi, $sql_upd)) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Data Diperbarui!',
            'text' => 'Perubahan data izin keluar kantor berhasil disimpan.'
        ];
    } else {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Gagal Memperbarui!',
            'text' => 'Terjadi kesalahan: ' . mysqli_error($koneksi)
        ];
    }
    echo "<script>window.location.href='index.php?page=izin_keluar';</script>";
    exit();
}

// 2. PROSES FORM: UPLOAD DOKUMEN E-DOC IZIN KELUAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_upload_edoc_keluar'])) {
    $id_izin      = (int)$_POST['id_izin'];
    $id_user      = (int)$_POST['id_user'];
    $nomor_surat  = trim($_POST['nomor_surat'] ?? '');
    $nama_dokumen = trim($_POST['nama_dokumen'] ?? '');
    $tgl_dokumen  = $_POST['tgl_dokumen'] ?? date('Y-m-d');
    $keterangan   = trim($_POST['keterangan'] ?? '');
    $file         = $_FILES['file_edoc'] ?? null;

    $res = upload_file_ke_edoc($koneksi, 'izin_keluar', $id_user, $nomor_surat, $nama_dokumen, $tgl_dokumen, $file, $keterangan, $id_izin, $_SESSION['id_user'] ?? 1);

    if ($res['status']) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Upload Berhasil!',
            'text' => 'Dokumen izin keluar kantor berhasil diunggah dan otomatis terarsip ke e-Doc Izin Keluar Kantor.'
        ];
    } else {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Gagal Upload!',
            'text' => $res['pesan']
        ];
    }
    echo "<script>window.location.href='index.php?page=izin_keluar';</script>";
    exit();
}

// Ambil daftar atasan untuk modal edit
$q_atasan_list = mysqli_query($koneksi, "SELECT id_user, nama_lengkap, jabatan FROM users WHERE is_atasan='1' ORDER BY nama_lengkap ASC");
$list_atasan_keluar = [];
while ($at = mysqli_fetch_assoc($q_atasan_list)) {
    $list_atasan_keluar[] = $at;
}

$bulanIndo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$hariIni = date('d') . ' ' . $bulanIndo[date('F')] . ' ' . date('Y');


$batas   = 7;
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$keyword_izin = "";
$where_clause_izin = "";

if (isset($_GET['cari_izin'])) {
    $keyword_izin = mysqli_real_escape_string($koneksi, $_GET['cari_izin']);
    $where_clause_izin = " AND (
        u.nama_lengkap LIKE '%$keyword_izin%' OR
        a.nama_lengkap LIKE '%$keyword_izin%' OR
        i.tgl_izin LIKE '%$keyword_izin%' OR
        i.keperluan LIKE '%$keyword_izin%' OR
        i.jam_keluar LIKE '%$keyword_izin%' OR
        i.jam_kembali LIKE '%$keyword_izin%'
    )";
}

$query_count_str_izin = "
    SELECT COUNT(i.id_izin) AS jumlah
    FROM izin_keluar i
    JOIN users u ON i.id_user = u.id_user
    LEFT JOIN users a ON i.id_atasan = a.id_user
    WHERE 1=1 $where_clause_izin
";
$query_count_izin = mysqli_query($koneksi, $query_count_str_izin);
$data_count_izin  = mysqli_fetch_assoc($query_count_izin);
$jumlah_data_izin = (int)($data_count_izin['jumlah'] ?? 0);
$total_halaman_izin = ($jumlah_data_izin > 0) ? (int)ceil($jumlah_data_izin / $batas) : 1;

$q_riwayat = mysqli_query($koneksi, "
    SELECT i.*, u.nama_lengkap AS pemohon, a.nama_lengkap AS atasan
    FROM izin_keluar i
    JOIN users u ON i.id_user = u.id_user
    LEFT JOIN users a ON i.id_atasan = a.id_user
    WHERE 1=1 $where_clause_izin
    ORDER BY i.id_izin DESC
    LIMIT $halaman_awal, $batas
");
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

    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, 
    .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange, 
    .flatpickr-day.endRange.inRange, .flatpickr-day.selected:focus, 
    .flatpickr-day.startRange:focus, .flatpickr-day.endRange:focus, 
    .flatpickr-day.selected:hover, .flatpickr-day.startRange:hover, 
    .flatpickr-day.endRange:hover, .flatpickr-day.selected.prevMonthDay, 
    .flatpickr-day.startRange.prevMonthDay, .flatpickr-day.endRange.prevMonthDay, 
    .flatpickr-day.selected.nextMonthDay, .flatpickr-day.startRange.nextMonthDay, 
    .flatpickr-day.endRange.nextMonthDay {
        background: var(--pn-green) !important;
        border-color: var(--pn-green) !important;
    }
    .flatpickr-months .flatpickr-month {
        background: var(--pn-green) !important;
        color: #fff !important;
        fill: #fff !important;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
        background-color: var(--pn-green) !important;
    }
    span.flatpickr-weekday {
        background: var(--pn-green) !important;
        color: #fff !important;
    }

    .card-header-pn {
        background-color: var(--pn-green);
        color: white;
        border-bottom: 4px solid var(--pn-gold);
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 10px 10px 0 0;
    }

    .card-header-pn.card-header-mini{
        padding-top: 10px !important;
        padding-bottom: 10px !important;
        min-height: 52px; 
        box-sizing: border-box;
    }

    .page-header-title { 
        border-left: 5px solid var(--pn-gold);
        padding-left: 15px; 
        color: var(--pn-green);
        font-weight: 700; 
        font-size: 1.6rem; 
    }

    .form-label-pn { 
        font-size: 0.85rem; 
        font-weight: 600; 
        color: var(--pn-green); 
        margin-bottom: 0.5rem;
        display: block;
    }

    .input-group-clean {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: #fff;
        transition: all 0.3s ease;
        overflow: hidden;
        height: 48px; 
        width: 100%;
        position: relative;
    }
    .input-group-clean:focus-within {
        border-color: var(--pn-green);
        box-shadow: 0 0 0 4px rgba(0, 77, 0, 0.1);
        transform: translateY(-1px);
    }
    .input-icon-clean {
        width: 45px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--pn-green);
        background-color: #f8f9fc;
        border-right: 1px solid #eaecf4;
        font-size: 1rem;
        flex-shrink: 0; 
    }
    
    .form-control-clean {
        flex: 1;
        border: none;
        height: 100%;
        width: 100%;
        padding: 0 15px;
        font-size: 0.95rem;
        color: #495057;
        background-color: #fff; 
        outline: none;
    }
    .form-control-clean:focus { box-shadow: none; }

    select.form-control-clean { padding-top: 12px; padding-bottom: 12px; cursor: pointer; }
    .input-group-clean.textarea-group { height: auto !important; align-items: stretch; }
    .input-group-clean.textarea-group .input-icon-clean { height: auto; min-height: 80px; padding-top: 0; }
    textarea.form-control-clean { padding-top: 15px; padding-bottom: 15px; line-height: 1.5; }

    .btn-pn-solid { 
        background: linear-gradient(45deg, var(--pn-green), var(--pn-dark-green)); 
        color: white; border: none; font-weight: 600; padding: 12px 20px; 
        border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transition: 0.3s; 
    }
    .btn-pn-solid:hover { 
        transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.2); color: var(--pn-gold); 
    }
    
    .card-clean { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); overflow: hidden; }
    .section-divider { display: flex; align-items: center; margin: 30px 0 20px 0; }
    .section-divider span { background-color: var(--pn-green); color: white; padding: 6px 15px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border-bottom: 2px solid var(--pn-gold-dark); }
    .section-divider hr { flex-grow: 1; border-top: 2px solid #e3e6f0; margin-left: 15px; }

    .table-pn-head {
        background-color: var(--pn-green);
        color: #fff;
        font-weight: 600;
        border-top: none;
    }
    .table-pn-head th {
        border-bottom: 3px solid var(--pn-gold) !important;
        vertical-align: middle !important;
        text-transform: uppercase;
        font-size: 0.80rem; 
        letter-spacing: 0.5px;
    }
    .table tbody td {
        font-size: 0.75rem !important; 
        vertical-align: middle;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 77, 0, 0.03) !important;
    }

    .btn-circle-action { 
        width: 33px; height: 33px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; cursor: pointer; 
    }
    .btn-print { background-color: #e3f2fd; color: #0d47a1; }
    .btn-print:hover { background-color: #bbdefb; transform: scale(1.1); }
    .btn-edit { background-color: #fff8e1; color: #f57f17; }
    .btn-edit:hover { background-color: #ffecb3; transform: scale(1.1); }
    .btn-upload { background-color: #e0f2f1; color: #00695c; }
    .btn-upload:hover { background-color: #b2dfdb; transform: scale(1.1); }
    .btn-edoc-done { background-color: #d1fae5; color: #047857; box-shadow: 0 0 0 2px #10b981 !important; }

    .search-wrapper { position: relative; width: 100%; max-width: 300px; }
    .search-input-inside {
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
    .search-input-inside:focus {
        background-color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(0, 77, 0, 0.18);
        outline: none;
    }
    .search-icon-inside { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #999; pointer-events: none; }

    .pagination { margin-top: 10px; }
    .pagination .page-item .page-link {
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
    .pagination .page-item.active .page-link {
        background: var(--pn-green) !important;
        color: #fff !important;
        border: 1px solid var(--pn-green) !important;
    }
    .pagination .page-item .page-link:hover {
        background: #f0fdf4 !important;
        border-color: var(--pn-green) !important;
        transform: translateY(-2px);
    }
    .pagination .page-item.disabled .page-link {
        color: #9ca3af !important;
        background: #f9fafb !important;
        border-color: #e5e7eb !important;
        transform: none;
    }
</style>

<div class="container-fluid mb-5 mt-4">
    
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="page-header-title">Izin Keluar Kantor</h1>
        <div class="bg-white p-2 rounded shadow-sm" style="border-left: 4px solid var(--pn-gold);">
            <span class="small font-weight-bold" style="color: var(--pn-green);">
                <i class="far fa-calendar-alt mr-2"></i><?php echo $hariIni; ?>
            </span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card card-clean shadow-sm h-100">
                <div class="card-header-pn card-header-mini">
                    <div class="font-weight-bold"><i class="fas fa-plus-circle mr-2"></i> Buat Izin Baru</div>
                </div>

                <div class="card-body p-4">
                    <form action="pages/proses_izin.php" method="POST" autocomplete="off">
                        
                        <div class="section-divider mt-0">
                            <span>A. Data Pegawai</span><hr>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-pn">Nama Pegawai <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="fas fa-search"></i></div>
                                <input class="form-control-clean" list="list_pegawai" id="input_pegawai" placeholder="Ketik nama untuk mencari..." required>
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
                                <input class="form-control-clean" list="list_atasan" id="input_atasan" placeholder="Ketik nama atasan..." required>
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
                            <label class="form-label-pn">Tanggal Izin <span class="text-danger">*</span></label>
                            <div class="input-group-clean">
                                <div class="input-icon-clean"><i class="far fa-calendar-alt"></i></div>
                                <input type="text" name="tgl_izin" class="form-control-clean flatpickr-date" value="<?php echo date('Y-m-d'); ?>" required placeholder="Pilih Tanggal">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-pn">Jam Keluar <span class="text-danger">*</span></label>
                                <div class="input-group-clean">
                                    <div class="input-icon-clean"><i class="far fa-clock"></i></div>
                                    <input type="text" name="jam_keluar" class="form-control-clean flatpickr-time" required placeholder="08:00">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-pn">Jam Kembali <span class="text-danger">*</span></label>
                                <div class="input-group-clean">
                                    <div class="input-icon-clean"><i class="fas fa-history"></i></div>
                                    <input type="text" name="jam_kembali" class="form-control-clean flatpickr-time" required placeholder="17:00">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-pn">Keperluan <span class="text-danger">*</span></label>
                            <div class="input-group-clean textarea-group">
                                <div class="input-icon-clean"><i class="fas fa-align-left"></i></div>
                                <textarea name="keperluan" class="form-control-clean" rows="2" placeholder="Jelaskan keperluan izin..." required></textarea>
                            </div>
                        </div>

                        <button type="submit" name="simpan_izin" class="btn btn-pn-solid btn-block py-2">
                            <i class="fas fa-save mr-2"></i> Simpan Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-clean shadow-sm mb-4">
                <div class="card-header-pn card-header-mini">
                    <div class="font-weight-bold"><i class="fas fa-history mr-2"></i>Riwayat Izin Keluar Kantor</div>

                    <div class="search-wrapper">
                        <input type="text" id="keyword_izin" class="search-input-inside"
                               placeholder="Cari Nama / Tanggal..."
                               value="<?php echo htmlspecialchars($keyword_izin); ?>"
                               autocomplete="off">
                        <i class="fas fa-search search-icon-inside"></i>
                    </div>
                </div>

                <div class="card-body">
                    <div id="area_tabel_izin">
                        <?php if(mysqli_num_rows($q_riwayat) == 0) { ?>
                            <div class="alert alert-light text-center border shadow-sm" style="border-radius: 12px;">
                                <h6 class="text-muted m-3"><i class="fas fa-info-circle mr-2"></i>Data riwayat izin tidak ditemukan.</h6>
                            </div>
                        <?php } else { ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead class="table-pn-head">
                                    <tr class="text-center">
                                        <th width="5%">No</th>
                                        <th>Pemohon</th>
                                        <th>Tanggal & Waktu</th>
                                        <th>Keperluan</th>
                                        <th>Atasan</th>
                                        <th width="12%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_array($q_riwayat)): ?>
                                    <tr>
                                        <td class="text-center font-weight-bold align-middle"><?= $no++; ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark" style="font-size: 15px;"><?= $row['pemohon']; ?></div>
                                        </td>
<td class="align-middle text-center" style="font-size: 0.85rem;">
    <div class="font-weight-bold text-primary" style="font-size: 0.9rem;">
        <?= date('d/m/Y', strtotime($row['tgl_izin'])); ?>
    </div>

    <div class="font-weight-bold text-dark mt-1"
         style="font-size: 0.65rem; white-space: nowrap;">
        <?= date('H:i', strtotime($row['jam_keluar'])); ?> - <?= date('H:i', strtotime($row['jam_kembali'])); ?> WIB
    </div>
</td>
                                        <td class="align-middle" style="font-size: 0.85rem;"><?= $row['keperluan']; ?></td>
                                        <td class="align-middle" style="font-size: 0.85rem;"><?= $row['atasan'] ?? '-'; ?></td>
                                        <td class="text-center align-middle">
                                            <?php 
                                            $edoc_items = get_edoc_terkait($koneksi, 'izin_keluar', $row['id_izin']);
                                            $has_edoc = !empty($edoc_items);
                                            ?>
                                            <div class="d-flex justify-content-center align-items-center">
                                                <!-- Cetak -->
                                                <a href="pages/cetak_izin_keluar.php?id=<?= $row['id_izin']; ?>" target="_blank" class="btn-circle-action btn-print shadow-sm mr-1" title="Cetak Surat">
                                                    <i class="fas fa-print fa-sm"></i>
                                                </a>

                                                <!-- Edit Data (Perbaiki Salah Ketik) -->
                                                <button type="button" class="btn-circle-action btn-edit shadow-sm mr-1 btn-modal-edit-keluar"
                                                        data-id="<?= $row['id_izin'] ?>"
                                                        data-iduser="<?= $row['id_user'] ?>"
                                                        data-nama="<?= htmlspecialchars($row['pemohon']) ?>"
                                                        data-idatasan="<?= $row['id_atasan'] ?>"
                                                        data-tgl="<?= $row['tgl_izin'] ?>"
                                                        data-jamkeluar="<?= date('H:i', strtotime($row['jam_keluar'])) ?>"
                                                        data-jamkembali="<?= date('H:i', strtotime($row['jam_kembali'])) ?>"
                                                        data-keperluan="<?= htmlspecialchars($row['keperluan']) ?>"
                                                        data-status="<?= htmlspecialchars($row['status']) ?>"
                                                        title="Edit Data Izin">
                                                    <i class="fas fa-pencil-alt fa-sm"></i>
                                                </button>

                                                <!-- Upload Dokumen (Otomatis ke e-Doc) -->
                                                <button type="button" class="btn-circle-action btn-upload shadow-sm mr-1 btn-modal-upload-keluar <?= $has_edoc ? 'btn-edoc-done' : '' ?>"
                                                        data-id="<?= $row['id_izin'] ?>"
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
                                Halaman <?php echo $halaman; ?> dari <?php echo $total_halaman_izin; ?>. total: <?php echo $jumlah_data_izin; ?> izin
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php if($halaman <= 1) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($halaman > 1){ echo "?page=izin_keluar&hal=".($halaman-1)."&cari_izin=".urlencode($keyword_izin); } ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for($x = 1; $x <= $total_halaman_izin; $x++): ?>
                                        <li class="page-item <?php if($halaman == $x) echo 'active'; ?>">
                                            <a class="page-link" href="?page=izin_keluar&hal=<?php echo $x; ?>&cari_izin=<?php echo urlencode($keyword_izin); ?>"><?php echo $x; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php if($halaman >= $total_halaman_izin) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($halaman < $total_halaman_izin){ echo "?page=izin_keluar&hal=".($halaman+1)."&cari_izin=".urlencode($keyword_izin); } ?>">
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

<!-- ═══════════════════════════════════════════════════════════
     MODAL 1: EDIT DATA IZIN KELUAR KANTOR (PERBAIKI SALAH KETIK)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditIzinKeluar" tabindex="-1" role="dialog" aria-labelledby="modalEditIzinKeluarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #F9A825;">
                <h5 class="modal-title font-weight-bold" id="modalEditIzinKeluarLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-pencil-alt mr-2 text-warning"></i> Edit Data Izin Keluar Kantor
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=izin_keluar" method="POST" autocomplete="off">
                <input type="hidden" name="aksi_edit_izin_keluar" value="1">
                <input type="hidden" name="id_izin" id="edit_keluar_id_izin">

                <div class="modal-body p-4" style="background: #fdfdfd;">
                    
                    <!-- Info Pemohon -->
                    <div class="p-3 mb-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x text-success mr-3"></i>
                            <div>
                                <small class="text-muted d-block">Nama Pegawai / Pemohon:</small>
                                <div class="font-weight-bold text-dark" id="edit_keluar_nama_text" style="font-size: 1rem;">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Atasan Langsung <span class="text-danger">*</span></label>
                        <select name="id_atasan" id="edit_keluar_id_atasan" class="form-control" required>
                            <option value="">-- Pilih Atasan --</option>
                            <?php foreach ($list_atasan_keluar as $at): ?>
                                <option value="<?= $at['id_user'] ?>"><?= htmlspecialchars($at['nama_lengkap']) ?> (<?= htmlspecialchars($at['jabatan']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Tanggal Izin <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_izin" id="edit_keluar_tgl_izin" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Jam Keluar <span class="text-danger">*</span></label>
                            <input type="time" name="jam_keluar" id="edit_keluar_jam_keluar" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Jam Kembali <span class="text-danger">*</span></label>
                            <input type="time" name="jam_kembali" id="edit_keluar_jam_kembali" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Keperluan <span class="text-danger">*</span></label>
                        <textarea name="keperluan" id="edit_keluar_keperluan" class="form-control" rows="2" required placeholder="Keperluan izin keluar..."></textarea>
                    </div>

                    <div class="form-group mb-1">
                        <label class="font-weight-bold small text-dark">Status Izin</label>
                        <select name="status" id="edit_keluar_status" class="form-control font-weight-bold">
                            <option value="Disetujui">Disetujui</option>
                            <option value="Diajukan">Diajukan</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>

                </div>
                
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold" style="background:#004d00; border-color:#004d00;">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 2: UPLOAD DOKUMEN IZIN KELUAR (OTOMATIS MASUK E-DOC)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalUploadEdocKeluar" tabindex="-1" role="dialog" aria-labelledby="modalUploadEdocKeluarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold" id="modalUploadEdocKeluarLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-cloud-upload-alt mr-2 text-warning"></i> Upload Dokumen Izin Keluar (e-Doc)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=izin_keluar" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="aksi_upload_edoc_keluar" value="1">
                <input type="hidden" name="id_izin" id="upload_keluar_id_izin">
                <input type="hidden" name="id_user" id="upload_keluar_id_user">

                <div class="modal-body p-4" style="background: #fdfdfd;">
                    
                    <div class="alert alert-info border-0 shadow-sm p-3 mb-3 small" style="border-radius: 10px; background:#e0f2fe; color:#0369a1;">
                        <i class="fas fa-magic mr-1 font-weight-bold"></i> Berkas yang diunggah di sini <strong>otomatis tersimpan & terarsip ke menu e-Doc Izin Keluar Kantor</strong> tanpa perlu input ulang atau upload manual.
                    </div>

                    <!-- Info Pemohon -->
                    <div class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <small class="text-muted d-block">Pemohon Izin:</small>
                        <div class="font-weight-bold text-dark" id="upload_keluar_nama_text">-</div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Nomor Surat / Bukti Izin (Opsional)</label>
                        <input type="text" name="nomor_surat" id="upload_keluar_nomor_surat" class="form-control" placeholder="Contoh: W13-U1/ /KP.05.2/XI/2026">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Judul Dokumen (di e-Doc) <span class="text-danger">*</span></label>
                        <input type="text" name="nama_dokumen" id="upload_keluar_nama_dokumen" class="form-control" required placeholder="Contoh: Surat Izin Keluar Kantor - Nama Pegawai">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Tanggal Dokumen <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_dokumen" id="upload_keluar_tgl_dokumen" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Pilih Berkas Dokumen <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="file_edoc" id="upload_keluar_file_input" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            <label class="custom-file-label" id="upload_keluar_file_label" for="upload_keluar_file_input">Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...</label>
                        </div>
                        <small class="text-muted mt-1 d-block">Format diperbolehkan: PDF, JPG, PNG, DOC, DOCX. Maksimal 10 MB.</small>
                    </div>

                    <div class="form-group mb-1">
                        <label class="font-weight-bold small text-dark">Keterangan / Catatan Arsip (Opsional)</label>
                        <textarea name="keterangan" id="upload_keluar_keterangan" class="form-control" rows="2" placeholder="Catatan tambahan dokumen e-Doc..."></textarea>
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
                if(hiddenEl.value == "") {
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
            allowInput: true
        });

        flatpickr(".flatpickr-time", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            minTime: "08:00",
            maxTime: "17:00",
            minuteIncrement: 1
        });
    });

    $(document).ready(function() {
        $('#keyword_izin').on('keyup', function() {
            var keyword = $(this).val();
            $('#area_tabel_izin').load(
                'index.php?page=izin_keluar&cari_izin=' + encodeURIComponent(keyword) + ' #area_tabel_izin'
            );
        });

        // Handler Modal Edit Izin Keluar
        $(document).on('click', '.btn-modal-edit-keluar', function() {
            var btn = $(this);
            $('#edit_keluar_id_izin').val(btn.data('id'));
            $('#edit_keluar_nama_text').text(btn.data('nama'));
            $('#edit_keluar_id_atasan').val(btn.data('idatasan'));
            $('#edit_keluar_tgl_izin').val(btn.data('tgl'));
            $('#edit_keluar_jam_keluar').val(btn.data('jamkeluar'));
            $('#edit_keluar_jam_kembali').val(btn.data('jamkembali'));
            $('#edit_keluar_keperluan').val(btn.data('keperluan'));
            $('#edit_keluar_status').val(btn.data('status'));

            $('#modalEditIzinKeluar').modal('show');
        });

        // Handler Modal Upload Dokumen e-Doc
        $(document).on('click', '.btn-modal-upload-keluar', function() {
            var btn = $(this);
            var nama = btn.data('nama');
            var tgl = btn.data('tgl');

            $('#upload_keluar_id_izin').val(btn.data('id'));
            $('#upload_keluar_id_user').val(btn.data('iduser'));
            $('#upload_keluar_nama_text').text(nama);
            
            var defaultJudul = 'Surat Izin Keluar Kantor - ' + nama;
            if (tgl) {
                defaultJudul += ' (' + tgl + ')';
            }
            $('#upload_keluar_nama_dokumen').val(defaultJudul);
            $('#upload_keluar_nomor_surat').val('');
            $('#upload_keluar_tgl_dokumen').val(new Date().toISOString().split('T')[0]);
            $('#upload_keluar_keterangan').val('');
            $('#upload_keluar_file_input').val('');
            $('#upload_keluar_file_label').text('Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');

            $('#modalUploadEdocKeluar').modal('show');
        });

        // Update custom file label on change
        $(document).on('change', '#upload_keluar_file_input', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#upload_keluar_file_label').text(fileName ? fileName : 'Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');
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
