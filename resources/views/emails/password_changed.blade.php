<!DOCTYPE html>
<html>
  <body>
    <p>Halo {{ $user->full_name ?? $user->email }},</p>
    <p>Password akun Anda berhasil diubah. Jika ini bukan Anda, segera lakukan reset password dan hubungi admin.</p>
    <p>Waktu: {{ now()->toDateTimeString() }}</p>
    <p>— Tim Kosanku</p>
  </body>
</html>
