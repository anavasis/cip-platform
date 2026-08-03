@extends('layouts.guest')
@section('title', 'Login — CIP')
@section('content')
<h1 class="mb-4 text-xl font-semibold">Sign in</h1>
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label class="cip-label" for="email">Email</label>
        <input class="cip-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    </div>
    <div>
        <label class="cip-label" for="password">Password</label>
        <input class="cip-input" id="password" type="password" name="password" required>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
    <button class="cip-btn w-full" type="submit">Login</button>
</form>
<div class="mt-4 text-sm"><a href="{{ route('password.request') }}">Forgot password?</a></div>
@endsection
