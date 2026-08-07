#🗳️Online Voting System

A web-based platform for conducting Class Representative (CR) elections at SRMU (Shri Ramswaroop Memorial University). Students register, complete a verified profile, and vote for candidates — while admins manage elections, verify student identities, and publish results.

<p align="center"> <img src="college-banner.png" alt="SRMU Online Voting System" width="600"> </p>
✨ Features
For Students
🔐 Secure registration & login — passwords hashed with password_hash(), prepared statements throughout to prevent SQL injection
🪪 Profile completion — students submit their name, age, roll number, ERP ID, course, section, and a selfie (captured live via webcam or uploaded from gallery)
✅ Admin-verified identity — profiles (and selfies) are manually reviewed and approved by an admin before a student can vote, keeping the election trustworthy
🗳️ Vote in elections — cast votes for CR candidates once verified
📊 View results — see live/published election results
✏️ Edit profile — update submitted details
🔑 Forgot password flow for account recovery
For Admins
🔐 Separate admin login, isolated from student authentication
🖼️ Review student photos/profiles before verifying an account
📋 Admin dashboard to manage students, candidates, and elections
📈 Manage & publish results
General
⏳ Global loading-screen overlay for a smoother page-transition experience
📱 Responsive form UI with password visibility toggles and live selfie capture (getUserMedia)
🛠️ Tech Stack
Layer	Technology
Backend	PHP (procedural, mysqli)
Database	MySQL / MariaDB
Frontend	HTML5, CSS3, vanilla JavaScript
Server	Apache (via XAMPP)
📁 Project Structure
online-voting/
├── admin-dashboard.php     # Admin control panel
├── admin-login.php         # Admin authentication
├── admin-logout.php        # Admin session termination
├── clear-session.php       # Helper to clear pending session data
├── college-banner.png      # Branding asset
├── college-logo.jpg        # Branding asset
├── dashboard.php           # Student dashboard
├── db.php                  # Database connection config
├── edit-profile.php        # Student profile editing
├── event-result.php        # Election results (per event)
├── event-vote.php          # Voting page for a specific event
├── forgot-password.php     # Password recovery flow
├── index.php                # Entry point / landing page
├── ir-vote.php              # Independent representative voting
├── loading.php               # Reusable loading-screen overlay component
├── login.php                  # Student login
├── logout.php                 # Student session termination
├── register.php               # Student registration
├── result.php                 # Overall results page
├── style.css                  # Global stylesheet
├── verify-form.php            # Student profile completion (photo + details)
├── view-photo.php             # Admin view of a student's uploaded photo
└── vote.php                   # Voting page

📝 Update this tree if your project structure differs — some file purposes above are inferred from filenames and should be double-checked against your actual code/comments.

⚙️ Setup & Installation
Prerequisites
XAMPP (or any Apache + PHP + MySQL stack)
PHP 7.4+ recommended
A modern browser (for webcam selfie capture support)
Steps
Clone the repository into your XAMPP htdocs folder:
bash
   git clone https://github.com/<your-username>/online-voting.git
   cd online-voting

Or place it directly at C:\xampp\htdocs\online-voting.

Start Apache & MySQL from the XAMPP Control Panel.
Create the database. Open phpMyAdmin, create a database (e.g. srmu_voting), and import your SQL schema file (add database.sql to the repo and reference it here).
Configure the database connection. Open db.php and update the credentials:
php
   <?php
   $conn = mysqli_connect("localhost", "root", "", "srmu_voting");
   if (!$conn) {
       die("Connection failed: " . mysqli_connect_error());
   }
Set up the uploads folder. Ensure an uploads/ directory exists in the project root with write permissions — this is where student selfies are stored.
bash
   mkdir uploads
Visit the app. Open your browser and go to:
   http://localhost/online-voting/
🚦 Usage Flow
Student registers via register.php (account created, unverified).
Student logs in via login.php.
Student completes their profile via verify-form.php — personal info, college info, and a selfie photo.
Admin reviews the submission (photo + details) from the admin dashboard and manually marks the student as verified.
Once verified, the student can cast their vote and later view results.

Admins access their own portal separately through admin-login.php → admin-dashboard.php.

🔒 Security Notes
All database queries use prepared statements (mysqli_prepare / mysqli_stmt_bind_param) to prevent SQL injection.
Passwords are stored using PHP's password_hash() / verified with password_verify() — never stored in plain text.
Student identity verification is manual (admin-reviewed) rather than automated, to keep the election process trustworthy for a small college deployment.
Uploaded photos are restricted to .jpg, .jpeg, and .png file types.

⚠️ Before deploying beyond a local/college network, consider adding CSRF protection, rate-limiting on login/registration, and HTTPS.

🚧 Future Improvements
 Add CSRF tokens to all forms
 Email notifications when a profile is verified/rejected
 Pagination on the admin dashboard for large student lists
 Export results as PDF/CSV
 Dockerize for easier setup
👤 Author

Ayan Akhtar Project submitted as part of a college coursework at SRMU.

📄 License

This project is for academic purposes. Feel free to fork and adapt it — consider adding an MIT License if you'd like others to reuse it freely.
