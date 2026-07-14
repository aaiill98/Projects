<?php
// list-view.php — واجهة موظف القسم (role_id=3) مع منع التوجيه المتكرر
session_start();
require_once __DIR__ . '/connection.php';

/* تحقق الجلسة والدور والقسم */
$roleId = $_SESSION['user_role_id'] ?? $_SESSION['role_id'] ?? null;
$deptId = $_SESSION['training_department_id'] ?? null;

if (!$roleId || (int) $roleId !== 3) {
    header('Location: login.php');
    exit;
}
if (!$deptId) {
    // موظف قسم بلا قسم مرتبط — لا توجد بيانات
    $deptId = 0;
}

/* ====================== AJAX: قبول/رفض ====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $ids = $_POST['application_ids'] ?? [];
    if (!is_array($ids))
        $ids = [$ids];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'لم يتم تحديد طلبات.']);
        exit;
    }

    // التحقق أن الطلب فعلاً وارد لقسم هذا الموظف وحالته in_dept
    $inboxCheck = $conn->prepare("
        SELECT id, stage, routed_pref
        FROM applications
        WHERE id=? AND status='in_dept' AND routed_pref IN (1,2)
          AND (
               (routed_pref=1 AND pref1_training_dept_id=?)
            OR (routed_pref=2 AND pref2_training_dept_id=?)
          )
        LIMIT 1
    ");

    if ($_POST['action'] === 'accept') {
        // قبول: accepted/accepted
        $upd = $conn->prepare("
            UPDATE applications
            SET stage='accepted', status='accepted', updated_at=NOW()
            WHERE id=? AND status='in_dept'
              AND (
                   (routed_pref=1 AND pref1_training_dept_id=?)
                OR (routed_pref=2 AND pref2_training_dept_id=?)
              )
        ");

        $ok = 0;
        $skip = 0;
        foreach ($ids as $id) {
            $inboxCheck->bind_param('iii', $id, $deptId, $deptId);
            $inboxCheck->execute();
            $rs = $inboxCheck->get_result();
            if (!$rs || !$rs->num_rows) {
                $skip++;
                continue;
            }

            $upd->bind_param('iii', $id, $deptId, $deptId);
            if ($upd->execute() && $upd->affected_rows > 0)
                $ok++;
            else
                $skip++;
        }
        $inboxCheck->close();
        $upd->close();
        echo json_encode(['success' => $ok > 0, 'message' => "تم قبول {$ok} من " . count($ids) . " طلب"]);
        exit;
    }

    if ($_POST['action'] === 'reject') {
        // رفض مع تسجيل حالة الرفض:
        // - لو stage='first' → يرجع للسكرتير: pending + routed_pref=NULL + rejected_pref1=TRUE
        // - لو stage='second' → يُلغى: canceled/canceled + rejected_pref2=TRUE
        $rejFirst = $conn->prepare("
            UPDATE applications
            SET status='pending', routed_pref=NULL, rejected_pref1=TRUE, updated_at=NOW()
            WHERE id=? AND status='in_dept' AND stage='first'
              AND routed_pref=1 AND pref1_training_dept_id=?
        ");
        $rejSecond = $conn->prepare("
            UPDATE applications
            SET stage='canceled', status='canceled', rejected_pref2=TRUE, updated_at=NOW()
            WHERE id=? AND status='in_dept' AND stage='second'
              AND routed_pref=2 AND pref2_training_dept_id=?
        ");

        $ok = 0;
        $skip = 0;
        foreach ($ids as $id) {
            // تأكد أنه وارد لهذا القسم
            $inboxCheck->bind_param('iii', $id, $deptId, $deptId);
            $inboxCheck->execute();
            $rs = $inboxCheck->get_result();
            if (!$rs || !$rs->num_rows) {
                $skip++;
                continue;
            }
            $row = $rs->fetch_assoc();
            $stage = $row['stage'];
            $pref = (int) $row['routed_pref'];

            if ($stage === 'first' && $pref === 1) {
                $rejFirst->bind_param('ii', $id, $deptId);
                if ($rejFirst->execute() && $rejFirst->affected_rows > 0) {
                    $ok++;
                } else {
                    $skip++;
                }
            } elseif ($stage === 'second' && $pref === 2) {
                $rejSecond->bind_param('ii', $id, $deptId);
                if ($rejSecond->execute() && $rejSecond->affected_rows > 0) {
                    $ok++;
                } else {
                    $skip++;
                }
            } else {
                $skip++;
            }
        }
        $inboxCheck->close();
        $rejFirst->close();
        $rejSecond->close();
        echo json_encode(['success' => $ok > 0, 'message' => "تم رفض/إرجاع {$ok} من " . count($ids) . " طلب"]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
    exit;
}

/* ====================== قراءة وارد القسم ====================== */
$apps = [];
if ($deptId) {
    $stmt = $conn->prepare("
        SELECT a.id,
               a.full_name, a.email, a.phone,
               a.training_duration, a.start_date, a.submitted_at,
               a.stage, a.routed_pref,
               t1.name_ar AS pref1_name, t2.name_ar AS pref2_name
        FROM applications a
        LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
        LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
        WHERE a.status='in_dept' AND a.routed_pref IN (1,2)
          AND (
               (a.routed_pref=1 AND a.pref1_training_dept_id=?)
            OR (a.routed_pref=2 AND a.pref2_training_dept_id=?)
          )
        ORDER BY a.submitted_at ASC, a.id ASC
    ");
    $stmt->bind_param('ii', $deptId, $deptId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc())
        $apps[] = $row;
    $stmt->close();
}

// اسم القسم للعرض
$deptName = '';
if ($deptId) {
    $d = $conn->prepare("SELECT name_ar FROM training_departments WHERE id=?");
    $d->bind_param('i', $deptId);
    $d->execute();
    $r = $d->get_result()->fetch_assoc();
    $deptName = $r['name_ar'] ?? '';
    $d->close();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>واجهة الإدارات التنفيذية</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        /* نفس الستايلات عندك بدون تعديل بصري */
        :root {
            --gov-navy: #1f2a59;
            --gov-royal: #2e62b1;
            --gov-accent: #c7a64b;
            --gov-muted: #eef2f7;
            --gov-text: #243042
        }

        * {
            box-sizing: border-box
        }

        body {
            font-family: 'Cairo', 'Tajawal', 'Segoe UI', 'Arial', 'Tahoma', sans-serif;
            background-color: var(--gov-navy);
            background-image: url('IMG_17068-2.png'), url('IMG_17068L.png');
            background-size: 320px auto, 300px auto;
            background-position: left bottom, right bottom;
            background-repeat: no-repeat;
            background-attachment: fixed;
            margin: 0;
            color: var(--gov-text)
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(10, 16, 32, 0.55);
            z-index: -1
        }

        .header-section {
            position: relative;
            background: radial-gradient(1200px 400px at 50% -100px, var(--gov-royal), transparent), linear-gradient(180deg, #203266, #1a244a 70%);
            border-bottom: 4px solid #fff
        }

        .header-section .main-logo {
            height: 130px;
            width: auto;
            filter: drop-shadow(0 8px 20px rgba(0, 0, 0, .35)) brightness(1.05)
        }

        .main-title {
            color: #fff;
            margin-top: 1rem;
            font-weight: 800;
            letter-spacing: .3px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, .35)
        }

        .subtitle {
            color: #e7ecf6;
            margin: 0;
            opacity: .95
        }

        .main-content {
            padding: 2rem 0 1rem
        }

        .control-panel {
            background: linear-gradient(180deg, #fff, #f9fbff);
            border: 1px solid #e4e9f2;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .12)
        }

        .form-label {
            color: #0f275a
        }

        .gov-btn {
            background-color: #f7f9fc;
            color: #1b2430;
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 24px;
            padding: 8px 18px;
            font-weight: 600;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            text-decoration: none;
            transition: all .2s ease
        }

        .gov-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18)
        }

        .gov-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(27, 36, 48, .12)
        }

        .gov-btn-success {
            background: linear-gradient(135deg, #117a43, #1fa25c);
            color: #fff;
            box-shadow: 0 6px 18px rgba(28, 160, 84, .4)
        }

        .gov-btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
            box-shadow: 0 6px 18px rgba(220, 53, 69, .4)
        }

        .gov-btn-light {
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6
        }

        .gov-btn i {
            font-size: .9em
        }

        .form-select,
        .gov-select {
            border: 1.5px solid #cfd7e6;
            border-radius: .8rem;
            padding: .9rem 1rem;
            background: #fff;
            width: 100%
        }

        .form-select:focus,
        .gov-select:focus {
            border-color: var(--gov-royal);
            box-shadow: 0 0 0 .25rem rgba(46, 98, 177, .15)
        }

        .filter-card {
            border: 1px solid #e4e9f2;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08)
        }

        .table-container {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .12)
        }

        .table-responsive {
            border: 1px solid #e4e9f2;
            border-radius: 1rem;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
            overflow: hidden;
            background: #fff
        }

        .table {
            margin: 0;
            width: 100%
        }

        .table-header {
            background: linear-gradient(90deg, #1f2a59, #2e62b1);
            color: #fff
        }

        .table-header th {
            padding: 1.1rem .75rem;
            font-weight: 800;
            font-size: .95rem;
            text-align: center;
            border: none;
            white-space: nowrap
        }

        .table tbody tr {
            background: #fff
        }

        .table tbody tr:nth-child(odd) {
            background: #f8fbff
        }

        .table tbody tr:hover {
            background: #eef5ff
        }

        .table td {
            text-align: center;
            padding: .9rem .75rem;
            color: var(--gov-text);
            vertical-align: middle;
            border-color: #eef2f7
        }

        .table-active {
            background: #e9f7ef !important
        }

        .form-check-input {
            width: 1.15rem;
            height: 1.15rem;
            cursor: pointer;
            border: 2px solid #a8b3ca;
            border-radius: .4rem
        }

        .form-check-input:checked {
            background-color: #1f2a59;
            border-color: #1f2a59;
            box-shadow: 0 0 0 .2rem rgba(31, 42, 89, .2)
        }

        .footer-section {
            background: linear-gradient(180deg, #1a244a, #0f1829);
            color: #e7ecf6;
            border-top: 3px solid var(--gov-accent)
        }

        .footer-section a {
            color: #c7a64b;
            text-decoration: none
        }

        .footer-section a:hover {
            text-decoration: underline
        }

        .small-btn {
            background-color: #fff !important;
            color: #1b2430 !important;
            border: 1px solid rgba(0, 0, 0, 0.12) !important;
            border-radius: 16px !important;
            padding: 1px 6px !important;
            font-size: .55rem !important;
            font-weight: 600 !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important
        }

        .small-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15) !important
        }

        .fade-in {
            animation: fadeIn .5s ease-in
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @media print {
            body {
                background: #fff !important
            }

            body::before,
            .header-section,
            .control-panel {
                display: none !important
            }

            .table-header {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact
            }
        }

        @media (max-width:768px) {
            .main-title {
                font-size: 1.5rem
            }

            .control-panel {
                margin: 1rem
            }

            .gov-btn {
                font-size: .85rem;
                padding: 6px 14px
            }

            .table-header th {
                font-size: .8rem;
                padding: .8rem .5rem
            }

            .table td {
                font-size: .85rem;
                padding: .7rem .5rem
            }
        }
    </style>
</head>

<body>
    <header class="header-section">
        <div class="container-fluid text-center py-4">
            <img alt="شعار إدارة التدريب والتعليم" class="main-logo" src="images/thc.png" />
            <h1 class="main-title">نظام إدارة المتقدمين والتوجيه الأكاديمي</h1>
            <p class="subtitle">وارد قسم: <?= htmlspecialchars($deptName ?: '—') ?></p>
            <a href="index.php?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
        </div>
    </header>

    <main class="main-content">
        <div class="container-fluid">
            <div class="control-panel bg-white p-4 mb-4 rounded shadow-sm">
                <div class="col-md-12 d-flex flex-wrap gap-2 mt-2">
                    <button class="gov-btn gov-btn-success" id="acceptBtn" type="button">قبول المحدد</button>
                    <button class="gov-btn gov-btn-danger" id="rejectBtn" type="button">رفض المحدد</button>
                    <button class="gov-btn gov-btn-light" id="printBtn" type="button">
                        <i class="fa-solid fa-print ms-2"></i> طباعة
                    </button>
                    <button class="gov-btn gov-btn-light" data-table="#executivesTable" id="exportBtn" type="button">
                        <i class="fa-solid fa-file-export ms-2"></i> تصدير
                    </button>
                </div>
            </div>

            <!-- جدول وارد القسم -->
            <div class="table-container bg-white">
                <div class="table-responsive">
                    <table class="table table-hover table-striped fade-in" id="executivesTable">
                        <thead class="table-header">
                            <tr>
                                <th><input class="form-check-input" id="selectAll" type="checkbox" /></th>
                                <th>اسم المتقدم</th>
                                <th>تخصص المتقدم</th>
                                <th>معدل المتقدم</th>
                                <th>رغبة المتقدم الأولى</th>
                                <th>رغبة المتقدم الثانية</th>
                                <th>تاريخ نهاية التدريب</th>
                                <th>تاريخ بداية التدريب</th>
                                <th>PDF</th>
                                <th>عن المتقدم</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($apps)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">لا توجد طلبات موجهة لهذا القسم.
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($apps as $r): ?>
                                    <tr>
                                        <td><input class="form-check-input row-check" type="checkbox"
                                                value="<?= (int) $r['id'] ?>" /></td>
                                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td><?= htmlspecialchars($r['pref1_name']) ?></td>
                                        <td><?= htmlspecialchars($r['pref2_name']) ?></td>
                                        <td>-</td>
                                        <td><?= htmlspecialchars($r['start_date']) ?></td>
                                        <td>-</td>
                                        <td>
                                            <button class="btn btn-sm small-btn" title="<?= htmlspecialchars($r['email']) ?>">
                                                <i class="fa-regular fa-envelope"></i>
                                            </button>
                                            <button class="btn btn-sm small-btn" title="<?= htmlspecialchars($r['phone']) ?>">
                                                <i class="fa-solid fa-phone"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <span class="text-muted small">
                                                <?= ($r['routed_pref'] == 1 ? 'مرحلة أولى' : 'مرحلة ثانية') ?> —
                                                <?= htmlspecialchars($r['submitted_at']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer-section py-3">
        <div class="container text-center">
            <div class="mb-2">تجمع الطائف الصحي – شركة الصحة القابضة</div>
            <div><a href="#"></a><a href="#"></a></div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        $(function () {
            // تحديد الكل
            $('#selectAll').on('change', function () { $('.row-check').prop('checked', this.checked); });

            function selectedIds() {
                return $('.row-check:checked').toArray().map(cb => cb.value);
            }

            async function postAction(action) {
                const ids = selectedIds();
                if (ids.length === 0) { alert('يرجى تحديد عنصر واحد على الأقل'); return; }
                if (action === 'reject' && !confirm('تأكيد رفض الطلبات المحددة؟')) return;
                const fd = new FormData();
                fd.append('action', action);
                ids.forEach(id => fd.append('application_ids[]', id));
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({ success: false, message: 'خطأ غير متوقع' }));
                alert(data.message || (data.success ? 'تم التنفيذ' : 'فشل التنفيذ'));
                if (data.success) location.reload();
            }

            $('#acceptBtn').on('click', () => postAction('accept'));
            $('#rejectBtn').on('click', () => postAction('reject'));

            // طباعة
            $('#printBtn').on('click', e => { e.preventDefault(); window.print(); });

            // تصدير
            function tableToWorkbook(tableId) {
                var table = document.getElementById(tableId);
                if (!table) return null;
                var wb = XLSX.utils.book_new();
                var ws = XLSX.utils.table_to_sheet(table);
                XLSX.utils.book_append_sheet(wb, ws, 'الوارد');
                return wb;
            }
            function downloadCSV(tableId, filename) {
                const rows = Array.from(document.querySelectorAll('#' + tableId + ' tr')).map(tr =>
                    Array.from(tr.querySelectorAll('th,td')).map(td => '"' + (td.innerText || '').replace(/"/g, '""') + '"').join(',')
                );
                const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = filename || 'table.csv';
                document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
            }
            $('#exportBtn').on('click', function (e) {
                e.preventDefault();
                const wb = window.XLSX ? tableToWorkbook('executivesTable') : null;
                if (wb) { XLSX.writeFile(wb, 'inbox.xlsx'); } else { downloadCSV('executivesTable', 'inbox.csv'); }
            });
        });
    </script>
</body>

</html>