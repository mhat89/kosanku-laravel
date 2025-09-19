@extends('layouts.app')

@section('title','Login')

@section('content')
  <h2>Login</h2>

  @if (session('status')) <div class="msg">{{ session('status') }}</div> @endif
  @if (session('error'))  <div class="err">{{ session('error') }}</div> @endif

  <form method="POST" action="{{ route('login.post') }}" novalidate>
    @csrf
    <label>Email</label>
    <input name="email" type="email" value="{{ old('email') }}" required autofocus>

    <label>Password</label>
    <input name="password" type="password" required>

    <button type="submit">Login</button>
  </form>

  <p style="margin-top:12px">
    Lupa password? <a href="{{ route('forgot.show') }}">Reset di sini</a>
  </p>
@endsection
