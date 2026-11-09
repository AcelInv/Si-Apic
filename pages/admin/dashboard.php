<?php
/** @var mysqli $koneksi */

// 1. Statistik Pengguna & Pengajuan Cuti
$get_pegawai = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role='user' OR role='admin'");
$d_pegawai = mysqli_fetch_assoc($get_pegawai);

$get_pending = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pengajuan_cuti WHERE status='Diajukan'");
$d_pending = mysqli_fetch_assoc($get_pending);

$get_acc = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pengajuan_cuti WHERE status='Disetujui'");
$d_acc = mysqli_fetch_assoc($get_acc);

$get_tolak = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pengajuan_cuti WHERE status='Ditolak'");
$d_tolak = mysqli_fetch_assoc($get_tolak);

// 2. Permohonan Terbaru (5 data terakhir)
$query_latest = "SELECT c.*, u.nama_lengkap, u.jabatan, c.created_at
                 FROM pengajuan_cuti c
                 JOIN users u ON c.id_user = u.id_user
                 ORDER BY c.created_at DESC LIMIT 5";
$sql_latest = mysqli_query($koneksi, $query_latest);

// 3. Data Kalender Libur Nasional & Cuti Bersama
$query_libur = mysqli_query($koneksi, "SELECT * FROM libur_nasional");
$libur_data = [];
if ($query_libur) {
    while($row = mysqli_fetch_assoc($query_libur)) {
        $libur_data[] = [
            'start' => $row['tanggal'],
            'title' => $row['keterangan'],
            'jenis' => strtolower($row['jenis_libur']),
            'allDay' => true
        ];
    }
}

// 4. Helper Tanggal Indonesia
$today = date('Y-m-d');
$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$nama_hari_ini = $hari_indo[date('l')];
$tgl_hari_ini_teks = $nama_hari_ini . ', ' . date('d') . ' ' . $bulan_indo[date('m')] . ' ' . date('Y');

// 5. Pegawai yang cuti per hari ini
$q_cuti_hari_ini = mysqli_query($koneksi, "
    SELECT p.*, u.nama_lengkap, u.nip, u.jabatan, p.lama_hari, p.alasan, p.tgl_mulai, p.tgl_selesai, j.nama_jenis
    FROM pengajuan_cuti p
    JOIN users u ON p.id_user = u.id_user
    JOIN jenis_cuti j ON p.id_jenis = j.id_jenis
    WHERE p.status = 'Disetujui'
      AND '$today' BETWEEN p.tgl_mulai AND p.tgl_selesai
    ORDER BY p.tgl_mulai ASC
");
$jml_cuti_hari_ini = $q_cuti_hari_ini ? mysqli_num_rows($q_cuti_hari_ini) : 0;

// 6. Rekapitulasi per jenis cuti
$q_jenis_cuti = mysqli_query($koneksi, "
    SELECT j.id_jenis, j.nama_jenis,
           COUNT(p.id_pengajuan) AS total_pengajuan,
           SUM(CASE WHEN p.status = 'Disetujui' THEN 1 ELSE 0 END) AS disetujui,
           SUM(CASE WHEN p.status = 'Diajukan' THEN 1 ELSE 0 END) AS menunggu,
           SUM(CASE WHEN p.status = 'Ditolak' THEN 1 ELSE 0 END) AS ditolak,
           COALESCE(SUM(CASE WHEN p.status = 'Disetujui' THEN p.lama_hari ELSE 0 END), 0) AS total_hari
    FROM jenis_cuti j
    LEFT JOIN pengajuan_cuti p ON j.id_jenis = p.id_jenis
    GROUP BY j.id_jenis, j.nama_jenis
    ORDER BY j.id_jenis ASC
");
$data_jenis_cuti = [];
if ($q_jenis_cuti) {
    while ($jc = mysqli_fetch_assoc($q_jenis_cuti)) {
        $data_jenis_cuti[] = $jc;
    }
}

// Helper meta visual persis sesuai wireframe gambar
if (!function_exists('get_jc_mockup_meta')) {
    function get_jc_mockup_meta($nama_jenis) {
        $n = strtolower($nama_jenis);
        if (strpos($n, 'tahunan') !== false) {
            return [
                'icon' => 'fa-calendar-alt',
                'icon_color' => '#16a34a',
                'bg' => '#eaf7ee',
                'border' => '#c2ebd0',
                'badge_class' => 'badge-soft-green'
            ];
        } elseif (strpos($n, 'sakit') !== false) {
            return [
                'icon' => 'fa-plus-square',
                'icon_color' => '#0284c7',
                'bg' => '#edf5fc',
                'border' => '#bfdbfe',
                'badge_class' => 'badge-soft-blue'
            ];
        } elseif (strpos($n, 'besar') !== false) {
            return [
                'icon' => 'fa-star',
                'icon_color' => '#8b5cf6',
                'bg' => '#f5edf8',
                'border' => '#e9d5ff',
                'badge_class' => 'badge-soft-purple'
            ];
        } elseif (strpos($n, 'lahir') !== false) {
            return [
                'icon' => 'fa-baby',
                'icon_color' => '#ec4899',
                'bg' => '#fdf0f4',
                'border' => '#fbcfe8',
                'badge_class' => 'badge-soft-pink'
            ];
        } elseif (strpos($n, 'penting') !== false) {
            return [
                'icon' => 'fa-exclamation-circle',
                'icon_color' => '#f97316',
                'bg' => '#fef6eb',
                'border' => '#fed7aa',
                'badge_class' => 'badge-soft-orange'
            ];
        } else {
            return [
                'icon' => 'fa-folder',
                'icon_color' => '#0284c7',
                'bg' => '#edf5fc',
                'border' => '#bfdbfe',
                'badge_class' => 'badge-soft-blue'
            ];
        }
    }
}
?>

<style>
:root {
    --pn-green: #004d00;
    --pn-green-dark: #003600;
    --pn-gold: #F9A825;
    --bg-page: #f2f5f3;
}

body { font-family: 'Poppins', sans-serif; background: var(--bg-page); }

/* Master Container */
.dash-layout {
    display: flex;
    flex-direction: column;
    gap: 10px;
    height: calc(100vh - 72px);
    padding: 10px 14px 8px 14px;
    box-sizing: border-box;
    overflow: hidden;
}

/* ══════════════════════════════════════════
   1. TOP SECTION (Left: Banner + Cards, Right: Calendar)
   ══════════════════════════════════════════ */
.dash-top-row {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}
.dash-top-left {
    flex: 68;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    min-height: 0;
}
.dash-top-right {
    flex: 32;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}

/* Banner */
.banner-mockup {
    background: linear-gradient(135deg, #004d00 0%, #006600 100%);
    border-radius: 12px;
    padding: 9px 18px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 77, 0, 0.15);
}
.banner-mockup .bm-left {
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 2;
}
.banner-mockup .bm-icon {
    font-size: 1.4rem;
    color: #facc15;
}
.banner-mockup h5 {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}
.banner-mockup p {
    font-size: .68rem;
    margin: 0;
    opacity: .85;
}
.banner-mockup .bm-watermark {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3.2rem;
    color: rgba(255, 255, 255, 0.12);
    z-index: 1;
}

/* Middle Row: Total Pegawai + Jenis Cuti */
.stats-mid-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
    flex: 1;
    min-height: 0;
}

/* Total Pegawai Card */
.card-total-pegawai {
    flex: 0 0 160px;
    background: #fff;
    border-radius: 12px;
    padding: 10px 12px 6px 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid #eef2f0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    height: 100%;
}
.card-total-pegawai .tp-head {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #1e293b;
    font-size: .78rem;
    font-weight: 700;
}
.card-total-pegawai .tp-head i {
    color: #10b981;
    font-size: .88rem;
}
.card-total-pegawai .tp-main {
    text-align: center;
    margin: 4px 0 2px 0;
}
.card-total-pegawai .tp-num {
    font-size: 2.7rem;
    font-weight: 900;
    color: #0d3e26;
    line-height: 1;
    letter-spacing: -1px;
}
.card-total-pegawai .tp-sub {
    font-size: .72rem;
    font-weight: 700;
    color: #1e293b;
    margin-top: 3px;
}
.card-total-pegawai .tp-avatar-svg {
    width: 100%;
    height: 38px;
    margin-bottom: -4px;
}

/* Container Jenis Cuti */
.card-jenis-cuti-wrap {
    flex: 1;
    background: #fff;
    border-radius: 12px;
    padding: 8px 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid #eef2f0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
    min-height: 0;
    height: 100%;
}
.card-jenis-cuti-wrap .jc-head {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #1e293b;
    font-size: .78rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.card-jenis-cuti-wrap .jc-head i {
    color: #10b981;
    font-size: .88rem;
}

/* 5 Cards Grid: 3 di atas, 2 di bawah */
.jc-cards-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    grid-template-rows: 1fr 1fr;
    gap: 6px;
    min-height: 0;
}
.jc-card-item:nth-child(1),
.jc-card-item:nth-child(2),
.jc-card-item:nth-child(3) {
    grid-column: span 2;
}
.jc-card-item:nth-child(4),
.jc-card-item:nth-child(5) {
    grid-column: span 3;
}

.jc-card-item {
    border-radius: 10px;
    padding: 6px 8px;
    border: 1px solid;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform .15s, box-shadow .15s;
    height: 100%;
}
.jc-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.jc-card-top {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    margin-bottom: 5px;
}
.jc-card-top span {
    font-size: .75rem;
    font-weight: 700;
    color: #1e293b;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    display: block;
    width: 100%;
}
.jc-stat-pill {
    background: #fff;
    border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3px 4px;
}
.jc-stat-col {
    flex: 1;
    text-align: center;
    padding: 0 2px;
}
.jc-stat-col .n-num {
    font-size: .95rem;
    font-weight: 800;
    line-height: 1.1;
    display: block;
}
.jc-stat-col .n-lbl {
    font-size: .56rem;
    font-weight: 700;
    color: #64748b;
    display: block;
    margin-top: 1px;
    text-transform: uppercase;
    letter-spacing: .3px;
}

/* Kalender Libur (Top Right) */
.card-kalender-mockup {
    background: #fff;
    border-radius: 12px;
    padding: 8px 12px 6px 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid #eef2f0;
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}
.card-kalender-mockup .kl-head {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #1e293b;
    font-size: .80rem;
    font-weight: 700;
    flex-shrink: 0;
}
.card-kalender-mockup .kl-head i {
    color: #10b981;
    font-size: .95rem;
}
.card-kalender-mockup .kl-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 0;
    overflow: hidden;
}
.card-kalender-mockup #calendar {
    font-size: .62rem;
}
.card-kalender-mockup .kl-footer {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-top: 4px;
    border-top: 1px solid #f1f5f9;
    font-size: .62rem;
    min-height: 0;
    flex-shrink: 0;
}
.kl-legend-row {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .62rem;
    line-height: 1;
}
.kl-legend-label {
    font-weight: 700;
    color: #334155;
    margin-right: 2px;
}
.kl-legend-text {
    color: #64748b;
    font-weight: 600;
    font-size: .60rem;
}
.kl-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.kl-dot-nasional {
    background: #ef4444;
}
.kl-dot-cuti {
    background: #facc15;
}
.kl-keterangan-list {
    height: 44px;
    max-height: 44px;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-right: 3px;
}
.kl-keterangan-list::-webkit-scrollbar {
    width: 4px;
}
.kl-keterangan-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.kl-keterangan-list::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 4px;
}
.kl-keterangan-list::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}
.kl-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: .61rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #f8fafc;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
    min-height: 19px;
    box-sizing: border-box;
}
.kl-tgl {
    font-weight: 700;
    color: #0f172a;
    flex-shrink: 0;
}
.kl-desc {
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.kl-empty {
    font-size: .60rem;
    color: #94a3b8;
    font-style: italic;
    line-height: 1.2;
    padding: 4px 2px;
    flex-shrink: 0;
}

/* FullCalendar styling sesuai mockup */
.fc-theme-standard td, .fc-theme-standard th, .fc-scrollgrid { border: none !important; }
.fc-daygrid-day-frame { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    min-height: 15px !important; 
    height: 16px !important; 
    padding: 0 !important; 
}
.fc .fc-daygrid-day-number {
    width: 17px; 
    height: 17px; 
    display: flex !important; 
    align-items: center; 
    justify-content: center;
    border-radius: 50%; 
    text-decoration: none !important; 
    color: #334155; 
    z-index: 2; 
    font-size: .59rem; 
    font-weight: 600;
    line-height: 1;
}
.fc-day-today { background: transparent !important; }
.fc-day-today .fc-daygrid-day-number { background: #004d00 !important; color: #fff !important; font-weight: 700; }
.holiday-nasional .fc-daygrid-day-number { background: #ef4444 !important; color: #fff !important; font-weight: 700; }
.holiday-cuti-bersama .fc-daygrid-day-number { background: #facc15 !important; color: #854d0e !important; font-weight: 700; }
.fc-event, .fc-daygrid-event { display: none !important; }
.fc .fc-toolbar { margin-bottom: 1px !important; }
.fc .fc-toolbar-title { font-size: .82rem !important; font-weight: 800; color: #0f172a !important; }
.fc .fc-button-primary { background: transparent !important; border: none !important; color: #475569 !important; padding: 0 4px !important; font-size: .75rem !important; }
.fc .fc-button-primary:hover { color: #004d00 !important; }
.fc .fc-button-primary:focus { box-shadow: none !important; }
.fc .fc-col-header-cell-cushion { font-size: .59rem; color: #475569; font-weight: 700; text-decoration: none !important; padding: 0 !important; }
.fc .fc-scroller { overflow: hidden !important; }

/* ══════════════════════════════════════════
   2. BOTTOM SECTION (Cuti Hari Ini & Permohonan Terbaru — 50%:50%)
   ══════════════════════════════════════════ */
.dash-bottom-row {
    display: flex;
    gap: 10px;
    flex: 1;
    min-height: 0;
}
.card-bottom-panel {
    flex: 1;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid #eef2f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

/* Header Hijau Tua Khas Mockup — UKURAN PERSIS SAMA & KONSISTEN */
.panel-header-green {
    background: #004d00;
    color: #fff;
    height: 40px;
    min-height: 40px;
    max-height: 40px;
    padding: 0 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    box-sizing: border-box;
}
.panel-header-green .ph-title {
    font-size: .86rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 7px;
    line-height: 1;
}
.panel-header-green .ph-title i {
    font-size: .92rem;
    line-height: 1;
}
.panel-header-green .ph-date {
    font-size: .68rem;
    opacity: .85;
    line-height: 1;
    display: flex;
    align-items: center;
}
.panel-header-green .ph-btn {
    border-radius: 20px;
    font-size: .66rem;
    font-weight: 700;
    padding: 2px 10px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-color: rgba(255,255,255,0.7);
    color: #fff;
    line-height: 1;
}
.panel-header-green .ph-btn:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
}

/* Body & Table */
.panel-table-body {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}
.panel-table-body::-webkit-scrollbar { width: 4px; }
.panel-table-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

.tbl-mockup {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
}
.tbl-mockup th {
    font-size: .66rem;
    font-weight: 700;
    color: #64748b;
    padding: 0 10px;
    height: 32px;
    line-height: 32px;
    background: #f8fafc;
    border-bottom: 1.5px solid #edf2f7;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 1;
    box-sizing: border-box;
}
.tbl-mockup td {
    font-size: .74rem;
    padding: 6px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    line-height: 1.25;
}
.tbl-mockup tr:hover {
    background: #fafcfb;
}

/* Badges Sesuai Mockup */
.badge-soft-green {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-soft-orange {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-soft-red {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-soft-blue {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-soft-purple {
    background: #f3e8ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-soft-pink {
    background: #fce7f3;
    color: #be185d;
    border: 1px solid #fbcfe8;
    border-radius: 20px;
    font-weight: 700;
    font-size: .66rem;
    padding: 2px 9px;
}
.badge-durasi {
    background: #f1f5f9;
    color: #334155;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: .66rem;
    font-weight: 700;
    border: 1px solid #e2e8f0;
}

/* Panel Footer (Lihat Semua) */
.panel-footer-mockup {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 4px 12px;
    background: #fff;
    border-top: 1px solid #f1f5f9;
    font-size: .66rem;
}
.panel-footer-mockup a {
    color: #059669;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.panel-footer-mockup a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 1024px) {
    .dash-layout { height: auto; overflow: visible; }
    .dash-top-row { flex-direction: column; }
    .dash-bottom-row { flex-direction: column; min-height: 500px; }
}
</style>

<div class="dash-layout">

    <!-- ═════════════════════════════════════════════════════════════
         1. TOP SECTION: BANNER, TOTAL PEGAWAI, JENIS CUTI & KALENDER
         ═════════════════════════════════════════════════════════════ -->
    <div class="dash-top-row">

        <!-- KIRI (68%): BANNER + MID CARDS -->
        <div class="dash-top-left">

            <!-- BANNER -->
            <div class="banner-mockup">
                <div class="bm-left">
                    <i class="fas fa-bullhorn bm-icon"></i>
                    <div>
                        <h5>Halo, Administrator!</h5>
                        <p>Panel Kontrol SI-APIC Pengadilan Negeri Yogyakarta &bull; <?= $tgl_hari_ini_teks ?></p>
                    </div>
                </div>
                <i class="fas fa-landmark bm-watermark"></i>
            </div>

            <!-- ROW: TOTAL PEGAWAI + JENIS CUTI -->
            <div class="stats-mid-row">

                <!-- TOTAL PEGAWAI (KIRI) -->
                <div class="card-total-pegawai">
                    <div class="tp-head">
                        <i class="fas fa-users"></i>
                        <span>Total Pegawai</span>
                    </div>
                    <div class="tp-main">
                        <div class="tp-num"><?= $d_pegawai['total'] ?></div>
                        <div class="tp-sub">Pegawai Aktif</div>
                    </div>
                    <!-- Siluet Avatars Persis Mockup -->
                    <svg class="tp-avatar-svg" viewBox="0 0 160 50">
                        <!-- Left Avatar -->
                        <circle cx="35" cy="22" r="11" fill="#a7f3d0"/>
                        <path d="M12 48 A 23 23 0 0 1 58 48 Z" fill="#a7f3d0"/>
                        <!-- Center Avatar -->
                        <circle cx="80" cy="18" r="14" fill="#6ee7b7"/>
                        <path d="M50 48 A 30 30 0 0 1 110 48 Z" fill="#6ee7b7"/>
                        <!-- Right Avatar -->
                        <circle cx="125" cy="22" r="11" fill="#a7f3d0"/>
                        <path d="M102 48 A 23 23 0 0 1 148 48 Z" fill="#a7f3d0"/>
                    </svg>
                </div>

                <!-- JENIS CUTI (KANAN - 3 DI ATAS, 2 DI BAWAH) -->
                <div class="card-jenis-cuti-wrap">
                    <div class="jc-head">
                        <i class="far fa-calendar-alt"></i>
                        <span>Jenis Cuti</span>
                    </div>

                    <div class="jc-cards-grid">
                        <?php foreach ($data_jenis_cuti as $jc):
                            $m = get_jc_mockup_meta($jc['nama_jenis']);
                        ?>
                        <div class="jc-card-item" style="background:<?= $m['bg'] ?>; border-color:<?= $m['border'] ?>;">
                            <div class="jc-card-top">
                                <span><?= htmlspecialchars($jc['nama_jenis']) ?></span>
                            </div>
                            <div class="jc-stat-pill">
                                <div class="jc-stat-col">
                                    <span class="n-num" style="color:<?= $m['icon_color'] ?? '#004d00' ?>;"><?= (int)$jc['total_pengajuan'] ?></span>
                                    <span class="n-lbl">Total Pengajuan</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>

        <!-- KANAN (32%): KALENDER LIBUR (SPAN KEDUA ROW) -->
        <div class="dash-top-right">
            <div class="card-kalender-mockup">
                <div class="kl-head">
                    <i class="far fa-calendar-check"></i>
                    <span>Kalender Libur</span>
                </div>

                <div class="kl-body">
                    <div id="calendar"></div>
                </div>

                <div class="kl-footer">
                    <div class="kl-legend-row">
                        <span class="kl-legend-label">Keterangan:</span>
                        <span class="kl-dot kl-dot-nasional"></span>
                        <span class="kl-legend-text">Nasional</span>
                        <span class="kl-dot kl-dot-cuti ml-2"></span>
                        <span class="kl-legend-text">Cuti Bersama</span>
                    </div>
                    <div id="kl-keterangan-list" class="kl-keterangan-list">
                        <!-- Diisi otomatis keterangan libur per bulan oleh JavaScript -->
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ═════════════════════════════════════════════════════════════
         2. BOTTOM SECTION: CUTI HARI INI & PERMOHONAN TERBARU (50%:50%)
         ═════════════════════════════════════════════════════════════ -->
    <div class="dash-bottom-row">

        <!-- CUTI HARI INI (KIRI - 50%) -->
        <div class="card-bottom-panel">
            <div class="panel-header-green">
                <div class="ph-title">
                    <i class="far fa-calendar-check text-warning"></i>
                    <span>Cuti Hari Ini</span>
                </div>
                <span class="ph-date"><?= $tgl_hari_ini_teks ?></span>
            </div>

            <div class="panel-table-body">
                <?php if ($jml_cuti_hari_ini > 0): ?>
                <table class="tbl-mockup">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:28px;">No</th>
                            <th>Pegawai</th>
                            <th>Jenis Cuti</th>
                            <th>Periode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; while ($cp = mysqli_fetch_assoc($q_cuti_hari_ini)): $mc = get_jc_mockup_meta($cp['nama_jenis']); ?>
                        <tr>
                            <td class="text-center text-muted font-weight-bold"><?= $no++ ?></td>
                            <td>
                                <div class="font-weight-bold text-dark" style="font-size:.78rem;"><?= htmlspecialchars($cp['nama_lengkap']) ?></div>
                                <div class="text-muted" style="font-size:.64rem;">NIP: <?= htmlspecialchars($cp['nip']) ?></div>
                            </td>
                            <td><span class="<?= $mc['badge_class'] ?>"><?= htmlspecialchars($cp['nama_jenis']) ?></span></td>
                            <td style="font-size:.74rem;" class="text-nowrap font-weight-bold text-dark"><?= date('d/m',strtotime($cp['tgl_mulai'])) ?> - <?= date('d/m/Y',strtotime($cp['tgl_selesai'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3 text-center text-muted">
                    <i class="fas fa-user-check text-success fa-2x mb-2" style="opacity: .7;"></i>
                    <h6 class="font-weight-bold text-dark mb-1" style="font-size: .86rem;">Tidak Ada Pegawai Yang Cuti Hari Ini</h6>
                    <p class="small mb-0" style="font-size: .70rem;">Seluruh pegawai Pengadilan Negeri Yogyakarta aktif bertugas pada tanggal <strong><?= $tgl_hari_ini_teks ?></strong>.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PERMOHONAN CUTI TERBARU (KANAN - 50%) -->
        <div class="card-bottom-panel">
            <div class="panel-header-green">
                <div class="ph-title">
                    <i class="fas fa-file-signature text-warning"></i>
                    <span>Permohonan Cuti Terbaru</span>
                    <?php if ($d_pending['total'] > 0): ?>
                        <span class="badge badge-warning text-dark font-weight-bold ml-1 px-2 py-0" style="border-radius:12px; font-size:0.64rem;"><?= $d_pending['total'] ?> Menunggu</span>
                    <?php endif; ?>
                </div>
                <a href="index.php?page=validasi_cuti" class="btn btn-sm btn-outline-light ph-btn">
                    Validasi Cuti <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <div class="panel-table-body">
                <table class="tbl-mockup">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:28px;">No</th>
                            <th>Pegawai & Jabatan</th>
                            <th class="text-center">Waktu Pengajuan</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($sql_latest && mysqli_num_rows($sql_latest) > 0) {
                            $no_p = 1;
                            while($row = mysqli_fetch_array($sql_latest)) {
                                $badge_class = ($row['status']=='Diajukan') ? 'badge-soft-orange' : (($row['status']=='Disetujui') ? 'badge-soft-green' : 'badge-soft-red');
                                $waktu = date('d/m/Y H:i', strtotime($row['created_at']));
                        ?>
                        <tr>
                            <td class="text-center text-muted font-weight-bold"><?= $no_p++ ?></td>
                            <td>
                                <div class="font-weight-bold text-dark text-truncate" style="max-width:170px; font-size:.76rem;"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                <div class="text-muted text-truncate" style="max-width:170px; font-size:.62rem;"><?= htmlspecialchars($row['jabatan']) ?></div>
                            </td>
                            <td class="text-center text-muted text-nowrap" style="font-size:.70rem;">
                                <i class="far fa-clock mr-1" style="opacity:.6;"></i><?= $waktu ?>
                            </td>
                            <td class="text-center">
                                <a href="index.php?page=validasi_cuti" class="<?= $badge_class ?> text-decoration-none">
                                    <?= htmlspecialchars($row['status']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr><td colspan="4" class="text-center text-muted py-3" style="font-size:.72rem;"><i class="fas fa-inbox mr-1"></i> Belum ada permohonan.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-footer-mockup">
                <a href="index.php?page=validasi_cuti">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

    </div>

</div>

<!-- FullCalendar Assets & Initialization -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

<script>
const dataLibur = <?php echo json_encode($libur_data); ?>;

document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('calendar');
    if (!el) return;

    var indoMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function renderKeteranganBulan(calInstance) {
        var listEl = document.getElementById('kl-keterangan-list');
        if (!listEl) return;

        var curDate = calInstance.getDate();
        var curYear = curDate.getFullYear();
        var curMonth = String(curDate.getMonth() + 1).padStart(2, '0');
        var ymPrefix = curYear + '-' + curMonth;

        var monthHolidays = dataLibur.filter(function(item) {
            return item.start && item.start.startsWith(ymPrefix);
        });

        monthHolidays.sort(function(a, b) {
            return a.start.localeCompare(b.start);
        });

        if (monthHolidays.length > 0) {
            var html = '';
            monthHolidays.forEach(function(item) {
                var parts = item.start.split('-');
                var dayNum = parseInt(parts[2], 10);
                var monIdx = parseInt(parts[1], 10) - 1;
                var monName = indoMonths[monIdx] || parts[1];
                var isNasional = item.jenis.indexOf('nasional') !== -1;
                var dotCls = isNasional ? 'kl-dot-nasional' : 'kl-dot-cuti';
                var jenisTxt = isNasional ? 'Libur Nasional' : 'Cuti Bersama';

                html += '<div class="kl-item" title="' + item.title + ' (' + jenisTxt + ')">' +
                        '<span class="kl-dot ' + dotCls + '"></span>' +
                        '<span class="kl-tgl">' + dayNum + ' ' + monName + ':</span>' +
                        '<span class="kl-desc">' + item.title + '</span>' +
                        '</div>';
            });
            listEl.innerHTML = html;
        } else {
            listEl.innerHTML = '<div class="kl-empty"><i class="far fa-calendar-times mr-1"></i>Tidak ada libur nasional di bulan ini</div>';
        }
    }

    var cal = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'id',
        height: 'auto',
        fixedWeekCount: true,
        headerToolbar: { left:'prev', center:'title', right:'next' },
        events: dataLibur,
        datesSet: function(info) {
            document.querySelectorAll('.fc-daygrid-day').forEach(function(dayEl) {
                var ds = dayEl.getAttribute('data-date');
                if (ds) {
                    var h = dataLibur.find(function(item){ return item.start === ds; });
                    if (h) {
                        var isNasional = h.jenis.indexOf('nasional') !== -1;
                        dayEl.classList.add(isNasional ? 'holiday-nasional' : 'holiday-cuti-bersama');
                        dayEl.setAttribute('title', h.title + ' (' + (isNasional ? 'Libur Nasional' : 'Cuti Bersama') + ')');
                    }
                }
            });

            renderKeteranganBulan(cal);
        }
    });
    cal.render();
});
</script>
