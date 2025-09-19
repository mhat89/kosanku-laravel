@extends('layouts.app')

@section('title','Lupa Password')

@section('content')
  <h2>Lupa Password</h2>

  <form method="post" action="{{ route('forgot.post') }}">
    @csrf
    <label>Email</label>
    <input name="email" type="email" required value="{{ old('email') }}">
    <button type="submit">Kirim OTP</button>
  </form>
@endsection
