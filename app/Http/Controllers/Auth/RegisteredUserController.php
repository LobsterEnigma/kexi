<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request): User {
            $setting = SiteSetting::query()
                ->where('key', 'registration_enabled')
                ->lockForUpdate()
                ->first();
            abort_if($setting && $setting->value !== 'true', 403, '注册当前已关闭。');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $localNow = now(config('kexi.display_timezone'));
            $season = $localNow->month >= 7 ? '秋季' : '春季';
            $user->timetables()->create([
                'name' => '主课表',
                'term_name' => $localNow->year.' '.$season,
                'week_count' => 18,
                'timezone' => config('kexi.display_timezone'),
                'near_threshold_minutes' => 30,
                'is_default' => true,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return redirect(route('dashboard', absolute: false));
    }
}
