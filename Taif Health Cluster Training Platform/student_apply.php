<?php
// student_apply.php
session_start();
require_once __DIR__ . '/connection.php';

// التحقق من تسجيل الدخول والدور
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (int)($_SESSION['role_id'] ?? 0);

if ($userId <= 0 || $userRole !== 4) { // role_id = 4 للطلاب فقط
    header('Location: index.php'); 
    exit; 
}

// التحقق من وجود طلب مفتوح
function hasOpenApplication($conn, $userId): bool {
    $stmt = $conn->prepare("SELECT id FROM applications WHERE applicant_id = ? AND status IN ('pending', 'in_dept')");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

// جلب أقسام التدريب
function fetchTrainingDepartments($conn): array {
    $stmt = $conn->prepare("SELECT id, name_ar FROM training_departments ORDER BY name_ar");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        $stmt->close();
        return $departments;
    }
    return [];
}

// التحقق من وجود طلب مفتوح
if (hasOpenApplication($conn, $userId)) {
    $openAppMessage = "لديك طلب تدريب مفتوح بالفعل. لا يمكنك تقديم طلب جديد حتى يتم البت في الطلب الحالي.";
}

$errors = [];
$success = false;

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($openAppMessage)) {
    $training_duration = trim($_POST['training_duration'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $pref1_id = (int)($_POST['pref1_training_dept_id'] ?? 0);
    $pref2_id = (int)($_POST['pref2_training_dept_id'] ?? 0);

    // التحقق من صحة البيانات
    if (!$training_duration) {
        $errors['training_duration'] = 'مدة التدريب مطلوبة.';
    }

    if (!$start_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $errors['start_date'] = 'تاريخ البداية مطلوب وبصيغة صحيحة.';
    } else {
        // التحقق من أن التاريخ في المستقبل
        $startDateTime = new DateTime($start_date);
        $today = new DateTime();
        if ($startDateTime <= $today) {
            $errors['start_date'] = 'تاريخ البداية يجب أن يكون في المستقبل.';
        }
    }

    if ($pref1_id <= 0) {
        $errors['pref1_training_dept_id'] = 'القسم الأول مطلوب.';
    }

    // التحقق من أن القسمين مختلفين
    if ($pref1_id > 0 && $pref2_id > 0 && $pref1_id === $pref2_id) {
        $errors['pref2_training_dept_id'] = 'القسم الثاني يجب أن يختلف عن القسم الأول.';
    }

    // التحقق من وجود الأقسام في قاعدة البيانات
    if ($pref1_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM training_departments WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $pref1_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $errors['pref1_training_dept_id'] = 'القسم الأول غير موجود.';
            }
            $stmt->close();
        }
    }

    if ($pref2_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM training_departments WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $pref2_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $errors['pref2_training_dept_id'] = 'القسم الثاني غير موجود.';
            }
            $stmt->close();
        }
    }

    // إذا لم توجد أخطاء، أدرج الطلب
    if (empty($errors)) {
        // جلب بيانات المستخدم من الجلسة أو قاعدة البيانات
        $stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // إدراج الطلب الجديد
                $insertSql = "INSERT INTO applications 
                             (applicant_id, full_name, email, phone, training_duration, start_date, 
                              pref1_training_dept_id, pref2_training_dept_id, stage, status, routed_pref) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'first', 'pending', NULL)";
                
                $stmt = $conn->prepare($insertSql);
                if ($stmt) {
                    // تحديد pref2_id إلى NULL إذا كان 0
                    $pref2_final = $pref2_id > 0 ? $pref2_id : null;
                    
                    $stmt->bind_param("isssssii", 
                        $userId, 
                        $user['full_name'], 
                        $user['email'], 
                        $user['phone'], 
                        $training_duration, 
                        $start_date, 
                        $pref1_id, 
                        $pref2_final
                    );
                    
                    if ($stmt->execute()) {
                        $success = true;
                        $stmt->close();
                        // إعادة توجيه لصفحة الانتظار
                        header('Location: student_status.php');
                        exit;
                    } else {
                        $errors['general'] = 'حدث خطأ أثناء حفظ الطلب. حاول مرة أخرى.';
                        $stmt->close();
                    }
                } else {
                    $errors['general'] = 'خطأ في إعداد الاستعلام.';
                }
            } else {
                $errors['general'] = 'لم يتم العثور على بيانات المستخدم.';
            }
        } else {
            $errors['general'] = 'خطأ في الاتصال بقاعدة البيانات.';
        }
    }
}

// جلب أقسام التدريب للعرض
$departments = fetchTrainingDepartments($conn);

// مساعدة لعرض القيم القديمة
function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>تقديم طلب التدريب</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/student_forms.css">
</head>

<body>
    <div class="background-pattern">
        <div class="container-fluid py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="card p-4 form-card">
                        <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="mx-auto mb-4 form-logo">
                        
                        <h2 class="text-center mb-4">تقديم طلب التدريب التعاوني</h2>
                        
                        <!-- عرض رسالة الطلب المفتوح -->
                        <?php if (isset($openAppMessage)): ?>
                            <div class="alert alert-warning text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($openAppMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <hr>
                                <a href="student_status.php" class="btn btn-primary">عرض حالة الطلب</a>
                            </div>
                        <?php else: ?>
                        
                        <!-- عرض الأخطاء العامة -->
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <!-- بيانات المستخدم -->
                        <div class="card mb-4 bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-user me-2"></i>بياناتك الشخصية</h6>
                                <p class="mb-1"><strong>الاسم:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mb-0"><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>

                        <!-- نموذج التقديم -->
                        <form method="post" novalidate>
                            

                            <!-- بداية إضافات من الصفحة القديمة: البيانات الأكاديمية -->
                            <div class="card mb-4" style="background-color: rgba(255, 255, 255, 0.1);">
                                <div class="card-body">
                                    <h6 class="card-title" style="color:white;"><i class="fas fa-graduation-cap me-2"></i>البيانات الأكاديمية</h6>

                                    <div class="mb-3">
                                        <label for="university" class="form-label">الجامعة</label>
                                        <input type="text" class="form-control" id="university" name="university"
                                               value="<?php echo old('university'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="degree" class="form-label">الدرجة</label>
                                        <input type="text" class="form-control" id="degree" name="degree"
                                               value="<?php echo old('degree'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="major" class="form-label">التخصص</label>
                                        <input type="text" class="form-control" id="major" name="major"
                                               value="<?php echo old('major'); ?>">
                                    </div>

                                    <div class="mb-0">
                                        <label for="gpa" class="form-label">المعدل (GPA)</label>
                                        <input type="text" class="form-control" id="gpa" name="gpa"
                                               placeholder="مثال: 4.50 / 5"
                                               value="<?php echo old('gpa'); ?>">
                                    </div>
                                </div>
                            </div>
                            <!-- نهاية إضافات البيانات الأكاديمية -->

                            <div class="mb-3">
                                <label for="training_duration" class="form-label required">
                                    <i class="fas fa-clock me-2"></i>مدة التدريب
                                </label>
                                <select class="form-control <?php echo !empty($errors['training_duration']) ? 'is-invalid' : ''; ?>" 
                                        id="training_duration" name="training_duration" required>
                                    <option value="">اختر مدة التدريب</option>
                                    <option value="4 أسابيع" <?php echo old('training_duration') === '4 أسابيع' ? 'selected' : ''; ?>>4 أسابيع</option>
                                    <option value="6 أسابيع" <?php echo old('training_duration') === '6 أسابيع' ? 'selected' : ''; ?>>6 أسابيع</option>
                                    <option value="8 أسابيع" <?php echo old('training_duration') === '8 أسابيع' ? 'selected' : ''; ?>>8 أسابيع</option>
                                    <option value="12 أسبوع" <?php echo old('training_duration') === '12 أسبوع' ? 'selected' : ''; ?>>12 أسبوع</option>
                                    <option value="4 أشهر" <?php echo old('training_duration') === '4 أشهر' ? 'selected' : ''; ?>>4 أشهر</option>
                                    <option value="6 أشهر" <?php echo old('training_duration') === '6 أشهر' ? 'selected' : ''; ?>>6 أشهر</option>
                                </select>
                                <?php if (!empty($errors['training_duration'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['training_duration'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="start_date" class="form-label required">
                                    <i class="fas fa-calendar-alt me-2"></i>تاريخ البداية المطلوب
                                </label>
                                <input type="date" 
                                       class="form-control <?php echo !empty($errors['start_date']) ? 'is-invalid' : ''; ?>" 
                                       id="start_date" name="start_date" 
                                       value="<?php echo old('start_date'); ?>" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       required>
                                <?php if (!empty($errors['start_date'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['start_date'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="pref1_training_dept_id" class="form-label required">
                                    <i class="fas fa-building me-2"></i>القسم التدريبي الأول (الرغبة الأولى)
                                </label>
                                <select class="form-control <?php echo !empty($errors['pref1_training_dept_id']) ? 'is-invalid' : ''; ?>" 
                                        id="pref1_training_dept_id" name="pref1_training_dept_id" required>
                                    <option value="">اختر القسم الأول</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo (int)old('pref1_training_dept_id') === $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['pref1_training_dept_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['pref1_training_dept_id'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="pref2_training_dept_id" class="form-label">
                                    <i class="fas fa-building me-2"></i>القسم التدريبي الثاني (الرغبة الثانية اختياري)
                                </label>
                                <select class="form-control <?php echo !empty($errors['pref2_training_dept_id']) ? 'is-invalid' : ''; ?>" 
                                        id="pref2_training_dept_id" name="pref2_training_dept_id">
                                    <option value="">اختر القسم الثاني (اختياري)</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo (int)old('pref2_training_dept_id') === $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name_ar'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    يفضل اختيار قسم ثانٍ كبديل عند عدم توفر مكان في القسم الأول
                                </div>
                                <?php if (!empty($errors['pref2_training_dept_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo htmlspecialchars($errors['pref2_training_dept_id'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- بداية إضافات من الصفحة القديمة: مرفق PDF + إقرار -->
                            <div class="mb-3">
                                <label for="pdf" class="form-label">
                                    <i class="fas fa-file-pdf me-2"></i>رفع ملف PDF اختياري
                                </label>
                                <input class="form-control" type="file" id="pdf" name="pdf" accept="application/pdf">
                                <div class="form-text">يمكن رفع خطاب توصية أو سيرة ذاتية بصيغة PDF.</div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="confirm_truth" name="confirm_truth"
                                       <?php echo isset($_POST['confirm_truth']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="confirm_truth">
                                    أقر بصحة جميع البيانات
                                </label>
                            </div>
                            <!-- نهاية إضافات المرفقات والإقرار -->

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>ملاحظات مهمة:</h6>
                                <ul class="mb-0 small">
                                    <li>سيتم مراجعة طلبك من قبل السكرتارية أولاً</li>
                                    <li>ستتم إحالة طلبك للقسم المناسب حسب توفر الأماكن</li>
                                    <li>ستصلك إشعارات على بريدك الإلكتروني بحالة الطلب</li>
                                    <li>يمكنك متابعة حالة طلبك من خلال حسابك</li>
                                </ul>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-paper-plane me-2"></i>تقديم الطلب
                                </button>
                                <a href="student_status.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-list me-2"></i>حالة الطلبات
                                </a>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="btn btn-link">
                                <i class="fas fa-home me-2"></i>العودة للصفحة الرئيسية
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // منع اختيار نفس القسم في الخيارين
        document.addEventListener('DOMContentLoaded', function() {
            const pref1 = document.getElementById('pref1_training_dept_id');
            const pref2 = document.getElementById('pref2_training_dept_id');
            
            function updateOptions() {
                const selectedInPref1 = pref1.value;
                const selectedInPref2 = pref2.value;
                
                // إعادة تفعيل جميع الخيارات
                Array.from(pref2.options).forEach(option => {
                    if (option.value !== '') {
                        option.disabled = false;
                    }
                });
                
                // تعطيل الخيار المحدد في الأول من الثاني
                if (selectedInPref1) {
                    const optionToDisable = pref2.querySelector(`option[value="${selectedInPref1}"]`);
                    if (optionToDisable) {
                        optionToDisable.disabled = true;
                        if (selectedInPref2 === selectedInPref1) {
                            pref2.value = '';
                        }
                    }
                }
            }
            
            pref1.addEventListener('change', updateOptions);
            pref2.addEventListener('change', updateOptions);
            
            // تشغيل عند التحميل للحالة الأولية
            updateOptions();
        });
    </script>
</body>
</html>
