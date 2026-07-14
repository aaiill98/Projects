<?php
// manager_overview.php — لوحة تحكم مدير التدريب (role_id = 1)
declare(strict_types=1);
session_start();

// التحقق من صلاحية المدير
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 5) {
    http_response_code(403);
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/connection.php';

// إعداد اللغة والتشفير
$conn->set_charset('utf8mb4');

// دالة مساعدة للتنسيق الآمن
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// جلب الإحصائيات العامة
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'in_dept_applications' => 0,
    'accepted_applications' => 0,
    'canceled_applications' => 0,
    'total_users' => 0,
    'total_departments' => 0,
    'applications_today' => 0
];

// إحصائية إجمالي الطلبات
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
if ($result) {
    $stats['total_applications'] = $result->fetch_assoc()['count'];
}

// إحصائيات حسب الحالة
$status_queries = [
    'pending_applications' => "SELECT COUNT(*) as count FROM applications WHERE status = 'pending'",
    'in_dept_applications' => "SELECT COUNT(*) as count FROM applications WHERE status = 'in_dept'",
    'accepted_applications' => "SELECT COUNT(*) as count FROM applications WHERE status = 'accepted'",
    'canceled_applications' => "SELECT COUNT(*) as count FROM applications WHERE status = 'canceled'"
];

foreach ($status_queries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $stats[$key] = $result->fetch_assoc()['count'];
    }
}

// إحصائية المستخدمين
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $stats['total_users'] = $result->fetch_assoc()['count'];
}

// إحصائية الأقسام
$result = $conn->query("SELECT COUNT(*) as count FROM training_departments");
if ($result) {
    $stats['total_departments'] = $result->fetch_assoc()['count'];
}

// طلبات اليوم
$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE DATE(submitted_at) = CURDATE()");
if ($result) {
    $stats['applications_today'] = $result->fetch_assoc()['count'];
}

// إحصائيات الأقسام (أكثر 5 أقسام طلباً)
$dept_stats = [];
$dept_query = "
    SELECT 
        td.name_ar as dept_name,
        COUNT(CASE WHEN a.routed_pref = 1 THEN 1 END) as pref1_count,
        COUNT(CASE WHEN a.routed_pref = 2 THEN 1 END) as pref2_count,
        COUNT(*) as total_requests
    FROM training_departments td
    LEFT JOIN applications a ON (
        (a.pref1_training_dept_id = td.id) OR 
        (a.pref2_training_dept_id = td.id)
    )
    GROUP BY td.id, td.name_ar
    HAVING total_requests > 0
    ORDER BY total_requests DESC
    LIMIT 5
";
$result = $conn->query($dept_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dept_stats[] = $row;
    }
}

// أحدث الطلبات (آخر 5)
$recent_applications = [];
$recent_query = "
    SELECT 
        a.id, a.full_name, a.submitted_at, a.status, a.stage,
        t1.name_ar as pref1_name,
        t2.name_ar as pref2_name,
        CASE 
            WHEN a.routed_pref = 1 THEN t1.name_ar
            WHEN a.routed_pref = 2 THEN t2.name_ar
            ELSE NULL
        END as routed_dept
    FROM applications a
    LEFT JOIN training_departments t1 ON t1.id = a.pref1_training_dept_id
    LEFT JOIN training_departments t2 ON t2.id = a.pref2_training_dept_id
    ORDER BY a.submitted_at DESC
    LIMIT 5
";
$result = $conn->query($recent_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}

// إحصائيات الأدوار
$role_stats = [];
$role_query = "
    SELECT r.name_ar, r.code, COUNT(u.id) as count
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    GROUP BY r.id, r.name_ar, r.code
    ORDER BY r.id
";
$result = $conn->query($role_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $role_stats[] = $row;
    }
}

// معلومات المدير المسجل الدخول
$manager_info = [
    'name' => $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'المدير',
    'email' => $_SESSION['email'] ?? $_SESSION['user_email'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم مدير التدريب - نظام إدارة التدريب</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #29327A;
            --secondary: #00D1B2;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #e9ecef;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .title-text h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .title-text p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--light);
            padding: 15px 20px;
            border-radius: 10px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
        }

        .user-info h3 {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 3px;
        }

        .user-info p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
        }

        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before { background: var(--danger); }
        .stat-card.info::before { background: var(--info); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-icon.primary { background: var(--primary); }
        .stat-icon.success { background: var(--success); }
        .stat-icon.warning { background: var(--warning); }
        .stat-icon.danger { background: var(--danger); }
        .stat-icon.info { background: var(--info); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }

        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_dept { background: #d4edda; color: #155724; }
        .status-accepted { background: #d1ecf1; color: #0c5460; }
        .status-canceled { background: #f8d7da; color: #721c24; }

        /* Department Stats */
        .dept-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            background: var(--light);
            margin-bottom: 10px;
        }

        .dept-name {
            font-weight: 600;
            color: var(--dark);
        }

        .dept-count {
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Action Buttons */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: var(--dark);
            border-color: var(--primary);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin: 0 auto 15px;
        }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .action-desc {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-header {
                padding: 20px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .title-text h1 {
                font-size: 1.5rem;
            }
        }

        /* Logout Button */
        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
            color: white;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <div class="header-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="title-text">
                        <h1>لوحة تحكم مدير التدريب</h1>
                        <p>نظام إدارة طلبات التدريب التعاوني</p>
                    </div>
                </div>
                <div class="header-user">
                    <div class="user-avatar">
                        <?= strtoupper(substr($manager_info['name'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <h3><?= e($manager_info['name']) ?></h3>
                        <p><?= e($manager_info['email']) ?></p>
                    </div>
                    <a href="index.php?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['total_applications'] ?></div>
                        <div class="stat-label">إجمالي الطلبات</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['pending_applications'] ?></div>
                        <div class="stat-label">في انتظار المراجعة</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['in_dept_applications'] ?></div>
                        <div class="stat-label">قيد المراجعة</div>
                    </div>
                    <div class="stat-icon info">
                        <i class="fas fa-eye"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['accepted_applications'] ?></div>
                        <div class="stat-label">الطلبات المقبولة</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['canceled_applications'] ?></div>
                        <div class="stat-label">الطلبات الملغاة</div>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?= $stats['applications_today'] ?></div>
                        <div class="stat-label">طلبات اليوم</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Applications -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">أحدث الطلبات</h2>
                    <a href="admin.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> عرض الكل
                    </a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>اسم المتقدم</th>
                                <th>الرغبة الأولى</th>
                                <th>الحالة</th>
                                <th>تاريخ التقديم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_applications)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #6c757d;">
                                        لا توجد طلبات حتى الآن
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_applications as $app): ?>
                                    <tr>
                                        <td><?= e($app['full_name']) ?></td>
                                        <td><?= e($app['pref1_name'] ?? 'غير محدد') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= e($app['status']) ?>">
                                                <?php
                                                $status_labels = [
                                                    'pending' => 'في الانتظار',
                                                    'in_dept' => 'قيد المراجعة',
                                                    'accepted' => 'مقبول',
                                                    'canceled' => 'ملغي'
                                                ];
                                                echo $status_labels[$app['status']] ?? $app['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?= date('Y/m/d H:i', strtotime($app['submitted_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">الأقسام الأكثر طلباً</h2>
                </div>
                <?php if (empty($dept_stats)): ?>
                    <p style="text-align: center; color: #6c757d;">لا توجد بيانات متاحة</p>
                <?php else: ?>
                    <?php foreach ($dept_stats as $dept): ?>
                        <div class="dept-item">
                            <div class="dept-name"><?= e($dept['dept_name']) ?></div>
                            <div class="dept-count"><?= $dept['total_requests'] ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">الإجراءات السريعة</h2>
            </div>
            <div class="actions-grid">
                <a href="admin.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="action-title">الطلبات المقبولة</div>
                    <div class="action-desc">عرض وإدارة جميع الطلبات المقبولة</div>
                </a>

                <a href="admin.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">إدارة المستخدمين</div>
                    <div class="action-desc">إضافة وإدارة مستخدمي النظام</div>
                </a>

                <a href="admin.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-title">التقارير والإحصائيات</div>
                    <div class="action-desc">عرض التقارير التفصيلية</div>
                </a>

                <a href="admin.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="action-title">إعدادات النظام</div>
                    <div class="action-desc">إدارة إعدادات النظام العامة</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        // تحديث الإحصائيات كل 5 دقائق
        setInterval(function() {
            location.reload();
        }, 300000);

        // إضافة تأثيرات تفاعلية
        document.querySelectorAll('.stat-card, .action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // عرض الوقت الحالي
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('ar-SA', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const timeElement = document.querySelector('.current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }

        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>