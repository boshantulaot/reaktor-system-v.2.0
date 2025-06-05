# Struktur Folder Reaktor System v2.0

## 📁 Struktur Direktori

```
/public_html/reaktorsystem/
├── /assets/
│   ├── /adminlte/          (Upload AdminLTE v4.0.0-beta3 di sini)
│   ├── /css/               (Custom CSS)
│   ├── /js/                (Custom JavaScript)
│   ├── /img/
│   │   ├── logo_koni.png
│   │   ├── kepitran.png    (Default avatar)
│   │   └── /icons/
│   └── /uploads/
│       ├── /profiles/      (Foto profil)
│       ├── /ktp/           (Foto KTP)
│       ├── /certificates/  (Sertifikat)
│       └── /documents/     (Dokumen lain)
│
├── /config/
│   ├── database.php        (Koneksi database)
│   ├── app.php            (Konfigurasi aplikasi)
│   └── constants.php      (Konstanta sistem)
│
├── /helpers/
│   ├── Auth.php           (Autentikasi)
│   ├── Security.php       (CSRF, XSS protection)
│   ├── Session.php        (Session management)
│   ├── Database.php       (Database wrapper)
│   ├── Upload.php         (File upload handler)
│   └── Functions.php      (Helper functions)
│
├── /layouts/
│   ├── header.php         (Header template)
│   ├── footer.php         (Footer template)
│   ├── sidebar.php        (Sidebar menu)
│   └── navbar.php         (Top navigation)
│
├── /modules/
│   ├── /auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── forgot-password.php
│   │   └── reset-password.php
│   │
│   ├── /dashboard/
│   │   ├── index.php
│   │   └── widgets.php
│   │
│   ├── /users/
│   │   ├── index.php      (List users)
│   │   ├── create.php     (Add user)
│   │   ├── edit.php       (Edit user)
│   │   ├── view.php       (View profile)
│   │   └── delete.php     (Delete user)
│   │
│   ├── /atlet/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── prestasi.php
│   │
│   ├── /pelatih/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── lisensi.php
│   │
│   ├── /wasit/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── sertifikat.php
│   │
│   ├── /cabor/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── view.php
│   │
│   ├── /digital-cards/
│   │   ├── index.php
│   │   ├── generate.php
│   │   ├── view.php
│   │   └── print.php
│   │
│   ├── /messages/
│   │   ├── index.php
│   │   ├── compose.php
│   │   ├── view.php
│   │   └── sent.php
│   │
│   └── /settings/
│       ├── index.php
│       ├── profile.php
│       └── system.php
│
├── /api/
│   ├── auth.php           (API authentication)
│   ├── users.php          (Users API)
│   ├── atlet.php          (Atlet API)
│   └── notifications.php  (Notifications API)
│
├── index.php              (Redirect to login/dashboard)
├── .htaccess             (URL rewriting & security)
└── init.php              (Bootstrap file)
```

## 🛠️ Langkah-langkah Setup

### 1. **Download AdminLTE v4.0.0-beta3**
```bash
# Download dari GitHub
https://github.com/ColorlibHQ/AdminLTE/releases/tag/v4.0.0-beta3

# Extract dan upload folder 'dist' ke:
/public_html/reaktorsystem/assets/adminlte/
```

### 2. **Buat Folder Structure**
Buat semua folder sesuai struktur di atas melalui:
- File Manager Hostinger
- Atau FTP client (FileZilla)

### 3. **Set Permissions**
```bash
# Folder uploads perlu writable
chmod 755 /assets/uploads/
chmod 755 /assets/uploads/profiles/
chmod 755 /assets/uploads/ktp/
chmod 755 /assets/uploads/certificates/
chmod 755 /assets/uploads/documents/
```

## 📝 File yang Akan Dibuat

### **Priority 1 (Core System)**
1. `/config/database.php` - Koneksi database
2. `/config/app.php` - Konfigurasi aplikasi
3. `/helpers/Database.php` - Database wrapper
4. `/helpers/Session.php` - Session management
5. `/helpers/Auth.php` - Authentication
6. `/init.php` - Bootstrap file

### **Priority 2 (Authentication)**
1. `/modules/auth/login.php` - Halaman login
2. `/layouts/header.php` - Template header
3. `/layouts/footer.php` - Template footer
4. `/modules/dashboard/index.php` - Dashboard

### **Priority 3 (CRUD Modules)**
1. `/modules/users/` - User management
2. `/modules/atlet/` - Atlet management
3. `/modules/cabor/` - Cabor management

## 🔧 Technology Stack

- **Frontend**: AdminLTE 4.0.0-beta3 + Bootstrap 5
- **Backend**: PHP 7.4/8.0
- **Database**: MySQL/MariaDB
- **Icons**: Font Awesome 6
- **Charts**: Chart.js
- **Datatables**: DataTables 1.13
- **Validation**: jQuery Validation
- **Notifications**: SweetAlert2

## 🎨 Color Scheme

```css
:root {
  --primary: #007bff;    /* KONI Blue */
  --success: #28a745;    /* Approve/Active */
  --danger: #dc3545;     /* Reject/Delete */
  --warning: #ffc107;    /* Pending */
  --info: #17a2b8;       /* Information */
  --dark: #343a40;       /* Sidebar */
}
```

## 📱 Responsive Breakpoints

- Mobile: < 576px
- Tablet: 576px - 768px
- Desktop: > 768px

## 🔐 Security Checklist

- [ ] CSRF tokens on all forms
- [ ] XSS protection (htmlspecialchars)
- [ 