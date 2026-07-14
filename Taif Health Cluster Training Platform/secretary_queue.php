<?php
// secretary_queue.php — واجهة السكرتير مع منع التوجيه المتكرر للأقسام المرفوضة
session_start();
require_once __DIR__ . '/connection.php';

/* تحقق الدور: سكرتير = role_id 2 */
$roleId = $_SESSION['user_role_id'] ?? $_SESSION['role_id'] ?? null;
if (!$roleId || (int) $roleId !== 2) {
    header('Location: login.php');
    exit;
}

/* ========== معالجات AJAX (POST لنفس الملف) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // IDs المختارة
    $ids = $_POST['application_ids'] ?? [];
    if (!is_array($ids))
        $ids = [$ids];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'لم يتم تحديد طلبات.']);
        exit;
    }

    // التحقق أن الطلب في انتظار السكرتير: pending + routed_pref IS NULL
    $checkStmt = $conn->prepare("SELECT id, pref1_training_dept_id, pref2_training_dept_id, 
                                       rejected_pref1, rejected_pref2
                               FROM applications
                               WHERE id=? AND status='pending' AND routed_pref IS NULL
                               LIMIT 1");

    // توجيه حسب القسم المختار
    if ($_POST['action'] === 'route_department') {
        $deptId = (int) ($_POST['department_id'] ?? 0);
        if ($deptId <= 0) {
            echo json_encode(['success' => false, 'message' => 'يرجى اختيار قسم للتوجيه.']);
            exit;
        }

        // جلب اسم القسم للرسائل
        $deptStmt = $conn->prepare("SELECT name_ar FROM training_departments WHERE id=?");
        $deptStmt->bind_param('i', $deptId);
        $deptStmt->execute();
        $deptResult = $deptStmt->get_result();
        $deptName = $deptResult->num_rows ? $deptResult->fetch_assoc()['name_ar'] : 'غير معروف';
        $deptStmt->close();

        $upd = $conn->prepare("UPDATE applications
                           SET routed_pref=?, stage=IF(?=1,'first','second'),
                               status='in_dept', updated_at=NOW()
                           WHERE id=? AND status='pending' AND routed_pref IS NULL");

        $ok = 0;
        $skip = 0;
        $notMatch = 0;
        $rejectedCount = 0;
        $rejectedDetails = [];
        
        foreach ($ids as $id) {
            $checkStmt->bind_param('i', $id);
            $checkStmt->execute();
            $rs = $checkStmt->get_result();
            if (!$rs || !$rs->num_rows) {
                $skip++;
                continue;
            }
            $row = $rs->fetch_assoc();
            
            $pref = 0;
            $isRejected = false;
            
            // تحديد إذا كان القسم المختار هو الرغبة الأولى أم الثانية
            if ((int) $row['pref1_training_dept_id'] === $deptId) {
                $pref = 1;
                $isRejected = (bool) $row['rejected_pref1'];
            } elseif ((int) $row['pref2_training_dept_id'] === $deptId) {
                $pref = 2;
                $isRejected = (bool) $row['rejected_pref2'];
            }
            
            if ($pref === 0) {
                $notMatch++;
                continue;
            }
            
            // فحص إذا كان القسم مرفوض مسبقاً
            if ($isRejected) {
                $rejectedCount++;
                $rejectedDetails[] = "الطلب رقم {$id} مرفوض مسبقاً من قسم {$deptName}";
                continue;
            }

            $upd->bind_param('iii', $pref, $pref, $id);
            if ($upd->execute() && $upd->affected_rows > 0)
                $ok++;
            else
                $skip++;
        }
        $checkStmt->close();
        $upd->close();

        // بناء رسالة النتيجة
        $msg = "تم توجيه {$ok} من " . count($ids) . " طلب";
        if ($notMatch > 0)
            $msg .= " (تم تخطي {$notMatch} لاختلاف القسم عن رغباتهم)";
        if ($rejectedCount > 0)
            $msg .= " (تم تخطي {$rejectedCount} مرفوض مسبقاً من هذا القسم)";
            
        $result = [
            'success' => $ok > 0, 
            'message' => $msg, 
            'routed' => $ok, 
            'skipped' => $skip, 
            'not_matched' => $notMatch,
            'rejected_count' => $rejectedCount
        ];
        
        if ($rejectedCount > 0 && count($rejectedDetails) <= 5) {
            $result['rejected_details'] = $rejectedDetails;
        }
        
        echo json_encode($result);
        exit;
    }

    // إلغاء (طالما الطلب غير مقبول)
    if ($_POST['action'] === 'cancel_applications') {
        $upd = $conn->prepare("UPDATE applications
                           SET stage='canceled', status='canceled', updated_at=NOW()
                           WHERE id=? AND status <> 'accepted'");
        $ok = 0;
        $skip = 0;
        foreach ($ids as $id) {
            // السماح بالإلغاء فقط لو الطلب مازال في انتظار السكرتير
            $checkStmt->bind_param('i', $id);
            $checkStmt->execute();
            $rs = $checkStmt->get_result();
            if (!$rs || !$rs->num_rows) {
                $skip++;
                continue;
            }

            $upd->bind_param('i', $id);
            if ($upd->execute() && $upd->affected_rows > 0)
                $ok++;
            else
                $skip++;
        }
        $checkStmt->close();
        $upd->close();

        echo json_encode(['success' => $ok > 0, 'message' => "تم إلغاء {$ok} من " . count($ids) . " طلب", 'canceled' => $ok, 'skipped' => $skip]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف.']);
    exit;
}

/* ========== بيانات القوائم ========== */
$departments = [];
if ($r = $conn->query("SELECT id, name_ar FROM training_departments ORDER BY name_ar")) {
    while ($row = $r->fetch_assoc())
        $departments[] = $row;
}

/* ========== فلترة الرغبات (GET) ========== */
$pref1_filter = $_GET['pref1_filter'] ?? 'all';
$pref2_filter = $_GET['pref2_filter'] ?? 'all';

$conds = ["a.status='pending'", "a.routed_pref IS NULL"];
$types = '';
$params = [];
if ($pref1_filter !== 'all') {
    $conds[] = "a.pref1_training_dept_id=?";
    $types .= 'i';
    $params[] = (int) $pref1_filter;
}
if ($pref2_filter !== 'all') {
    $conds[] = "a.pref2_training_dept_id=?";
    $types .= 'i';
    $params[] = (int) $pref2_filter;
}

// تحديث الاستعلام لإضافة حقول الرفض ومعلومات الخيارات المتاحة
$sql = "SELECT a.id AS application_id,
               a.full_name, a.email, a.phone,
               a.training_duration, a.start_date, a.submitted_at,
               a.pref1_training_dept_id, t1.name_ar AS pref1_name,
               a.pref2_training_dept_id, t2.name_ar AS pref2_name,
               a.rejected_pref1, a.rejected_pref2,
               -- مؤشر للخيارات المتاحة للتوجيه
               CASE 
                   WHEN NOT a.rejected_pref1 AND NOT a.rejected_pref2 THEN 'both_available'
                   WHEN NOT a.rejected_pref1 AND a.rejected_pref2 THEN 'pref1_only'
                   WHEN a.rejected_pref1 AND NOT a.rejected_pref2 THEN 'pref2_only'
                   ELSE 'none_available'
               END as routing_options,
               -- نص يوضح الخيارات المتاحة
               CASE 
                   WHEN NOT a.rejected_pref1 AND NOT a.rejected_pref2 THEN 'يمكن التوجيه لأي من الرغبتين'
                   WHEN NOT a.rejected_pref1 AND a.rejected_pref2 THEN CONCAT('يمكن التوجيه للرغبة الأولى فقط: ', t1.name_ar)
                   WHEN a.rejected_pref1 AND NOT a.rejected_pref2 THEN CONCAT('يمكن التوجيه للرغبة الثانية فقط: ', t2.name_ar)
                   ELSE 'لا توجد خيارات متاحة - يجب الإلغاء'
               END as available_options_text,
               -- حالة الرفض للعرض
               CASE 
                   WHEN a.rejected_pref1 AND a.rejected_pref2 THEN CONCAT('مرفوض من كلا القسمين: ', t1.name_ar, ' و ', t2.name_ar)
                   WHEN a.rejected_pref1 THEN CONCAT('مرفوض من الرغبة الأولى: ', t1.name_ar)
                   WHEN a.rejected_pref2 THEN CONCAT('مرفوض من الرغبة الثانية: ', t2.name_ar)
                   ELSE NULL
               END as rejection_info
        FROM applications a
        LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
        LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
        WHERE " . implode(' AND ', $conds) . "
        ORDER BY 
            -- ترتيب الطلبات: المتاحة أولاً، ثم المحدودة، ثم المستحيلة
            CASE 
                WHEN NOT a.rejected_pref1 AND NOT a.rejected_pref2 THEN 1
                WHEN (NOT a.rejected_pref1 AND a.rejected_pref2) OR (a.rejected_pref1 AND NOT a.rejected_pref2) THEN 2
                ELSE 3
            END,
            a.submitted_at ASC, a.id ASC";
            
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$applications = [];
while ($row = $res->fetch_assoc())
    $applications[] = $row;
$stmt->close();

// إحصاءات للعرض
$stats = [
    'total' => count($applications),
    'both_available' => 0,
    'limited_options' => 0,
    'no_options' => 0
];

foreach ($applications as $app) {
    switch ($app['routing_options']) {
        case 'both_available':
            $stats['both_available']++;
            break;
        case 'pref1_only':
        case 'pref2_only':
            $stats['limited_options']++;
            break;
        case 'none_available':
            $stats['no_options']++;
            break;
    }
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
        /* (نفس CSS الأصلي بلا تغيير بصري) */
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

        .gov-btn-primary {
            background: linear-gradient(135deg, #2e62b1, #1b3e7a);
            color: #fff;
            box-shadow: 0 6px 18px rgba(46, 98, 177, .4)
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

        .select2-container {
            width: 100% !important
        }

        .select2-container--default .select2-selection--single {
            border: 1.5px solid #cfd7e6;
            border-radius: .8rem;
            height: 46px;
            display: flex;
            align-items: center
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 44px;
            padding-right: 12px
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px
        }

        .select2-results__option {
            direction: rtl
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
            <p class="subtitle">واجهة الإدارات التنفيذية</p>
            <a href="index.php?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
        </div>
    </header>

    <main class="main-content">
        <div class="container-fluid">
            <div class="control-panel bg-white p-4 mb-4 rounded shadow-sm">
                <div class="col-md-12 d-flex flex-wrap gap-2 mt-2">
                    <button class="gov-btn gov-btn-success" type="button"
                        onclick="location.href='accepted.php'">المتقدمين المقبولين</button>
                    <button class="gov-btn gov-btn-danger" id="deleteBtn" type="button">حذف العنصر المحدد</button>
                    <button class="gov-btn gov-btn-light" id="printBtn" type="button">
                        <i class="fa-solid fa-print ms-2"></i> طباعة
                    </button>
                    <button class="gov-btn gov-btn-light" data-table="#executivesTable" id="exportBtn" type="button">
                        <i class="fa-solid fa-file-export ms-2"></i> تصدير
                    </button>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="card filter-card mb-3">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold mb-1" for="pref1Select">
                                        <span style="color:black">الرغبة الأولى للمتقدم</span>
                                    </label>
                                    <select class="form-select gov-select" id="pref1Select">
                                        <option value="all" <?= ($pref1_filter === 'all' ? 'selected' : '') ?>>الكل</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?= (int) $d['id'] ?>" <?= ($pref1_filter !== 'all' && (int) $pref1_filter === (int) $d['id'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($d['name_ar']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold mb-1" for="pref2Select">
                                        <span style="color:black">الرغبة الثانية للمتقدم</span>
                                    </label>
                                    <select class="form-select gov-select" id="pref2Select">
                                        <option value="all" <?= ($pref2_filter === 'all' ? 'selected' : '') ?>>الكل</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?= (int) $d['id'] ?>" <?= ($pref2_filter !== 'all' && (int) $pref2_filter === (int) $d['id'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($d['name_ar']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold" for="directionSelect">توجيه المتقدم إلى القسم</label>
                        <div class="d-flex align-items-end">
                            <select class="form-select" id="directionSelect">
                                <option value="all" selected>الكل</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="gov-btn gov-btn-primary ms-2" type="button" id="routeBtn">توجيه</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- جدول المتقدمين -->
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
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="11" class="text-muted text-center py-4">لا توجد طلبات تنتظر التوجيه.</td>
                                </tr>
                            <?php else:
                                foreach ($applications as $r): ?>
                                    <tr>
                                        <td><input class="form-check-input row-check" type="checkbox"
                                                value="<?= (int) $r['application_id'] ?>" /></td>
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
                                            <span class="text-muted small"><?= htmlspecialchars($r['submitted_at']) ?></span>
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
            <div>
                <a href="#"></a>
                <a href="#"></a>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        $(document).ready(function () {
            // Select2
            $('select').each(function () {
                $(this).select2({ dir: 'rtl', width: '100%', allowClear: true, minimumResultsForSearch: 0 });
            });

            // فلاتر الرغبات: إعادة تحميل بعنوان GET
            $('#pref1Select, #pref2Select').on('change', function () {
                const p1 = $('#pref1Select').val() || 'all';
                const p2 = $('#pref2Select').val() || 'all';
                const url = new URL(window.location.href);
                url.searchParams.set('pref1_filter', p1);
                url.searchParams.set('pref2_filter', p2);
                window.location.href = url.toString();
            });

            // طباعة
            $('#printBtn').on('click', function (e) { e.preventDefault(); window.print(); });

            // تصدير
            function tableToWorkbook(tableId) {
                const table = document.getElementById(tableId);
                if (!table) return null;
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.table_to_sheet(table);
                XLSX.utils.book_append_sheet(wb, ws, 'المتقدمين');
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
                if (window.XLSX) {
                    const wb = tableToWorkbook('executivesTable');
                    if (wb) { XLSX.writeFile(wb, 'المتقدمين.xlsx'); return; }
                }
                downloadCSV('executivesTable', 'المتقدمين.csv');
            });

            // تحديد/إلغاء الكل
            $('#selectAll').on('change', function () { $('.row-check').prop('checked', this.checked); });

            // إلغاء (نفس زر الحذف)
            $('#deleteBtn').on('click', async function (e) {
                e.preventDefault();
                const ids = $('.row-check:checked').toArray().map(cb => cb.value);
                if (ids.length === 0) { alert('يرجى تحديد عنصر واحد على الأقل'); return; }
                if (!confirm('هل تريد إلغاء الطلبات المحددة؟')) return;

                const fd = new FormData();
                fd.append('action', 'cancel_applications');
                ids.forEach(id => fd.append('application_ids[]', id));
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({ success: false, message: 'خطأ غير متوقع' }));
                alert(data.message || (data.success ? 'تم الإلغاء' : 'فشل الإلغاء'));
                if (data.success) location.reload();
            });

            // توجيه حسب القسم المختار
            $('#routeBtn').on('click', async function (e) {
                e.preventDefault();
                const ids = $('.row-check:checked').toArray().map(cb => cb.value);
                const deptId = $('#directionSelect').val();
                if (ids.length === 0) { alert('يرجى تحديد متقدم واحد على الأقل'); return; }
                if (!deptId || deptId === 'all') { alert('يرجى اختيار قسم للتوجيه'); return; }

                if (!confirm('تأكيد توجيه المتقدمين للقسم المحدد؟')) return;

                const fd = new FormData();
                fd.append('action', 'route_department');
                fd.append('department_id', deptId);
                ids.forEach(id => fd.append('application_ids[]', id));
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json().catch(() => ({ success: false, message: 'خطأ غير متوقع' }));
                alert(data.message || (data.success ? 'تم التوجيه' : 'فشل التوجيه'));
                if (data.success) location.reload();
            });
        });
    </script>
</body>

</html>