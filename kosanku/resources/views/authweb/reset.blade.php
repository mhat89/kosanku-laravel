@extends('layouts.app')

@section('title','Reset Password')

@section('content')
  <h2>Reset Password</h2>
  <p>Email: <b>{{ $email }}</b></p>

  <form method="post" action="{{ route('forgot.reset.post') }}">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <input type="hidden" name="code"  value="{{ $code }}">

    <label>Password Baru</label>
    <input name="password" type="password" minlength="6" required>

    <label>Ulangi Password Baru</label>
    <input name="password_confirmation" type="password" minlength="6" required>

    <button type="submit">Simpan Password</button>
  </form>
@endsection
