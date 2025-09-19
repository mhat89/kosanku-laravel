@extends('layouts.app')

@section('title','Dashboard')

@section('content')
  <h2>Dashboard</h2>
  <p>Selamat datang! Anda sudah login.</p>

  <div style="margin-top:20px">
    <h3>Token API (disimpan di session):</h3>
    <code>{{ session('api_token') }}</code>
  </div>

  <div style="margin-top:20px">
    <p>Di sini nanti bisa tampilkan data user, peta, dsb.</p>
  </div>
@endsection
