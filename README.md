# SIMAD Certificates Hub

An institutional certificate issuance, customization, verification, and management platform engineered for **SIMAD University**.

![SIMAD University](simad_university_logo.png)

---

## 🌟 Key Features

1. **Brand Identity & Precision Credentialing**:
   - Built with SIMAD University's official brand colors (`#21a249`, `#5e9fd4`, `#ffffff`, `#000000`).
   - Drag-and-drop / Slider-based visual Certificate Customization Studio (interactive coordinate controls for Recipient Name, Certificate ID, Conferred Issue Date, and QR Code).
   - High-fidelity 300 DPI print canvas ensuring exact A4 landscape PDF and PNG exports.

2. **Public Verification & Self-Service Portal**:
   - Dedicated Public Verification Ledger (`/public/verify.php`) with SHA-256 cryptographic document fingerprints and active/revoked status badges.
   - Public Lookup Portal (`/public/public_certificate_download.php`) allowing attendees to find, preview, and download their certificates by registered email.
   - High-contrast, scannable QR codes linking directly to institutional verification records.
   - Mobile-first, responsive layouts with graceful text wrapping.

3. **Batch Operations & Import Engine**:
   - Native Excel (`.xlsx` & `.xls`) and CSV import engine with client-side SheetJS live previews and zero-dependency PHP backend parsing.
   - Bulk Certificate PDF and high-res ZIP export (`/admin/bulk_download_modal.php`).
   - One-click sample CSV and sample Excel template downloads.

4. **Multi-Workshop & Cohort Management**:
   - Manage multiple cohorts, workshops, instructors, dates, and locations.
   - Individual certificate revocation and status auditing.
   - Fast database reset and seeding utility (`clear_data.bat` / `clear_data.php`).

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (Native PDO MySQL, ZipArchive, SimpleXML)
- **Database**: MySQL / MariaDB
- **Styling**: Tailwind CSS (Tailwind 3 CDN with custom SIMAD design tokens)
- **Libraries**:
  - `SheetJS` (Excel `.xlsx` / `.xls` client-side parsing and template generation)
  - `jsPDF` (High-resolution PDF generation)
  - `JSZip` (Client-side bulk archive bundling)
  - `QRCode.js` (Scannable verification QR generation)

---

## 🚀 Installation & Local Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) with Apache and MySQL (PHP 8.0+ recommended)
- Git

### 1. Clone the Repository
Clone into your XAMPP web root directory:
```bash
cd c:\xampp\htdocs
git clone https://github.com/hamettomar/SIMAD_certificates.git certificate
```

### 2. Import the Database
1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open `phpMyAdmin` (e.g. `http://localhost/phpmyadmin/`) or MySQL CLI.
3. Import the included `database.sql` file:
   ```bash
   mysql -u root -p < database.sql
   ```
   *(Default configuration connects to database `certificate_hub` on `localhost:3306` with user `root` and empty password).*

### 3. Access the Application
- **Organizer / Admin Workspace**:
  `http://localhost/certificate/admin/organizer_sign_in.php`
  - Default Admin Email: `ahmed@gmail.com`
  - Default Admin Password: `password123`
- **Public Certificate Lookup**:
  `http://localhost/certificate/public/public_certificate_download.php`
- **Public Verification Ledger**:
  `http://localhost/certificate/public/verify.php`

---

## 📁 Repository Structure

```
├── admin/                           # Organizer portal & management modules
│   ├── organizer_dashboard.php      # Main dashboard & metric overviews
│   ├── certificate_customize.php    # Visual certificate studio
│   ├── certificate_preview_modal.php# High-res preview & PDF generator
│   ├── bulk_download_modal.php      # Multi-certificate ZIP exporter
│   ├── import_participants_modal.php# Excel & CSV import engine
│   ├── cohorts_management.php       # Workshop & cohort manager
│   ├── participants_management.php  # Participant enrollment table
│   └── organizer_sign_in.php        # Admin authentication
├── includes/                        # Core backend layouts & DB connections
│   ├── db.php                       # PDO database connection & query helpers
│   ├── auth.php                     # Session security & access guards
│   ├── head.php                     # Tailwind design tokens & external scripts
│   ├── header.php                   # Top app bar
│   └── sidebar.php                  # Institutional sidebar navigation
├── public/                          # Public-facing screens
│   ├── verify.php                   # Institutional cryptographic verification ledger
│   ├── public_certificate_download.php # Public lookup & attendee search
│   ├── certificate_found_result.php # Certificate viewing & download stage
│   └── api_lookup.php               # Async attendee lookup API
├── uploads/                         # Certificate image blanks & templates
├── database.sql                     # Complete DB schema & seed data
├── clear_data.php                   # Database wipe & reset utility
├── clear_data.bat                   # 1-click Windows CLI reset runner
└── simad_university_logo.png        # Official SIMAD University brand logo
```

---

## 📄 License
SIMAD University © 2026. All rights reserved.
