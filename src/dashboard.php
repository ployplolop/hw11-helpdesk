<?php
require_once 'config/db.php';

// Handle POST actions (change status / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'change_status' && $id > 0) {
        $allowed = ['รอดำเนินการ', 'กำลังซ่อม', 'เสร็จสิ้น'];
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, $allowed, true)) {
            $stmt = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $newStatus, ':id' => $id]);
            header('Location: dashboard.php?alert=status_changed&sid=' . $id);
            exit;
        }
    }

    if ($_POST['action'] === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        header('Location: dashboard.php?alert=deleted&sid=' . $id);
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

// รับค่า filter สถานะจาก GET parameter
$allowedStatuses = ['รอดำเนินการ', 'กำลังซ่อม', 'เสร็จสิ้น'];
$filterStatus = $_GET['status'] ?? '';

// Query ข้อมูลตามเงื่อนไข filter + ORDER BY id DESC
if ($filterStatus !== '' && in_array($filterStatus, $allowedStatuses, true)) {
    $stmt = $pdo->prepare('SELECT * FROM tickets WHERE status = :status ORDER BY id DESC');
    $stmt->execute([':status' => $filterStatus]);
} else {
    $filterStatus = '';
    $stmt = $pdo->query('SELECT * FROM tickets ORDER BY id DESC');
}
$tickets = $stmt->fetchAll();

// นับสถิติจากทั้งหมด (ไม่ขึ้นกับ filter)
$stmtAll = $pdo->query('SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status');
$statusCounts = $stmtAll->fetchAll();
$pending = 0; $progress = 0; $done = 0; $total = 0;
foreach ($statusCounts as $row) {
    $total += $row['cnt'];
    if ($row['status'] === 'รอดำเนินการ') $pending = $row['cnt'];
    elseif ($row['status'] === 'กำลังซ่อม') $progress = $row['cnt'];
    elseif ($row['status'] === 'เสร็จสิ้น') $done = $row['cnt'];
}
$filteredCount = count($tickets);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IT SPUC Mini Helpdesk</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
            background: #eef2f7;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== TOP BAR ===== */
        .top-bar {
            background: linear-gradient(135deg, #0f2744 0%, #163d6e 50%, #1a5296 100%);
            padding: 28px 24px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .top-bar::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -10%;
            width: 120%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 50%, rgba(255,255,255,0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .top-bar h1 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            position: relative;
        }
        .top-bar .subtitle {
            color: rgba(255,255,255,0.55);
            font-size: 0.82rem;
            font-weight: 400;
            margin-top: 4px;
            position: relative;
        }

        /* ===== MAIN CONTENT ===== */
        .main {
            max-width: 1120px;
            width: 100%;
            margin: -20px auto 0;
            padding: 0 20px 40px;
            position: relative;
            z-index: 1;
        }

        /* ===== SUMMARY CARDS ===== */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(15,53,114,0.06);
            border: 1px solid rgba(255,255,255,0.8);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(15,53,114,0.12);
        }
        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .summary-info { flex: 1; min-width: 0; }
        .summary-info .s-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 2px;
        }
        .summary-info .s-num {
            font-size: 1.65rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .sc-all .summary-icon  { background: #e8eef5; }
        .sc-all .s-label { color: #64748b; }
        .sc-all .s-num   { color: #0f2744; }

        .sc-pend .summary-icon { background: #fef3c7; }
        .sc-pend .s-label { color: #92400e; }
        .sc-pend .s-num   { color: #b45309; }

        .sc-prog .summary-icon { background: #dbeafe; }
        .sc-prog .s-label { color: #1e40af; }
        .sc-prog .s-num   { color: #1d4ed8; }

        .sc-done .summary-icon { background: #dcfce7; }
        .sc-done .s-label { color: #166534; }
        .sc-done .s-num   { color: #16a34a; }

        /* ===== CONTENT CARD ===== */
        .content-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(15,53,114,0.07);
            overflow: hidden;
        }

        /* ===== FILTER BAR ===== */
        .filter-section {
            padding: 20px 28px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .filter-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-pills .f-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            margin-right: 4px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            border: 1.5px solid #e2e8f0;
            color: #64748b;
            background: #f8fafc;
            transition: all 0.2s;
            cursor: pointer;
        }
        .pill:hover {
            border-color: #93b4d4;
            color: #1a5296;
            background: #eff6ff;
        }
        .pill.active {
            background: linear-gradient(135deg, #1a5296, #2470c7);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(26,82,150,0.3);
        }
        .pill .count {
            background: rgba(0,0,0,0.08);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .pill.active .count {
            background: rgba(255,255,255,0.25);
        }
        .filter-result {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .filter-result strong {
            color: #475569;
        }

        /* ===== TABLE ===== */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 820px;
        }
        thead th {
            background: #f1f5f9;
            color: #475569;
            padding: 12px 20px;
            font-size: 0.72rem;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }
        col.col-id      { width: 5%; }
        col.col-name    { width: 12%; }
        col.col-device  { width: 11%; }
        col.col-issue   { width: 28%; }
        col.col-status  { width: 12%; }
        col.col-time    { width: 15%; }
        col.col-action  { width: 17%; }

        thead th.th-center { text-align: center; }

        tbody tr {
            transition: background 0.15s;
        }
        tbody tr:nth-child(even) { background: #fafcfe; }
        tbody tr:hover { background: #eef4fd; }
        tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.86rem;
            color: #334155;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }

        .td-id {
            text-align: center;
            font-weight: 700;
            color: #1a5296;
            font-size: 0.85rem;
        }
        .td-name {
            font-weight: 500;
            color: #1e293b;
        }
        .td-device {
            color: #475569;
        }
        .td-issue {
            color: #475569;
            line-height: 1.5;
        }
        .td-status { text-align: center; }
        .td-time {
            color: #94a3b8;
            font-size: 0.78rem;
            font-variant-numeric: tabular-nums;
        }
        .td-time .time-date {
            display: block;
            color: #64748b;
            font-weight: 600;
            font-size: 0.82rem;
        }
        .td-time .time-clock {
            display: block;
            color: #94a3b8;
            font-size: 0.74rem;
            margin-top: 1px;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-pending  { background: #fef9c3; color: #92400e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-done     { background: #dcfce7; color: #166534; }

        /* Badge dot */
        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .badge-pending .badge-dot  { background: #f59e0b; }
        .badge-progress .badge-dot { background: #3b82f6; }
        .badge-done .badge-dot     { background: #22c55e; }

        /* Action column */
        .td-action {
            text-align: center;
            white-space: nowrap;
        }
        .td-action .actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .btn-view {
            background: #1a5296;
            color: #fff;
        }
        .btn-view:hover {
            background: #163d6e;
            box-shadow: 0 2px 8px rgba(26,82,150,0.3);
        }
        .btn-edit {
            background: #16a34a;
            color: #fff;
        }
        .btn-edit:hover {
            background: #15803d;
            box-shadow: 0 2px 8px rgba(22,163,74,0.3);
        }
        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            background: #ef4444;
            color: #fff;
        }
        .btn-delete:hover {
            background: #dc2626;
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,39,68,0.45);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(15,53,114,0.18);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: modalIn 0.2s ease-out;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a3a5c;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 1.1rem;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .modal-close:hover { background: #e2e8f0; color: #334155; }
        .modal-body {
            padding: 24px;
        }
        .modal-body .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-body .detail-row:last-child { border-bottom: none; }
        .modal-body .detail-label {
            width: 100px;
            flex-shrink: 0;
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .modal-body .detail-value {
            flex: 1;
            font-size: 0.88rem;
            color: #1e293b;
            word-break: break-word;
        }
        .modal-body .edit-group {
            margin-bottom: 20px;
        }
        .modal-body .edit-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        .modal-body .edit-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.88rem;
            font-family: inherit;
            color: #334155;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .modal-body .edit-select:focus { border-color: #1a5296; outline: none; background: #fff; }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #edf2f7;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .modal-btn {
            padding: 9px 22px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }
        .modal-btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }
        .modal-btn-cancel:hover { background: #e2e8f0; }
        .modal-btn-save {
            background: #16a34a;
            color: #fff;
        }
        .modal-btn-save:hover { background: #15803d; box-shadow: 0 2px 8px rgba(22,163,74,0.3); }

        /* ===== DISABLED BUTTON ===== */
        .btn-action:disabled,
        .btn-delete:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state .empty-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.4;
        }
        .empty-state .empty-title {
            font-size: 1rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }
        .empty-state .empty-sub {
            font-size: 0.84rem;
            color: #94a3b8;
        }

        /* ===== BOTTOM SECTION ===== */
        .bottom-section {
            padding: 20px 28px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .link-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            color: #1a5296;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.86rem;
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            transition: all 0.2s;
        }
        .link-back:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }

        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.76rem;
        }
        .footer span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            padding: 6px 16px;
            border-radius: 20px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .summary-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { padding: 22px 16px 28px; }
            .top-bar h1 { font-size: 1.25rem; }
            .main { padding: 0 12px 30px; }
            .summary-row { gap: 10px; }
            .summary-card { padding: 14px 14px; gap: 12px; }
            .summary-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .summary-info .s-num { font-size: 1.35rem; }
            .filter-section { padding: 16px 18px; }
            .pill { padding: 6px 14px; font-size: 0.76rem; }

            .table-wrap { overflow-x: auto; }
            table { min-width: 0; }
            table, thead, tbody, th, td, tr { display: block; }
            colgroup { display: none; }
            thead { display: none; }
            tbody tr {
                background: #fff;
                border-bottom: 8px solid #f1f5f9;
                padding: 4px 0;
            }
            tbody tr:nth-child(even) { background: #fff; }
            tbody tr:hover { background: #fafcfe; }
            tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 20px;
                border-bottom: 1px solid #f8fafc;
                text-align: right;
            }
            tbody td:last-child { border-bottom: none; }
            tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #475569;
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                flex-shrink: 0;
                margin-right: 16px;
            }
            .td-id, .td-status { text-align: right; }
            .td-time .time-date,
            .td-time .time-clock { display: inline; }
            .td-time .time-clock { margin-left: 6px; }
            .bottom-section { padding: 16px 18px; }
        }
        @media (max-width: 480px) {
            .summary-row { grid-template-columns: 1fr 1fr; gap: 8px; }
            .summary-card { padding: 12px 10px; gap: 10px; }
            .summary-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 10px; }
            .summary-info .s-num { font-size: 1.2rem; }
            .summary-info .s-label { font-size: 0.65rem; }
            .filter-section { justify-content: center; }
            .filter-pills { justify-content: center; }
            .filter-result { width: 100%; text-align: center; }
            .link-back { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <div class="top-bar">
        <h1>📋 IT SPUC Mini Helpdesk</h1>
        <p class="subtitle">ระบบติดตามแจ้งซ่อม — Dashboard</p>
    </div>

    <!-- ===== MAIN ===== -->
    <div class="main">

        <!-- Summary Cards -->
        <div class="summary-row">
            <div class="summary-card sc-all">
                <div class="summary-icon">📊</div>
                <div class="summary-info">
                    <div class="s-label">เคสทั้งหมด</div>
                    <div class="s-num"><?= $total ?></div>
                </div>
            </div>
            <div class="summary-card sc-pend">
                <div class="summary-icon">⏳</div>
                <div class="summary-info">
                    <div class="s-label">รอดำเนินการ</div>
                    <div class="s-num"><?= $pending ?></div>
                </div>
            </div>
            <div class="summary-card sc-prog">
                <div class="summary-icon">🔧</div>
                <div class="summary-info">
                    <div class="s-label">กำลังซ่อม</div>
                    <div class="s-num"><?= $progress ?></div>
                </div>
            </div>
            <div class="summary-card sc-done">
                <div class="summary-icon">✅</div>
                <div class="summary-info">
                    <div class="s-label">เสร็จสิ้น</div>
                    <div class="s-num"><?= $done ?></div>
                </div>
            </div>
        </div>

        <!-- Content Card: Filter + Table -->
        <div class="content-card">

            <!-- Filter Bar -->
            <div class="filter-section">
                <div class="filter-pills">
                    <span class="f-label">กรองสถานะ:</span>
                    <a href="dashboard.php" class="pill <?= $filterStatus === '' ? 'active' : '' ?>">ทั้งหมด <span class="count"><?= $total ?></span></a>
                    <a href="dashboard.php?status=<?= urlencode('รอดำเนินการ') ?>" class="pill <?= $filterStatus === 'รอดำเนินการ' ? 'active' : '' ?>">รอดำเนินการ <span class="count"><?= $pending ?></span></a>
                    <a href="dashboard.php?status=<?= urlencode('กำลังซ่อม') ?>" class="pill <?= $filterStatus === 'กำลังซ่อม' ? 'active' : '' ?>">กำลังซ่อม <span class="count"><?= $progress ?></span></a>
                    <a href="dashboard.php?status=<?= urlencode('เสร็จสิ้น') ?>" class="pill <?= $filterStatus === 'เสร็จสิ้น' ? 'active' : '' ?>">เสร็จสิ้น <span class="count"><?= $done ?></span></a>
                </div>
                <div class="filter-result">แสดง <strong><?= $filteredCount ?></strong> จาก <strong><?= $total ?></strong> รายการ</div>
            </div>

            <?php if ($filteredCount > 0): ?>
            <!-- Table -->
            <div class="table-wrap">
                <table>
                    <colgroup>
                        <col class="col-id">
                        <col class="col-name">
                        <col class="col-device">
                        <col class="col-issue">
                        <col class="col-status">
                        <col class="col-time">
                        <col class="col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-center">รหัส</th>
                            <th>ผู้แจ้ง</th>
                            <th>อุปกรณ์</th>
                            <th>อาการ</th>
                            <th class="th-center">สถานะ</th>
                            <th>เวลาแจ้ง</th>
                            <th class="th-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($tickets as $t):
                            $status = $t['status'];
                            $badgeClass = 'badge-pending';
                            if ($status === 'กำลังซ่อม') $badgeClass = 'badge-progress';
                            elseif ($status === 'เสร็จสิ้น') $badgeClass = 'badge-done';

                            // แยกวันที่ + เวลา
                            $dt = $t['created_at'];
                            $datePart = substr($dt, 0, 10);
                            $timePart = substr($dt, 11, 8);
                        ?>
                        <tr>
                            <td class="td-id" data-label="รหัส"><?= $rowNum++ ?></td>
                            <td class="td-name" data-label="ผู้แจ้ง"><?= htmlspecialchars($t['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="td-device" data-label="อุปกรณ์"><?= htmlspecialchars($t['device_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="td-issue" data-label="อาการ"><?= htmlspecialchars($t['issue_detail'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="td-status" data-label="สถานะ">
                                <span class="badge <?= $badgeClass ?>"><span class="badge-dot"></span> <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="td-time" data-label="เวลาแจ้ง">
                                <span class="time-date"><?= htmlspecialchars($datePart, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="time-clock"><?= htmlspecialchars($timePart, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="td-action" data-label="จัดการ">
                                <div class="actions">
                                    <button type="button" class="btn-action btn-view" onclick="openViewModal(<?= (int)$t['id'] ?>, '<?= htmlspecialchars($t['student_name'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($t['device_name'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($t['issue_detail'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($t['created_at'], ENT_QUOTES, 'UTF-8') ?>')">👁</button>
                                    <button type="button" class="btn-action btn-edit" onclick="openEditModal(<?= (int)$t['id'] ?>, '<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>')" <?= $status === 'เสร็จสิ้น' ? 'disabled' : '' ?>>บันทึก</button>
                                    <form method="POST" style="display:inline;" id="deleteForm-<?= (int)$t['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                        <button type="button" class="btn-delete" <?= $status === 'เสร็จสิ้น' ? 'disabled' : '' ?> onclick="confirmDelete(<?= (int)$t['id'] ?>)">ลบ</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <div class="empty-title">ยังไม่มีรายการแจ้งซ่อม</div>
                <div class="empty-sub"><?= $filterStatus !== '' ? 'ไม่พบรายการในสถานะ "' . htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') . '"' : 'ยังไม่มีเคสในระบบ' ?></div>
            </div>
            <?php endif; ?>

            <!-- Bottom -->
            <div class="bottom-section">
                <a href="index.php" class="link-back">← กลับไปหน้าแจ้งซ่อม</a>
            </div>

        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <span>พัฒนาโดย: 67709956 นางสาววรณดี สละกลม — Collaborator / Backend</span>
    </div>

    <!-- ===== VIEW MODAL ===== -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h3>📄 รายละเอียดเคส #<span id="view-id"></span></h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <div class="detail-label">ผู้แจ้ง</div>
                    <div class="detail-value" id="view-name"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">อุปกรณ์</div>
                    <div class="detail-value" id="view-device"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">อาการ</div>
                    <div class="detail-value" id="view-issue"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">สถานะ</div>
                    <div class="detail-value" id="view-status"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">เวลาแจ้ง</div>
                    <div class="detail-value" id="view-time"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal('viewModal')">ปิด</button>
                <button type="button" class="modal-btn modal-btn-save" id="view-edit-btn" onclick="viewToEdit()">✎ แก้ไข</button>
            </div>
        </div>
    </div>

    <!-- ===== EDIT MODAL ===== -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <form method="POST" id="editForm">
                <div class="modal-header">
                    <h3>✎ แก้ไขสถานะเคส #<span id="edit-id-display"></span></h3>
                    <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="edit-group">
                        <label for="edit-status">เลือกสถานะใหม่</label>
                        <select name="new_status" id="edit-status" class="edit-select">
                            <option value="รอดำเนินการ">⏳ รอดำเนินการ</option>
                            <option value="กำลังซ่อม">🔧 กำลังซ่อม</option>
                            <option value="เสร็จสิ้น">✅ เสร็จสิ้น</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('editModal')">ยกเลิก</button>
                    <button type="submit" class="modal-btn modal-btn-save">✓ บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    var _viewId = 0, _viewStatus = '';

    function openViewModal(id, name, device, issue, status, time) {
        _viewId = id;
        _viewStatus = status;
        document.getElementById('view-id').textContent = id;
        document.getElementById('view-name').textContent = name;
        document.getElementById('view-device').textContent = device;
        document.getElementById('view-issue').textContent = issue;
        document.getElementById('view-time').textContent = time;

        var statusEl = document.getElementById('view-status');
        var badgeClass = 'badge-pending';
        if (status === 'กำลังซ่อม') badgeClass = 'badge-progress';
        else if (status === 'เสร็จสิ้น') badgeClass = 'badge-done';
        statusEl.innerHTML = '<span class="badge ' + badgeClass + '"><span class="badge-dot"></span> ' + status + '</span>';

        // Hide edit button if status is เสร็จสิ้น
        var editBtn = document.getElementById('view-edit-btn');
        if (status === 'เสร็จสิ้น') {
            editBtn.style.display = 'none';
        } else {
            editBtn.style.display = '';
        }

        document.getElementById('viewModal').classList.add('active');
    }

    function viewToEdit() {
        closeModal('viewModal');
        openEditModal(_viewId, _viewStatus);
    }

    function openEditModal(id, currentStatus) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-id-display').textContent = id;
        document.getElementById('edit-status').value = currentStatus;
        document.getElementById('editModal').classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
                m.classList.remove('active');
            });
        }
    });

    // Auto-remove alert toast after animation
    var toast = document.querySelector('.alert-toast');
    if (toast) {
        setTimeout(function() { toast.remove(); }, 4200);
        // Clean URL params
        if (window.history.replaceState) {
            window.history.replaceState(null, '', 'dashboard.php');
        }
    }

    // SweetAlert: delete confirmation
    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'ต้องการลบรายการ #' + id + ' ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ลบเลย',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('deleteForm-' + id).submit();
            }
        });
    }

    // SweetAlert: show alerts from URL params
    <?php
    $alert = $_GET['alert'] ?? '';
    $sid = (int)($_GET['sid'] ?? 0);
    if ($alert === 'status_changed'): ?>
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ!',
        text: 'เปลี่ยนสถานะเคส #<?= $sid ?> สำเร็จแล้ว',
        timer: 2500,
        showConfirmButton: false
    });
    <?php elseif ($alert === 'deleted'): ?>
    Swal.fire({
        icon: 'success',
        title: 'ลบแล้ว!',
        text: 'ลบเคส #<?= $sid ?> เรียบร้อยแล้ว',
        timer: 2500,
        showConfirmButton: false
    });
    <?php endif; ?>

    // Clean URL params
    if (window.history.replaceState) {
        window.history.replaceState(null, '', 'dashboard.php');
    }
    </script>

</body>
</html>
