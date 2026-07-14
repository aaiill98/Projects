<?php
// login.php - نسخة اختبار (بدون التحقق من كلمة المرور)
session_start();
require_once __DIR__ . '/connection.php';

$errorMsg = '';
$oldEmail = '';

// إعادة توجيه المستخدم المسجل دخوله بالفعل
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // سيتم تجاهلها
    $oldEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    if ($email === '') {
        $errorMsg = 'الرجاء إدخال البريد الإلكتروني.';
    } else {
        // جلب بيانات المستخدم مع معلومات القسم التدريبي إذا وُجد
        $sql = "SELECT u.id, u.full_name, u.email, u.password_hash, u.role_id, u.training_department_id,
                       r.name_ar as role_name, td.name_ar as training_dept_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN training_departments td ON u.training_department_id = td.id
                WHERE u.email = ? LIMIT 1";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            // ===== وضع الاختبار: تجاهل كلمة المرور =====
            if ($user) { // تم العثور على المستخدم بالبريد الإلكتروني
                // تخزين بيانات الجلسة
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['full_name'] = $user['full_name']; // إضافة للتوافق
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['email'] = $user['email']; // إضافة للتوافق
                $_SESSION['role_id'] = (int) $user['role_id'];
                $_SESSION['user_role_id'] = (int) $user['role_id']; // إضافة للتوافق
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['training_department_id'] = $user['training_department_id'] ? (int) $user['training_department_id'] : null;
                $_SESSION['training_dept_name'] = $user['training_dept_name'];

                // تحديد صفحة إعادة التوجيه حسب الدور
                $redirectPage = 'index.php'; // الافتراضي

                switch ((int) $user['role_id']) {
                    case 1: // إدارة التدريب
                        $redirectPage = 'manager.php';
                        break;

                    case 2: // السكرتارية
                        $redirectPage = 'secretary_queue.php';
                        break;

                    case 3: // موظف قسم تدريبي
                        // التحقق من وجود قسم تدريبي مرتبط
                        if ($user['training_department_id']) {
                            $redirectPage = 'department.php'; // صفحة موظف القسم
                        } else {
                            // موظف قسم بدون قسم محدد - خطأ في البيانات
                            session_destroy();
                            $errorMsg = 'حسابك غير مكتمل الإعداد. يرجى التواصل مع الإدارة.';
                            break;
                        }
                        break;

                    case 4: // طالب متدرب
                        // التحقق من وجود طلبات سابقة
                        $hasActiveApp = false;
                        $checkSql = "SELECT id, status FROM applications WHERE applicant_id = ? AND status IN ('pending', 'in_dept') LIMIT 1";
                        if ($checkStmt = $conn->prepare($checkSql)) {
                            $userId = (int) $user['id'];
                            $checkStmt->bind_param("i", $userId);
                            $checkStmt->execute();
                            $appResult = $checkStmt->get_result();
                            $hasActiveApp = $appResult->num_rows > 0;
                            $checkStmt->close();
                        }

                        if ($hasActiveApp) {
                            $redirectPage = 'student_apply.php'; // لديه طلب مفتوح
                        } else {
                            $redirectPage = 'student_apply.php'; // لا يوجد طلب مفتوح
                        }
                        break;

                    case 5: // الادمن
                        $redirectPage = 'manager_overview.php';
                        break;

                    default:
                        // دور غير معروف
                        session_destroy();
                        $errorMsg = 'نوع الحساب غير معروف. يرجى التواصل مع الإدارة.';
                        break;
                }

                // إعادة التوجيه إذا لم تحدث أخطاء
                if (empty($errorMsg)) {
                    header('Location: ' . $redirectPage);
                    exit;
                }
            } else {
                $errorMsg = 'البريد الإلكتروني غير موجود في النظام.';
            }
        } else {
            $errorMsg = 'خطأ في الاتصال بقاعدة البيانات. حاول مرة أخرى.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة التدريب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">


    <style>
        input[type="email"], input[type="password"] {
            direction: rtl !important;
            text-align: right !important;
        }
        input[type="email"]::placeholder, input[type="password"]::placeholder {
            direction: rtl !important;
            text-align: right !important;
        }
    </style>
</head>

<body>
    <header class="header-bar d-flex justify-content-center align-items-center">
        <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="main-logo-img" />
    </header>

    <div class="main-bg d-flex justify-content-center align-items-center">
        <div class="card p-4 form-container" id="login-form-card">
            <div class="text-center mb-4">
                <h2 class="mb-2">تسجيل الدخول</h2>
                <p class="text-muted">نظام إدارة طلبات التدريب التعاوني</p>
            </div>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form id="login-form" method="post" action="" novalidate>
                <div class="mb-3">
                    <label for="loginEmail" class="form-label">
                        <i class="fas fa-envelope me-2"></i>البريد الإلكتروني
                    </label>
                    <input type="email" class="form-control custom-input" id="loginEmail" name="email" required
                        value="<?php echo $oldEmail; ?>" autocomplete="email" placeholder="البريد الإلكتروني">
                </div>

                <div class="mb-3">
                    <label for="loginPassword" class="form-label">
                        <i class="fas fa-lock me-2"></i>كلمة المرور
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control custom-input" id="loginPassword" name="password"
                            placeholder="أدخل كلمة المرور" required autocomplete="current-password">

                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                        <label class="form-check-label small" for="rememberMe">
                            تذكرني
                        </label>
                    </div>
                    <a href="forgot-password.php" class="text-decoration-none forgot-password-link small">
                        نسيت كلمة المرور؟
                    </a>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary signup-btn">
                        <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                    </button>
                </div>
            </form>

            <div class="text-center">
                <hr class="my-3">
                <p class="text-muted small">
                    ليس لديك حساب؟
                    <a href="signup.php" class="text-decoration-none signup-link">
                        إنشاء حساب جديد
                    </a>
                </p>
            </div>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إظهار/إخفاء كلمة المرور
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('loginPassword');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // التركيز على حقل البريد الإلكتروني عند التحميل
        document.addEventListener('DOMContentLoaded', function () {
            const emailInput = document.getElementById('loginEmail');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });

        // إضافة تأثيرات التحقق
        (function () {
            'use strict';
            const form = document.getElementById('login-form');

            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        })();

        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function () {
            // إضافة تأثيرات للمدخلات
            const inputs = document.querySelectorAll('.custom-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function () {
                    this.parentElement.style.borderColor = '#00bfff';
                });

                input.addEventListener('blur', function () {
                    if (!this.classList.contains('is-invalid') && !this.classList.contains('is-valid')) {
                        this.parentElement.style.borderColor = 'rgba(255, 255, 255, 0.4)';
                    }
                });
            });

            // تحسين زر إظهار/إخفاء كلمة المرور
            const togglePassword = document.getElementById('togglePassword');
            togglePassword.addEventListener('mouseenter', function () {
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
            });

            togglePassword.addEventListener('mouseleave', function () {
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
            });

            // إصلاح قوي للـ direction باستخدام JavaScript
            function forceRTLDirection() {
                const emailInput = document.getElementById('loginEmail');
                const passwordInput = document.getElementById('loginPassword');

                if (emailInput) {
                    emailInput.style.setProperty('direction', 'rtl', 'important');
                    emailInput.style.setProperty('text-align', 'right', 'important');
                    emailInput.setAttribute('dir', 'rtl');
                }

                if (passwordInput) {
                    passwordInput.style.setProperty('direction', 'rtl', 'important');
                    passwordInput.style.setProperty('text-align', 'right', 'important');
                    passwordInput.setAttribute('dir', 'rtl');
                }
            }

            // تطبيق الإصلاح فوراً وعند أي تغيير
            forceRTLDirection();

            // مراقبة التغييرات وإعادة تطبيق الإصلاح
            const observer = new MutationObserver(forceRTLDirection);
            observer.observe(document.body, {
                attributes: true,
                subtree: true,
                attributeFilter: ['style', 'class']
            });
        });
    </script>
</body>

</html>