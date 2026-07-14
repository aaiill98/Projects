CREATE TABLE colleges (
  college_id INT AUTO_INCREMENT PRIMARY KEY,
  name_ar VARCHAR(100) NOT NULL
);

CREATE TABLE jobs (
  job_id INT AUTO_INCREMENT PRIMARY KEY,
  college_id INT NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  FOREIGN KEY (college_id) REFERENCES colleges(college_id)
);

CREATE TABLE job_apps (
  app_id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  cv_path VARCHAR(255) NOT NULL,
  message TEXT,
  FOREIGN KEY (job_id) REFERENCES jobs(job_id)
);

INSERT INTO colleges (name_ar) VALUES
('علوم الحاسوب'),
('الطب'),
('الهندسة');

INSERT INTO jobs (college_id, name_ar, image_path) VALUES
(1, 'مطور برمجيات', 'images/job1.jpg'),
(1, 'محلل نظم', 'images/job2.jpg'),
(2, 'ممرض سريري', 'images/job3.jpg'),
(2, 'فني مختبر', 'images/job4.jpg'),
(3, 'مهندس ميكانيكا', 'images/job5.jpg'),
(3, 'مهندس كهرباء', 'images/job6.jpg');

INSERT INTO job_apps (job_id, name, email, phone, cv_path, message) VALUES
(1, 'أحمد محمد', 'ahmed.sa@example.com', '0501234567', 'uploads/ahmed_cv.pdf', 'لدي خبرة في تطوير البرمجيات.'),
(4, 'سارة علي', 'sarah.sa@example.com', '0557654321', 'uploads/sarah_cv.pdf', 'خبرة عملية في المختبرات.'),
(6, 'خالد عبدالله', 'khaled.sa@example.com', '0531122334', 'uploads/khaled_cv.pdf', 'متخصص في الهندسة الكهربائية.');

UPDATE jobs
SET image_path = 'images/university1.png';
