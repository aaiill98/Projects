<?php
// admin.php — صفحة إدارة المستخدمين للنظام الجديد
declare(strict_types=1);
session_start();

// الاتصال بقاعدة البيانات
require_once __DIR__ . '/connection.php';
if ($conn->connect_errno) {
  http_response_code(500);
  echo "خطأ اتصال بقاعدة البيانات.";
  exit;
}
$conn->set_charset('utf8mb4');

// تهيئة CSRF Token بسيط
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// رسائل الحالة
$errors = [];
$success = null;

// جلب الأدوار والأقسام التدريبية لقوائم الاختيار
$roles = [];
$rolesSql = "SELECT id, code, name_ar FROM roles ORDER BY id ASC";
if ($res = $conn->query($rolesSql)) {
  while ($row = $res->fetch_assoc()) {
    $roles[] = $row;
  }
  $res->free();
} else {
  $errors[] = "تعذر جلب الأدوار.";
}

$training_departments = [];
$deptSql = "SELECT id, name_ar FROM training_departments ORDER BY name_ar ASC";
if ($res = $conn->query($deptSql)) {
  while ($row = $res->fetch_assoc()) {
    $training_departments[] = $row;
  }
  $res->free();
} else {
  $errors[] = "تعذر جلب الأقسام التدريبية.";
}

// عند الإرسال: إدخال مستخدم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // تحقق من CSRF
  $postedCsrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
    $errors[] = "طلب غير صالح.";
  }

  // استقبال المتغيرات
  $full_name = trim((string) ($_POST['full_name'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));
  $phone = trim((string) ($_POST['phone'] ?? ''));
  $password_raw = (string) ($_POST['password'] ?? '');
  $role_id = isset($_POST['role_id']) ? (int) $_POST['role_id'] : null;
  $training_department_id = isset($_POST['training_department_id']) ? (int) $_POST['training_department_id'] : null;
  $country_code = trim((string) ($_POST['country_code'] ?? 'SA'));
  $date_of_birth = trim((string) ($_POST['date_of_birth'] ?? ''));

  // التحقق البسيط
  if ($full_name === '' || mb_strlen($full_name) > 150) {
    $errors[] = "الاسم الكامل مطلوب وبحد أقصى 150 حرفًا.";
  }
  if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "البريد الإلكتروني غير صالح.";
  }
  if ($phone === '' || mb_strlen($phone) > 20) {
    $errors[] = "رقم الجوال مطلوب وبحد أقصى 20 خانة.";
  }
  if ($password_raw === '') {
    $errors[] = "كلمة المرور مطلوبة.";
  }
  if (empty($role_id) || !in_array($role_id, [1, 2, 3, 4])) {
    $errors[] = "الدور الوظيفي مطلوب.";
  }
  // القسم التدريبي مطلوب فقط لموظفي الأقسام (role_id = 3)
  if ($role_id === 3 && empty($training_department_id)) {
    $errors[] = "القسم التدريبي مطلوب لموظفي الأقسام.";
  }
  if ($country_code === '' || mb_strlen($country_code) > 2) {
    $errors[] = "رمز الدولة مطلوب (خانتين).";
  }
  // التاريخ بصيغة YYYY-MM-DD
  $dobDate = DateTime::createFromFormat('Y-m-d', $date_of_birth);
  $dobValid = $dobDate && $dobDate->format('Y-m-d') === $date_of_birth;
  if (!$dobValid) {
    $errors[] = "تاريخ الميلاد يجب أن يكون بصيغة YYYY-MM-DD.";
  }

  // تحقق من عدم تكرار البريد الإلكتروني
  if (!$errors) {
    $checkEmailSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkEmailSql);
    if ($checkStmt) {
      $checkStmt->bind_param("s", $email);
      $checkStmt->execute();
      $result = $checkStmt->get_result();
      if ($result->num_rows > 0) {
        $errors[] = "هذا البريد الإلكتروني مستخدم بالفعل.";
      }
      $checkStmt->close();
    }
  }

  // إذا لا توجد أخطاء، نفذ الإدخال
  if (!$errors) {
    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
    if ($password_hash === false) {
      $errors[] = "فشل توليد كلمة المرور.";
    } else {
      // إدخال المستخدم (training_department_id فقط إذا كان role_id = 3)
      $final_training_dept_id = ($role_id === 3) ? $training_department_id : null;
      
      $sql = "INSERT INTO users (full_name, email, phone, password_hash, role_id, training_department_id, country_code, date_of_birth)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $stmt = $conn->prepare($sql);
      if (!$stmt) {
        $errors[] = "تعذر تجهيز الاستعلام.";
      } else {
        $stmt->bind_param(
          "sssssiis",
          $full_name,
          $email,
          $phone,
          $password_hash,
          $role_id,
          $final_training_dept_id,
          $country_code,
          $date_of_birth
        );
        if ($stmt->execute()) {
          $success = "تم إضافة المستخدم بنجاح.";
          // إعادة إنشاء توكن جديد بعد نجاح العملية
          $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
          // تفريغ الحقول بعد الحفظ
          $_POST = [];
        } else {
          if ($conn->errno === 1062) {
            $errors[] = "هذا البريد موجود بالفعل.";
          } else {
            $errors[] = "حدث خطأ أثناء الحفظ: " . $conn->error;
          }
        }
        $stmt->close();
      }
    }
  }
}

// جلب المستخدمين مع الأدوار والأقسام للعرض
$users = [];
$listSql = "SELECT u.id, u.full_name, u.email, u.phone, u.country_code, u.date_of_birth, u.created_at,
                   r.name_ar AS role_name, r.code AS role_code,
                   td.name_ar AS training_department_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN training_departments td ON td.id = u.training_department_id
            ORDER BY u.created_at DESC";
if ($res = $conn->query($listSql)) {
  while ($row = $res->fetch_assoc()) {
    $users[] = $row;
  }
  $res->free();
}

// إحصائيات سريعة
$stats = [];
$statsSql = "SELECT r.name_ar, COUNT(*) as count 
             FROM users u 
             LEFT JOIN roles r ON r.id = u.role_id 
             GROUP BY u.role_id, r.name_ar 
             ORDER BY u.role_id";
if ($res = $conn->query($statsSql)) {
  while ($row = $res->fetch_assoc()) {
    $stats[] = $row;
  }
  $res->free();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>إدارة المستخدمين - نظام إدارة التدريب</title>
  <style>
    :root {
      --ink: #1b2430;
      --muted: #6b7280;
      --surface: #f7f9fc;
      --border: rgba(0, 0, 0, .12);
      --success: #1c8c55;
      --danger: #b42318;
      --primary: #29327A;
      --secondary: #00D1B2;
    }

    * {
      box-sizing: border-box
    }

    body {
      font-family: 'Cairo', 'Tajawal', 'Segoe UI', 'Arial', 'Tahoma', sans-serif;
      background-color: #f3f6fb;
      color: var(--ink);
      margin: 0;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 16px;
    }

    header {
      background: linear-gradient(180deg, var(--primary), #1a274d);
      color: #fff;
      border-bottom: 1px solid rgba(255, 255, 255, .15);
      padding: 20px 0;
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .logo-section {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .logo {
      width: 60px;
      height: 60px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .header-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
    }

    .header-subtitle {
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.9rem;
      margin: 0;
    }

    .stats-grid {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 1rem;
      min-width: 120px;
      text-align: center;
    }

    .stat-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--secondary);
    }

    .stat-label {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.8);
    }

    .panel {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
      padding: 1.5rem;
      margin-top: 20px;
    }

    .panel-header {
      border-bottom: 1px dashed var(--border);
      padding-bottom: 1rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    h1, h2 {
      margin: 0;
      font-size: 1.25rem;
      color: var(--ink);
    }

    .badge {
      background: #eef2ff;
      color: #243b78;
      padding: .4rem .8rem;
      border-radius: .75rem;
      font-size: .85rem;
      font-weight: 600;
    }

    .badge-success {
      background: #e9f7f1;
      color: var(--success);
    }

    .badge-info {
      background: #e3f2fd;
      color: #1976d2;
    }

    .muted {
      color: var(--muted);
      font-size: .9rem;
    }

    .alert {
      padding: 1rem;
      border-radius: 12px;
      margin: 1rem 0;
      border-left: 4px solid;
    }

    .alert-success {
      background: #e9f7f1;
      border-color: var(--success);
      color: var(--success);
    }

    .alert-danger {
      background: #fff2f2;
      border-color: var(--danger);
      color: var(--danger);
    }

    .grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(12, 1fr);
    }

    .col-3 {
      grid-column: span 12;
    }

    .col-4 {
      grid-column: span 12;
    }

    .col-6 {
      grid-column: span 12;
    }

    @media (min-width: 768px) {
      .col-3 {
        grid-column: span 3;
      }
      .col-4 {
        grid-column: span 4;
      }
      .col-6 {
        grid-column: span 6;
      }
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: .5rem;
      color: var(--ink);
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"],
    input[type="date"],
    select {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: .75rem;
      font-size: .95rem;
      transition: border-color 0.3s ease;
    }

    input:focus,
    select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(41, 50, 122, 0.1);
    }

    .form-note {
      font-size: 0.8rem;
      color: var(--muted);
      margin-top: 0.25rem;
    }

    .table-wrap {
      overflow: auto;
      border-radius: 12px;
      border: 1px solid var(--border);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 800px;
    }

    th, td {
      padding: 1rem 0.75rem;
      text-align: right;
      border-bottom: 1px solid var(--border);
    }

    thead th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--ink);
      white-space: nowrap;
    }

    tbody tr:hover {
      background: #f8fafc;
    }

    .actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      grid-column: 1 / -1;
      margin-top: 1rem;
    }

    .btn {
      background: #fff;
      border: 1px solid var(--border);
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
      background: var(--primary);
      border-color: var(--primary);
      color: white;
    }

    .btn-success {
      background: var(--success);
      border-color: var(--success);
      color: white;
    }

    .role-indicator {
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .role-A { background: #fef3c7; color: #92400e; }
    .role-B { background: #dbeafe; color: #1e40af; }
    .role-C { background: #dcfce7; color: #166534; }
    .role-D { background: #fce7f3; color: #be185d; }

    .back-link {
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      font-weight: 500;
    }

    .back-link:hover {
      color: white;
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        text-align: center;
      }
      
      .stats-grid {
        justify-content: center;
      }
      
      .actions {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="container">
      <div class="header-content">
        <div class="logo-section">
          <div class="logo">👥</div>
          <div>
            <h1 class="header-title">إدارة المستخدمين</h1>
            <p class="header-subtitle">نظام إدارة طلبات التدريب التعاوني</p>
          </div>
        </div>
        <div class="stats-grid">
          <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
              <div class="stat-number"><?= $stat['count'] ?></div>
              <div class="stat-label"><?= htmlspecialchars($stat['name_ar'] ?? 'غير محدد', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div>
          <a href="manager_overview.php" class="back-link">← العودة للوحة الرئيسية</a>
        </div>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="panel">
      <div class="panel-header">
        <h2>إنشاء مستخدم جديد</h2>
        <span class="badge">إدارة الوصول</span>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?>
            <div>• <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="" class="grid" novalidate>
        <input type="hidden" name="csrf_token"
          value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-4">
          <label for="full_name">الاسم الكامل</label>
          <input id="full_name" name="full_name" type="text" maxlength="150"
            value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="col-4">
          <label for="email">البريد الإلكتروني</label>
          <input id="email" name="email" type="email" maxlength="150"
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="col-4">
          <label for="phone">رقم الجوال</label>
          <input id="phone" name="phone" type="tel" maxlength="20"
            value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="col-3">
          <label for="role_id">الدور الوظيفي</label>
          <select id="role_id" name="role_id" required>
            <option value="" disabled <?= empty($_POST['role_id']) ? 'selected' : '' ?>>اختر الدور</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?= (int) $role['id'] ?>" <?= (isset($_POST['role_id']) && (int) $_POST['role_id'] === (int) $role['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($role['name_ar'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-3">
          <label for="training_department_id">القسم التدريبي</label>
          <select id="training_department_id" name="training_department_id">
            <option value="" <?= empty($_POST['training_department_id']) ? 'selected' : '' ?>>لا يوجد</option>
            <?php foreach ($training_departments as $dept): ?>
              <option value="<?= (int) $dept['id'] ?>" <?= (isset($_POST['training_department_id']) && (int) $_POST['training_department_id'] === (int) $dept['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($dept['name_ar'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-note">مطلوب فقط لموظفي الأقسام التدريبية</div>
        </div>

        <div class="col-3">
          <label for="country_code">رمز الدولة</label>
          <input id="country_code" name="country_code" type="text" maxlength="2"
            value="<?= htmlspecialchars($_POST['country_code'] ?? 'SA', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="col-3">
          <label for="date_of_birth">تاريخ الميلاد</label>
          <input id="date_of_birth" name="date_of_birth" type="date"
            value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="col-6">
          <label for="password">كلمة المرور</label>
          <input id="password" name="password" type="password" required>
          <div class="form-note">يُنصح باستخدام كلمة مرور قوية (8 أحرف على الأقل)</div>
        </div>

        <div class="actions">
          <button class="btn" type="reset">🗑️ تفريغ الحقول</button>
          <button class="btn btn-primary" type="submit">✅ إضافة المستخدم</button>
        </div>
      </form>
    </section>

    <section class="panel">
      <div class="panel-header">
        <h2>قائمة المستخدمين</h2>
        <span class="badge badge-info">إجمالي: <?= count($users) ?> مستخدم</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>الاسم الكامل</th>
              <th>البريد الإلكتروني</th>
              <th>الجوال</th>
              <th>الدور</th>
              <th>القسم التدريبي</th>
              <th>الدولة</th>
              <th>تاريخ الميلاد</th>
              <th>تاريخ الإنشاء</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$users): ?>
              <tr>
                <td colspan="9" class="muted" style="text-align:center;padding:2rem;">
                  📋 لا توجد بيانات مستخدمين
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int) $u['id'] ?></td>
                  <td><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <span class="role-indicator role-<?= htmlspecialchars($u['role_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($u['role_name'] ?? 'غير محدد', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($u['training_department_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['country_code'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($u['date_of_birth'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= date('Y/m/d H:i', strtotime($u['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <div class="panel-header">
        <h2>ملاحظات مهمة</h2>
        <span class="badge badge-success">إرشادات النظام</span>
      </div>
      <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
          <h4 style="color: var(--primary); margin: 0 0 0.5rem;">🏢 إدارة التدريب (A)</h4>
          <p style="margin: 0; font-size: 0.9rem;">لديهم صلاحية عرض جميع الطلبات والإحصائيات الشاملة</p>
        </div>
        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
          <h4 style="color: var(--primary); margin: 0 0 0.5rem;">📋 السكرتارية (B)</h4>
          <p style="margin: 0; font-size: 0.9rem;">يقومون بتوجيه الطلبات للأقسام المناسبة</p>
        </div>
        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
          <h4 style="color: var(--primary); margin: 0 0 0.5rem;">🎯 موظفو الأقسام (C)</h4>
          <p style="margin: 0; font-size: 0.9rem;">يراجعون ويقبلون/يرفضون الطلبات في أقسامهم التدريبية</p>
        </div>
        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px;">
          <h4 style="color: var(--primary); margin: 0 0 0.5rem;">🎓 الطلاب (D)</h4>
          <p style="margin: 0; font-size: 0.9rem;">يقدمون طلبات التدريب ويتابعون حالة طلباتهم</p>
        </div>
      </div>
      
      <div style="margin-top: 1.5rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
        <h4 style="color: #856404; margin: 0 0 0.5rem;">⚠️ تنبيهات مهمة</h4>
        <ul style="margin: 0; padding-right: 1.5rem; color: #856404;">
          <li>القسم التدريبي مطلوب فقط لموظفي الأقسام (الدور C)</li>
          <li>تأكد من صحة البريد الإلكتروني لأنه سيستخدم لتسجيل الدخول</li>
          <li>كلمة المرور يجب أن تكون قوية ومعقدة</li>
          <li>لا يمكن تعديل البيانات بعد الإنشاء من هذه الصفحة</li>
        </ul>
      </div>
    </section>
  </main>

  <script>
    // إخفاء/إظهار حقل القسم التدريبي حسب الدور المختار
    document.getElementById('role_id').addEventListener('change', function() {
      const roleId = parseInt(this.value);
      const deptField = document.getElementById('training_department_id').parentElement;
      
      if (roleId === 3) {
        deptField.style.display = 'block';
        document.getElementById('training_department_id').required = true;
      } else {
        deptField.style.display = 'none';
        document.getElementById('training_department_id').required = false;
        document.getElementById('training_department_id').value = '';
      }
    });

    // تطبيق القاعدة عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
      const roleSelect = document.getElementById('role_id');
      if (roleSelect.value) {
        roleSelect.dispatchEvent(new Event('change'));
      }
    });

    // تحسين تجربة المستخدم للنموذج
    document.querySelectorAll('input, select').forEach(element => {
      element.addEventListener('focus', function() {
        this.style.borderColor = 'var(--primary)';
      });
      
      element.addEventListener('blur', function() {
        this.style.borderColor = 'var(--border)';
      });
    });

    // تأكيد قبل تفريغ النموذج
    document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
      if (!confirm('هل أنت متأكد من تفريغ جميع الحقول؟')) {
        e.preventDefault();
      }
    });

    // تحسين عرض الجدول على الشاشات الصغيرة
    function adjustTableDisplay() {
      const table = document.querySelector('table');
      const container = document.querySelector('.table-wrap');
      
      if (window.innerWidth < 768) {
        container.style.fontSize = '0.85rem';
      } else {
        container.style.fontSize = '1rem';
      }
    }

    window.addEventListener('resize', adjustTableDisplay);
    adjustTableDisplay();
  </script>
</body>

</html>