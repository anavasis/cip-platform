@extends('layouts.app')
@section('title', 'Profile — CIP')
@section('content')
<h1 class="mb-6 font-[family-name:var(--font-display)] text-3xl font-bold">User profile</h1>
<div class="grid gap-4 lg:grid-cols-2">
<form class="cip-card space-y-3" method="POST" action="{{ route('app.profile.update') }}">
@csrf @method('PUT')
<div><label class="cip-label">Name</label><input class="cip-input" name="name" value="{{ old('name', $user->name) }}" required></div>
<div><label class="cip-label">Email</label><input class="cip-input" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
<button class="cip-btn" type="submit">Save profile</button>
</form>
<form class="cip-card space-y-3" method="POST" action="{{ route('app.profile.password') }}">
@csrf @method('PUT')
<div><label class="cip-label">Current password</label><input class="cip-input" type="password" name="current_password" required></div>
<div><label class="cip-label">New password</label><input class="cip-input" type="password" name="password" required></div>
<div><label class="cip-label">Confirm password</label><input class="cip-input" type="password" name="password_confirmation" required></div>
<button class="cip-btn" type="submit">Change password</button>
</form>
</div>
@endsection
