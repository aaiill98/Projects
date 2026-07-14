<?php
include 'connection.php';

$selected_college = isset($_GET['college_id']) ? intval($_GET['college_id']) : null;

$colleges_res = mysqli_query($conn, "SELECT college_id, name_ar FROM colleges");

if ($selected_college) {
  $jobs_res = mysqli_query(
    $conn,
    "SELECT job_id, name_ar, image_path 
         FROM jobs 
         WHERE college_id = $selected_college"
  );
} else {
  $jobs_res = mysqli_query(
    $conn,
    "SELECT job_id, name_ar, image_path 
         FROM jobs"
  );
}
?>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>University Portal</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container">
      <a class="navbar-brand" href="#">Mrs. College Guide</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="home.html">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="jobs.php">Jobs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="apply.html">Apply</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.html">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.html">Contact</a>
          </li>
        </ul>
        <ul class="navbar-nav ms-3">
          <li class="nav-item">
            <a class="nav-link login-link" href="#">LOGIN</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-warning signup-button" href="#">SIGN UP</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Search Controls -->
  <section class="search-section">
    <div class="container">
      <form method="get" action="jobs.php" class="search-controls">
        <select name="college_id">
          <option value="">All Colleges</option>
          <?php while ($col = mysqli_fetch_assoc($colleges_res)): ?>
            <option value="<?= $col['college_id'] ?>" <?= ($col['college_id'] === $selected_college) ? 'selected' : '' ?>>
              <?= htmlspecialchars($col['name_ar']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <button type="submit">Search Jobs</button>
      </form>
    </div>
  </section>

  <!-- Job Results (hidden until search) -->
  <!-- Job Listings -->
  <section class="jobs-section">
    <div class="container">
      <div class="jobs-grid">
        <?php while ($job = mysqli_fetch_assoc($jobs_res)): ?>
          <div class="job-card">
            <img src="<?= htmlspecialchars($job['image_path']) ?>" alt="<?= htmlspecialchars($job['name_ar']) ?>">
            <h3><?= htmlspecialchars($job['name_ar']) ?></h3>
            <small>
              <a href="apply.html" class="uni-btn">
                Apply Now →
              </a>
            </small>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </section>

  <!-- Footer Section -->
  <section class="newsletter-section">
    <div class="container">
      <h2>Subscribe to our newsletter</h2>
      <p class="newsletter-text">
        Get expert advice for your journey to university delivered to your inbox each month. It’s short, and worthwhile
        – we promise!
      </p>
      <form class="newsletter-form">
        <input type="email" placeholder="Email address" required>
        <label>
          <input type="checkbox">
          I confirm I am over 16 and I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy
            Notice</a>.
        </label>
      </form>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-linkedin-in"></i></a>
      </div>
    </div>
  </section>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script.js"></script>
</body>

</html>