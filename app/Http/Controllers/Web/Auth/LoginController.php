<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\User;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('app.home');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $this->seedDefaultContext($user);

        if (Organization::query()->count() === 0) {
            return redirect()->route('setup.show');
        }

        return redirect()->intended(route('app.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        OperatorContext::set(null, null);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function seedDefaultContext(User $user): void
    {
        $membership = $user->organizationMemberships()->with('organization')->first();
        if ($membership === null) {
            return;
        }

        $organization = $membership->organization;
        $project = $organization?->projects()->orderBy('name')->first();
        OperatorContext::set($organization?->id, $project?->id);
    }
}
