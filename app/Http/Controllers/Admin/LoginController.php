<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use App\User;
use App\Mail\SendOtpMail;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');

        $this->redirectTo = getAdminPanelUrl();
    }

    public function showLoginForm()
    {
        $data = [
            'pageTitle' => trans('auth.login'),
        ];


        return view('admin.auth.login', $data);
    }

    /**
     * Check either username or email.
     * @return string
     */
    public function username()
    {
        return 'email';
    }

    /**
     * Validate the user login.
     * @param Request $request
     */
    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
                'email' => 'required|email|exists:users,email,status,active',
                'password' => 'required|min:4',
            ]
        );
    }

    /**
     * @param Request $request
     * @throws ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $request->session()->put('login_error', trans('auth.failed'));
        throw ValidationException::withMessages(
            [
                'error' => [trans('auth.failed')],
            ]
        );
    }

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email|exists:users,email,status,active',
        ];

        // Only require password if OTP is not being submitted
        if (!$request->has('otp')) {
            $rules['password'] = 'required|min:4';
        }

        if (!empty(getGeneralSecuritySettings('captcha_for_admin_login')) && !$request->has('otp')) {
            $rules['captcha'] = 'required|captcha';
        }

        // validate the form data
        $this->validate($request, $rules);

        // Check if this is an OTP verification request
        if ($request->has('otp')) {
            $user = User::where('email', $request->email)->first();

            if ($user->otp == $request->otp && now()->lt($user->otp_expire)) {
                // OTP is valid, log the user in
                Auth::login($user, $request->remember);
                $user->otp = null; // Clear OTP after successful login
                $user->otp_expire = null;
                $user->save();

                return Redirect::to(getAdminPanelUrl());
            } else {
                return redirect()->back()->withInput($request->only('email', 'remember'))->withErrors([
                    'otp' => 'Invalid or expired OTP.',
                ]);
            }
        }

        // If we get here, it's the initial login request (not OTP verification)
        if (Auth::validate(['email' => $request->email, 'password' => $request->password])) {
            $user = User::where('email', $request->email)->first();

            // Generate and send OTP
            $generateOtp = rand(100000, 999999);
            $user->otp = $generateOtp;
            $user->otp_expire = now()->addMinutes(15);
            $user->save();

            // TODO: Send OTP to user via email/SMS
            Mail::to($user->email)->send(new SendOtpMail([
                'email' => $user->email,
                'otp' => $generateOtp
            ]));

            // Redirect to OTP verification view
            return view('admin.auth.otp-verify', [
                'email' => $request->email,
                'remember' => $request->remember
            ]);
        }

        return redirect()->back()->withInput($request->only('email', 'remember'))->withErrors([
            'password' => 'Wrong password or this account not approved yet.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect(getAdminPanelUrl() . '/login');
    }
}
