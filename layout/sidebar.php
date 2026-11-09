<?php
$pageAktif = $_GET['page'] ?? null;

if (!$pageAktif) {
    $pageAktif = ($_SESSION['role'] == 'admin') ? 'dashboard_admin' : 'dashboard_user';
}
?>

<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar"
    style="background: linear-gradient(180deg, #004d00 10%, #004d00 100%); position: sticky; top: 0; height: 100vh; overflow-y: auto; overflow-x: hidden; z-index: 1050;">

    <style>
        @media (min-width: 768px) {
            .sidebar:not(.toggled) {
                width: 15.5rem !important;
            }
        }
        .sidebar .nav-item .nav-link span { 
            font-size: 0.78rem !important; 
            letter-spacing: -0.15px;
            white-space: nowrap;
        }
        .sidebar-brand-text { font-size: 0.85rem; }

        #accordionSidebar { scrollbar-width: none; -ms-overflow-style: none; }
        #accordionSidebar::-webkit-scrollbar { display: none; }

        .sidebar .nav-item .nav-link i{
            color: rgba(255, 255, 255, 0.35) !important;
            transition: all .2s ease;
        }
        .sidebar .nav-item .nav-link:hover i{
            color: rgba(255, 255, 255, 0.6) !important;
        }
        .sidebar .nav-item.active .nav-link i,
        .sidebar .nav-item .nav-link.active i{
            color: #F9A825 !important;
        }

        .sidebar:not(.toggled) .nav-item{
            margin: 0 0 .18rem 0 !important;
        }
        .sidebar:not(.toggled) .nav-item .nav-link{
            padding: .55rem .85rem !important;
            display: flex;
            align-items: center;
            line-height: 1.18 !important;
        }
        .sidebar:not(.toggled) .nav-item .nav-link i{
            font-size: .95rem !important;
            flex-shrink: 0;
            margin-right: .55rem !important;
        }
        .sidebar:not(.toggled) .sidebar-heading{
            padding: .62rem 1rem .24rem !important;
            margin: 0 !important;
            font-size: .62rem !important;
            opacity: .85;
        }
        .sidebar:not(.toggled) .sidebar-divider{
            margin: .36rem 0 !important;
        }
        .sidebar:not(.toggled) .sidebar-brand{
            padding: 1.2rem 1rem !important;
        }

        .sidebar.toggled { width: 6.5rem !important; }

        .sidebar.toggled .sidebar-brand{
            flex-direction: column !important;
            height: auto !important;
            padding: .75rem .25rem !important;
        }
        .sidebar.toggled .sidebar-brand-text sup { display: none !important; }
        .sidebar.toggled .sidebar-brand-text{
            display: block !important;
            font-size: 0.75rem !important;
            margin: 5px 0 0 0 !important;
            text-align: center;
        }
        .sidebar.toggled .sidebar-heading{
            display: block !important;
            text-align: center !important;
            font-size: .55rem !important;
            padding: .45rem .25rem .15rem !important;
            opacity: .8;
            white-space: normal;
            line-height: 1.05 !important;
        }
        .sidebar.toggled .nav-item .nav-link{
            text-align: center !important;
            padding: .25rem .15rem !important;
            width: 100% !important;
            height: 3.25rem !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.1 !important;
        }
        .sidebar.toggled .nav-item .nav-link i{
            margin-right: 0 !important;
            margin-bottom: 2px !important;
            font-size: .95rem !important;
        }
        .sidebar.toggled .nav-item .nav-link span{
            display: block !important;
            font-size: .58rem !important;
            line-height: 1.0 !important;
        }
        .sidebar.toggled .sidebar-divider{ margin: .2rem 0 !important; }
    </style>

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon">
            <?php if(file_exists("assets/img/logo.png")): ?>
                <img src="assets/img/logo.png" style="width: 40px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
            <?php else: ?>
                <i class="fas fa-balance-scale" style="color: #F9A825; font-size: 1.5rem;"></i>
            <?php endif; ?>
        </div>
        <div class="sidebar-brand-text mx-2" style="font-weight: 800; letter-spacing: 1px;">
            SI-APIC <sup style="color: #F9A825;">PNYK</sup>
        </div>
    </a>

    <hr class="sidebar-divider my-0">

    <?php if($_SESSION['role'] == 'admin'): ?>

        <li class="nav-item <?php echo ($pageAktif == 'dashboard_admin') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=dashboard_admin">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard Admin</span>
            </a>
        </li>

        <div class="sidebar-heading">Administrasi</div>
        <li class="nav-item <?php echo ($pageAktif == 'data_pegawai') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=data_pegawai">
                <i class="fas fa-fw fa-users"></i>
                <span>Data Pegawai</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <div class="sidebar-heading">Laporan & Cuti</div>
        <li class="nav-item <?php echo ($pageAktif == 'laporan_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=laporan_cuti">
                <i class="fas fa-fw fa-print"></i>
                <span>Laporan Cuti</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'input_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=input_cuti">
                <i class="fas fa-fw fa-calendar-plus"></i>
                <span>Input Cuti Pegawai</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'validasi_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=validasi_cuti">
                <i class="fas fa-fw fa-check-double"></i>
                <span>Persetujuan Cuti</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Izin Insidentil</div>

        <li class="nav-item <?php echo ($pageAktif == 'izin_keluar') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=izin_keluar">
                <i class="fas fa-fw fa-door-open"></i>
                <span>Izin Keluar Kantor</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'izin_pulang') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=izin_pulang">
                <i class="fas fa-fw fa-walking"></i>
                <span>Izin Pulang Awal</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <div class="sidebar-heading">Hari Libur</div>
        <li class="nav-item <?php echo ($pageAktif == 'manage_libur') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=manage_libur">
                <i class="fas fa-fw fa-calendar-alt"></i>
                <span>Atur Libur Nasional</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <div class="sidebar-heading">Upload Dokumen (e-Doc)</div>
        <li class="nav-item <?php echo ($pageAktif == 'edoc_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=edoc_cuti">
                <i class="fas fa-fw fa-file-contract"></i>
                <span>e-Doc Keluar Cuti</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'edoc_pulang') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=edoc_pulang">
                <i class="fas fa-fw fa-file-export"></i>
                <span>e-Doc Keluar Awal</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'edoc_keluar') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=edoc_keluar">
                <i class="fas fa-fw fa-file-signature"></i>
                <span>e-Doc Izin Keluar Kantor</span>
            </a>
        </li>

    <?php else: ?>

        <li class="nav-item <?php echo ($pageAktif == 'dashboard_user') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=dashboard_user">
                <i class="fas fa-fw fa-home"></i>
                <span>Beranda</span>
            </a>
        </li>

        <div class="sidebar-heading">Menu Utama</div>
        <li class="nav-item <?php echo ($pageAktif == 'form_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=form_cuti">
                <i class="fas fa-fw fa-calendar-plus"></i>
                <span>Ajukan Cuti</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'riwayat_cuti') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=riwayat_cuti">
                <i class="fas fa-fw fa-history"></i>
                <span>Riwayat</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Izin Insidentil</div>

        <li class="nav-item <?php echo ($pageAktif == 'izin_keluar') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=izin_keluar">
                <i class="fas fa-fw fa-door-open"></i>
                <span>Izin Keluar Kantor</span>
            </a>
        </li>

        <li class="nav-item <?php echo ($pageAktif == 'izin_pulang') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php?page=izin_pulang">
                <i class="fas fa-fw fa-walking"></i>
                <span>Izin Pulang Awal</span>
            </a>
        </li>

    <?php endif; ?>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">Akun</div>
    <li class="nav-item <?php echo ($pageAktif == 'ganti_password') ? 'active' : ''; ?>">
        <a class="nav-link" href="index.php?page=ganti_password">
            <i class="fas fa-fw fa-key"></i>
            <span>Ganti Password</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="logout.php" id="tombol-logout-sidebar" style="cursor: pointer;">
            <i class="fas fa-fw fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('tombol-logout-sidebar');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Yakin ingin keluar?',
                    text: "Sesi Anda akan diakhiri.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#006B3F',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Keluar',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) { window.location.href = href; }
                });
            } else {
                if (confirm('Yakin ingin keluar dari sistem?')) { window.location.href = href; }
            }
        });
    }
});
</script>
