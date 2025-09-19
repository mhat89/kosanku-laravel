<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aktivasi Akun</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 24px; }
  form { max-width: 460px; display: grid; gap: 12px; }
  input, button { padding: 10px; font-size: 14px; }
</style>
</head>
<body>
  <h2>Aktivasi Akun (OTP)</h2>
  <form id="f">
    <input id="email" type="email" placeholder="Email" required>
    <input id="code" type="text" placeholder="Kode OTP 6 digit" required>
    <button type="submit">Verifikasi</button>
  </form>
  <pre id="out"></pre>

  <script>
    document.getElementById('f').addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('email').value.trim();
      const code  = document.getElementById('code').value.trim();
      const res = await fetch('/api/auth/verify-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, code })
      });
      const txt = await res.text();
      document.getElementById('out').textContent = txt;
    });
  </script>
</body>
</html>
