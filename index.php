<?php
session_start();

// 1. KONEKSI DATABASE
// Menggunakan path standar karena index.php ada di root folder
include 'config/database.php';

// Cek Login
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php?pesan=belum_login");
    exit();
}

// =============================================================
// LOGIKA PAGE (ROUTING)
// =============================================================
if(isset($_GET['page'])){
    $page = $_GET['page'];
} else {
    // Default page jika tidak ada parameter
    if($_SESSION['role'] == 'admin'){
        $page = 'dashboard_admin';
    } else {
        $page = 'dashboard_user';
    }
}

// 2. Load Header
include 'layout/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* 1. Global Font */
    body, #wrapper, h1, h2, h3, h4, h5, h6, p, span, a, div, table, input, button, select, textarea {
        font-family: 'Poppins', sans-serif !important;
    }
    
    /* 2. Background Content Lebih Bersih */
    #content-wrapper { background-color: #F2F5F3 !important; }

    /* 3. MODIFIKASI KARTU (CARD) */
    .card {
        border-radius: 20px !important;
        border: none !important;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05) !important;
        overflow: hidden;
        margin-bottom: 25px;
    }
    .card-header {
        background-color: #fff !important;
        border-bottom: 1px solid #f0f0f0 !important;
        padding: 20px;
    }

    /* 4. MODIFIKASI TOMBOL & INPUT */
    .btn {
        border-radius: 12px !important;
        padding: 10px 20px;
        font-weight: 500;
        box-shadow: none !important;
    }
    .form-control {
        border-radius: 12px !important;
        height: 45px;
        padding: 10px 15px;
    }

    /* 5. Sidebar Active State */
    .sidebar .nav-item.active .nav-link {
        font-weight: 600 !important;
        color: #ffffff !important;
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 5px solid #F9A825;
        padding-left: 1rem;
        border-radius: 0 10px 10px 0;
    }
    .sidebar .nav-item.active .nav-link i { color: #F9A825 !important; }
    .sidebar .nav-item .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.05);
        border-radius: 0 10px 10px 0;
    }
</style>

<?php
// 3. Load Sidebar & Topbar
include 'layout/sidebar.php';
include 'layout/topbar.php';

// 4. Load Konten Halaman (Routing)

// --- AREA ADMIN ---
if($page == "dashboard_admin" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/dashboard.php';
} 
else if($page == "validasi_cuti" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/validasi_cuti.php';
}
else if($page == "data_pegawai" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/data_pegawai.php';
}
else if($page == "form_pegawai" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/form_pegawai.php';
}
else if($page == "proses_pegawai" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/proses_pegawai.php';
}
else if($page == "input_cuti" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/input_cuti.php';
}
else if($page == "manage_libur" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/manage_libur.php';
}
else if($page == "laporan_cuti" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/laporan_cuti.php';
}
else if($page == "edoc_cuti" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/edoc_cuti.php';
}
else if($page == "edoc_pulang" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/edoc_pulang.php';
}
else if($page == "edoc_keluar" && $_SESSION['role'] == 'admin') {
    include 'pages/admin/edoc_keluar.php';
}

// --- FITUR: IZIN KELUAR (Admin & User) ---
else if($page == "izin_keluar") {
    if($_SESSION['role'] == 'admin'){
        include 'pages/admin/izin_keluar.php';
    } else {
        include 'pages/user/izin_keluar.php';
    }
}

// --- FITUR BARU: IZIN PULANG AWAL (Admin & User) ---
// Bagian ini yang ditambahkan agar link di sidebar berfungsi
else if($page == "izin_pulang") {
    if($_SESSION['role'] == 'admin'){
        // Admin melihat rekap data
        include 'pages/admin/izin_pulang.php';
    } else {
        // User melihat form input
        include 'pages/user/izin_pulang.php';
    }
}

// --- AREA USER ---
else if($page == "dashboard_user") {
    include 'pages/user/dashboard.php';
}
else if($page == "form_cuti") {
    include 'pages/user/form_cuti.php';
}
else if($page == "riwayat_cuti") {
    include 'pages/user/riwayat_cuti.php';
}

// --- AREA UMUM ---
else if($page == "ganti_password") {
    include 'pages/ganti_password.php'; 
}
else {
    echo "
    <div class='container-fluid mt-5 text-center'>
        <i class='fas fa-search fa-3x text-gray-300 mb-3'></i>
        <h3>Halaman tidak ditemukan!</h3>
        <p>Halaman yang Anda cari (<b>$page</b>) tidak tersedia atau Anda tidak memiliki akses.</p>
        <a href='index.php' class='btn btn-primary'>Kembali ke Dashboard</a>
    </div>";
}

// 5. Load Footer
include 'layout/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if(isset($_SESSION['swal'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        confirmButtonColor: '#1cc88a',
        confirmButtonText: 'OK'
    }).then((result) => {
        // Opsional: window.location.reload(); 
    });
</script>
<?php 
    unset($_SESSION['swal']); 
?>
<?php endif; ?>