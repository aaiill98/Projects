<?php
session_start();
require_once __DIR__ . '/connection.php';

$userId = $_SESSION['user_id'] ?? null;
$application = null;
$targetDeptName = null;

if ($userId) {
    // جلب تفاصيل الطلب الأحدث للمستخدم
    $stmt = mysqli_prepare($conn, "
        SELECT a.*, 
               t1.name_ar as pref1_dept_name,
               t2.name_ar as pref2_dept_name,
               CASE 
                   WHEN a.routed_pref = 1 THEN t1.name_ar
                   WHEN a.routed_pref = 2 THEN t2.name_ar
                   ELSE NULL
               END as target_dept_name
        FROM applications a
        LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
        LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
        WHERE a.applicant_id = ? 
        ORDER BY a.submitted_at DESC 
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $application = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// إذا لم يوجد طلب، إعادة توجيه لصفحة التقديم
if (!$application) {
    header('Location: student_apply.php');
    exit;
}

// تحديد الرسائل حسب الحالة
function getStatusMessage($status, $stage, $routed_pref, $rejected_pref1, $rejected_pref2) {
    switch ($status) {
        case 'pending':
            if ($routed_pref === null) {
                if ($rejected_pref1 && $rejected_pref2) {
                    return 'طلبك مرفوض من كلا القسمين - يرجى التواصل مع إدارة التدريب';
                } elseif ($rejected_pref1 || $rejected_pref2) {
                    return 'طلبك في انتظار إعادة التوجيه من السكرتارية';
                } else {
                    return 'طلبك في انتظار التوجيه من السكرتارية';
                }
            } else {
                return 'طلبك تحت المراجعة في القسم المختص';
            }
        case 'in_dept':
            return 'طلبك قيد المراجعة في القسم المختص';
        case 'accepted':
            return 'مبروك! تم قبول طلبك';
        case 'canceled':
            if ($stage === 'canceled') {
                return 'تم إلغاء طلبك';
            } else {
                return 'طلبك ملغي';
            }
        default:
            return 'حالة غير معروفة';
    }
}

$statusMessage = getStatusMessage(
    $application['status'], 
    $application['stage'], 
    $application['routed_pref'],
    $application['rejected_pref1'] ?? false,
    $application['rejected_pref2'] ?? false
);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة طلب التدريب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/waiting.css">
</head>
<body>
    <div class="container-fluid vh-100 d-flex flex-column waiting-page">
        <header class="row p-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <a href="#" class="logo-link">
                    <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="header-logo">
                </a>
                <a href="student_status.php" class="btn btn-link text-white text-decoration-none">
                    <i class="fas fa-arrow-right me-2"></i>
                    العودة للصفحة الرئيسية
                </a>
            </div>
        </header>

        <main class="row flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="col-12 col-lg-10">
                
                <!-- معلومات الطلب -->
                <div class="application-info-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt me-2"></i>تفاصيل طلب التدريب</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>اسم المتقدم:</strong> <?= htmlspecialchars($application['full_name']) ?></p>
                                <p><strong>مدة التدريب:</strong> <?= htmlspecialchars($application['training_duration']) ?></p>
                                <p><strong>تاريخ البدء:</strong> <?= date('Y/m/d', strtotime($application['start_date'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>الرغبة الأولى:</strong> <?= htmlspecialchars($application['pref1_dept_name']) ?></p>
                                <p><strong>الرغبة الثانية:</strong> <?= htmlspecialchars($application['pref2_dept_name']) ?></p>
                                <?php if ($application['target_dept_name']): ?>
                                <p><strong>القسم المحول إليه:</strong> <?= htmlspecialchars($application['target_dept_name']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($application['rejected_pref1'] || $application['rejected_pref2']): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php if ($application['rejected_pref1'] && $application['rejected_pref2']): ?>
                                تم رفض طلبك من كلا القسمين. يرجى التواصل مع إدارة التدريب.
                            <?php elseif ($application['rejected_pref1']): ?>
                                تم رفض طلبك من الرغبة الأولى (<?= htmlspecialchars($application['pref1_dept_name']) ?>).
                            <?php elseif ($application['rejected_pref2']): ?>
                                تم رفض طلبك من الرغبة الثانية (<?= htmlspecialchars($application['pref2_dept_name']) ?>).
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- حالة الطلب الحالية -->
                <div class="text-center mb-5">
                    <div class="main-message">
                        <h2 class="text-white mb-3">
                            <?php if ($application['status'] === 'accepted'): ?>
                                <i class="fas fa-check-circle text-success me-2"></i>مبروك! تم قبول طلبك
                            <?php elseif ($application['status'] === 'canceled'): ?>
                                <i class="fas fa-times-circle text-danger me-2"></i>تم إلغاء طلبك
                            <?php else: ?>
                                <i class="fas fa-clock text-warning me-2"></i>طلبك قيد المعالجة
                            <?php endif; ?>
                        </h2>
                        <p class="text-white-50"><?= $statusMessage ?></p>
                    </div>
                </div>

                <!-- مراحل الطلب -->
                <div class="progress-container">
                    <div class="progress-steps d-flex justify-content-between align-items-center">

                        <!-- المرحلة 1: تم استلام الطلب -->
                        <div class="step completed" data-step="1">
                            <div class="step-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="step-label">تم استلام طلبك</div>
                            <div class="step-date"><?= date('Y/m/d', strtotime($application['submitted_at'])) ?></div>
                        </div>

                        <div class="step-line completed"></div>

                        <!-- المرحلة 2: توجيه السكرتارية -->
                        <div class="step <?= ($application['routed_pref'] !== null) ? 'completed' : (($application['status'] === 'pending' && $application['routed_pref'] === null) ? 'active' : '') ?>" data-step="2">
                            <div class="step-circle">
                                <?php if ($application['routed_pref'] !== null): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($application['status'] === 'pending' && $application['routed_pref'] === null): ?>
                                    <i class="fas fa-cog fa-spin"></i>
                                <?php else: ?>
                                    <span>2</span>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">مراجعة السكرتارية</div>
                            <?php if ($application['routed_pref'] !== null): ?>
                            <div class="step-date">تم التوجيه للقسم</div>
                            <?php endif; ?>
                        </div>

                        <div class="step-line <?= ($application['status'] === 'in_dept' || $application['status'] === 'accepted') ? 'completed' : '' ?>"></div>

                        <!-- المرحلة 3: مراجعة القسم -->
                        <div class="step <?= ($application['status'] === 'in_dept') ? 'active' : (($application['status'] === 'accepted') ? 'completed' : '') ?>" data-step="3">
                            <div class="step-circle">
                                <?php if ($application['status'] === 'accepted'): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($application['status'] === 'in_dept'): ?>
                                    <i class="fas fa-cog fa-spin"></i>
                                <?php else: ?>
                                    <span>3</span>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">مراجعة القسم</div>
                            <?php if ($application['target_dept_name']): ?>
                            <div class="step-info"><?= htmlspecialchars($application['target_dept_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="step-line <?= ($application['status'] === 'accepted') ? 'completed' : '' ?>"></div>

                        <!-- المرحلة 4: القبول والتوجيه -->
                        <div class="step <?= ($application['status'] === 'accepted') ? 'active' : '' ?>" data-step="4">
                            <div class="step-circle">
                                <?php if ($application['status'] === 'accepted'): ?>
                                    <i class="fas fa-graduation-cap"></i>
                                <?php else: ?>
                                    <span>4</span>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">توجه لتجمع الطائف الصحي</div>
                            <div class="step-info">لتوقيع نموذج التوجيهية</div>
                        </div>

                    </div>
                </div>

                <!-- إجراءات إضافية -->
                <?php if ($application['status'] === 'accepted'): ?>
                <div class="text-center mt-5">
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>تم قبول طلبك!</h5>
                        <p>يرجى التوجه إلى تجمع الطائف الصحي لاستكمال إجراءات التدريب وتوقيع نموذج التوجيهية.</p>
                        <div class="mt-3">
                            <a href="tel:+966123456789" class="btn btn-success me-2">
                                <i class="fas fa-phone me-2"></i>تواصل معنا
                            </a>
                            <a href="#" class="btn btn-outline-light">
                                <i class="fas fa-map-marker-alt me-2"></i>العنوان
                            </a>
                        </div>
                    </div>
                </div>
                <?php elseif ($application['status'] === 'canceled' || ($application['rejected_pref1'] && $application['rejected_pref2'])): ?>
                <div class="text-center mt-5">
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-times-circle me-2"></i>تم إلغاء الطلب</h5>
                        <p>للاستفسار أو تقديم طلب جديد، يرجى التواصل مع إدارة التدريب.</p>
                        <div class="mt-3">
                            <a href="tel:+966123456789" class="btn btn-danger me-2">
                                <i class="fas fa-phone me-2"></i>تواصل معنا
                            </a>
                            <a href="student_apply.php" class="btn btn-outline-light">
                                <i class="fas fa-plus me-2"></i>طلب جديد
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center mt-5">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>نصائح مفيدة</h5>
                        <p>• ستصلك رسالة نصية عند تحديث حالة طلبك<br>
                           • يمكنك زيارة هذه الصفحة في أي وقت لمتابعة حالة طلبك<br>
                           • في حالة وجود استفسار، تواصل معنا على الرقم أدناه</p>
                        <a href="tel:+966123456789" class="btn btn-info mt-2">
                            <i class="fas fa-phone me-2"></i>تواصل معنا
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث الصفحة كل دقيقتين لمتابعة التحديثات
        setTimeout(function() {
            location.reload();
        }, 120000); // 2 دقيقة

        // إضافة تأثيرات تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.step');
            steps.forEach(function(step, index) {
                setTimeout(function() {
                    step.style.opacity = '0';
                    step.style.transform = 'translateY(20px)';
                    step.style.transition = 'all 0.6s ease';
                    
                    setTimeout(function() {
                        step.style.opacity = '1';
                        step.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 200);
            });
        });
    </script>
</body>
</html>