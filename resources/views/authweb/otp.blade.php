@extends('layouts.app')

@section('title','Verifikasi OTP')

@section('content')
  <h2>Masukkan Kode OTP</h2>

  <form method="post" action="{{ route('forgot.otp.post') }}">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <label>Kode OTP (6 digit)</label>
    <input name="code" type="text" pattern="\d{6}" maxlength="6" required value="{{ old('code') }}">
    <button type="submit">Lanjut</button>
  </form>

  <form method="post" action="{{ route('forgot.resend') }}" style="margin-top:8px">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <button type="submit">Kirim Ulang OTP</button>
  </form>
@endsection
