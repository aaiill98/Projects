<?php
// index.php
session_start();

// تسجيل الخروج إن طُلب
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? ($_SESSION['user_name'] ?? 'المستخدم') : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>تجمع الطائف الصحي</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div id="landing-page" class="page-container">
    <header class="header-bar d-flex justify-content-between align-items-center p-3">
      <div class="logo-placeholder"></div>
      <div class="logo">
        <img src="images/thc.png" alt="شعار تجمع الطائف الصحي" class="main-logo-img" />
      </div>

      <?php if (!$isLoggedIn): ?>
        <a href="login.php" class="btn btn-login">تسجيل الدخول</a>
      <?php else: ?>
        <div class="d-flex align-items-center gap-2">
          <span class="text-white small">مرحبًا، <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
          <a href="student_status.php" class="btn btn-signup">لوحة المتدرب</a>
          <a href="index.php?logout=1" class="btn btn-outline-light">تسجيل الخروج</a>
        </div>
      <?php endif; ?>
    </header>

    <main class="container flex-grow-1 d-flex flex-column justify-content-center text-center text-white">
      <section class="hero-section">
        <h1 class="hero-title">
          مرحبا بك في منصة التدريب التعاوني للتجمع الصحي
          <br />
          عن تجمع الطائف الصحي
        </h1>
      </section>

      <div class="row justify-content-center mt-5">
        <div class="col-lg-3 col-md-4 mb-4">
          <div class="info-card">
            <h3>من نحن</h3>
            <p class="card-text">
              تجمع الطائف الصحي هو ربط المنشآت الصحية المختلفة في المنطقة الواحدة من أجل تقديم خدمات صحية شاملة.
              لتكون منظومة مؤسسية موحدة ومتكاملة تسمى التجمع الصحى وهو الشكل المبدئي لمنظمة رعاية المسؤول.
            </p>
          </div>
        </div>
        <div class="col-lg-3 col-md-4 mb-4">
          <div class="info-card">
            <h3>رؤيتنا</h3>
            <p class="card-text">نرتقي معًا للرعاية الصحية للتجمع</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-4 mb-4">
          <div class="info-card">
            <h3>التحول الرقمي</h3>
            <p class="card-text">
              التحول الرقمي هو عملية الاستفادة من التقنيات الرقمية لتعزيز رعاية المرضى والمشاركة،
              وتحسين الكفاءة التشغيلية، وتحسين اتصال البيانات.
            </p>
          </div>
        </div>
      </div>

      <section class="program-section text-center p-4">
        <h2 class="section-title">برنامج التدريب التعاوني</h2>
        <p class="section-description">
          يقدم مجمع الطائف الصحي برنامجاً تدريبياً تعاونياً مصمماً لتمكين طلاب الجامعات من خلال تحويل معارفهم الأكاديمية
          إلى خبرة عملية، من خلال المشاركة الفعالة في مختلف أقسام المجمع، تتاح للمتدربين فرصة تطوير مهاراتهم المهنية،
          واكتساب فهم أعمق لقطاع الرعاية الصحية، والمساهمة في مشاريع عملية تعزز جاهزيتهم لسوق العمل بعد التخرج.
        </p>

        <?php if (!$isLoggedIn): ?>
          <a href="signup.php" class="btn btn-signup">تسجيل</a>
        <?php else: ?>
          <a href="student_apply.php" class="btn btn-signup">ابدأ التقديم الآن</a>
        <?php endif; ?>
      </section>
    </main>

    <footer class="main-footer mt-auto">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-md-6">
            <p class="footer-text">جميع الحقوق محفوظة للصحة الرقمية 2025</p>
          </div>
          <div class="col-md-6 text-md-end text-center">
            <div class="social-icons">
              <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
              <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
              <a href="#" class="social-icon"><i class="fab fa-pinterest"></i></a>
              <a href="#" class="social-icon"><i class="fab fa-linkedin"></i></a>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
</body>

</html>
