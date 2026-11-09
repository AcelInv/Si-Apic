<?php
/** @var mysqli $koneksi */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$swal_script = "";

if(isset($_POST['simpan_pejabat'])){
    $ketua_nama = mysqli_real_escape_string($koneksi, $_POST['ketua_nama']);
    $ketua_nip  = mysqli_real_escape_string($koneksi, $_POST['ketua_nip']);
    $wakil_nama = mysqli_real_escape_string($koneksi, $_POST['wakil_nama']);
    $wakil_nip  = mysqli_real_escape_string($koneksi, $_POST['wakil_nip']);
    
    $cek_set = mysqli_query($koneksi, "SELECT * FROM tbl_setting_instansi WHERE id_setting='1'");
    if(mysqli_num_rows($cek_set) == 0){
        mysqli_query($koneksi, "INSERT INTO tbl_setting_instansi (id_setting, nama_instansi) VALUES (1, 'Pengadilan Negeri')");
    }

    $update = mysqli_query($koneksi, "UPDATE tbl_setting_instansi SET 
        ketua_nama='$ketua_nama', ketua_nip='$ketua_nip',
        wakil_nama='$wakil_nama', wakil_nip='$wakil_nip'
        WHERE id_setting='1'");
    
    if($update){
        $swal_script = "Swal.fire({ title: 'Berhasil', text: 'Pejabat berhasil disimpan!', icon: 'success' }).then(() => { window.location='index.php?page=data_pegawai'; });";
    }
}

$query_set   = mysqli_query($koneksi, "SELECT * FROM tbl_setting_instansi WHERE id_setting='1'");
$set_instansi = mysqli_fetch_array($query_set);
if(!$set_instansi) { $set_instansi = ['ketua_nama' => '', 'ketua_nip' => '', 'wakil_nama' => '', 'wakil_nip' => '']; }

if (isset($_POST['tambah'])) {
    $nip          = htmlspecialchars($_POST['nip']);
    $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
    $password     = md5($_POST['password']); 
    $jabatan      = htmlspecialchars($_POST['jabatan']); 
    $pangkat      = htmlspecialchars($_POST['pangkat']); 
    $role         = isset($_POST['role']) ? $_POST['role'] : 'user';
    $is_atasan    = isset($_POST['is_atasan']) ? '1' : '0';
    $status_akun  = 'aktif'; 
    $ct_n         = $_POST['sisa_cuti_n'];
    $ct_n1        = $_POST['sisa_cuti_n1'];
    $ct_sakit     = $_POST['kuota_cuti_sakit'];

    $cek_nip = mysqli_query($koneksi, "SELECT nip FROM users WHERE nip='$nip'");
    if (mysqli_num_rows($cek_nip) > 0) {
        $swal_script = "Swal.fire({ title: 'Gagal!', text: 'NIP sudah terdaftar!', icon: 'error' });";
    } else {
        $query = mysqli_query($koneksi, "INSERT INTO users (nip, nama_lengkap, password, jabatan, pangkat, role, status_akun, sisa_cuti_n, sisa_cuti_n1, kuota_cuti_sakit, is_atasan) VALUES ('$nip', '$nama_lengkap', '$password', '$jabatan', '$pangkat', '$role', '$status_akun', '$ct_n', '$ct_n1', '$ct_sakit', '$is_atasan')");

        if ($query) {
            $swal_script = "Swal.fire({ title: 'Berhasil!', text: 'Pegawai baru ditambahkan.', icon: 'success' }).then(() => { window.location = 'index.php?page=data_pegawai'; });";
        } else {
            $swal_script = "Swal.fire({ title: 'Error!', text: '" . mysqli_error($koneksi) . "', icon: 'error' });";
        }
    }
}

if (isset($_POST['edit'])) {
    $id_user      = $_POST['id_user'];
    $nip          = htmlspecialchars($_POST['nip']);
    $nama_lengkap = htmlspecialchars($_POST['nama_lengkap']);
    $jabatan      = htmlspecialchars($_POST['jabatan']);
    $pangkat      = htmlspecialchars($_POST['pangkat']); 
    $role         = isset($_POST['role']) ? $_POST['role'] : 'user';
    $status_akun  = $_POST['status_akun']; 
    $is_atasan    = isset($_POST['is_atasan']) ? '1' : '0';
    $ct_n         = $_POST['sisa_cuti_n'];
    $ct_n1        = $_POST['sisa_cuti_n1'];
    $ct_sakit     = $_POST['kuota_cuti_sakit'];
    $halaman_aktif = isset($_POST['halaman_aktif']) ? $_POST['halaman_aktif'] : 1;

   if (!empty($_POST['password'])) {
        $password = md5($_POST['password']);
        $query_update = "UPDATE users SET nip='$nip', nama_lengkap='$nama_lengkap', password='$password', jabatan='$jabatan', pangkat='$pangkat', role='$role', status_akun='$status_akun', sisa_cuti_n='$ct_n', sisa_cuti_n1='$ct_n1', kuota_cuti_sakit='$ct_sakit', is_atasan='$is_atasan' WHERE id_user='$id_user'";
    } else {
        $query_update = "UPDATE users SET nip='$nip', nama_lengkap='$nama_lengkap', jabatan='$jabatan', pangkat='$pangkat', role='$role', status_akun='$status_akun', sisa_cuti_n='$ct_n', sisa_cuti_n1='$ct_n1', kuota_cuti_sakit='$ct_sakit', is_atasan='$is_atasan' WHERE id_user='$id_user'";
    }
    
    $run_update = mysqli_query($koneksi, $query_update);

    if ($run_update) {
        // 2. Modifikasi bagian window.location
        // Pastikan parameter '&halaman=' sesuai dengan nama variabel pagination di kodemu (misal: &page_no=, &p=, atau &halaman=)
        $swal_script = "Swal.fire({ 
            title: 'Berhasil!', 
            text: 'Data Pegawai diperbarui.', 
            icon: 'success' 
        }).then(() => { 
            window.location = 'index.php?page=data_pegawai&halaman=" . $halaman_aktif . "'; 
        });";
    } else {
        $swal_script = "Swal.fire({ title: 'Gagal!', text: '" . mysqli_error($koneksi) . "', icon: 'error' });";
    }
}

$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;

if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $cek = mysqli_query($koneksi, "SELECT status_akun FROM users WHERE id_user='$id'");
    $row = mysqli_fetch_array($cek);
    $new_status = ($row['status_akun'] == 'aktif') ? 'tidak_aktif' : 'aktif';
    mysqli_query($koneksi, "UPDATE users SET status_akun='$new_status' WHERE id_user='$id'");
    echo "<script>window.location='index.php?page=data_pegawai&halaman=" . $halaman . "';</script>";
}

if (isset($_GET['hapus_pegawai'])) {
    $id_hapus = (int)$_GET['hapus_pegawai'];
    
    // Cegah admin menghapus akunnya sendiri yang sedang login
    if (isset($_SESSION['id_user']) && $id_hapus == $_SESSION['id_user']) {
        $swal_script = "Swal.fire({ title: 'Gagal!', text: 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.', icon: 'error' }).then(() => { window.location = 'index.php?page=data_pegawai&halaman=" . $halaman . "'; });";
    } else {
        $cek_u = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE id_user='$id_hapus'");
        if (mysqli_num_rows($cek_u) > 0) {
            $u_data = mysqli_fetch_assoc($cek_u);
            $nama_p = addslashes($u_data['nama_lengkap']);
            
            // Hapus data terkait terlebih dahulu untuk menjaga integritas database
            mysqli_query($koneksi, "DELETE FROM pengajuan_cuti WHERE id_user='$id_hapus'");
            mysqli_query($koneksi, "DELETE FROM izin_keluar WHERE id_user='$id_hapus'");
            mysqli_query($koneksi, "DELETE FROM izin_pulang WHERE id_user='$id_hapus'");
            mysqli_query($koneksi, "DELETE FROM tbl_edoc WHERE id_user='$id_hapus'");
            
            // Bersihkan referensi atasan pada data pegawai lain jika ada
            mysqli_query($koneksi, "UPDATE pengajuan_cuti SET id_atasan=NULL WHERE id_atasan='$id_hapus'");
            mysqli_query($koneksi, "UPDATE izin_keluar SET id_atasan=NULL WHERE id_atasan='$id_hapus'");
            mysqli_query($koneksi, "UPDATE izin_pulang SET id_atasan=0 WHERE id_atasan='$id_hapus'");
            
            $del = mysqli_query($koneksi, "DELETE FROM users WHERE id_user='$id_hapus'");
            if ($del) {
                $swal_script = "Swal.fire({ title: 'Berhasil!', text: 'Data pegawai \"$nama_p\" berhasil dihapus.', icon: 'success' }).then(() => { window.location = 'index.php?page=data_pegawai&halaman=" . $halaman . "'; });";
            } else {
                $swal_script = "Swal.fire({ title: 'Gagal!', text: 'Gagal menghapus data pegawai: " . addslashes(mysqli_error($koneksi)) . "', icon: 'error' });";
            }
        } else {
            $swal_script = "Swal.fire({ title: 'Gagal!', text: 'Pegawai tidak ditemukan.', icon: 'error' });";
        }
    }
}

$batas = 10;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$keyword = "";
$where_clause = "";
$url_pencarian = "";
if (isset($_GET['cari'])) {
    $keyword = mysqli_real_escape_string($koneksi, $_GET['cari']);
    $where_clause = "WHERE u.nama_lengkap LIKE '%$keyword%' OR u.nip LIKE '%$keyword%'";
    $url_pencarian = "&cari=" . $keyword;
}
$query_jumlah = mysqli_query($koneksi, "SELECT count(*) AS total FROM users u $where_clause");
$data_jumlah = mysqli_fetch_assoc($query_jumlah);
$total_data = $data_jumlah['total'];
$total_halaman = ceil($total_data / $batas);

$nomor = $halaman_awal + 1;
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { --pn-green: #004d00; --pn-gold: #F9A825; --text-dark: #2c3e50; }
    body { font-family: 'Poppins', sans-serif !important; background-color: #f4f6f9; }
    
    .page-header-title { border-left: 5px solid var(--pn-gold); padding-left: 15px; color: var(--pn-green); font-weight: 700; font-size: 1.6rem; }

    .card-pn-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
    .card-header-green { background-color: #1b5e20; color: white; padding: 15px 25px; border-bottom: 4px solid var(--pn-gold); display: flex; justify-content: space-between; align-items: center; }
    
    .header-search-box { position: relative; width: 300px; }
    .header-search-input { width: 100%; border-radius: 20px; border: none; padding: 6px 15px 6px 35px; font-size: 0.9rem; outline: none; }
    .header-search-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; font-size: 0.9rem; }

    .btn-pn-outline { background-color: transparent; color: var(--pn-green); border: 2px solid var(--pn-green); font-weight: 600; border-radius: 8px; padding: 8px 15px; transition: all 0.3s ease; }
    .btn-pn-outline:hover { background-color: var(--pn-green); color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 77, 0, 0.2); }

    .btn-pn-solid { background-color: var(--pn-green); color: white; border: 2px solid var(--pn-green); font-weight: 600; border-radius: 8px; padding: 8px 15px; transition: all 0.3s ease; }
    .btn-pn-solid:hover { background-color: #003800; color: var(--pn-gold); border-color: #003800; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 77, 0, 0.3); }

    .table-custom { width: 100%; border-collapse: separate; border-spacing: 0 5px; }
    
    .thead-pn { background-color: var(--pn-green); color: white; }
    .thead-pn th {
        padding: 12px 15px;
        border-bottom: 3px solid var(--pn-gold) !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        vertical-align: middle !important;
        border-top: none;
        white-space: nowrap;
    }

    .table-custom tbody tr { background-color: white; transition: 0.2s; }
    .table-custom tbody tr:hover { background-color: #f1f8e9; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

    .table-custom td { 
        padding: 12px 15px; 
        vertical-align: middle !important;
        border-bottom: 1px solid #eee; 
        font-size: 0.95rem; 
        color: #333; 
    }

    .col-fixed-no {
        width: 60px;
        min-width: 60px;
        max-width: 60px;
        text-align: center;
        font-weight: 500;
    }

    .text-pn { color: var(--pn-green) !important; }
    
    .badge-status-active { 
        background-color: #d4edda; 
        color: #155724; 
        padding: 3px 10px; 
        border-radius: 50px; 
        font-size: 0.7rem; 
        font-weight: 600; 
        border: 1px solid #c3e6cb; 
        display: inline-block;
    }
    
    .badge-status-inactive { 
        background-color: #f8d7da; 
        color: #721c24; 
        padding: 3px 10px;
        border-radius: 50px; 
        font-size: 0.7rem; 
        font-weight: 600; 
        border: 1px solid #f5c6cb; 
        display: inline-block;
    }
    
    .badge-atasan { 
        background-color: #e3f2fd; 
        color: #0d47a1; 
        border: 1px solid #bbdefb; 
        padding: 3px 10px; 
        border-radius: 50px; 
        font-size: 0.7rem; 
        display: inline-block; 
    }

    .btn-action-edit { background: #fff3cd; color: #856404; border:none; border-radius: 8px; padding: 6px 12px; transition: 0.2s; }
    .btn-action-off { background: #ffebee; color: #c62828; border:none; border-radius: 8px; padding: 6px 12px; transition: 0.2s; }
    .btn-action-on { background: #e8f5e9; color: #2e7d32; border:none; border-radius: 8px; padding: 6px 12px; transition: 0.2s; }
    
    .btn-action-edit:hover, .btn-action-off:hover, .btn-action-on:hover { opacity: 0.8; transform: scale(1.05); }

    .btn-circle-action { width: 35px; height: 35px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; cursor: pointer; }
    .btn-edit { background-color: #fff3cd; color: #856404; }
    .btn-edit:hover { background-color: #ffe699; transform: scale(1.1); }
    .btn-off { background-color: #ffebee; color: #c62828; }
    .btn-off:hover { background-color: #ffcdd2; transform: scale(1.1); }
    .btn-on { background-color: #e8f5e9; color: #2e7d32; }
    .btn-on:hover { background-color: #c8e6c9; transform: scale(1.1); }
    .btn-delete { background-color: #fee2e2; color: #dc2626; }
    .btn-delete:hover { background-color: #fca5a5; color: #991b1b; transform: scale(1.1); }

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

<div class="container-fluid mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h3 class="page-header-title">Data Pegawai & Cuti</h3>
        <div>
            <button class="btn btn-pn-outline shadow-sm mr-2" data-toggle="modal" data-target="#modalPejabat">
                <i class="fas fa-user-tie mr-2"></i> Atur Pejabat
            </button>
            <button class="btn btn-pn-solid shadow-sm" data-toggle="modal" data-target="#modalTambah">
                <i class="fas fa-plus mr-2"></i> Tambah Pegawai
            </button>
        </div>
    </div>

    <div class="card card-pn-custom">
        <div class="card-header-green">
            <div class="font-weight-bold" style="font-size: 1.1rem;">
                <i class="fas fa-list-ul mr-2"></i> Daftar Pegawai
            </div>
            <div class="header-search-box">
                <form action="index.php" method="GET" onsubmit="return false;"> 
                    <input type="text" id="keyword" class="header-search-input" placeholder="Cari Nama / NIP..." value="<?php echo $keyword; ?>" autocomplete="off">
                    <i class="fas fa-search header-search-icon"></i>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div id="area_tabel" class="p-3">
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead class="thead-pn">
                            <tr>
                                <th class="col-fixed-no">No</th>
                                
                                <th class="text-left" style="min-width: 250px;">Pegawai</th>
                                <th class="text-left" style="min-width: 150px;">Jabatan</th>
                                <th class="text-center" width="10%">Sisa Cuti<br><small>(Tahun Ini)</small></th>
                                <th class="text-center" width="10%">Sisa Cuti<br><small>(Tahun Lalu)</small></th>
                                <th class="text-center" width="10%">Sisa Cuti<br><small>(Sakit)</small></th>
                                <th class="text-center" style="min-width: 145px; width: 145px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query_string = "SELECT u.* FROM users u $where_clause ORDER BY u.id_user ASC LIMIT $halaman_awal, $batas";
                            $query_pegawai = mysqli_query($koneksi, $query_string);
                            $data_users_search = []; 

                            if (!$query_pegawai) {
                                echo "<tr><td colspan='7' class='text-center text-danger font-weight-bold py-4'>Error: " . mysqli_error($koneksi) . "</td></tr>";
                            } elseif (mysqli_num_rows($query_pegawai) == 0) {
                                echo "<tr><td colspan='7' class='text-center text-secondary py-5'><i class='fas fa-inbox fa-3x mb-3 text-gray-300'></i><br>Data tidak ditemukan.</td></tr>";
                            } else {
                                while ($data = mysqli_fetch_array($query_pegawai)) {
                                    $data_users_search[] = $data; 
                                    $is_active = ($data['status_akun'] == 'aktif');
                                    $pangkat_text = isset($data['pangkat']) && !empty($data['pangkat']) ? $data['pangkat'] : '-';

                                    $icon_admin = "";
                                    $role_check = strtolower(trim($data['role']));
                                    if ($role_check === '1' || strpos($role_check, 'admin') !== false) {
                                        $icon_admin = '<i class="fas fa-user-shield ml-2" style="color: var(--pn-gold); font-size: 0.9rem;" title="Administrator"></i>';
                                    }
                            ?>
                            <tr class="align-middle" <?php echo !$is_active ? 'style="background-color: #f8f9fa; opacity: 0.8;"' : ''; ?>>
                                
                                <td class="col-fixed-no"><?php echo $nomor++; ?></td>
                                
                                <td>
                                    <div class="d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center">
                                            <span class="font-weight-bold text-dark" style="font-size: 1rem;"><?php echo $data['nama_lengkap']; ?></span>
                                            <?php echo $icon_admin; ?>
                                        </div>
                                        
                                        <span class="small text-secondary mt-1">NIP: <?php echo $data['nip']; ?></span>
                                        
                                        <div class="mt-1">
                                            <?php if($is_active): ?>
                                                <span class="badge badge-status-active">AKTIF</span>
                                            <?php else: ?>
                                                <span class="badge badge-status-inactive">NONAKTIF</span>
                                            <?php endif; ?>
                                            
                                            <?php if($data['is_atasan'] == '1'): ?>
                                                <span class="badge badge-atasan ml-1"><i class="fas fa-user-check mr-1"></i>ATASAN</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex flex-column justify-content-center">
                                        <span class="font-weight-bold text-pn" style="font-size: 0.95rem;"><?php echo $data['jabatan']; ?></span>
                                        <small class="text-muted"><?php echo $pangkat_text; ?></small>
                                    </div>
                                </td>
                                
                                <td class="text-center">
                                    <span class="font-weight-bold text-dark" style="font-size:1rem;"><?php echo $data['sisa_cuti_n']; ?></span>
                                </td>
                                
                                <td class="text-center text-secondary font-weight-bold" style="font-size:1rem;">
                                    <?php echo $data['sisa_cuti_n1']; ?>
                                </td>
                                
                                <td class="text-center text-info font-weight-bold" style="font-size:1rem;">
                                    <?php echo $data['kuota_cuti_sakit']; ?>
                                </td>
                                
                                <td class="text-center">
                                    <div class="d-flex justify-content-center">
                                        <button type="button" class="btn-circle-action btn-edit shadow-sm mr-2" data-toggle="modal" data-target="#modalEdit<?php echo $data['id_user']; ?>" title="Edit Data">
                                            <i class="fas fa-pen fa-sm"></i>
                                        </button>
                                        <?php if($is_active): ?>
                                            <button onclick="konfirmasiStatus('<?php echo $data['id_user']; ?>', 'nonaktifkan', '<?php echo addslashes($data['nama_lengkap']); ?>')" class="btn-circle-action btn-off shadow-sm mr-2" title="Nonaktifkan Akun">
                                                <i class="fas fa-power-off fa-sm"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="konfirmasiStatus('<?php echo $data['id_user']; ?>', 'aktifkan', '<?php echo addslashes($data['nama_lengkap']); ?>')" class="btn-circle-action btn-on shadow-sm mr-2" title="Aktifkan Akun">
                                                <i class="fas fa-check fa-sm"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="konfirmasiHapus('<?php echo $data['id_user']; ?>', '<?php echo addslashes($data['nama_lengkap']); ?>')" class="btn-circle-action btn-delete shadow-sm" title="Hapus Pegawai">
                                            <i class="fas fa-trash-alt fa-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php } } ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3 align-items-center">
                    <div class="col-md-6">
                        <p class="text-secondary small mb-0">Halaman <strong><?php echo $halaman; ?></strong> dari <?php echo $total_halaman; ?>. Total: <?php echo $total_data; ?> Data.</p>
                    </div>
                    <div class="col-md-6 d-flex justify-content-end">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm m-0">
                                <?php if($halaman > 1): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=data_pegawai&halaman=<?php echo $halaman - 1; ?><?php echo $url_pencarian; ?>"><i class="fas fa-chevron-left"></i></a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                                <?php endif; ?>
                                <?php for($x = 1; $x <= $total_halaman; $x++): 
                                    $active_class = ($x == $halaman) ? 'active' : ''; ?>
                                    <li class="page-item <?php echo $active_class; ?>"><a class="page-link" href="index.php?page=data_pegawai&halaman=<?php echo $x; ?><?php echo $url_pencarian; ?>"><?php echo $x; ?></a></li>
                                <?php endfor; ?>
                                <?php if($halaman < $total_halaman): ?>
                                    <li class="page-item"><a class="page-link" href="index.php?page=data_pegawai&halaman=<?php echo $halaman + 1; ?><?php echo $url_pencarian; ?>"><i class="fas fa-chevron-right"></i></a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>

                <?php foreach($data_users_search as $data): ?>
                <div class="modal fade" id="modalEdit<?php echo $data['id_user']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg"> 
                        <div class="modal-content">
                            <div class="modal-header" style="background-color: var(--pn-green); color: white; border-bottom: 4px solid var(--pn-gold);">
                                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i> Edit Pegawai</h5>
                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <form method="POST">
                                <div class="modal-body text-left text-dark">
                                    <input type="hidden" name="id_user" value="<?php echo $data['id_user']; ?>">
                                    
                                    <input type="hidden" name="halaman_aktif" value="<?php echo isset($_GET['halaman']) ? $_GET['halaman'] : 1; ?>">

                                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3">Identitas</h6>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold small">NIP</label>
                                            <input type="text" name="nip" class="form-control" value="<?php echo $data['nip']; ?>" required>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold small">Nama Lengkap</label>
                                            <input type="text" name="nama_lengkap" class="form-control" value="<?php echo $data['nama_lengkap']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <label class="font-weight-bold small">Jabatan</label>
                                            <input type="text" name="jabatan" class="form-control" value="<?php echo $data['jabatan']; ?>" required>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label class="font-weight-bold small">Pangkat</label>
                                            <input type="text" name="pangkat" class="form-control" value="<?php echo isset($data['pangkat']) ? $data['pangkat'] : ''; ?>">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label class="font-weight-bold small">Status Akun</label>
                                            <select name="status_akun" class="form-control">
                                                <option value="aktif" <?php echo ($data['status_akun']=='aktif')?'selected':''; ?>>Aktif</option>
                                                <option value="tidak_aktif" <?php echo ($data['status_akun']=='tidak_aktif')?'selected':''; ?>>Tidak Aktif</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group bg-light p-2 rounded border">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="chkAtasanEdit<?= $data['id_user']; ?>" name="is_atasan" value="1" <?php echo ($data['is_atasan'] == '1') ? 'checked' : ''; ?>>
                                            <label class="custom-control-label font-weight-bold text-dark" for="chkAtasanEdit<?= $data['id_user']; ?>">
                                                Tandai sebagai opsi Atasan Langsung?
                                            </label>
                                            <div class="small text-muted ml-4">Jika dicentang, nama ini akan muncul di list pilihan atasan.</div>
                                        </div>
                                    </div>

                                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3 mt-3">Kuota Cuti</h6>
                                    <div class="row bg-light p-3 rounded mx-0 mb-3">
                                        <div class="col-md-4"><label class="small font-weight-bold">Sisa Tahun Ini</label><input type="number" name="sisa_cuti_n" class="form-control" value="<?php echo $data['sisa_cuti_n']; ?>"></div>
                                        <div class="col-md-4"><label class="small font-weight-bold">Sisa Tahun Lalu</label><input type="number" name="sisa_cuti_n1" class="form-control" value="<?php echo $data['sisa_cuti_n1']; ?>"></div>
                                        <div class="col-md-4"><label class="small font-weight-bold">Cuti Sakit</label><input type="number" name="kuota_cuti_sakit" class="form-control" value="<?php echo $data['kuota_cuti_sakit']; ?>"></div>
                                    </div>

                                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3 mt-3">Akses & Peran</h6>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold small">Password <span class="text-muted">(Isi jika ingin ubah)</span></label>
                                            <input type="password" name="password" class="form-control" placeholder="******">
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="font-weight-bold small">Role</label>
                                            <select name="role" class="form-control">
                                                <option value="user" <?php echo ($data['role']=='user')?'selected':''; ?>>User</option>
                                                <option value="admin" <?php echo ($data['role']=='admin')?'selected':''; ?>>Admin</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                    <button type="submit" name="edit" class="btn btn-pn-solid">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div> 
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--pn-green); color: white; border-bottom: 4px solid var(--pn-gold);">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-plus mr-2"></i> Tambah Pegawai Baru</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3">Identitas</h6>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">NIP <span class="text-danger">*</span></label>
                            <input type="text" name="nip" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row mt-1">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Jabatan <span class="text-danger">*</span></label>
                            <input type="text" name="jabatan" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Pangkat</label>
                            <input type="text" name="pangkat" class="form-control" placeholder="Contoh: Penata Muda (III/a)">
                        </div>
                    </div>

                    <div class="form-group bg-light p-2 rounded border mt-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="chkAtasanBaru" name="is_atasan" value="1">
                            <label class="custom-control-label font-weight-bold text-dark" for="chkAtasanBaru">
                                Tandai sebagai opsi Atasan Langsung?
                            </label>
                            <div class="small text-muted ml-4">Jika dicentang, nama ini akan muncul di list pilihan atasan.</div>
                        </div>
                    </div>
                    
                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3 mt-3">Kuota Cuti Awal</h6>
                    <div class="row bg-light p-3 rounded mx-0 mb-3">
                        <div class="col-md-4"><label class="small font-weight-bold">Cuti Tahun Ini</label><input type="number" name="sisa_cuti_n" class="form-control" value="12"></div>
                        <div class="col-md-4"><label class="small font-weight-bold">Sisa Tahun Lalu</label><input type="number" name="sisa_cuti_n1" class="form-control" value="0"></div>
                        <div class="col-md-4"><label class="small font-weight-bold">Sakit</label><input type="number" name="kuota_cuti_sakit" class="form-control" value="0"></div>
                    </div>

                    <h6 class="font-weight-bold text-success border-bottom pb-2 mb-3 mt-3">Akses</h6>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Role</label>
                            <select name="role" class="form-control">
                                <option value="user">User Biasa</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-pn-solid">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPejabat" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--pn-gold); color: #000;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-user-tie mr-2"></i> Atur Pejabat Penandatangan</h5>
                <button type="button" class="close text-dark" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-light border border-warning small p-2 mb-3 text-dark">
                        <i class="fas fa-info-circle text-warning"></i> Data ini akan muncul di cetakan surat.
                    </div>
                    <datalist id="list_nama_pegawai">
                        <?php
                        $q_all = mysqli_query($koneksi, "SELECT nama_lengkap, nip FROM users ORDER BY nama_lengkap ASC");
                        while($user = mysqli_fetch_array($q_all)){ echo "<option value='".$user['nama_lengkap']."' data-nip='".$user['nip']."'>"; }
                        ?>
                    </datalist>
                    <h6 class="font-weight-bold text-dark border-bottom pb-2">1. Ketua Pengadilan</h6>
                    <div class="form-group mb-2">
                        <label class="small mb-0">Nama Lengkap</label>
                        <input type="text" name="ketua_nama" id="ketua_nama" class="form-control" list="list_nama_pegawai" value="<?= $set_instansi['ketua_nama'] ?? '' ?>" required autocomplete="off" onchange="autoIsiNip('ketua')">
                    </div>
                    <div class="form-group">
                        <label class="small mb-0">NIP</label>
                        <input type="text" name="ketua_nip" id="ketua_nip" class="form-control" value="<?= $set_instansi['ketua_nip'] ?? '' ?>" required>
                    </div>
                    <h6 class="font-weight-bold text-dark border-bottom pb-2 mt-4">2. Wakil Ketua</h6>
                    <div class="form-group mb-2">
                        <label class="small mb-0">Nama Lengkap</label>
                        <input type="text" name="wakil_nama" id="wakil_nama" class="form-control" list="list_nama_pegawai" value="<?= $set_instansi['wakil_nama'] ?? '' ?>" autocomplete="off" onchange="autoIsiNip('wakil')">
                    </div>
                    <div class="form-group">
                        <label class="small mb-0">NIP</label>
                        <input type="text" name="wakil_nip" id="wakil_nip" class="form-control" value="<?= $set_instansi['wakil_nip'] ?? '' ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" name="simpan_pejabat" class="btn btn-warning font-weight-bold text-dark">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoIsiNip(tipe) {
    var inputNama = document.getElementById(tipe + '_nama').value;
    var listOptions = document.getElementById('list_nama_pegawai').options;
    for (var i = 0; i < listOptions.length; i++) {
        if (listOptions[i].value === inputNama) {
            document.getElementById(tipe + '_nip').value = listOptions[i].getAttribute('data-nip');
            break;
        }
    }
}
function konfirmasiStatus(id, aksi, nama) {
    Swal.fire({
        title: 'Konfirmasi',
        html: `Apakah Anda yakin ingin <b>${aksi}</b> akses untuk:<br>${nama}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: aksi == 'aktifkan' ? '#004d00' : '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Lakukan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?page=data_pegawai&toggle_status=' + id + '&halaman=<?php echo $halaman; ?>';
        }
    })
}

function konfirmasiHapus(id, nama) {
    Swal.fire({
        title: 'Hapus Pegawai?',
        html: `Apakah Anda yakin ingin menghapus data pegawai:<br><strong class="text-danger">${nama}</strong>?<br><small class="text-muted d-block mt-2">Seluruh riwayat pengajuan cuti dan izin terkait pegawai ini juga akan dibersihkan secara permanen.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?page=data_pegawai&hapus_pegawai=' + id + '&halaman=<?php echo $halaman; ?>';
        }
    });
}

$(document).ready(function() {
    $('#keyword').on('keyup', function() {
        var keyword = $(this).val();
        $('#area_tabel').load('index.php?page=data_pegawai&cari=' + encodeURIComponent(keyword) + ' #area_tabel', function(response, status, xhr) {
            if (status == "error") {
                console.log("Error: " + xhr.status + " " + xhr.statusText);
            }
        });
    });
});
</script>

<?php if (!empty($swal_script)): ?>
    <script><?php echo $swal_script; ?></script>
<?php endif; ?>