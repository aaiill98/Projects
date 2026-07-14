<?php
// forgot-password.php - صفحة استعادة كلمة المرور
declare(strict_types=1);
session_start();

require_once __DIR__ . '/connection.php';

// إعداد المتغيرات
$errorMsg = '';
$successMsg = '';
$oldEmail = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $oldEmail = $email;
    
    // التحقق من صحة البريد الإلكتروني
    if (empty($email)) {
        $errorMsg = 'يرجى إدخال البريد الإلكتروني';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'البريد الإلكتروني غير صحيح';
    } else {
        // البحث عن المستخدم في قاعدة البيانات
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // في بيئة الإنتاج، هنا ستقوم بإرسال رابط إعادة تعيين كلمة المرور
                // للبريد الإلكتروني للمستخدم
                
                // مؤقتاً، سنعرض رسالة نجاح
                $successMsg = 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني. يرجى التحقق من صندوق الوارد.';
                $oldEmail = ''; // مسح البريد بعد النجاح
                
                // هنا يمكنك إضافة منطق إرسال البريد الإلكتروني
                // مثال:
                // $reset_token = bin2hex(random_bytes(32));
                // حفظ التوكن في قاعدة البيانات مع وقت انتهاء صلاحية
                // إرسال بريد إلكتروني يحتوي على رابط إعادة التعيين
                
            } else {
                $errorMsg = 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني';
            }
            $stmt->close();
        } else {
            $errorMsg = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور - نظام إدارة التدريب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        /* تحسينات إضافية خاصة بصفحة استعادة كلمة المرور */
        .form-container {
            max-width: 450px;
        }
        
        .icon-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-icon {
            width: 80px;
            height: 80px;
            background: rgba(0, 191, 255, 0.1);
            border: 2px solid rgba(0, 191, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #00bfff;
            font-size: 2rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .help-text {
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .help-text i {
            color: #00bfff;
            margin-left: 0.5rem;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-to-login a {
            color: #00bfff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-to-login a:hover {
            color: #0099cc;
            text-decoration: underline;
        }
        
        .success-container {
            text-align: center;
            padding: 2rem 0;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: rgba(40, 167, 69, 0.1);
            border: 3px solid rgba(40, 167, 69, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: #28a745;
            font-size: 2.5rem;
            animation: successPulse 1s ease-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>

<body>
    <header class="header-bar d-flex justify-content-center align-items-center">
        <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="main-logo-img" />
    </header>

    <div class="main-bg d-flex justify-content-center align-items-center">
        <div class="card p-4 form-container" id="forgot-password-card">
            
            <?php if (!empty($successMsg)): ?>
                <!-- رسالة النجاح -->
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 style="color: #28a745; margin-bottom: 1rem;">تم الإرسال بنجاح!</h2>
                    <div class="alert alert-success" style="background-color: rgba(40, 167, 69, 0.15); border-color: rgba(40, 167, 69, 0.3); color: #28a745;">
                        <i class="fas fa-envelope me-2"></i>
                        <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="help-text" style="background: rgba(40, 167, 69, 0.1); border-color: rgba(40, 167, 69, 0.3);">
                        <i class="fas fa-info-circle" style="color: #28a745;"></i>
                        <strong>تعليمات مهمة:</strong><br>
                        • تحقق من صندوق الوارد وملف الرسائل غير المرغوب فيها<br>
                        • الرابط صالح لمدة 24 ساعة فقط<br>
                        • لا تشارك الرابط مع أي شخص آخر
                    </div>
                </div>
            <?php else: ?>
                <!-- نموذج استعادة كلمة المرور -->
                <div class="icon-container">
                    <div class="reset-icon">
                        <i class="fas fa-key"></i>
                    </div>
                </div>

                <div class="text-center mb-4">
                    <h2 class="mb-2">استعادة كلمة المرور</h2>
                    <p class="text-muted">أدخل بريدك الإلكتروني لإرسال رابط إعادة التعيين</p>
                </div>

                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    سنرسل لك رابطاً آمناً لإعادة تعيين كلمة المرور. تأكد من إدخال البريد الإلكتروني المرتبط بحسابك.
                </div>

                <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form id="forgot-password-form" method="post" action="" novalidate>
                    <div class="mb-4">
                        <label for="resetEmail" class="form-label">
                            <i class="fas fa-envelope me-2"></i>البريد الإلكتروني
                        </label>
                        <input
                            type="email"
                            class="form-control custom-input"
                            id="resetEmail"
                            name="email"
                            placeholder="أدخل بريدك الإلكتروني"
                            required
                            value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="email"
                        >
                        <div class="invalid-feedback">
                            يرجى إدخال بريد إلكتروني صحيح
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary signup-btn">
                            <i class="fas fa-paper-plane me-2"></i>إرسال رابط الاستعادة
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="back-to-login">
                <a href="login.php">
                    <i class="fas fa-arrow-right me-1"></i>العودة لتسجيل الدخول
                </a>
            </div>

            <hr class="my-3">
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    روابط إعادة التعيين آمنة ومشفرة
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التركيز على حقل البريد الإلكتروني عند التحميل
            const emailInput = document.getElementById('resetEmail');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }

            // إضافة تأثيرات التحقق
            (function() {
                'use strict';
                const form = document.getElementById('forgot-password-form');
                
                if (form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    });
                }
            })();

            // تحسين تجربة المستخدم للمدخلات
            const inputs = document.querySelectorAll('.custom-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#00bfff';
                });
                
                input.addEventListener('blur', function() {
                    if (!this.classList.contains('is-invalid') && !this.classList.contains('is-valid')) {
                        this.style.borderColor = 'rgba(255, 255, 255, 0.4)';
                    }
                });
            });

            // تأثير إضافي للأيقونة
            const resetIcon = document.querySelector('.reset-icon');
            if (resetIcon) {
                resetIcon.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                resetIcon.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            }

            // إعادة توجيه تلقائية بعد النجاح (اختياري)
            <?php if (!empty($successMsg)): ?>
            setTimeout(function() {
                // يمكن إضافة إعادة توجيه تلقائية هنا إذا أردت
                // window.location.href = 'login.php';
            }, 30000); // 30 ثانية
            <?php endif; ?>
        });
    </script>
</body>
</html>