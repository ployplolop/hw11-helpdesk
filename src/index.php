<?php
require_once 'config/db.php';

$success = isset($_GET['success']) && $_GET['success'] == '1';
$error = '';

$student_name = '';
$device_name  = '';
$issue_detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $device_name  = trim($_POST['device_name'] ?? '');
    $issue_detail = trim($_POST['issue_detail'] ?? '');

    if ($student_name === '' || $device_name === '' || $issue_detail === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO tickets (student_name, device_name, issue_detail)
            VALUES (:student_name, :device_name, :issue_detail)
        ');
        $stmt->execute([
            ':student_name' => $student_name,
            ':device_name'  => $device_name,
            ':issue_detail' => $issue_detail,
        ]);

        header('Location: index.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งซ่อม - IT SPUC Mini Helpdesk</title>
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

        /* ===== MAIN ===== */
        .main {
            max-width: 600px;
            width: 100%;
            margin: -20px auto 0;
            padding: 0 20px 40px;
            position: relative;
            z-index: 1;
        }

        /* ===== FORM CARD ===== */
        .form-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 24px rgba(15,53,114,0.07);
            overflow: hidden;
        }
        .form-header {
            padding: 24px 28px 0;
        }
        .form-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a3a5c;
            margin-bottom: 4px;
        }
        .form-header p {
            font-size: 0.82rem;
            color: #94a3b8;
        }
        .form-body {
            padding: 24px 28px 28px;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            color: #334155;
            background: #f8fafc;
            transition: all 0.15s;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #1a5296;
            outline: none;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26,82,150,0.12);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* ===== SUBMIT BUTTON ===== */
        .btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: linear-gradient(135deg, #1a5296, #2470c7);
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(26,82,150,0.25);
        }
        .btn-submit:hover {
            box-shadow: 0 6px 20px rgba(26,82,150,0.35);
            transform: translateY(-1px);
        }

        /* ===== BOTTOM LINK ===== */
        .bottom-section {
            padding: 20px 28px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .link-dashboard {
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
        .link-dashboard:hover {
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

        /* Large desktop */
        @media (min-width: 1200px) {
            .main { max-width: 680px; }
            .top-bar { padding: 36px 24px 40px; }
            .top-bar h1 { font-size: 1.75rem; }
            .top-bar .subtitle { font-size: 0.9rem; margin-top: 6px; }
            .form-header h2 { font-size: 1.2rem; }
            .form-header p { font-size: 0.88rem; }
            .form-body { padding: 30px 36px 36px; }
            .form-header { padding: 30px 36px 0; }
            .form-group label { font-size: 0.84rem; }
            .form-group input[type="text"],
            .form-group textarea { padding: 14px 18px; font-size: 0.95rem; }
            .btn-submit { padding: 16px 28px; font-size: 1.02rem; }
            .bottom-section { padding: 24px 36px; }
            .link-dashboard { padding: 12px 28px; font-size: 0.9rem; }
            .footer { font-size: 0.8rem; }
        }

        /* Tablet */
        @media (max-width: 768px) {
            .top-bar { padding: 22px 16px 28px; }
            .top-bar h1 { font-size: 1.3rem; }
            .top-bar .subtitle { font-size: 0.78rem; }
            .main { padding: 0 12px 30px; }
            .form-body { padding: 20px 18px 24px; }
            .form-header { padding: 20px 18px 0; }
            .bottom-section { padding: 16px 18px; }
            .btn-submit { font-size: 0.9rem; }
        }

        /* Small phone */
        @media (max-width: 480px) {
            .top-bar { padding: 18px 12px 24px; }
            .top-bar h1 { font-size: 1.15rem; }
            .top-bar .subtitle { font-size: 0.72rem; }
            .main { padding: 0 8px 24px; }
            .form-card { border-radius: 12px; }
            .form-header { padding: 16px 14px 0; }
            .form-header h2 { font-size: 1rem; }
            .form-body { padding: 16px 14px 20px; }
            .form-group { margin-bottom: 16px; }
            .form-group input[type="text"],
            .form-group textarea { padding: 10px 12px; font-size: 0.85rem; }
            .btn-submit { padding: 12px 20px; font-size: 0.88rem; border-radius: 10px; }
            .bottom-section { padding: 14px; }
            .link-dashboard { width: 100%; justify-content: center; font-size: 0.82rem; }
            .footer span { font-size: 0.68rem; padding: 5px 12px; }
        }
    </style>
</head>
<body>

    <!-- ===== HEADER ===== -->
    <div class="top-bar">
        <h1>🛠️ IT SPUC Mini Helpdesk</h1>
        <p class="subtitle">ระบบสำหรับการบันทึกและติดตามการแจ้งซ่อมอุปกรณ์ IT</p>
    </div>

    <!-- ===== MAIN ===== -->
    <div class="main">
        <div class="form-card">

            <div class="form-header">
                <h2>📝 แจ้งซ่อมอุปกรณ์</h2>
                <p>กรอกรายละเอียดเพื่อแจ้งปัญหาอุปกรณ์ IT</p>
            </div>

            <div class="form-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="student_name">ชื่อผู้แจ้ง</label>
                        <input type="text" id="student_name" name="student_name" maxlength="100"
                               value="<?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="เช่น สมชาย ใจดี" required>
                    </div>
                    <div class="form-group">
                        <label for="device_name">ชื่ออุปกรณ์</label>
                        <input type="text" id="device_name" name="device_name" maxlength="100"
                               value="<?= htmlspecialchars($device_name, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="เช่น เครื่องพิมพ์ HP LaserJet" required>
                    </div>
                    <div class="form-group">
                        <label for="issue_detail">อาการ / รายละเอียดปัญหา</label>
                        <textarea id="issue_detail" name="issue_detail" maxlength="500"
                                  placeholder="อธิบายอาการที่พบ..." required><?= htmlspecialchars($issue_detail, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">📩 ส่งเรื่องแจ้งซ่อม</button>
                </form>
            </div>

            <div class="bottom-section">
                <a href="dashboard.php" class="link-dashboard">📋 ดูรายการแจ้งซ่อมทั้งหมด →</a>
            </div>

        </div>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <span>พัฒนาโดย: 67702499 นางสาวกชกร ภิรมย์แก้ว / Project Owner / Frontend</span>
    </div>

    <script>
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ!',
        text: 'ส่งเรื่องแจ้งซ่อมเรียบร้อยแล้ว!',
        timer: 2500,
        showConfirmButton: false
    });
    <?php endif; ?>
    <?php if ($error): ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: '<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>'
    });
    <?php endif; ?>

    // Clean URL params
    if (window.history.replaceState) {
        window.history.replaceState(null, '', 'index.php');
    }
    </script>

</body>
</html>
