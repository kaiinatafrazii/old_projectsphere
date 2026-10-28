# ProjectSphere – Student Project Showcase & Evaluation Portal

> **Academic Final Year Project for Diploma / Degree in Computer Engineering / Information Technology**  
> Built with **PHP, MySQL, HTML5, CSS3, Bootstrap 5, and JavaScript**.  
> **100% Native Code – No AI/ML APIs, No complex frameworks, Beginner-friendly & fully explainable.**

---

## 📋 Table of Contents
1. [Project Overview](#project-overview)
2. [User Roles & Permissions](#user-roles--permissions)
3. [Evaluation & Automatic Ranking System (100 Marks)](#evaluation--automatic-ranking-system)
4. [Source Code Protection Policy](#source-code-protection-policy)
5. [Default Login Credentials](#default-login-credentials)
6. [XAMPP Installation & Setup Guide](#xampp-installation--setup-guide)
7. [Database Architecture & Schema](#database-architecture--schema)
8. [Folder Structure](#folder-structure)
9. [Viva / Presentation Guide for Students](#viva--presentation-guide-for-students)

---

## 1. Project Overview

**ProjectSphere** is a centralized college web application that streamlines the academic project lifecycle. Final-year students submit capstone projects (synopsis, problem statement, objectives, features, screenshots, documentation PDF, and private source code archives). Faculty evaluators review submissions, verify private source code, award marks according to a 100-mark standardized college rubric, and provide constructive feedback.

The portal automatically computes overall and category rankings, publishing a transparent leaderboard while strictly shielding student source code from unauthorized downloads.

---

## 2. User Roles & Permissions

| Role | Access Permissions | Restricted From |
| :--- | :--- | :--- |
| **Student** | • Register & login<br>• Submit project with documentation & screenshots<br>• Edit pending project drafts<br>• View review status (Pending / Approved / Rejected)<br>• View marks breakdown & faculty feedback<br>• Manage student profile | • Accessing or downloading source code of other students<br>• Accessing faculty admin portal<br>• Editing projects after evaluation |
| **Teacher / Admin** | • Dedicated faculty login portal (`/admin/login.php`)<br>• View 6-metric dashboard statistics<br>• Filter & search all submissions<br>• Download private source code ZIP for inspection<br>• Approve or reject submissions with remarks<br>• Input marks (Innovation, Functionality, UI, Tech, Docs)<br>• Manage project categories (CRUD)<br>• View student rosters & submission counts | • Restricted by session checks to faculty accounts |
| **Student Viewer / Public** | • Browse approved projects catalog<br>• Search by keyword, category, or technology<br>• Sort by latest submissions or rank leaderboard<br>• View project synopsis, features, and demo links<br>• View faculty score breakdown and feedback | • Source code ZIP archives<br>• Private Git repository links<br>• Database credentials and admin logs |

---

## 3. Evaluation & Automatic Ranking System

Projects are assessed against a **100-mark standardized academic rubric**:

| Criteria | Maximum Marks | Evaluation Focus |
| :--- | :---: | :--- |
| **Innovation & Uniqueness** | 20 Marks | Originality of idea, societal relevance, creativity |
| **System Functionality** | 30 Marks | Working modules, error handling, completeness |
| **UI / UX & Responsive Design** | 20 Marks | Visual aesthetics, intuitive navigation, mobile responsiveness |
| **Technology Stack Usage** | 15 Marks | Appropriate language choices, clean database design |
| **Presentation & Documentation** | 15 Marks | Clarity of PDF report, synopsis, demo video |
| **TOTAL** | **100 Marks** | **Automatically calculated in real-time** |

### Automatic Ranking Engine (`includes/functions.php -> recalculate_rankings()`)
- Whenever a faculty member inputs or updates an evaluation, the system executes `recalculate_rankings($pdo)`.
- It queries all approved, evaluated projects sorted by `total_score DESC, evaluated_at ASC`.
- It computes:
  1. **Overall Rank** (Rank #1, #2, #3...) with proper tie handling.
  2. **Category Rank** (Rank within Web Development, Mobile, IoT, etc.).
- Updates the `rankings` table instantly.

---

## 4. Source Code Protection Policy

A core requirement of ProjectSphere is academic integrity:
1. **Private Submissions:** Source code archives (`.zip`) are saved into `uploads/private-code/`.
2. **Access Control:** Only authenticated admins (`is_admin() === true`) have download links inside `admin/review-project.php`.
3. **Public Views:** Student viewers only see the project synopsis, features, technologies, screenshots, and public demo video links.

---

## 5. Default Login Credentials

### Faculty / Admin Account
- **Portal URL:** `http://localhost/projectsphere/admin/login.php` or `http://localhost/projectsphere/login.php` (Choose Teacher tab)
- **Username / Email:** `admin@projectsphere.edu`
- **Password:** `admin123`

### Sample Student Accounts
- **Student Portal:** `http://localhost/projectsphere/login.php`
- **Password for all sample students:** `student123`

| Student Name | Roll Number | Email Address | Submitted Project |
| :--- | :--- | :--- | :--- |
| **Rahul Sharma** | `DCS-2023-01` | `rahul@college.edu` | Smart Library Management (Rank #1) |
| **Priya Patel** | `DCS-2023-14` | `priya@college.edu` | Automated Attendance QR (Rank #2) |
| **Vikram Singh** | `DCS-2023-42` | `vikram@college.edu` | Network Packet Sniffer (Rank #3) |
| **Amit Kumar** | `DCS-2023-28` | `amit@college.edu` | Campus Placement Portal (Rank #4) |
| **Neha Verma** | `DCS-2023-35` | `neha@college.edu` | IoT Smart Agriculture (Rank #5) |

---

## 6. XAMPP Installation & Setup Guide

### Step 1: Copy Project Folder to XAMPP
Copy the entire `projectsphere` folder into your XAMPP web root:
```
C:\xampp\htdocs\projectsphere
```

### Step 2: Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 3: Setup the Database (2 Options)

#### Option A: One-Click Web Installer (Recommended & Fastest)
1. Open your browser and navigate directly to:
   ```text
   http://localhost/projectsphere/install.php
   ```
2. Click **"Install & Populate Database"**.
3. The script automatically connects, creates `projectsphere`, executes the SQL file, verifies all 10 tables, and loads sample records with zero manual configuration.

#### Option B: Manual Import via phpMyAdmin
1. Open your browser and visit: `http://localhost/phpmyadmin/`
2. Click **New** in the left sidebar and enter database name: `projectsphere` (Collation: `utf8mb4_unicode_ci`), then click **Create**.
3. Select the `projectsphere` database, click the **Import** tab at the top.
4. Click **Choose File** and select:
   ```text
   C:\xampp\htdocs\projectsphere\database\projectsphere.sql
   ```
5. Click **Import** at the bottom. You will see a green success notification.

### Step 4: Run the Application
Open your web browser and navigate to:
```
http://localhost/projectsphere/
```

### Hosting Database Configuration
For the configured InfinityFree database, `backend/core/db.php` uses:
- **Host:** `sql207.infinityfree.com`
- **Database:** `if0_43297350_projectsphere`
- **Username:** `if0_43297350`

Set the database password as the `PROJECTSPHERE_DB_PASSWORD` environment variable in the hosting environment. Do not put the password in source control. The application will report a configuration error if this variable is not set. This configuration connects to the hosting database even when running locally; the local XAMPP database setup above is not used unless you change `backend/core/db.php`.

### Step 5: Configure Password Reset Emails (Gmail)
Password reset uses PHP's `mail()` function and XAMPP Sendmail. To enable it:
1. In `C:\xampp\sendmail\sendmail.ini`, set `smtp_server=smtp.gmail.com`, `smtp_port=587`, and `smtp_ssl=auto`.
2. Set `auth_username` to your Gmail address and `auth_password` to a Google App Password (enable Google 2-Step Verification first; enter the App Password without spaces). Do not use your regular Gmail password or commit the App Password.
3. In `C:\xampp\php\php.ini`, set `sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"` and `sendmail_from` to the same Gmail address.
4. Restart Apache from the XAMPP Control Panel.
5. Use **Forgot password?** on the login screen. The six-digit code expires after 10 minutes and is limited to five verification attempts.

The reset page creates its reset-code table automatically on an existing database. The schema also includes the table for fresh database installs.

---

## 7. Database Architecture & Schema

The database consists of **10 normalized tables**:

1. **`users`**: Authentication credentials (`id`, `username`, `email`, `password`, `role` [admin/student], `status`).
2. **`students`**: Academic profile (`id`, `user_id`, `roll_no`, `full_name`, `department`, `semester`, `phone`, `bio`).
3. **`admins`**: Faculty profile (`id`, `user_id`, `full_name`, `department`, `designation`, `phone`).
4. **`project_categories`**: Domains (`id`, `name`, `slug`, `description`, `icon`).
5. **`projects`**: Submission repository (`id`, `student_id`, `category_id`, `title`, `slug`, `short_description`, `problem_statement`, `objectives`, `features`, `technologies`, `demo_url`, `thumbnail_image`, `documentation_file`, `private_source_code_file`, `status` [pending/approved/rejected]).
6. **`project_team_members`**: Dynamic team roster (`id`, `project_id`, `member_name`, `roll_no`, `role`).
7. **`project_images`**: Multi-image gallery screenshots (`id`, `project_id`, `image_path`, `caption`).
8. **`evaluations`**: 100-mark rubric scores (`id`, `project_id`, `admin_id`, `innovation_score`, `functionality_score`, `ui_design_score`, `tech_usage_score`, `presentation_score`, `total_score`, `feedback_text`, `evaluated_at`).
9. **`feedback`**: Chronological faculty remarks log (`id`, `project_id`, `admin_id`, `comment`, `created_at`).
10. **`rankings`**: Pre-calculated leaderboard cache (`id`, `project_id`, `category_id`, `total_score`, `overall_rank`, `category_rank`).

---

## 8. Folder Structure

```
projectsphere/
├── index.php                 # Root entrypoint (seamlessly routes to frontend/)
├── .htaccess                 # Unified URL rewrite engine & security rules
├── README.md                 # Project documentation & setup manual
│
├── frontend/                 # 🌐 CLIENT & USER INTERFACE
│   ├── index.php             # Public homepage & hero showcase
│   ├── browse.php            # Search & filter approved projects catalog
│   ├── project-details.php   # Detailed project view, rubric scores & faculty feedback
│   ├── search.php            # Quick search redirect handler
│   ├── login.php             # Unified Student & Evaluator Login
│   ├── register.php          # Student registration portal
│   ├── logout.php            # Session termination
│   ├── privacy.php           # Privacy policy draft
│   ├── terms.php             # Terms of service draft
│   ├── 404.php               # Custom 404 page
│   ├── robots.php            # Dynamic robots.txt
│   ├── sitemap.php           # Dynamic XML sitemap generator
│   │
│   ├── admin/                # Faculty Admin Portal
│   │   ├── login.php         # Dedicated admin sign-in
│   │   ├── dashboard.php     # Statistics, analytics & quick actions
│   │   ├── projects.php      # Project approval workflow & filters
│   │   ├── review-project.php# Project verification screen
│   │   ├── evaluate.php      # 100-mark rubric grading form
│   │   ├── rankings.php      # Leaderboard management & manual recalculation
│   │   ├── categories.php    # Category CRUD operations
│   │   ├── students.php      # Student directory & management
│   │   ├── feedback.php      # Communication & remarks
│   │   ├── profile.php       # Evaluator account settings
│   │   └── download-source.php# Secure authenticated ZIP source code download
│   │
│   ├── student/              # Student Workspace
│   │   ├── dashboard.php     # Personal overview & submission counter
│   │   ├── submit-project.php# Multi-section capstone submission form
│   │   ├── edit-project.php  # Edit pending project draft
│   │   ├── my-projects.php   # Student submission portfolio
│   │   ├── my-evaluations.php# Marks breakdown & faculty notes
│   │   └── profile.php       # Student academic profile & password reset
│   │
│   ├── project/              # Route compatibility
│   │   └── details.php       # Forwarder to project-details.php
│   │
│   └── assets/               # Static Frontend Assets
│       ├── css/style.css     # Design system, glassmorphism & responsive layouts
│       ├── js/main.js        # Client validation, live previews & interactions
│       └── images/           # SVG illustrations, logos & project sample visuals
│
└── backend/                  # ⚙️ SERVER, DATABASE & API LOGIC
    ├── install.php           # One-click database installation & migration wizard
    │
    ├── core/                 # Shared Backend Services & Layouts
    │   ├── .htaccess         # Deny unauthorized directory browsing
    │   ├── db.php            # PDO MySQL connection handler & error fallback
    │   ├── auth.php          # Session security, role guards & auth helpers
    │   ├── functions.php     # Auto-routing base_url(), upload security & ranking engine
    │   ├── header.php        # Global HTML header with SEO tags
    │   ├── footer.php        # Global footer & scripts
    │   ├── admin-navbar.php  # Faculty sidebar navigation
    │   └── student-navbar.php# Student sidebar navigation
    │
    ├── api/                  # REST JSON Endpoints
    │   ├── config.php        # JSON headers, session guards & response helpers
    │   ├── auth.php          # Login / session API
    │   ├── projects.php      # Projects CRUD & status API
    │   ├── evaluations.php   # Marks evaluation API
    │   ├── rankings.php      # Leaderboard API
    │   ├── categories.php    # Categories API
    │   ├── students.php      # Students API
    │   ├── feedback.php      # Remarks API
    │   └── stats.php         # Analytics & counters API
    │
    ├── database/             # Database Schemas & Migrations
    │   ├── .htaccess         # Block direct HTTP access
    │   └── projectsphere.sql # Complete 10-table schema with realistic seed data
    │
    └── uploads/              # File Storage
        ├── .htaccess         # Direct PHP execution blocked
        ├── documents/        # Project documentation PDFs
        ├── project-images/   # Screenshots & project thumbnails
        └── private-code/     # Shielded private source code ZIP archives (.htaccess secured)
```

Private source archives are downloaded through `frontend/admin/download-source.php`, which requires a faculty session. The `backend/core/`, `backend/database/`, and `backend/uploads/private-code/` directories also deny direct HTTP access.

When presenting this project to external college examiners:
1. **Explain the Architecture:** "We followed a modular PHP architecture using PDO prepared statements to guard against SQL Injection and `password_hash()` with Bcrypt to secure credentials."
2. **Demonstrate Role Separation:** Log in as a student to show the submission process, then switch to the Faculty Portal to download the private source code, award rubric marks, and watch the platform rank calculate automatically.
3. **Highlight the Code Privacy Feature:** Emphasize that while peer projects can be browsed for learning ideas and documentation, the actual source code remains strictly confidential for examination integrity.
