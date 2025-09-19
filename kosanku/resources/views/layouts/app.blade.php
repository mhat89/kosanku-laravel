<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Kosanku')</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:24px}
    header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
    nav form{margin:0}
    nav button{background:#f03e3e;color:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer}
    nav button:hover{background:#c92a2a}
    .container{max-width:640px;margin:auto}
    .msg{padding:10px;background:#e7f5ff;border:1px solid #a5d8ff;border-radius:6px;margin-bottom:12px}
    .err{color:#c92a2a;margin-bottom:12px}
  </style>
</head>
<body>
  <header>
    <h1>Kosanku</h1>
    <nav>
    @if (session('api_token'))
      <form method="POST" action="{{ route('logout.web') }}">
        @csrf
        <button type="submit">Logout</button>
      </form>
    @else
      <a href="{{ route('login.show') }}">Login</a>
    @endif
  </nav>

  </header>

  <div class="container">
    @if (session('status')) <div class="msg">{{ session('status') }}</div> @endif
    @if ($errors->any())
      <div class="err">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @yield('content')
  </div>
</body>
</html>
