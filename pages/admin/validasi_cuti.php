<?php
/** @var mysqli $koneksi */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/edoc_helper.php';

// 1. PROSES FORM: EDIT DATA PENGAJUAN CUTI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_edit_cuti'])) {
    $id_pengajuan = (int)$_POST['id_pengajuan'];
    $nomor_surat  = mysqli_real_escape_string($koneksi, trim($_POST['nomor_surat'] ?? ''));
    $id_jenis     = (int)$_POST['id_jenis'];
    $tgl_mulai    = mysqli_real_escape_string($koneksi, $_POST['tgl_mulai']);
    $tgl_selesai  = mysqli_real_escape_string($koneksi, $_POST['tgl_selesai']);
    $lama_hari    = (int)$_POST['lama_hari'];
    $alasan       = mysqli_real_escape_string($koneksi, trim($_POST['alasan']));
    $alamat_cuti  = mysqli_real_escape_string($koneksi, trim($_POST['alamat_cuti']));
    $no_telepon   = mysqli_real_escape_string($koneksi, trim($_POST['no_telepon']));
    $status       = mysqli_real_escape_string($koneksi, $_POST['status']);
    $catatan      = mysqli_real_escape_string($koneksi, trim($_POST['catatan_admin'] ?? ''));

    $q_j = mysqli_query($koneksi, "SELECT nama_jenis FROM jenis_cuti WHERE id_jenis=$id_jenis");
    $d_j = mysqli_fetch_assoc($q_j);
    $nama_jenis = mysqli_real_escape_string($koneksi, $d_j['nama_jenis'] ?? '');

    $sql_upd = "UPDATE pengajuan_cuti SET 
                    nomor_surat = '$nomor_surat',
                    id_jenis = $id_jenis,
                    jenis_cuti = '$nama_jenis',
                    tgl_mulai = '$tgl_mulai',
                    tgl_selesai = '$tgl_selesai',
                    lama_hari = $lama_hari,
                    alasan = '$alasan',
                    alamat_cuti = '$alamat_cuti',
                    no_telepon = '$no_telepon',
                    status = '$status',
                    catatan_admin = '$catatan'
                WHERE id_pengajuan = $id_pengajuan";

    if (mysqli_query($koneksi, $sql_upd)) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Data Diperbarui!',
            'text' => 'Perubahan data pengajuan cuti berhasil disimpan.'
        ];
    } else {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Gagal Memperbarui!',
            'text' => 'Terjadi kesalahan: ' . mysqli_error($koneksi)
        ];
    }
    echo "<script>window.location.href='index.php?page=validasi_cuti';</script>";
    exit();
}

// 2. PROSES FORM: UPLOAD DOKUMEN E-DOC CUTI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_upload_edoc_cuti'])) {
    $id_pengajuan = (int)$_POST['id_pengajuan'];
    $id_user      = (int)$_POST['id_user'];
    $nomor_surat  = trim($_POST['nomor_surat'] ?? '');
    $nama_dokumen = trim($_POST['nama_dokumen'] ?? '');
    $tgl_dokumen  = $_POST['tgl_dokumen'] ?? date('Y-m-d');
    $keterangan   = trim($_POST['keterangan'] ?? '');
    $file         = $_FILES['file_edoc'] ?? null;

    $res = upload_file_ke_edoc($koneksi, 'cuti', $id_user, $nomor_surat, $nama_dokumen, $tgl_dokumen, $file, $keterangan, $id_pengajuan, $_SESSION['id_user'] ?? 1);

    if ($res['status']) {
        if (!empty($nomor_surat)) {
            $nomor_surat_esc = mysqli_real_escape_string($koneksi, $nomor_surat);
            mysqli_query($koneksi, "UPDATE pengajuan_cuti SET nomor_surat = '$nomor_surat_esc' WHERE id_pengajuan = $id_pengajuan AND (nomor_surat IS NULL OR nomor_surat = '')");
        }
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Upload Berhasil!',
            'text' => 'Dokumen cuti berhasil diunggah dan otomatis terarsip ke dalam menu e-Doc Keluar Cuti.'
        ];
    } else {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Gagal Upload!',
            'text' => $res['pesan']
        ];
    }
    echo "<script>window.location.href='index.php?page=validasi_cuti';</script>";
    exit();
}

// Ambil daftar seluruh jenis cuti untuk dropdown modal edit
$q_all_jenis = mysqli_query($koneksi, "SELECT * FROM jenis_cuti ORDER BY id_jenis ASC");
$list_jenis = [];
while ($lj = mysqli_fetch_assoc($q_all_jenis)) {
    $list_jenis[] = $lj;
}

$batas   = 10; 
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$keyword = "";
$where_clause = ""; 

if (isset($_GET['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_GET['cari']);
    $where_clause = " AND (users.nama_lengkap LIKE '%$keyword%' OR pengajuan_cuti.tgl_pengajuan LIKE '%$keyword%' OR pengajuan_cuti.status LIKE '%$keyword%')";
}


$query_count_str = "SELECT count(id_pengajuan) as jumlah 
                    FROM pengajuan_cuti 
                    JOIN users ON pengajuan_cuti.id_user = users.id_user 
                    WHERE 1=1 $where_clause";
$query_count = mysqli_query($koneksi, $query_count_str);
$data_count  = mysqli_fetch_assoc($query_count);
$jumlah_data = $data_count['jumlah'];
$total_halaman = ceil($jumlah_data / $batas);


$query_utama = "SELECT pengajuan_cuti.*, 
                        users.nama_lengkap, users.nip, 
                        users.sisa_cuti_n, users.sisa_cuti_n1, users.kuota_cuti_sakit, 
                        jenis_cuti.nama_jenis 
                FROM pengajuan_cuti 
                JOIN users ON pengajuan_cuti.id_user = users.id_user 
                JOIN jenis_cuti ON pengajuan_cuti.id_jenis = jenis_cuti.id_jenis 
                WHERE 1=1 $where_clause
                ORDER BY pengajuan_cuti.tgl_pengajuan DESC, pengajuan_cuti.id_pengajuan DESC 
                LIMIT $halaman_awal, $batas";

$query = mysqli_query($koneksi, $query_utama);
$nomor = $halaman_awal + 1;
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { 
        --pn-green: #004d00; 
        --pn-gold: #F9A825; 
        --text-dark: #333;
    }
    
    body { font-family: 'Poppins', sans-serif; }

    .card-pn { 
        border: none; 
        border-radius: 12px; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        background-color: #fff;
        overflow: hidden; 
    }
    .card-header-pn { 
        background: linear-gradient(135deg, #004d00 0%, #003300 100%); 
        color: white; 
        border-bottom: 4px solid var(--pn-gold); 
        padding: 18px 25px; 
    }

    .page-title-pn { 
        font-weight: 700; 
        border-left: 5px solid var(--pn-gold); 
        padding-left: 15px; 
        color: var(--pn-green) !important; 
        font-size: 1.5rem;
    }

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
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 77, 0, 0.03) !important;
    }
    
    .badge-status { 
        border-radius: 50px;
        padding: 6px 12px; 
        font-weight: 600; 
        font-size: 12px; 
        letter-spacing: 0.5px; 
    }

    /* --- STYLE BARU UNTUK JENIS CUTI --- */
    .badge-jenis-cuti {
        background-color: #e9f5e9;
        color: var(--pn-green);
        border: 1px solid #c3e6cb;
        padding: 5px 12px;
        border-radius: 50px; 
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        display: inline-block;
        white-space: nowrap; 
    }
    
    .btn-circle-action { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; cursor: pointer; }
    .btn-print { background-color: #e3f2fd; color: #0d47a1; }
    .btn-print:hover { background-color: #bbdefb; transform: scale(1.1); }
    .btn-edit { background-color: #fff8e1; color: #f57f17; }
    .btn-edit:hover { background-color: #ffecb3; transform: scale(1.1); }
    .btn-upload { background-color: #e0f2f1; color: #00695c; }
    .btn-upload:hover { background-color: #b2dfdb; transform: scale(1.1); }
    .btn-edoc-done { background-color: #d1fae5; color: #047857; box-shadow: 0 0 0 2px #10b981 !important; }
    .btn-approve { background-color: #e8f5e9; color: #2e7d32; }
    .btn-approve:hover { background-color: #c8e6c9; transform: scale(1.1); }
    .btn-delete { background-color: #ffebee; color: #c62828; }
    .btn-delete:hover { background-color: #ffcdd2; transform: scale(1.1); }
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

    .search-wrapper { position: relative; width: 100%; max-width: 300px; }
    .search-input-inside {
        width: 100%;
        padding: 6px 35px 6px 15px !important;
        border-radius: 20px !important;
        border: none;
        background-color: #fff;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        outline: none;
    }
    .search-input-inside:focus {
        background-color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(0, 77, 0, 0.18);
        outline: none;
    }
    .search-icon-inside { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; pointer-events: none; }
</style>

<div class="container-fluid mb-5 mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="page-title-pn mb-0">Validasi Permohonan Cuti</h1>
    </div>

    <div class="card card-pn mb-4">
        <div class="card-header-pn d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0">
                <i class="fas fa-check-double mr-2"></i>
                <span class="font-weight-bold" style="font-size: 1.1rem;">Daftar Pengajuan Masuk</span>
            </div>
            
            <div class="search-wrapper">
                <input type="text" id="keyword" class="search-input-inside" 
                       placeholder="Cari Nama / Tanggal..." 
                       value="<?php echo $keyword; ?>" 
                       autocomplete="off">
                <i class="fas fa-search search-icon-inside"></i>
            </div>
        </div>

        <div class="card-body">
            <div id="area_tabel">
                <?php if(mysqli_num_rows($query) == 0) { ?>
                    <div class="alert alert-light text-center border shadow-sm" style="border-radius: 12px;">
                        <h6 class="text-muted m-3"><i class="fas fa-info-circle mr-2"></i>Data pengajuan tidak ditemukan.</h6>
                    </div>
                <?php } else { ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead class="table-pn-head">
                            <tr class="text-center">
                                <th width="5%">No</th>
                                <th>Pegawai</th>
                                <th>Jenis Cuti</th>
                                <th>Detail Pengajuan</th>
                                <th>Nomor Surat</th>
                                <th>Status</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            while($row = mysqli_fetch_array($query)){ 
                                $raw_status = strtolower(trim($row['status'])); 
                                $bg_row = "";
                                if ($raw_status == 'disetujui') {
                                    $badge = '<span class="badge badge-status" style="background-color: #d4edda; color: #155724;"><i class="fas fa-check mr-1"></i>DISETUJUI</span>';
                                } elseif ($raw_status == 'ditolak') {
                                    $badge = '<span class="badge badge-status" style="background-color: #f8d7da; color: #721c24;"><i class="fas fa-times mr-1"></i>DITOLAK</span>';
                                    $bg_row = "style='background-color: #fff5f5;'"; 
                                } else {
                                    $badge = '<span class="badge badge-status" style="background-color: #fff3cd; color: #856404;"><i class="fas fa-clock mr-1"></i>MENUNGGU</span>';
                                    $bg_row = "style='background-color: #fffdf0;'"; 
                                }
                            ?>
                            <tr <?php echo $bg_row; ?>>
                                <td class="text-center font-weight-bold align-middle"><?php echo $nomor++; ?></td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark" style="font-size: 15px;"><?php echo $row['nama_lengkap']; ?></div>
                                    <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>NIP: <?php echo $row['nip']; ?></small>
                                </td>
                                
                                <td class="align-middle text-center">
                                    <span class="badge-jenis-cuti">
                                        <?php echo $row['nama_jenis']; ?>
                                    </span>
                                </td>

                                <td class="align-middle">
                                    <div class="font-weight-bold text-primary"><?php echo $row['lama_hari']; ?> Hari Kerja</div>
                                    <small class="text-dark d-block mt-1">
                                        <i class="far fa-calendar-alt mr-1 text-muted"></i>
                                        <?php echo date('d/m/Y', strtotime($row['tgl_mulai'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($row['tgl_selesai'])); ?>
                                    </small>
                                    <small class="text-muted font-italic d-block mt-1" style="font-size: 11px;">
                                        Diajukan: <?php echo date('d M Y', strtotime($row['tgl_pengajuan'])); ?>
                                    </small>
                                </td>
                                
                                <td class="text-center align-middle">
                                    <?php 
                                    $edoc_items = get_edoc_terkait($koneksi, 'cuti', $row['id_pengajuan']);
                                    $has_edoc = !empty($edoc_items);
                                    if(!empty($row['nomor_surat'])) { ?>
                                        <span class="font-weight-bold text-dark small" style="display:inline-block; max-width:150px; line-height:1.2;">
                                            <?php echo htmlspecialchars($row['nomor_surat']); ?>
                                        </span>
                                    <?php } else { echo '<span class="text-muted small">-</span>'; } ?>
                                </td>

                                <td class="text-center align-middle"><?php echo $badge; ?></td>
                                
                                <td class="text-center align-middle">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <!-- 1. Cetak Surat -->
                                        <a href="pages/admin/cetak_cuti_admin.php?id=<?php echo $row['id_pengajuan']; ?>" target="_blank" class="btn-circle-action btn-print shadow-sm mr-1" title="Cetak Surat Permohonan">
                                            <i class="fas fa-print fa-sm"></i>
                                        </a>

                                        <!-- 2. Edit Data Pengajuan (Perbaiki Salah Ketik/Typo) -->
                                        <button type="button" class="btn-circle-action btn-edit shadow-sm mr-1 btn-modal-edit-cuti"
                                                data-id="<?php echo $row['id_pengajuan']; ?>"
                                                data-iduser="<?php echo $row['id_user']; ?>"
                                                data-nama="<?php echo htmlspecialchars($row['nama_lengkap']); ?>"
                                                data-nip="<?php echo htmlspecialchars($row['nip']); ?>"
                                                data-nomorsurat="<?php echo htmlspecialchars($row['nomor_surat'] ?? ''); ?>"
                                                data-idjenis="<?php echo $row['id_jenis']; ?>"
                                                data-tglmulai="<?php echo $row['tgl_mulai']; ?>"
                                                data-tglselesai="<?php echo $row['tgl_selesai']; ?>"
                                                data-lamahari="<?php echo $row['lama_hari']; ?>"
                                                data-alasan="<?php echo htmlspecialchars($row['alasan'] ?? ''); ?>"
                                                data-alamat="<?php echo htmlspecialchars($row['alamat_cuti'] ?? ''); ?>"
                                                data-telepon="<?php echo htmlspecialchars($row['no_telepon'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                                data-catatan="<?php echo htmlspecialchars($row['catatan_admin'] ?? ''); ?>"
                                                title="Edit Data Pengajuan">
                                            <i class="fas fa-pencil-alt fa-sm"></i>
                                        </button>

                                        <!-- 3. Upload Dokumen (Otomatis Masuk e-Doc) -->
                                        <button type="button" class="btn-circle-action btn-upload shadow-sm mr-1 btn-modal-upload-cuti <?php echo $has_edoc ? 'btn-edoc-done' : ''; ?>"
                                                data-id="<?php echo $row['id_pengajuan']; ?>"
                                                data-iduser="<?php echo $row['id_user']; ?>"
                                                data-nama="<?php echo htmlspecialchars($row['nama_lengkap']); ?>"
                                                data-nip="<?php echo htmlspecialchars($row['nip']); ?>"
                                                data-nomorsurat="<?php echo htmlspecialchars($row['nomor_surat'] ?? ''); ?>"
                                                data-jenis="<?php echo htmlspecialchars($row['nama_jenis']); ?>"
                                                data-tgl="<?php echo $row['tgl_pengajuan']; ?>"
                                                title="<?php echo $has_edoc ? 'Dokumen sudah diupload ke e-Doc (Klik untuk tambah dokumen baru)' : 'Upload Dokumen (Otomatis Masuk ke e-Doc)'; ?>">
                                            <i class="fas fa-cloud-upload-alt fa-sm"></i>
                                        </button>

                                        <!-- 4. Tombol Setujui & Tolak (Jika status masih menunggu) -->
                                        <?php if(strpos($raw_status, 'menunggu') !== false || $raw_status == 'diajukan' || $raw_status == '') { ?>
                                            <button class="btn-circle-action btn-approve shadow-sm mr-1"
                                                    onclick="konfirmasiValidasi('setuju', <?php echo $row['id_pengajuan']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap'], ENT_QUOTES); ?>')" 
                                                    title="Setujui">
                                                <i class="fas fa-check fa-sm"></i>
                                            </button>
                                            
                                            <button class="btn-circle-action btn-delete shadow-sm"
                                                    onclick="konfirmasiValidasi('tolak', <?php echo $row['id_pengajuan']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap'], ENT_QUOTES); ?>')" 
                                                    title="Tolak">
                                                <i class="fas fa-times fa-sm"></i>
                                            </button>
                                        <?php } ?>
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
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3">
                    <div class="text-muted small mb-2 mb-md-0">
                        Halaman <?php echo $halaman; ?> dari <?php echo $total_halaman; ?>. total: <?php echo $jumlah_data; ?> pengajuan
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php if($halaman <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php if($halaman > 1){ echo "?page=validasi_cuti&hal=".($halaman-1)."&cari=$keyword"; } ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for($x = 1; $x <= $total_halaman; $x++): ?>
                                <li class="page-item <?php if($halaman == $x) echo 'active'; ?>">
                                    <a class="page-link" href="?page=validasi_cuti&hal=<?php echo $x; ?>&cari=<?php echo $keyword; ?>"><?php echo $x; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php if($halaman >= $total_halaman) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php if($halaman < $total_halaman){ echo "?page=validasi_cuti&hal=".($halaman+1)."&cari=$keyword"; } ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php } ?>
            </div> 
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 1: EDIT DATA PENGAJUAN CUTI (PERBAIKI SALAH KETIK)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditCuti" tabindex="-1" role="dialog" aria-labelledby="modalEditCutiLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #F9A825;">
                <h5 class="modal-title font-weight-bold" id="modalEditCutiLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-pencil-alt mr-2 text-warning"></i> Edit Data Pengajuan Cuti
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=validasi_cuti" method="POST" autocomplete="off">
                <input type="hidden" name="aksi_edit_cuti" value="1">
                <input type="hidden" name="id_pengajuan" id="edit_cuti_id_pengajuan">

                <div class="modal-body p-4" style="background: #fdfdfd; max-height: calc(85vh - 120px); overflow-y: auto;">
                    
                    <!-- Info Pemohon -->
                    <div class="p-3 mb-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x text-success mr-3"></i>
                            <div>
                                <div class="font-weight-bold text-dark" id="edit_cuti_nama_text" style="font-size: 1rem;">-</div>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>NIP: <span id="edit_cuti_nip_text">-</span></small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Nomor Surat Cuti</label>
                            <input type="text" name="nomor_surat" id="edit_cuti_nomor_surat" class="form-control" placeholder="Contoh: W13-U1/ /KP.05.2/XI/2026">
                            <small class="text-muted">Dapat diisi atau diperbaiki sesuai penomoran resmi.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-dark">Jenis Cuti <span class="text-danger">*</span></label>
                            <select name="id_jenis" id="edit_cuti_id_jenis" class="form-control" required>
                                <?php foreach ($list_jenis as $lj): ?>
                                    <option value="<?= $lj['id_jenis'] ?>"><?= htmlspecialchars($lj['nama_jenis']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-dark">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_mulai" id="edit_cuti_tgl_mulai" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-dark">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_selesai" id="edit_cuti_tgl_selesai" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-dark">Lama Hari Kerja <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="lama_hari" id="edit_cuti_lama_hari" class="form-control" min="1" required>
                                <div class="input-group-append"><span class="input-group-text small">Hari</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="font-weight-bold small text-dark">Alasan Cuti <span class="text-danger">*</span></label>
                        <textarea name="alasan" id="edit_cuti_alasan" class="form-control" rows="2" required placeholder="Alasan cuti..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Alamat Selama Menjalankan Cuti <span class="text-danger">*</span></label>
                            <textarea name="alamat_cuti" id="edit_cuti_alamat" class="form-control" rows="2" required placeholder="Alamat..."></textarea>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">No. Telepon / WA <span class="text-danger">*</span></label>
                            <input type="text" name="no_telepon" id="edit_cuti_telepon" class="form-control" required placeholder="08xxxxxxxxxx">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold small text-dark">Status Pengajuan <span class="text-danger">*</span></label>
                            <select name="status" id="edit_cuti_status" class="form-control font-weight-bold">
                                <option value="Diajukan">Diajukan (Menunggu)</option>
                                <option value="Disetujui">Disetujui</option>
                                <option value="Ditolak">Ditolak</option>
                                <option value="Dibatalkan">Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold small text-dark">Catatan Admin / Verifikator</label>
                            <input type="text" name="catatan_admin" id="edit_cuti_catatan" class="form-control" placeholder="Catatan tambahan (opsional)...">
                        </div>
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
     MODAL 2: UPLOAD DOKUMEN CUTI (OTOMATIS TERSIMPAN KE E-DOC)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalUploadEdocCuti" tabindex="-1" role="dialog" aria-labelledby="modalUploadEdocCutiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header py-3" style="background: linear-gradient(135deg, #004d00 0%, #003300 100%); color: white; border-bottom: 3px solid #10b981;">
                <h5 class="modal-title font-weight-bold" id="modalUploadEdocCutiLabel" style="font-size: 1.05rem;">
                    <i class="fas fa-cloud-upload-alt mr-2 text-warning"></i> Upload Dokumen Cuti (e-Doc)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form action="index.php?page=validasi_cuti" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="aksi_upload_edoc_cuti" value="1">
                <input type="hidden" name="id_pengajuan" id="upload_cuti_id_pengajuan">
                <input type="hidden" name="id_user" id="upload_cuti_id_user">

                <div class="modal-body p-4" style="background: #fdfdfd;">
                    
                    <div class="alert alert-info border-0 shadow-sm p-3 mb-3 small" style="border-radius: 10px; background:#e0f2fe; color:#0369a1;">
                        <i class="fas fa-magic mr-1 font-weight-bold"></i> Berkas yang diunggah di sini <strong>otomatis tersimpan & terarsip ke dalam menu e-Doc Keluar Cuti</strong>, tanpa perlu input ulang atau upload manual.
                    </div>

                    <!-- Info Pegawai -->
                    <div class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="font-weight-bold text-dark" id="upload_cuti_nama_text">-</div>
                        <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>NIP: <span id="upload_cuti_nip_text">-</span> &bull; <span id="upload_cuti_jenis_text" class="text-success font-weight-bold">-</span></small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Nomor Surat Cuti</label>
                        <input type="text" name="nomor_surat" id="upload_cuti_nomor_surat" class="form-control" placeholder="Contoh: W13-U1/245/KP.05.2/XI/2026">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Judul Dokumen (di e-Doc) <span class="text-danger">*</span></label>
                        <input type="text" name="nama_dokumen" id="upload_cuti_nama_dokumen" class="form-control" required placeholder="Contoh: Surat Cuti Tahunan - Nama Pegawai">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Tanggal Dokumen <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_dokumen" id="upload_cuti_tgl_dokumen" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-dark">Pilih Berkas Dokumen <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="file_edoc" id="upload_cuti_file_input" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            <label class="custom-file-label" id="upload_cuti_file_label" for="upload_cuti_file_input">Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...</label>
                        </div>
                        <small class="text-muted mt-1 d-block">Format diperbolehkan: PDF, JPG, PNG, DOC, DOCX. Maksimal 10 MB.</small>
                    </div>

                    <div class="form-group mb-1">
                        <label class="font-weight-bold small text-dark">Keterangan / Catatan Arsip (Opsional)</label>
                        <textarea name="keterangan" id="upload_cuti_keterangan" class="form-control" rows="2" placeholder="Catatan tambahan dokumen e-Doc..."></textarea>
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

<script>
    $(document).ready(function() {
        $('#keyword').on('keyup', function() {
            var keyword = $(this).val();
            $('#area_tabel').load('index.php?page=validasi_cuti&cari=' + encodeURIComponent(keyword) + ' #area_tabel', function() {
            });
        });

        // Event handler: Klik Tombol Edit Cuti
        $(document).on('click', '.btn-modal-edit-cuti', function() {
            var btn = $(this);
            $('#edit_cuti_id_pengajuan').val(btn.data('id'));
            $('#edit_cuti_nama_text').text(btn.data('nama'));
            $('#edit_cuti_nip_text').text(btn.data('nip'));
            $('#edit_cuti_nomor_surat').val(btn.data('nomorsurat'));
            $('#edit_cuti_id_jenis').val(btn.data('idjenis'));
            $('#edit_cuti_tgl_mulai').val(btn.data('tglmulai'));
            $('#edit_cuti_tgl_selesai').val(btn.data('tglselesai'));
            $('#edit_cuti_lama_hari').val(btn.data('lamahari'));
            $('#edit_cuti_alasan').val(btn.data('alasan'));
            $('#edit_cuti_alamat').val(btn.data('alamat'));
            $('#edit_cuti_telepon').val(btn.data('telepon'));
            $('#edit_cuti_status').val(btn.data('status'));
            $('#edit_cuti_catatan').val(btn.data('catatan'));

            $('#modalEditCuti').modal('show');
        });

        // Event handler: Klik Tombol Upload Dokumen e-Doc
        $(document).on('click', '.btn-modal-upload-cuti', function() {
            var btn = $(this);
            var nama = btn.data('nama');
            var jenis = btn.data('jenis');
            var nomor = btn.data('nomorsurat');

            $('#upload_cuti_id_pengajuan').val(btn.data('id'));
            $('#upload_cuti_id_user').val(btn.data('iduser'));
            $('#upload_cuti_nama_text').text(nama);
            $('#upload_cuti_nip_text').text(btn.data('nip'));
            $('#upload_cuti_jenis_text').text(jenis);
            $('#upload_cuti_nomor_surat').val(nomor);
            
            // Buat judul dokumen default yang jelas dan deskriptif
            var defaultJudul = 'Surat Cuti ' + jenis + ' - ' + nama;
            if (nomor) {
                defaultJudul += ' (' + nomor + ')';
            }
            $('#upload_cuti_nama_dokumen').val(defaultJudul);
            $('#upload_cuti_tgl_dokumen').val(new Date().toISOString().split('T')[0]);
            $('#upload_cuti_keterangan').val('');
            $('#upload_cuti_file_input').val('');
            $('#upload_cuti_file_label').text('Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');

            $('#modalUploadEdocCuti').modal('show');
        });

        // Update custom file label on change
        $(document).on('change', '#upload_cuti_file_input', function() {
            var fileName = $(this).val().split('\\').pop();
            $('#upload_cuti_file_label').text(fileName ? fileName : 'Pilih file (PDF, JPG, PNG, DOC, maks 10MB)...');
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

    function konfirmasiValidasi(aksi, id, nama) {
        let judul, teks, warnaTombol, textTombol;

        if(aksi === 'setuju') {
            judul = 'Setujui Pengajuan?';
            teks = 'Pengajuan cuti pegawai ' + nama + ' akan disetujui.';
            warnaTombol = '#28a745'; 
            textTombol = 'Ya, Setujui!';
        } else {
            judul = 'Tolak Pengajuan?';
            teks = 'Pengajuan cuti ' + nama + ' akan DITOLAK dan kuota dikembalikan.';
            warnaTombol = '#d33';
            textTombol = 'Ya, Tolak!';
        }

        Swal.fire({
            title: judul,
            text: teks,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: warnaTombol,
            cancelButtonColor: '#6c757d',
            confirmButtonText: textTombol,
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'pages/admin/proses_validasi.php?aksi=' + aksi + '&id=' + id;
            }
        });
    }
</script>