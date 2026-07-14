<?php
// student_status.php
session_start();
require_once __DIR__ . '/connection.php';

// التحقق من تسجيل الدخول والدور
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);

if ($userId <= 0 || $userRole !== 4) { // role_id = 4 للطلاب فقط
    header('Location: index.php');
    exit;
}

// جلب الطلب الحالي للطالب من الـ View المخصص
$sql = "SELECT * FROM v_applications_base WHERE applicant_id = ? ORDER BY submitted_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('خطأ في إعداد الاستعلام: ' . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$app = $result->fetch_assoc();
$stmt->close();

// إذا لم يوجد طلب، توجيه لصفحة التقديم
if (!$app) {
    header('Location: student_apply.php');
    exit;
}

// دالة لتنسيق النص
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// دالة لتحويل حالة الطلب إلى نص عربي
function getStatusText($status, $stage) {
    switch ($status) {
        case 'pending':
            return ['text' => 'في انتظار المراجعة من السكرتارية', 'class' => 'warning', 'icon' => 'clock'];
        case 'in_dept':
            if ($stage === 'first') {
                return ['text' => 'تم إحالته للقسم الأول - في انتظار الرد', 'class' => 'info', 'icon' => 'paper-plane'];
            } else {
                return ['text' => 'تم إحالته للقسم الثاني - في انتظار الرد', 'class' => 'info', 'icon' => 'paper-plane'];
            }
        case 'accepted':
            return ['text' => 'تم قبول طلبك - مبروك!', 'class' => 'success', 'icon' => 'check-circle'];
        case 'canceled':
            return ['text' => 'تم إلغاء الطلب', 'class' => 'danger', 'icon' => 'times-circle'];
        default:
            return ['text' => 'حالة غير معروفة', 'class' => 'secondary', 'icon' => 'question'];
    }
}

// دالة للحصول على اسم المرحلة
function getStageText($stage) {
    switch ($stage) {
        case 'first': return 'المرحلة الأولى';
        case 'second': return 'المرحلة الثانية';
        case 'accepted': return 'مقبول';
        case 'canceled': return 'ملغي';
        default: return 'غير محدد';
    }
}

$statusInfo = getStatusText($app['status'], $app['stage']);
$canReapply = in_array($app['status'], ['accepted', 'canceled']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة طلب التدريب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --bg: #29327A;
            --accent: #00D1B2;
            --text: #ffffff;
            --muted: rgba(255, 255, 255, .75);
            --card-bg: rgba(255, 255, 255, 0.10);
            --card-bd: rgba(255, 255, 255, 0.20);
            --field-bg: rgba(255, 255, 255, 0.15);
            --field-bd: rgba(255, 255, 255, 0.50);
        }

        body, .card, .section-card, .section-title, .section-title *,
        .field-label, .field-value, .text-white, .text-light {
            color: var(--text) !important;
        }

        .background-pattern {
            min-height: 100vh;
            background-color: var(--bg);
        }

        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--card-bd);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(5px);
        }

        .section-card {
            background: transparent;
            border: 1px solid var(--card-bd);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .28);
        }

        .section-title {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 1.25rem;
        }

        .field-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.25);
        }

        .field-row:last-child {
            border-bottom: none;
        }

        .field-label {
            font-weight: 600;
            font-size: 1rem;
        }

        .field-value {
            font-weight: 500;
            background: var(--field-bg);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--field-bd);
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }

        .timeline {
            position: relative;
            padding-right: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            right: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.3);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-right: 15px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            right: -8px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.5);
            background: var(--bg);
        }

        .timeline-item.active::before {
            background: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(0, 209, 178, 0.5);
        }

        .timeline-item.completed::before {
            background: #28a745;
            border-color: #28a745;
        }

        @media (max-width: 768px) {
            .field-row {
                grid-template-columns: 1fr;
                gap: 8px;
                text-align: center;
            }
            
            .timeline {
                padding-right: 20px;
            }
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            display: inline-block;
            border-bottom: 3px solid var(--accent);
            padding-bottom: 0.3rem;
        }
    </style>
</head>

<body>
    <div class="background-pattern">
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10">
                    <div class="card p-4 form-card">
                        
                        <!-- Header -->
                        <div class="text-center mb-4">
                            <h2 class="page-title">حالة طلب التدريب</h2>
                            <p class="text-muted mt-2">
                                تم تقديم الطلب في: <?= date('d/m/Y - H:i', strtotime($app['submitted_at'])) ?>
                            </p>
                        </div>

                        <!-- أزرار التنقل -->
                        <div class="text-center mb-4">
                            <a href="index.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-home me-2"></i>الصفحة الرئيسية
                            </a>
                            <?php if ($canReapply): ?>
                            <a href="student_apply.php" class="btn btn-outline-success">
                                <i class="fas fa-plus me-2"></i>تقديم طلب جديد
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- حالة الطلب الحالية -->
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-info-circle"></i> الحالة الحالية
                            </div>
                            <div class="text-center mb-3">
                                <span class="status-badge alert-<?= $statusInfo['class'] ?>">
                                    <i class="fas fa-<?= $statusInfo['icon'] ?>"></i>
                                    <?= $statusInfo['text'] ?>
                                </span>
                            </div>
                            <div class="field-row">
                                <div class="field-label">رقم الطلب</div>
                                <div class="field-value">#<?= $app['applicant_id'] ?></div>
                            </div>
                            <div class="field-row">
                                <div class="field-label">المرحلة</div>
                                <div class="field-value"><?= e(getStageText($app['stage'])) ?></div>
                            </div>
                            <?php if ($app['routed_pref']): ?>
                            <div class="field-row">
                                <div class="field-label">القسم المُحال إليه</div>
                                <div class="field-value">
                                    <?= e($app['target_dept_name']) ?> 
                                    <small class="text-muted">(الرغبة <?= $app['routed_pref'] === 1 ? 'الأولى' : 'الثانية' ?>)</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($app['notes'])): ?>
                            <div class="field-row">
                                <div class="field-label">ملاحظات</div>
                                <div class="field-value"><?= e($app['notes']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- مسار الطلب -->
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-route"></i><a href="waiting.php"> مسار الطلب</a>
                            </div>
                            <div class="timeline">
                                <div class="timeline-item completed">
                                    <h6><i class="fas fa-paper-plane me-2"></i>تم تقديم الطلب</h6>
                                    <small class="text-muted">
                                        <?= date('d/m/Y - H:i', strtotime($app['submitted_at'])) ?>
                                    </small>
                                </div>
                                
                                <div class="timeline-item <?= $app['status'] !== 'pending' ? 'completed' : 'active' ?>">
                                    <h6><i class="fas fa-user-edit me-2"></i>مراجعة السكرتارية</h6>
                                    <small class="text-muted">
                                        <?= $app['status'] === 'pending' ? 'في الانتظار...' : 'تم التوجيه للقسم' ?>
                                    </small>
                                </div>
                                
                                <?php if ($app['routed_pref']): ?>
                                <div class="timeline-item <?= $app['status'] === 'in_dept' ? 'active' : ($app['status'] === 'accepted' ? 'completed' : '') ?>">
                                    <h6><i class="fas fa-building me-2"></i>مراجعة القسم</h6>
                                    <small class="text-muted">
                                        <?= $app['target_dept_name'] ?>
                                        <?= $app['status'] === 'in_dept' ? ' - في الانتظار...' : 
                                           ($app['status'] === 'accepted' ? ' - تم القبول' : ' - تم الرفض') ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($app['status'] === 'accepted'): ?>
                                <div class="timeline-item completed">
                                    <h6><i class="fas fa-check-circle me-2 text-success"></i>تم قبول الطلب</h6>
                                    <small class="text-success">مبروك! تم قبولك في التدريب</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- بيانات الطلب -->
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-user"></i> البيانات الشخصية
                            </div>
                            <div class="field-row">
                                <div class="field-label">الاسم الكامل</div>
                                <div class="field-value"><?= e($app['full_name']) ?></div>
                            </div>
                            <div class="field-row">
                                <div class="field-label">البريد الإلكتروني</div>
                                <div class="field-value"><?= e($app['applicant_email']) ?></div>
                            </div>
                            <div class="field-row">
                                <div class="field-label">رقم الجوال</div>
                                <div class="field-value"><?= e($app['applicant_phone']) ?></div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-briefcase"></i> بيانات التدريب
                            </div>
                            <div class="field-row">
                                <div class="field-label">مدة التدريب</div>
                                <div class="field-value"><?= e($app['training_duration']) ?></div>
                            </div>
                            <div class="field-row">
                                <div class="field-label">تاريخ البداية المطلوب</div>
                                <div class="field-value"><?= date('d/m/Y', strtotime($app['start_date'])) ?></div>
                            </div>
                            <div class="field-row">
                                <div class="field-label">القسم الأول (الرغبة الأولى)</div>
                                <div class="field-value">
                                    <?= e($app['pref1_dept_name']) ?>
                                    <?php if ($app['routed_pref'] == 1): ?>
                                        <span class="badge bg-primary ms-2">محال إليه</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($app['pref2_dept_name']): ?>
                            <div class="field-row">
                                <div class="field-label">القسم الثاني (الرغبة الثانية)</div>
                                <div class="field-value">
                                    <?= e($app['pref2_dept_name']) ?>
                                    <?php if ($app['routed_pref'] == 2): ?>
                                        <span class="badge bg-primary ms-2">محال إليه</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- رسائل إضافية حسب الحالة -->
                        <?php if ($app['status'] === 'pending'): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>في انتظار المراجعة</h6>
                            <p class="mb-0">طلبك قيد المراجعة من قبل السكرتارية. سيتم توجيهه للقسم المناسب حسب توفر الأماكن. ستصلك رسالة بريد إلكتروني عند حدوث أي تطوير.</p>
                        </div>
                        <?php elseif ($app['status'] === 'in_dept'): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-clock me-2"></i>قيد المراجعة من القسم</h6>
                            <p class="mb-0">تم إحالة طلبك إلى قسم <strong><?= e($app['target_dept_name']) ?></strong> وهو قيد المراجعة حالياً. انتظر الرد من القسم.</p>
                        </div>
                        <?php elseif ($app['status'] === 'accepted'): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>تم قبول طلبك!</h6>
                            <p class="mb-0">مبروك! تم قبول طلبك في قسم <strong><?= e($app['target_dept_name']) ?></strong>. ستتصل بك إدارة القسم قريباً لتحديد موعد البدء وتفاصيل التدريب.</p>
                        </div>
                        <?php elseif ($app['status'] === 'canceled'): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-times-circle me-2"></i>تم إلغاء الطلب</h6>
                            <p class="mb-0">للأسف تم إلغاء طلبك. يمكنك تقديم طلب جديد في وقت آخر.</p>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>