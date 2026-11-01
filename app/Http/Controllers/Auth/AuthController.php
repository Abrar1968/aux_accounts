<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\System\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request)
    {
        $this->auth->login($request, $request->string('email'), $request->string('password'), $request->boolean('remember'));

        return redirect()->intended($this->auth->homeFor($request->user()));
    }

    public function destroy(Request $request)
    {
        $this->auth->logout($request);

        return redirect()->route('login');
    }

    public function forgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $this->auth->sendResetLink($request->string('email'));

        // Same answer whether or not the address exists (no account enumeration).
        return $this->toast('If that address has an account, a reset link is on its way.', 'success', 'Check your email');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $status = $this->auth->reset($data);
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('toast', ['type' => 'success', 'title' => 'Password updated', 'message' => 'Sign in with your new password.']);
    }
}
