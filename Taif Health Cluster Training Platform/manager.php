<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
session_start();

require_once __DIR__ . '/connection.php';

// التحقق من الدور (مدير التدريب = role_id 1)
$roleId = null;
if (isset($_SESSION['user_role_id'])) {
    $roleId = (int) $_SESSION['user_role_id'];
} elseif (isset($_SESSION['user']['role_id'])) {
    $roleId = (int) $_SESSION['user']['role_id'];
} elseif (isset($_SESSION['role_id'])) {
    $roleId = (int) $_SESSION['role_id'];
}
$hasAccess = ($roleId === 1);

if (!$hasAccess) {
    http_response_code(403);
    echo ($roleId !== null) ? "Your role_id is: " . $roleId : "No role_id found in session.";
    header("Refresh: 2; URL=login.php");
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// جلب أقسام التدريب للفلترة
$departments = [];
$q1 = "SELECT id, name_ar FROM training_departments ORDER BY name_ar ASC";
if ($r1 = mysqli_query($conn, $q1)) {
    while ($row = mysqli_fetch_assoc($r1))
        $departments[] = $row;
    mysqli_free_result($r1);
}

$selectedDeptId = (isset($_GET['dept']) && $_GET['dept'] !== '') ? trim($_GET['dept']) : '';

// تحديد نوع العرض (مقبولة أو مرفوضة)
$viewType = isset($_GET['view']) ? $_GET['view'] : 'accepted';
$allowedViews = ['accepted', 'rejected'];
if (!in_array($viewType, $allowedViews)) {
    $viewType = 'accepted';
}

// حساب الإحصائيات حسب القسم المختار
$totalApplications = 0;
$acceptedApplications = 0;
$rejectedApplications = 0;

// استعلام الإحصائيات الأساسي
$statsBaseQuery = "
    SELECT 
        status,
        COUNT(*) as count
    FROM applications a
    WHERE 1=1
";

// إضافة فلترة القسم إذا تم اختياره
$statsCondition = "";
if ($selectedDeptId !== '' && $selectedDeptId !== '__ALL__') {
    $selectedDeptId = mysqli_real_escape_string($conn, $selectedDeptId);
    $statsCondition = " AND (
        (a.routed_pref = 1 AND a.pref1_training_dept_id = '{$selectedDeptId}')
        OR (a.routed_pref = 2 AND a.pref2_training_dept_id = '{$selectedDeptId}')
    )";
}

$statsQuery = $statsBaseQuery . $statsCondition . " GROUP BY status";

$result = mysqli_query($conn, $statsQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        switch ($row['status']) {
            case 'accepted':
                $acceptedApplications = (int)$row['count'];
                break;
            case 'canceled':
                $rejectedApplications = (int)$row['count'];
                break;
            default:
                // pending, in_dept تحسب كطلبات إجمالية
                break;
        }
    }
    mysqli_free_result($result);
}

// حساب إجمالي الطلبات
$totalQuery = "SELECT COUNT(*) as total FROM applications a WHERE 1=1" . $statsCondition;
$totalResult = mysqli_query($conn, $totalQuery);
if ($totalResult) {
    $totalRow = mysqli_fetch_assoc($totalResult);
    $totalApplications = (int)$totalRow['total'];
    mysqli_free_result($totalResult);
}

// جلب الطلبات حسب النوع المطلوب
$applications = [];

if ($viewType === 'accepted') {
    $sql = "
        SELECT
            a.*,
            t1.name_ar AS pref1_dept_name,
            t2.name_ar AS pref2_dept_name
        FROM applications a
        LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
        LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
        WHERE a.status = 'accepted' AND a.stage = 'accepted'
    ";
} else { // rejected
    $sql = "
        SELECT
            a.*,
            t1.name_ar AS pref1_dept_name,
            t2.name_ar AS pref2_dept_name
        FROM applications a
        LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
        LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
        WHERE a.status = 'canceled' AND a.stage = 'canceled'
    ";
}

if ($selectedDeptId !== '' && $selectedDeptId !== '__ALL__') {
    $sql .= " AND (
        (a.routed_pref = 1 AND a.pref1_training_dept_id = '{$selectedDeptId}')
        OR (a.routed_pref = 2 AND a.pref2_training_dept_id = '{$selectedDeptId}')
    )";
}

$sql .= " ORDER BY a.submitted_at DESC, a.id DESC";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // إضافة القسم المخصص فعلياً للطالب
        if ($row['routed_pref'] == 1) {
            $row['assigned_dept_name'] = $row['pref1_dept_name'];
        } elseif ($row['routed_pref'] == 2) {
            $row['assigned_dept_name'] = $row['pref2_dept_name'];
        } else {
            $row['assigned_dept_name'] = 'غير محدد';
        }
        $applications[] = $row;
    }
    mysqli_free_result($result);
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>إدارة التدريب والتعليم - <?= $viewType === 'accepted' ? 'الطلبات المقبولة' : 'الطلبات المرفوضة' ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/acc-style.css" rel="stylesheet" />
    <style>
        /* إضافة ستايل للبطاقات الإحصائية */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border: 1px solid #e3e6f0;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.2);
            text-decoration: none;
            color: inherit;
        }

        .stats-card.active {
            border-left-width: 6px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .stats-card.total {
            border-left: 4px solid #5a67d8;
        }

        .stats-card.accepted {
            border-left: 4px solid #28a745;
        }

        .stats-card.rejected {
            border-left: 4px solid #dc3545;
        }

        .stats-card.total.active {
            border-left-color: #5a67d8;
            background: linear-gradient(135deg, rgba(90, 103, 216, 0.1) 0%, rgba(90, 103, 216, 0.05) 100%);
        }

        .stats-card.accepted.active {
            border-left-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
        }

        .stats-card.rejected.active {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
        }

        .stats-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stats-card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6c757d;
            margin: 0;
        }

        .stats-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stats-card.total .stats-card-icon {
            background: rgba(90, 103, 216, 0.15);
            color: #5a67d8;
        }

        .stats-card.accepted .stats-card-icon {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .stats-card.rejected .stats-card-icon {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }

        .stats-card-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin: 0;
        }

        .stats-card.total .stats-card-number {
            color: #5a67d8;
        }

        .stats-card.accepted .stats-card-number {
            color: #28a745;
        }

        .stats-card.rejected .stats-card-number {
            color: #dc3545;
        }

        .stats-card-footer {
            font-size: 0.8rem;
            color: #858796;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
            
            .stats-card-number {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <header class="header-section">
        <div class="header-inner">
            <div class="logo-wrap" style="text-align:center; padding-top: 1rem;">
                <img alt="شعار تجمع الطائف الصحي" class="main-logo" src="images/thc.png" />
            </div>
            <div class="branding" style="text-align:center">
                <h1 class="main-title">إدارة التدريب والتعليم</h1>
                <p class="subtitle">لوحة <?= $viewType === 'accepted' ? 'الطلبات المقبولة' : 'الطلبات المرفوضة' ?> — Taif Health Cluster</p>
                <a href="index.php?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </header>

    <main class="container-xxl main-content">
        <!-- قسم الإحصائيات -->
        <section class="panel fade-in" style="padding: 1.5rem;">
            <div class="stats-container">
                <div class="stats-card total">
                    <div class="stats-card-header">
                        <h6 class="stats-card-title">إجمالي الطلبات</h6>
                        <div class="stats-card-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <h2 class="stats-card-number"><?= $totalApplications ?></h2>
                    <div class="stats-card-footer">
                        العدد الكلي للطلبات المقدمة
                    </div>
                </div>

                <a href="?view=accepted<?= $selectedDeptId ? '&dept=' . urlencode($selectedDeptId) : '' ?>" 
                   class="stats-card accepted <?= $viewType === 'accepted' ? 'active' : '' ?>">
                    <div class="stats-card-header">
                        <h6 class="stats-card-title">الطلبات المقبولة</h6>
                        <div class="stats-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <h2 class="stats-card-number"><?= $acceptedApplications ?></h2>
                    <div class="stats-card-footer">
                        الطلبات التي تم قبولها وتأكيدها
                    </div>
                </a>

                <a href="?view=rejected<?= $selectedDeptId ? '&dept=' . urlencode($selectedDeptId) : '' ?>" 
                   class="stats-card rejected <?= $viewType === 'rejected' ? 'active' : '' ?>">
                    <div class="stats-card-header">
                        <h6 class="stats-card-title">الطلبات المرفوضة</h6>
                        <div class="stats-card-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <h2 class="stats-card-number"><?= $rejectedApplications ?></h2>
                    <div class="stats-card-footer">
                        الطلبات التي تم رفضها أو إلغاؤها
                    </div>
                </a>
            </div>
        </section>

        <section class="panel control-panel fade-in" style="padding:1rem 1rem;">
            <div class="control-grid">
                <div style="flex:1; min-width:260px; max-width:360px;">
                    <label class="form-label fw-bold" for="specializationSelect">القسم التدريبي</label>
                    <form method="get" style="display:flex; gap:.5rem; align-items:center;">
                        <input type="hidden" name="view" value="<?= e($viewType) ?>">
                        <select aria-label="القسم التدريبي" class="form-select searchable" name="dept"
                            id="specializationSelect" style="min-width:260px;">
                            <option value="" <?= $selectedDeptId === '' ? 'selected' : '' ?>>جميع الأقسام</option>
                            <option value="__ALL__" <?= $selectedDeptId === '__ALL__' ? 'selected' : '' ?>>الكل</option>
                            <?php foreach ($departments as $dep): ?>
                                <option value="<?= e($dep['id']) ?>" <?= ($selectedDeptId === (string) $dep['id']) ? 'selected' : '' ?>>
                                    <?= e($dep['name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="gov-btn"><i class="fa-solid fa-search"></i> بحث</button>
                    </form>
                </div>
                <div class="actions-left">
                    <div class="btn-row">
                        <a class="gov-btn" href="manager_overview.php"><i class="fa-solid fa-arrow-right"></i> العودة
                            للوحة الرئيسية</a>
                        <a class="gov-btn" href="#" id="btnExport"><i class="fa-solid fa-file-export"></i> تصدير</a>
                        <a class="gov-btn" href="#" id="btnPrint"><i class="fa-solid fa-print"></i> طباعة</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel table-wrap fade-in" id="tableSection" style="padding:1rem;">
            <div class="table-container table-responsive">
                <table class="table" id="<?= $viewType ?>Table">
                    <thead class="table-header">
                        <tr>
                            <th><input class="form-check-input" id="selectAll" type="checkbox" /></th>
                            <th>اسم المتقدم</th>
                            <th>البريد الإلكتروني</th>
                            <th>رقم الجوال</th>
                            <th>مدة التدريب</th>
                            <th>تاريخ البدء</th>
                            <th>القسم المخصص</th>
                            <th>الرغبة الأولى</th>
                            <th>الرغبة الثانية</th>
                            <th>تاريخ التقديم</th>
                            <?php if ($viewType === 'rejected'): ?>
                                <th>الحالة</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="<?= $viewType === 'rejected' ? '11' : '10' ?>" style="text-align:center; padding: 2rem;">
                                    <i class="fa-solid fa-inbox"
                                        style="font-size: 2rem; color: #ccc; margin-bottom: 1rem;"></i>
                                    <br>
                                    <?= $viewType === 'accepted' ? 'لا توجد طلبات مقبولة حالياً.' : 'لا توجد طلبات مرفوضة حالياً.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr class="<?= $viewType ?>">
                                    <td><input class="form-check-input row-check" type="checkbox"
                                            value="<?= e($app['id']) ?>" /></td>
                                    <td>
                                        <span class="cell-text" style="font-weight: 600;">
                                            <?= e($app['full_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= e($app['email']) ?>"
                                            style="color: var(--gov-royal); text-decoration: none;">
                                            <?= e($app['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="tel:<?= e($app['phone']) ?>"
                                            style="color: var(--gov-royal); text-decoration: none;">
                                            <?= e($app['phone']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($app['training_duration']) ?></td>
                                    <td><?= date('Y/m/d', strtotime($app['start_date'])) ?></td>
                                    <td>
                                        <span class="badge <?= $viewType === 'accepted' ? 'badge-info' : 'badge-danger' ?>">
                                            <?= e($app['assigned_dept_name'] ?? 'غير محدد') ?>
                                        </span>
                                    </td>
                                    <td><?= e($app['pref1_dept_name']) ?></td>
                                    <td><?= e($app['pref2_dept_name']) ?></td>
                                    <td><?= date('Y/m/d H:i', strtotime($app['submitted_at'])) ?></td>
                                    <?php if ($viewType === 'rejected'): ?>
                                        <td>
                                            <span class="badge badge-danger">
                                                <?= $app['stage'] === 'canceled' ? 'مرفوض' : 'ملغي' ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="actions" style="display:flex; justify-content:space-between; gap:.5rem; padding:1rem 0;">
                <div class="left" style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <span style="color: var(--gov-text); font-weight: 600;">
                        <i class="fa-solid fa-<?= $viewType === 'accepted' ? 'check-circle' : 'times-circle' ?>" 
                           style="color: <?= $viewType === 'accepted' ? '#28a745' : '#dc3545' ?>;"></i>
                        إجمالي الطلبات <?= $viewType === 'accepted' ? 'المقبولة' : 'المرفوضة' ?>: <?= count($applications) ?>
                    </span>
                </div>
                <div class="right" style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <button class="btn-guide" id="btnExport2"><i class="fa-solid fa-download"></i> تصدير
                        القائمة</button>
                    <button class="btn-guide" id="btnPrint2"><i class="fa-solid fa-print"></i> طباعة</button>
                    <?php if ($viewType === 'accepted'): ?>
                        <button class="btn-guide" id="btnSendEmails"
                            style="background: linear-gradient(135deg,#28a745,#1e7e34);"><i
                                class="fa-solid fa-envelope"></i> إرسال إشعارات</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <div class="footer-pad"></div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        $(function () {
            // تفعيل Select2
            if ($.fn.select2) {
                $('#specializationSelect').select2({
                    width: '100%',
                    placeholder: 'اختر القسم التدريبي'
                });
            }

            // تحديد الكل
            $('#selectAll').change(function () {
                $('.row-check').prop('checked', this.checked);
                updateSelectedCount();
            });

            $('.row-check').change(function () {
                updateSelectedCount();
                $('#selectAll').prop('checked', $('.row-check:checked').length === $('.row-check').length);
            });

            // تصدير إلى Excel
            $('#btnExport, #btnExport2').click(function (e) {
                e.preventDefault();
                exportToExcel();
            });

            function exportToExcel() {
                const table = document.getElementById('<?= $viewType ?>Table');
                const wb = XLSX.utils.table_to_book(table, { sheet: "<?= $viewType === 'accepted' ? 'الطلبات المقبولة' : 'الطلبات المرفوضة' ?>" });

                // تحسين البيانات المصدرة
                const ws = wb.Sheets["<?= $viewType === 'accepted' ? 'الطلبات المقبولة' : 'الطلبات المرفوضة' ?>"];

                // إزالة عمود checkbox
                <?php if ($viewType === 'rejected'): ?>
                    XLSX.utils.sheet_add_aoa(ws, [
                        ["اسم المتقدم", "البريد الإلكتروني", "رقم الجوال", "مدة التدريب", "تاريخ البدء", "القسم المخصص", "الرغبة الأولى", "الرغبة الثانية", "تاريخ التقديم", "الحالة"]
                    ], { origin: "A1" });
                <?php else: ?>
                    XLSX.utils.sheet_add_aoa(ws, [
                        ["اسم المتقدم", "البريد الإلكتروني", "رقم الجوال", "مدة التدريب", "تاريخ البدء", "القسم المخصص", "الرغبة الأولى", "الرغبة الثانية", "تاريخ التقديم"]
                    ], { origin: "A1" });
                <?php endif; ?>

                XLSX.writeFile(wb, `<?= $viewType === 'accepted' ? 'الطلبات_المقبولة' : 'الطلبات_المرفوضة' ?>_${new Date().toISOString().split('T')[0]}.xlsx`);
            }

            // طباعة
            $('#btnPrint, #btnPrint2').click(function (e) {
                e.preventDefault();
                window.print();
            });

            <?php if ($viewType === 'accepted'): ?>
            // إرسال إشعارات (يمكن تطويرها لاحقاً)
            $('#btnSendEmails').click(function (e) {
                e.preventDefault();
                const selectedIds = $('.row-check:checked').map(function () {
                    return this.value;
                }).get();

                if (selectedIds.length === 0) {
                    alert('يرجى تحديد طلبات لإرسال الإشعارات إليها');
                    return;
                }

                if (confirm(`هل تريد إرسال إشعارات القبول لـ ${selectedIds.length} متقدم؟`)) {
                    // هنا يمكن إضافة منطق إرسال الإشعارات
                    alert('تم إرسال الإشعارات بنجاح!');
                }
            });
            <?php endif; ?>

            function updateSelectedCount() {
                const selectedCount = $('.row-check:checked').length;
                if (selectedCount > 0) {
                    $('.left').html(`
                        <span style="color: var(--gov-royal); font-weight: 600;">
                            <i class="fa-solid fa-check-square"></i>
                            تم تحديد ${selectedCount} من الطلبات
                        </span>
                    `);
                } else {
                    $('.left').html(`
                        <span style="color: var(--gov-text); font-weight: 600;">
                            <i class="fa-solid fa-<?= $viewType === 'accepted' ? 'check-circle' : 'times-circle' ?>" 
                               style="color: <?= $viewType === 'accepted' ? '#28a745' : '#dc3545' ?>;"></i>
                            إجمالي الطلبات <?= $viewType === 'accepted' ? 'المقبولة' : 'المرفوضة' ?>: <?= count($applications) ?>
                        </span>
                    `);
                }
            }
        });
    </script>
</body>

</html>