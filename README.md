Collection ini mencakup:

## 📋 Endpoint yang Tersedia:
### 🔍 Health Check
- Ping - untuk mengecek status API
### 👤 User Authentication
- Register, Login, Logout
- OTP verification dan resend
- Forgot password dengan OTP
- Change password
- Logout all devices
### 👨‍💼 Admin Authentication
- Admin register, login, logout
- Admin OTP verification
- Admin profile management
- Admin forgot password
### 🏠 Boarding House (Kosan) - Public
- Get Nearby Kosan - mencari kos terdekat berdasarkan koordinat
- Search Kosan - pencarian kos berdasarkan keyword
- Get Kosan Detail - detail informasi kos
- Get Kosan Images - gambar-gambar kos
### 🏠 Boarding House (Kosan) - Protected
- Create, Update, Delete kosan (memerlukan authentication)
### 📸 Kosan Image Management
- Upload multiple images
- Set primary image
- Delete image
- Update image order
## 🔧 Environment Variables:
- base_url : http://localhost:8000 (default)
- user_token : untuk menyimpan token user setelah login
- admin_token : untuk menyimpan token admin setelah login
## 💡 Cara Penggunaan:
1. 1.
   Import file kosanku-laravel.json ke Postman
2. 2.
   Set environment variable base_url sesuai dengan URL server Laravel Anda
3. 3.
   Login terlebih dahulu untuk mendapatkan token
4. 4.
   Copy token ke environment variable user_token atau admin_token
5. 5.
   Token akan otomatis digunakan untuk endpoint yang memerlukan authentication
Collection ini sudah lengkap dengan contoh request body dan parameter yang diperlukan untuk setiap endpoint.