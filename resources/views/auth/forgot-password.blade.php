@extends('layouts.guest')
@section('title', 'Forgot password — CIP')
@section('content')
<h1 class="mb-4 text-xl font-semibold">Forgot password</h1>
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label class="cip-label" for="email">Email</label>
        <input class="cip-input" id="email" type="email" name="email" value="{{ old('email') }}" required>
    </div>
    <button class="cip-btn w-full" type="submit">Email reset link</button>
</form>
@endsection
