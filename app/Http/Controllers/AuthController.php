<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\Email;
use App\Models\User;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;


class AuthController extends Controller
{
    use HasApiTokens;

    public function login(StoreLoginRequest $request)
    {
        $name_email = Str::contains($request['email'], '@') ? 'email' : 'name';
        $error = ValidationException::withMessages(['password' => __('auth.failed')]);
        $attributes = $request->validated();
        if ($name_email == 'email') {
            $email = Email::where('email', $attributes['email'])->with(['users'])->first();
            if (!$email) {
                throw $error;
            } elseif ($email) {
                if (!$email->email_verified_at) {
                    throw ValidationException::withMessages(['email' => __('auth.email')]);
                }
                if (!Hash::check($attributes['password'], $email->users->password)) {
                    throw $error;
                }
            }
        } elseif ($name_email == 'name') {
            if (!auth()->attempt([
                'name' => $attributes['email'], 'password' => $attributes['password']
            ])) {
                throw $error;
            }
        }

        $user = $name_email == 'email' ? User::where('id', $email->user_id)->with(['emails'])->first() : User::where('name', $request['email'])->with(['emails'])->first();
        if (!$user->emails->where('email_verified_at', '<>', null)->first()) {
            throw ValidationException::withMessages(['email' => __('auth.email')]);
        }

        request()->session()->regenerate();

        Auth::login($user);
        return response(['user' => auth()->user()]);
    }
    public function me()
    {
        $user = Auth::user();
        $withEmail = User::where('id', $user->id)->with(['emails'])->first();
        return response(['user' => $withEmail]);
    }

    public function register(StoreUserRequest $request)
    {
        $credentials = $request->validated();
        $token = Str::random(64);
        $user = User::create([
            "name" => $credentials["name"],
            'password' => bcrypt($credentials["password"])
        ]);

        $email = Email::create([
            "email" => $credentials["email"],
            'primary' => true,
            'user_id' => $user->id,
            'token' => $token
        ]);

        Mail::send('email.email-verification', ['token' => $token, 'name' => $user->name], function ($message) use ($request) {
            $message->to($request->email);
            $message->subject('Email Verification Mail');
        });

        return response()->json([
            'message' => "Success",
            'code' => 201,
            'user' => $user,
        ]);
    }

    public function logout()
    {
        request()->session()->invalidate();
        return response()->json([
            'message' => 'You successfully logged out'
        ]);
    }

    public function verifyAccount($token)
    {
        $email = Email::where('token', $token)->first();

        if (!is_null($email)) {
            $email->email_verified_at = now();
            $email->save();
        }

        return redirect(env('MAIL_TO_URL'));
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $google_user = Socialite::with('google')->stateless()->user();

        $user = User::where('google_id', $google_user->getId())->first();
        if (!$user) {
            $emailAlreadyExists = Email::where('email', $google_user->getEmail())->first();
            if ($emailAlreadyExists) {
                return response()->json([
                    'message' => "email already exists",
                    'code' => 422,
                ]);
            }
            $new_user = User::create([
                "name" => $google_user->getName(),
                'google_id' => $google_user->getId()
            ]);
            $email = Email::create([
                "email" => $google_user->getEmail(),
                'user_id' => $new_user->id,
            ]);

            request()->session()->regenerate();
            Auth::login($new_user);
            return redirect(env('FRONTEND_URL'));
        } else {

            request()->session()->regenerate();
            Auth::login($user);
            return redirect(env('FRONTEND_URL'));
        }
        try {
        } catch (\Throwable $th) {
            return $th;
        }
    }
}
