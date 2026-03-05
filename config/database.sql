-- ============================================================
-- KCA CHART — Complete Database Schema v2
-- Real KCA University data: Schools, Courses, Clubs, 2026 Calendar
-- All seed passwords are bcrypt of 'password'
-- ============================================================

CREATE DATABASE IF NOT EXISTS kcachart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kcachart;

-- ─────────────────────────────────────────
-- USERS  (school column added)
-- ─────────────────────────────────────────
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(30) UNIQUE,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(160) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('student','staff','admin') DEFAULT 'student',
    school        VARCHAR(160) DEFAULT NULL,
    department    VARCHAR(200) DEFAULT NULL,
    course        VARCHAR(200) DEFAULT NULL,
    year_of_study TINYINT DEFAULT 1,
    study_mode    ENUM('day','evening','weekend','distance','online') DEFAULT 'day',
    bio           TEXT,
    avatar        VARCHAR(255) DEFAULT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    is_online     TINYINT(1) DEFAULT 0,
    last_seen     DATETIME DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- SPACES
-- ─────────────────────────────────────────
CREATE TABLE spaces (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(120) UNIQUE NOT NULL,
    description TEXT,
    type        ENUM('academic','club','administrative','co-curricular','professional') DEFAULT 'academic',
    school      VARCHAR(160) DEFAULT NULL,
    banner      VARCHAR(255) DEFAULT NULL,
    created_by  INT NOT NULL,
    is_private  TINYINT(1) DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE space_members (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    space_id  INT NOT NULL,
    user_id   INT NOT NULL,
    role      ENUM('member','moderator','admin') DEFAULT 'member',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_membership (space_id, user_id),
    FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- POSTS
-- ─────────────────────────────────────────
CREATE TABLE posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    space_id   INT DEFAULT NULL,
    content    TEXT NOT NULL,
    tag        ENUM('academic','social','administrative','urgent','event','announcement','career') DEFAULT 'academic',
    attachment VARCHAR(255) DEFAULT NULL,
    is_pinned  TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE SET NULL
);

CREATE TABLE post_reactions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    reaction   ENUM('like','celebrate','insightful') DEFAULT 'like',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (post_id, user_id, reaction),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    parent_id  INT DEFAULT NULL,
    content    TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- EVENTS (category + campus columns added)
-- ─────────────────────────────────────────
CREATE TABLE events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    category      ENUM('academic','social','administrative','career','club','exam','deadline') DEFAULT 'academic',
    event_date    DATE NOT NULL,
    start_time    TIME NOT NULL,
    end_time      TIME DEFAULT NULL,
    location      VARCHAR(200),
    campus        ENUM('Town Campus','Western Campus','Kitengela Campus','Online','All Campuses') DEFAULT 'Town Campus',
    space_id      INT DEFAULT NULL,
    created_by    INT NOT NULL,
    banner        VARCHAR(255) DEFAULT NULL,
    max_attendees INT DEFAULT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_id)   REFERENCES spaces(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE CASCADE
);

CREATE TABLE event_rsvps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    user_id    INT NOT NULL,
    status     ENUM('going','maybe','not_going') DEFAULT 'going',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- MESSAGES
-- ─────────────────────────────────────────
CREATE TABLE conversations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE conversation_participants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id         INT NOT NULL,
    last_read       DATETIME DEFAULT NULL,
    UNIQUE KEY unique_participant (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
);

CREATE TABLE messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id       INT NOT NULL,
    content         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    sent_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE
);

-- ─────────────────────────────────────────
-- NOTIFICATIONS
-- ─────────────────────────────────────────
CREATE TABLE notifications (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    actor_id       INT DEFAULT NULL,
    type           ENUM('post_like','comment','new_post','event_reminder','space_invite','message','announcement') NOT NULL,
    reference_id   INT DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    message        TEXT NOT NULL,
    is_read        TINYINT(1) DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- FOLLOWS
-- ─────────────────────────────────────────
CREATE TABLE follows (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    follower_id  INT NOT NULL,
    following_id INT NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
);


-- ═══════════════════════════════════════════
-- SEED DATA — Real KCA University Data
-- ═══════════════════════════════════════════

-- ─────────────────────────────────────────
-- USERS  (all passwords = 'password')
-- LOGIN: Student ID OR email (both supported)
-- ─────────────────────────────────────────
INSERT INTO users (student_id, full_name, email, password_hash, role, school, department, course, year_of_study) VALUES
('ADMIN001','System Administrator','admin@kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin','ICT Department','Directorate of ICT',NULL,0),

('STF/001','Dr. James Kariuki','j.kariuki@kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'staff','School of Technology','Department of Software Development & Information Systems',NULL,0),

('STF/002','Prof. Grace Njoroge','g.njoroge@kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'staff','School of Business','Department of Accounting, Finance & Economics',NULL,0),

('STF/003','Dr. Faith Atieno','f.atieno@kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'staff','School of Education, Arts & Social Sciences','Department of Educational & Psychological Studies',NULL,0),

('KCA/2023/001','Brian Otieno','b.otieno@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Technology','Department of Software Development & Information Systems',
 'BSc in Software Development',3),

('KCA/2023/002','Sharon Wanjiku','s.wanjiku@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Technology','Department of Networks & Applied Computing',
 'BSc in Information Technology',2),

('KCA/2024/011','Kevin Mutua','k.mutua@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Technology','Department of Software Development & Information Systems',
 'BSc in Data Science',1),

('KCA/2022/078','Fatuma Hassan','f.hassan@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Technology','Department of Software Development & Information Systems',
 'BSc in Information Security & Forensics',4),

('KCA/2023/055','Derrick Omondi','d.omondi@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Technology','Department of Networks & Applied Computing',
 'Diploma in Information Technology',2),

('KCA/2023/033','Amina Mwangi','a.mwangi@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Business','Department of Business Administration',
 'Bachelor of Commerce',3),

('KCA/2024/044','Dennis Kamau','d.kamau@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Business','Department of Accounting, Finance & Economics',
 'BSc in Actuarial Science',1),

('KCA/2022/099','Mary Akinyi','m.akinyi@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Business','Department of Business Administration',
 'Bachelor of Procurement & Logistics',4),

('KCA/2023/071','Linda Chebet','l.chebet@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Education, Arts & Social Sciences','Department of Performing Arts, Film & Media',
 'BA in Journalism & Digital Media',2),

('KCA/2024/082','Peter Njuguna','p.njuguna@students.kcau.ac.ke',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student','School of Education, Arts & Social Sciences','Department of Educational & Psychological Studies',
 'BA in Counselling Psychology',1);


-- ─────────────────────────────────────────
-- SPACES — Real KCA clubs, departments, admin
-- ─────────────────────────────────────────
INSERT INTO spaces (name, slug, description, type, school, created_by) VALUES

-- ADMINISTRATIVE (IDs 1-3)
('Registrar Announcements','registrar-announcements',
 'Official updates, exam notices, and deadlines from the KCA University Registrar Office. All students must follow this space.',
 'administrative',NULL,1),

('Dean of Students Office','dean-of-students',
 'Welfare updates, SAKU news, disciplinary notices, and student support services from the Dean of Students.',
 'administrative',NULL,1),

('Finance & Fees Notices','finance-fees',
 'Fee payment deadlines, HELB updates, fee structures, bursaries, and financial aid information.',
 'administrative',NULL,1),

-- SCHOOL OF TECHNOLOGY — Academic (IDs 4-12)
('BSc Software Development','bsc-software-dev',
 'Community for all BSc Software Development students. Share notes, code snippets, GitHub repos, and project ideas.',
 'academic','School of Technology',2),

('BSc Information Technology','bsc-information-technology',
 'BSc IT students space — unit discussions, lab practicals, assignments, and resources.',
 'academic','School of Technology',2),

('BSc Data Science','bsc-data-science',
 'Data Science students. Python, R, Machine Learning, data visualisation, and Kenyan datasets.',
 'academic','School of Technology',2),

('BSc Information Security & Forensics','bsc-info-security',
 'Cybersecurity students space. CTF challenges, ethical hacking resources, CISA exam prep.',
 'academic','School of Technology',2),

('BSc Applied Computing','bsc-applied-computing',
 'Applied Computing students — algorithms, cloud computing, system design.',
 'academic','School of Technology',2),

('BSc Gaming & Animation Technology','bsc-gaming-animation',
 'Game development, 3D modelling, Unity, Unreal Engine — creative tech community.',
 'academic','School of Technology',2),

('Diploma in Information Technology','diploma-it',
 'Diploma IT students. Foundation units, practicals, and peer support network.',
 'academic','School of Technology',2),

('Bachelor of Business Information Technology (BBIT)','bbit',
 'BBIT students — bridging business and technology. ERP systems, e-commerce, and business analytics.',
 'academic','School of Technology',2),

('MSc Data Science & Analytics (Postgraduate)','msc-data-analytics',
 'Postgraduate space for MSc Data Science, MSc Data Analytics, and MSc Information Systems Management students.',
 'academic','School of Technology',2),

-- SCHOOL OF BUSINESS — Academic (IDs 13-19)
('Bachelor of Commerce','bachelor-of-commerce',
 'B.Com community — management, marketing, entrepreneurship, and business strategy discussions.',
 'academic','School of Business',3),

('BSc Actuarial Science','bsc-actuarial-science',
 'Actuarial Science students. IFoA/SOA exam prep, ASSK activities, actuarial tables, and career guidance.',
 'academic','School of Business',3),

('Bachelor of Procurement & Logistics','bsc-procurement-logistics',
 'Supply chain, procurement, and logistics. CPSP-K exam support, KISM resources, and industry insights.',
 'academic','School of Business',3),

('BSc Forensic Accounting','bsc-forensic-accounting',
 'Forensic Accounting and fraud examination. CFFE resources, case studies, and career pathways.',
 'academic','School of Business',3),

('BSc Economics & Statistics','bsc-economics-statistics',
 'Economics and Statistics students. KNBS data, econometrics, policy analysis, and research.',
 'academic','School of Business',3),

('MBA & Postgraduate Business','mba-postgrad-business',
 'MBA and business postgraduate students. Leadership, research methodology, and networking.',
 'academic','School of Business',3),

('ACCA, CPA & Professional Accountancy','acca-cpa-professional',
 'ACCA (UK), CPA Kenya, CIMA, and ATD students. Past papers, study groups, and KASNEB exam tips.',
 'professional','School of Business',3),

-- SEASS — Academic (IDs 20-23)
('BA Journalism & Digital Media','ba-journalism',
 'Journalism and Digital Media students. Campus journalism, content creation, media projects, and internships.',
 'academic','School of Education, Arts & Social Sciences',4),

('BA Counselling Psychology','ba-counselling',
 'Counselling Psychology students and peer support community. Mental health awareness and practicum resources.',
 'academic','School of Education, Arts & Social Sciences',4),

('BA Criminology & Criminal Justice','ba-criminology',
 'Criminology students. Case studies, criminal law, corrections systems, and career pathways.',
 'academic','School of Education, Arts & Social Sciences',4),

('Bachelor of Education (B.Ed)','bachelor-education',
 'B.Ed Arts and Early Childhood Education. Teaching practice support, pedagogy, and curriculum resources.',
 'academic','School of Education, Arts & Social Sciences',4),

-- CLUBS & SOCIETIES (IDs 24-38)
('KCAU Tech Club','tech-club',
 'KCA University flagship technology club. Hackathons, coding bootcamps, tech talks, and innovation challenges. All students welcome!',
 'club',NULL,2),

('KCA Cybersecurity Club','cybersecurity-club',
 'Ethical hacking, CTF competitions, cyber defense workshops, and security awareness. Built by BSc InfoSec students.',
 'club',NULL,2),

('Google Developer Student Club (GDSC) KCA','gdsc-kca',
 'Official GDSC at KCA University — Google tech workshops, Solution Challenge, and community projects.',
 'club',NULL,2),

('Microsoft Student Learn Community','microsoft-slc',
 'Microsoft Azure, Power Platform, Microsoft 365 certifications, and student workshops.',
 'club',NULL,2),

('Drama Club','drama-club',
 'Theatrical productions, creative writing, improvisation, and performing arts. All schools welcome.',
 'club',NULL,4),

('Music Club','music-club',
 'Choir, instruments, beat production, and campus performances. All genres and skill levels welcome.',
 'club',NULL,4),

('Wildlife Club','wildlife-club',
 'Conservation, nature walks, environmental awareness, and wildlife photography. Protecting Kenya heritage.',
 'club',NULL,1),

('Journalism Club','journalism-club',
 'Campus journalism, content creation, student newsletter, and media projects. Open to all students.',
 'club',NULL,4),

('Peer Counselling Club','peer-counselling',
 'Student-led peer support and mental health awareness. Trained peer counsellors offering confidential support.',
 'club',NULL,4),

('Chama Cha Kiswahili','chama-cha-kiswahili',
 'Kiswahili language, culture, literature, and arts. Debates, poetry, and cultural events across campus.',
 'club',NULL,4),

('Actuarial Students Association of Kenya (ASSK) — KCA Chapter','assk-kca',
 'IFoA/SOA exam preparation, industry talks, and networking for actuarial science students.',
 'club',NULL,3),

('KCA Debate Society','debate-society',
 'Competitive debating, public speaking, and critical thinking. Inter-university tournaments and campus debates.',
 'club',NULL,1),

('KCA Sports & Athletics','sports-athletics',
 'Football, rugby, basketball, hockey, badminton, table tennis, karate, taekwondo, and inter-university competitions.',
 'co-curricular',NULL,1),

('KCA Entrepreneurship Hub','entrepreneurship-hub',
 'StartUp Hub for KCA student entrepreneurs. Pitch competitions, mentorship, and business plan support.',
 'co-curricular',NULL,3),

('SAKU — Students Association of KCA University','saku',
 'Your official student government. SAKU represents all students on academic, welfare, and social matters.',
 'administrative',NULL,1),

-- CAREER & PROFESSIONAL (IDs 39-40)
('Career Centre & Internships','career-centre',
 'Official Career Centre space. Job listings, internships, CV clinics, career fairs, and employer connections.',
 'co-curricular',NULL,1),

('CPA, CS & KASNEB Professional Studies','cpa-cs-kasneb',
 'CPA Kenya, Certified Secretaries (CS), ATD, and other KASNEB exam students. Past papers and study schedules.',
 'professional',NULL,3);


-- ─────────────────────────────────────────
-- SPACE MEMBERSHIPS
-- ─────────────────────────────────────────
INSERT INTO space_members (space_id, user_id, role) VALUES
-- Registrar (1) — everyone
(1,1,'admin'),(1,2,'member'),(1,3,'member'),(1,4,'member'),
(1,5,'member'),(1,6,'member'),(1,7,'member'),(1,8,'member'),
(1,9,'member'),(1,10,'member'),(1,11,'member'),(1,12,'member'),
(1,13,'member'),(1,14,'member'),
-- Dean of Students (2)
(2,1,'admin'),(2,5,'member'),(2,6,'member'),(2,10,'member'),(2,13,'member'),
-- Finance (3)
(3,1,'admin'),(3,5,'member'),(3,10,'member'),(3,11,'member'),
-- BSc Software Dev (4)
(4,2,'admin'),(4,5,'member'),(4,8,'member'),
-- BSc IT (5)
(5,2,'admin'),(5,6,'member'),(5,9,'member'),
-- BSc Data Science (6)
(6,2,'admin'),(6,7,'member'),
-- BSc InfoSec (7)
(7,2,'admin'),(7,8,'member'),
-- Bachelor of Commerce (13)
(13,3,'admin'),(13,10,'member'),(13,12,'member'),
-- Actuarial Science (14)
(14,3,'admin'),(14,11,'member'),
-- Procurement (15)
(15,3,'admin'),(15,12,'member'),
-- BA Journalism (20)
(20,4,'admin'),(20,13,'member'),
-- BA Counselling (21)
(21,4,'admin'),(21,14,'member'),
-- Tech Club (24)
(24,2,'admin'),(24,5,'member'),(24,6,'member'),(24,7,'member'),(24,8,'member'),(24,9,'member'),(24,10,'member'),
-- Cybersecurity Club (25)
(25,2,'admin'),(25,5,'member'),(25,8,'member'),
-- GDSC (26)
(26,2,'admin'),(26,5,'member'),(26,6,'member'),(26,7,'member'),
-- Drama Club (28)
(28,4,'admin'),(28,13,'member'),(28,14,'member'),
-- Wildlife Club (30)
(30,1,'admin'),(30,10,'member'),(30,14,'member'),
-- Journalism Club (31)
(31,4,'admin'),(31,13,'member'),
-- Peer Counselling (32)
(32,4,'admin'),(32,14,'member'),(32,10,'member'),
-- ASSK (34)
(34,3,'admin'),(34,11,'member'),
-- SAKU (38)
(38,1,'admin'),(38,5,'member'),(38,10,'member'),(38,13,'member'),
-- Career Centre (39)
(39,1,'admin'),(39,5,'member'),(39,6,'member'),(39,10,'member'),(39,11,'member'),(39,12,'member'),(39,13,'member');


-- ─────────────────────────────────────────
-- POSTS — Real KCA context
-- ─────────────────────────────────────────
INSERT INTO posts (user_id, space_id, content, tag, is_pinned) VALUES
(1,1,'WELCOME TO KCA CHART — the official KCA University campus community platform for the January–April 2026 Trimester. Use this space to connect with fellow students, get campus updates, and collaborate. All students must follow the Registrar Announcements space for official notices.','announcement',1),

(1,1,'JANUARY–APRIL 2026 TRIMESTER KEY DATES: Classes began Jan 8 (Day/Evening) and Jan 10 (Weekend). Deadline to Add/Drop Units: Jan 23. CAT 1: Feb 23 – Mar 1. CAT 2: Mar 9. Coursework marks published: Mar 16. Final Exams begin: Mar 23. Final Exams end: Apr 17. Online unit registration for May trimester: Apr 7–24.','administrative',1),

(1,1,'SUPPLEMENTARY EXAMS: Supplementary and Special Examinations are scheduled February 16–20, 2026. Eligible students must confirm their timetable via the student portal. Contact the Registrar Office (Block A, Room 3) for queries before February 13.','urgent',0),

(1,3,'FEES REMINDER: Students with outstanding fee balances risk being barred from the Final Trimester Examinations (beginning March 23). HELB disbursements for this trimester should reflect by end of February. Visit the Finance Office (Ground Floor, Main Building) or call 020-2412342 for assistance.','urgent',1),

(2,4,'Welcome BSc Software Development students! I am Dr. Kariuki, your academic coordinator this trimester. Assignment 1 for CSD 301 (Web Systems Development) is due by January 18. All unit materials are on the LMS under your enrolled units. Reach me via email j.kariuki@kcau.ac.ke or during office hours (Mon/Wed 2–4pm, Room ST-204).','academic',0),

(2,24,'The 2nd KCA University Tech Expo is confirmed for March 6, 2026! The Tech Club is calling all innovators. Project submissions open February 15. Categories: AI & ML, Web/App Development, Cybersecurity, IoT, and Creative Tech. Industry judges from Safaricom, Microsoft, and Andela confirmed. Prizes worth KSH 50,000!','event',0),

(3,13,'B.Com and Business students — the School of Business Career Workshop runs March 12. Final-year students in Commerce, Procurement, Finance, and Economics are prioritised for the CV clinic and employer sessions. Register on the Career Centre portal by March 5. Spots are limited to 60 students.','career',0),

(4,20,'Journalism and Digital Media Year 2 and 3 — attendance at Public Lecture I (April 10) is compulsory. The lecture is part of your Media Law & Ethics coursework assessment. You will be required to submit a 500-word reflective piece within 5 days of the lecture. More details on LMS.','academic',0),

(5,4,'Hey BSc Software Dev Year 3! Anyone forming study groups for the CSD 401 (Software Engineering) CAT 1 coming up Feb 23? I have Larman UML notes and past papers from 2023–2025. Let us meet in the library Thursday 4pm. Comment below if you are in!','academic',0),

(5,24,'KCA Tech Club — who is building something for the Tech Expo on March 6? I am working on a student resource management app (Django + React) and need 2 teammates: frontend dev and data/ML. DM me or comment here. Let us represent KCA well!','social',0),

(6,5,'BSc IT Year 2 — sharing the VLAN and subnetting lab topology we covered in class Monday. Key points for the practical report: remember CIDR notation for all subnet masks, document your router configuration commands, and include a network diagram. Report due next Thursday.','academic',0),

(7,6,'Built my first end-to-end ML pipeline using Python (scikit-learn + pandas) on the KNBS county economic dataset. Predicting regional GDP from agricultural and education indicators — accuracy at 78% so far. Anyone else working on similar projects? Happy to share notebooks. GitHub link in my profile.','academic',0),

(8,25,'Cybersecurity Club: PicoCTF 2026 registration is open until March 1. Last year KCA entered 3 teams — let us do better this year! I will run a prep session on web exploitation (XSS, SQLi, IDOR) and binary basics next Wednesday 5pm, Computer Lab 2. Bring your laptops. Kali Linux or any Linux VM.','social',0),

(10,13,'B.Com crew — sharing 2020–2025 KASNEB ATD past papers for those preparing for professional exams alongside the degree. Very useful for units like Financial Accounting and Business Law. Drop your email in the comments and I will share the Google Drive link.','academic',0),

(11,34,'ASSK KCA Chapter meeting this Friday 4pm, Innovation Lab Room 2. Mock CT1 (Financial Mathematics) paper — IFoA April 2026 sitting. Bring actuarial tables. We have a practicing actuary from Jubilee Insurance joining virtually to answer career questions. All Actuarial Science students welcome!','social',0),

(13,31,'Started a student blog documenting KCA campus life in the January 2026 trimester — from lecture halls to clubs to Nairobi street food near campus. Looking for contributors: writers, photographers, and short video creators. Good portfolio content. Message me if interested!','social',0),

(14,32,'Reminder from the Peer Counselling Club: exam stress is real and manageable. We meet every Wednesday 1–2pm at the Student Welfare Office (Block B, Ground Floor). All conversations are confidential. You can also reach any of our trained counsellors individually. Your mental health matters.','social',0),

(5,NULL,'Life hack for KCA students: the Library on the 4th floor opens at 7am and closes 9pm on weekdays during trimester. Quiet study floors are 4 and 5. Each student gets 20 free black-and-white print pages per trimester via the library printing system. Log in with your student portal credentials.','social',0),

(10,NULL,'Food recommendation near Town Campus: there is a great local spot on University Way that does a solid KSH 120 lunch (rice, stew, vegetables). Better than the cafeteria queue at peak hours. The KCA family always has the best local knowledge — drop your recommendations below!','social',0),

(13,NULL,'Welcome to all new students joining KCA in the January 2026 trimester! Orientation was January 6 and Matriculation is January 30. Senior students: let us make them feel at home. New students: do not be shy — post your questions here, reach out in the spaces, and explore the clubs. The KCA family is warm!','social',0);


-- ─────────────────────────────────────────
-- EVENTS — Real 2026 Academic Calendar
-- ─────────────────────────────────────────
INSERT INTO events (title, description, category, event_date, start_time, end_time, location, campus, created_by) VALUES

('Supplementary & Special Examinations',
 'Supplementary and Special Examinations for eligible students from the previous trimester. Check your exam timetable on the student portal. Bring student ID and exam card. Arrive 15 minutes early.',
 'exam','2026-02-16','08:00:00','17:00:00','Examination Halls','All Campuses',1),

('CAT 1 — All Units (Jan/Apr Trimester)',
 'Continuous Assessment Test 1 for all registered units. This CAT contributes to your final coursework mark. Confirm your unit registration on the student portal before this period. Check unit timetables with your lecturers.',
 'exam','2026-02-23','08:00:00',NULL,'All Lecture Halls','All Campuses',1),

('2nd KCA University Tech Expo',
 'The 2nd annual KCA University Tech Expo — showcasing student and staff innovation. Hackathon challenge, industry speaker sessions, startup pitches, and networking. Categories: AI & ML, Web/App, Cybersecurity, IoT, and Creative Tech. Industry judges from Safaricom, Microsoft, and Andela. Prizes: KSH 50,000.',
 'academic','2026-03-06','09:00:00','17:00:00','Main Hall & Innovation Lab, Town Campus','Town Campus',2),

('CAT 2 — All Units (Jan/Apr Trimester)',
 'Continuous Assessment Test 2 for all units. Prepare thoroughly — combined with CAT 1 this makes up 30% of your final grade.',
 'exam','2026-03-09','08:00:00',NULL,'All Lecture Halls','All Campuses',1),

('School of Business Career Workshop & CV Clinic',
 'Career development workshop for final-year business students. CV review, mock interviews, and employer networking sessions. Employers confirmed from Deloitte, KCB, and Kenya Pipeline. Register on Career Centre portal by March 5.',
 'career','2026-03-12','09:00:00','16:00:00','Main Auditorium & Career Centre','Town Campus',3),

('IP Bootcamp & Workshop',
 'Intellectual Property Bootcamp covering patents, copyright, trademarks, and IP strategy for researchers and student innovators. Organized by the Directorate of Special Projects. Open to all students and staff.',
 'academic','2026-03-17','08:30:00','17:00:00','Innovation Lab, Town Campus','Town Campus',1),

('Career Fair 2026',
 'Annual KCA University Career Fair. 40+ employers from tech, finance, consulting, media, and public sector. Bring printed CVs and professional attire. Free CV review. Also features a graduate school fair for those considering postgraduate studies.',
 'career','2026-03-13','10:00:00','16:00:00','KCA Amphitheatre, Town Campus','Town Campus',1),

('Final Trimester Examinations Begin — Jan/Apr 2026',
 'Final examinations for the January–April 2026 trimester commence. All students must have a valid exam card, cleared fees, and no outstanding disciplinary matters. Arrive 15 minutes before your exam. No access after distribution of papers.',
 'exam','2026-03-23','08:00:00',NULL,'Examination Halls — All Campuses','All Campuses',1),

('Good Friday — University Closed',
 'Public Holiday. University closed. No examinations or classes scheduled.',
 'administrative','2026-04-03','00:00:00',NULL,'All Campuses','All Campuses',1),

('Easter Monday — University Closed',
 'Public Holiday. University closed.',
 'administrative','2026-04-06','00:00:00',NULL,'All Campuses','All Campuses',1),

('Project Defense — Undergraduate Final Year',
 'Final year undergraduate project defense presentations. Submit final project documents to supervisors at least one week prior. Smart dress code. Assessment panel includes external examiners.',
 'academic','2026-04-07','09:00:00','17:00:00','Department Seminar Rooms, Town Campus','Town Campus',2),

('Academic Writing & Research Workshop — Western Campus',
 'Academic writing and research methodology workshop for undergraduate and postgraduate students at the Western Campus, Kisumu. Covers literature review, citation, and research design.',
 'academic','2026-04-07','09:00:00','17:00:00','Seminar Room, Western Campus','Western Campus',1),

('Public Lecture I — January/April Trimester',
 'First KCA University Public Lecture for 2026. Speaker and topic to be announced on the university website and noticeboards. Attendance mandatory for Year 2 and 3 Journalism students (part of Media Law coursework).',
 'academic','2026-04-10','14:00:00','17:00:00','Main Auditorium, Town Campus','Town Campus',1),

('Final Trimester Examinations End — Jan/Apr 2026',
 'Last day of final examinations for the January–April 2026 trimester. Results publication schedule will be announced after marking.',
 'exam','2026-04-17','08:00:00','17:00:00','Examination Halls — All Campuses','All Campuses',1),

('Start of May 2026 Trimester',
 'The May–August 2026 Trimester begins. New student reporting: May 5. Orientation: May 6. Continuing students: May 7. Day/Evening classes begin May 8. Weekend classes begin May 9. Ensure your unit pre-registration (April 7–24) is complete.',
 'academic','2026-05-04','08:00:00',NULL,'All Campuses','All Campuses',1),

('Taxnovation Summit 2026',
 'Annual Taxation and Innovation Summit organized by the School of Business. Features Kenya Revenue Authority representatives, tax professionals, and student innovation presentations. Open to all students. CPD points for professionals.',
 'academic','2026-05-20','09:00:00','17:00:00','Main Auditorium, Town Campus','Town Campus',3),

('Matriculation Ceremony — May 2026 Intake',
 'Official Matriculation Ceremony for new students joining in May 2026. Smart dress code required. Parents and guardians welcome. Ceremony followed by guided campus tour for new students.',
 'academic','2026-05-29','10:00:00','13:00:00','Main Auditorium, Town Campus','Town Campus',1),

('20th KCA University Graduation Day',
 'The 20th Graduation Ceremony of KCA University. Congratulations to all graduating students! Collect your gown from the student services office at least 3 days before. Guests limited to 2 per graduate. Full programme on the university website.',
 'academic','2026-07-03','09:00:00','14:00:00','Main Auditorium, Town Campus','Town Campus',1),

('Research Colloquium 2026',
 'Annual Research Colloquium where staff and postgraduate students present ongoing research. Open to all KCA community. A great opportunity to learn about KCA research projects and network with researchers.',
 'academic','2026-07-24','09:00:00','17:00:00','Conference Hall, Town Campus','Town Campus',1),

('Creative Arts Expo 2026',
 'Annual showcase of student work in film, performing arts, digital media, music, and creative writing. Open to the public. Organized by SEASS, Drama Club, and Music Club. Entrance free for KCA students with student ID.',
 'social','2026-07-16','10:00:00','18:00:00','Main Hall & Outdoor Amphitheatre, Town Campus','Town Campus',4),

('Final Trimester Examinations — May/Aug 2026',
 'Final examinations for the May–August 2026 trimester begin. Valid exam card and cleared fees required.',
 'exam','2026-07-27','08:00:00',NULL,'Examination Halls — All Campuses','All Campuses',1),

('Start of September 2026 Trimester',
 'September–December 2026 Trimester begins. New student reporting: Sep 7. Orientation: Sep 8. Continuing students: Sep 9. Day/Evening classes begin Sep 10. Weekend classes begin Sep 12.',
 'academic','2026-09-01','08:00:00',NULL,'All Campuses','All Campuses',1),

('University Library Week 2026',
 'Annual Library Week — workshops on research databases (JSTOR, EBSCOhost), citation tools (Zotero, Mendeley), and information literacy. Orientation sessions for new students. Visit the library daily for activities.',
 'academic','2026-09-14','09:00:00','17:00:00','University Library, 4th Floor Main Building','Town Campus',1),

('5th KCA University Innovation & Industry Summit',
 'KCA flagship annual event — Innovation and Industry Summit. Keynote speakers, student innovation showcase, startup pitches, investor sessions, and networking. Industry partners from tech, finance, healthcare, and public sector. All students strongly encouraged to attend.',
 'academic','2026-10-28','09:00:00','18:00:00','Main Hall & Exhibition Grounds, Town Campus','Town Campus',1),

('21st KCA University Graduation Ceremony',
 '21st Graduation Ceremony. Final date to apply for graduation: October 23, 2026. Apply on the student portal before the deadline. Collect gown at least 3 days before ceremony.',
 'academic','2026-11-27','09:00:00','14:00:00','Main Auditorium, Town Campus','Town Campus',1),

('Final Trimester Examinations Begin — Sep/Dec 2026',
 'Final examinations for the September–December 2026 trimester. Cleared fees and valid exam card required. Last exam: December 18.',
 'exam','2026-11-23','08:00:00',NULL,'Examination Halls — All Campuses','All Campuses',1),

-- CLUB EVENTS
('Tech Club — Tech Expo Project Kickoff Meeting',
 'Planning and kickoff for the 2nd KCA Tech Expo (March 6). Bring your project ideas, form teams, and meet mentors. Pizza provided! Project categories: AI & ML, Web/App, Cybersecurity, IoT, Creative Tech. All SoT students welcome.',
 'club','2026-02-27','16:00:00','18:00:00','Innovation Lab, Ground Floor','Town Campus',2),

('KCA Cybersecurity Club — PicoCTF 2026 Prep Session',
 'Preparation session for PicoCTF 2026. Topics: web exploitation (XSS, SQLi), binary basics, reverse engineering, and OSINT. Beginner friendly — bring laptop with Kali Linux or any Linux VM.',
 'club','2026-03-04','17:00:00','19:00:00','Computer Lab 2, Block C','Town Campus',2),

('ASSK KCA — CT1 Financial Mathematics Mock Paper',
 'Mock paper session for IFoA CT1 (Financial Mathematics) — April 2026 sitting. Bring actuarial tables. Guest speaker: practising actuary from Jubilee Insurance (virtual). All Actuarial Science students welcome.',
 'club','2026-02-28','16:00:00','18:00:00','Innovation Lab Room 2','Town Campus',3),

('Peer Counselling Club — Exam Stress Management Week',
 'Exam Stress Awareness Week at KCA. Daily drop-in sessions, group discussions, wellness activities, and professional counsellor sessions. Theme: Study Smart, Live Well. Open to all students throughout the week.',
 'social','2026-03-02','08:00:00','17:00:00','Student Welfare Office & Outdoor Plaza','Town Campus',4),

('GDSC KCA — Google Solution Challenge 2026 Kickoff',
 'Google Developer Student Club KCA kickoff for the 2026 Solution Challenge. Theme: Build with AI using Google technology for local and global SDG problems. Form teams of 2–4. Free Google Cloud credits for participants.',
 'club','2026-03-10','16:00:00','18:30:00','Innovation Lab, Ground Floor','Town Campus',2);


-- ─────────────────────────────────────────
-- RSVP SEED DATA
-- ─────────────────────────────────────────
INSERT INTO event_rsvps (event_id, user_id, status) VALUES
(3,5,'going'),(3,6,'going'),(3,7,'going'),(3,8,'going'),(3,9,'going'),(3,10,'going'),
(7,5,'going'),(7,6,'going'),(7,10,'going'),(7,11,'going'),(7,12,'going'),(7,13,'going'),
(19,10,'going'),(19,11,'going'),(19,12,'going'),
(20,13,'going'),(20,14,'going'),
(27,5,'going'),(27,6,'going'),(27,8,'going'),
(28,5,'going'),(28,7,'going'),(28,8,'going'),
(29,11,'going'),
(30,14,'going'),(30,10,'going');


-- ─────────────────────────────────────────
-- SAMPLE COMMENTS
-- ─────────────────────────────────────────
INSERT INTO comments (post_id, user_id, content) VALUES
(9,6,'I am in for the study group! Thursday 4pm works. I found some solid Larman UML diagrams on Lucidchart that match what we did in class. Sharing in the BSc Software space.'),
(9,7,'Same here. Also bringing the activity diagrams from the CSD 401 past paper 2024. Library 4th floor quiet zone has enough space for 6 people.'),
(10,6,'Count me in for frontend! I have been working with React + Tailwind for the last 3 months. DM sent.'),
(10,8,'I can handle the backend and security layer. Already have a Django REST framework setup. Let us sync Saturday morning.'),
(19,5,'Yes! Did not know about the free printing. Does it cover A4 only or can we print A3 for engineering diagrams?'),
(19,9,'I think it is A4 only and black and white. Colour printing is KSH 20 per page at the library desk.');


-- ─────────────────────────────────────────
-- FOLLOWS
-- ─────────────────────────────────────────
INSERT INTO follows (follower_id, following_id) VALUES
(5,2),(5,6),(5,7),(5,8),
(6,5),(6,2),(6,9),
(7,5),(7,6),
(8,5),(8,2),
(10,3),(10,11),(10,12),
(13,4),(13,14),
(14,13),(14,4);

-- ═══════════════════════════════════════════
-- ADVERTISEMENT BANNERS
-- ═══════════════════════════════════════════
CREATE TABLE IF NOT EXISTS banners (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255)  NOT NULL,
    description  TEXT          DEFAULT NULL,
    image        VARCHAR(255)  NOT NULL,
    link_url     VARCHAR(500)  DEFAULT NULL,
    link_label   VARCHAR(100)  DEFAULT 'Learn More',
    placement    ENUM('feed','sidebar','events','spaces') DEFAULT 'feed',
    is_active    TINYINT(1)    DEFAULT 1,
    start_date   DATE          DEFAULT NULL,
    end_date     DATE          DEFAULT NULL,
    clicks       INT           DEFAULT 0,
    views        INT           DEFAULT 0,
    created_by   INT           DEFAULT NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
