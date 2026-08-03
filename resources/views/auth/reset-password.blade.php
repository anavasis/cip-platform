@extends('layouts.guest')
@section('title', 'Reset password — CIP')
@section('content')
<h1 class="mb-4 text-xl font-semibold">Reset password</h1>
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="cip-label" for="email">Email</label>
        <input class="cip-input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
    </div>
    <div>
        <label class="cip-label" for="password">New password</label>
        <input class="cip-input" id="password" type="password" name="password" required>
    </div>
    <div>
        <label class="cip-label" for="password_confirmation">Confirm password</label>
        <input class="cip-input" id="password_confirmation" type="password" name="password_confirmation" required>
    </div>
    <button class="cip-btn w-full" type="submit">Reset password</button>
</form>
@endsection
