<?php
// signup.php
session_start();
require_once __DIR__ . '/connection.php';

$REDIRECT_AFTER_SIGNUP = 'login.php';

$errors = [];
$old = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'date_of_birth' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام القيم
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $dob       = trim($_POST['date_of_birth'] ?? ''); // YYYY-MM-DD

    // حفظ القيم لإعادة عرضها
    $old['full_name'] = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
    $old['phone']     = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    $old['email']     = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $old['date_of_birth'] = htmlspecialchars($dob, ENT_QUOTES, 'UTF-8');

    // التحقق
    if (mb_strlen($full_name) < 4) {
        $errors['full_name'] = 'يجب أن يكون الاسم 4 أحرف على الأقل.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'البريد الإلكتروني غير صحيح.';
    }

    // قبول 05XXXXXXXX أو +9665XXXXXXXX
    if (!preg_match('/^(05\d{8}|\+9665\d{8})$/', $phone)) {
        $errors['phone'] = 'رقم الجوال غير صحيح. مثال: 05XXXXXXXX أو +9665XXXXXXXX';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'كلمة المرور يجب أن لا تقل عن 8 أحرف.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'كلمة المرور غير متطابقة.';
    }

    // تاريخ الميلاد بصيغة صحيحة
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $errors['date_of_birth'] = 'صيغة تاريخ الميلاد يجب أن تكون YYYY-MM-DD.';
    } else {
        [$y,$m,$d] = explode('-', $dob);
        if (!checkdate((int)$m, (int)$d, (int)$y)) {
            $errors['date_of_birth'] = 'تاريخ الميلاد غير صالح.';
        } else {
            // التأكد من أن المستخدم لا يقل عمره عن 16 سنة
            $birth_date = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
            if ($age < 16) {
                $errors['date_of_birth'] = 'يجب أن يكون عمرك 16 سنة على الأقل للتسجيل.';
            }
        }
    }

    // إن لم توجد أخطاء، نتابع الإدخال
    if (empty($errors)) {
        // تطبيع رقم الجوال إلى +9665XXXXXXXX إن كان يبدأ بـ 05
        if (strpos($phone, '05') === 0) {
            $phone = '+9665' . substr($phone, 2);
        }

        // التأكد من عدم تكرار البريد
        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors['email'] = 'هذا البريد مسجل بالفعل.';
            }
            $stmt->close();
        } else {
            $errors['__all'] = 'خطأ في الاستعلام عن البريد.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $country_code = 'SA';
            $student_role_id = 4; // الطلاب المتدربين حسب جدول roles

            // إدراج المستخدم كطالب متدرب (role_id = 4, training_department_id = NULL)
            $ins = "INSERT INTO users (full_name, email, phone, password_hash, role_id, training_department_id, country_code, date_of_birth)
                    VALUES (?, ?, ?, ?, ?, NULL, ?, ?)";
            if ($st = $conn->prepare($ins)) {
                $st->bind_param('sssssss', $full_name, $email, $phone, $hash, $student_role_id, $country_code, $dob);
                if ($st->execute()) {
                    // نجاح التسجيل
                    $_SESSION['signup_success'] = 'تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول.';
                    header('Location: ' . $REDIRECT_AFTER_SIGNUP);
                    exit;
                } else {
                    $errors['__all'] = 'تعذر إنشاء الحساب. الرجاء المحاولة لاحقًا.';
                }
                $st->close();
            } else {
                $errors['__all'] = 'خطأ أثناء حفظ البيانات.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل حساب طالب جديد - منظومة إدارة التدريب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/sign.css" />
</head>

<body>
    <header class="header-bar d-flex justify-content-center align-items-center">
        <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="main-logo-img" />
    </header>

    <div class="main-bg d-flex justify-content-center align-items-center">
        <div class="card p-4 form-container" id="signup-form-card">
            <h2 class="text-center mb-2">تسجيل حساب طالب جديد</h2>
            <p class="text-center text-muted mb-3">منظومة إدارة طلبات التدريب</p>

            <?php if (!empty($errors['__all'])): ?>
                <div class="alert alert-danger py-2" role="alert">
                    <small><?php echo htmlspecialchars($errors['__all'], ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            <?php endif; ?>

            <form id="signup-form" method="post" action="">
                <!-- الاسم والبريد في صف واحد للشاشات الكبيرة -->
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="signupName" name="full_name"
                                   placeholder="الاسم الكامل" value="<?php echo $old['full_name']; ?>" required>
                        </div>
                        <?php if (!empty($errors['full_name'])): ?>
                            <div class="text-danger small"><?php echo htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="input-group custom-input">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="signupEmail" name="email"
                               placeholder="البريد الإلكتروني" value="<?php echo $old['email']; ?>" required>
                    </div>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="text-danger small"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>

                <!-- رقم الجوال وتاريخ الميلاد في صف واحد للشاشات الكبيرة -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="signupMobile" name="phone"
                                   placeholder="رقم الجوال" value="<?php echo $old['phone']; ?>" required>
                        </div>
                        <?php if (!empty($errors['phone'])): ?>
                            <div class="text-danger small"><?php echo htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="signupDOB" name="date_of_birth"
                                   value="<?php echo $old['date_of_birth']; ?>" required>
                        </div>
                        <?php if (!empty($errors['date_of_birth'])): ?>
                            <div class="text-danger small"><?php echo htmlspecialchars($errors['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- كلمات المرور في صف واحد -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="signupPassword" name="password"
                                   placeholder="كلمة المرور" required>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="text-danger small"><?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="form-text">8 أحرف على الأقل</div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password"
                                   placeholder="تأكيد كلمة المرور" required>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="text-danger small"><?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-info py-2 mb-3" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <small><strong>ملاحظة:</strong> بالتسجيل، ستتمكن من تقديم طلبات التدريب في الأقسام المختلفة.</small>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn signup-btn">
                        <i class="fas fa-user-plus me-2"></i>إنشاء حساب طالب
                    </button>
                </div>
            </form>

            <div class="text-center">
                <small>
                    <span>لديك حساب بالفعل؟ </span>
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-sign-in-alt me-1"></i>تسجيل الدخول
                    </a>
                </small>
            </div>

            <hr class="my-2">
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    بياناتك محمية ومشفرة بأحدث معايير الأمان
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // تحديد الحد الأقصى للتاريخ (اليوم)
        const dobInput = document.getElementById('signupDOB');
        const today = new Date().toISOString().split('T')[0];
        dobInput.max = today;
        
        // تحديد الحد الأدنى للتاريخ (16 سنة)
        const minDate = new Date();
        minDate.setFullYear(minDate.getFullYear() - 65);
        dobInput.min = minDate.toISOString().split('T')[0];
        
        // التحقق من تطابق كلمات المرور
        const password = document.getElementById('signupPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        
        function checkPasswordMatch() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('كلمة المرور غير متطابقة');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // تحسين تجربة المستخدم للمدخلات
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.borderColor = '#00bfff';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.borderColor = 'rgba(255, 255, 255, 0.5)';
            });
        });
    });
    </script>
</body>
</html>