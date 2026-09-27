<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function timezone(Request $request)
    {
        $data = $request->validate(['timezone' => ['required', 'string', 'max:64', 'timezone:all'], 'initialize' => ['sometimes', 'boolean']]);
        if ($request->boolean('initialize')) {
            // First device detection must never overwrite a choice from another tab/device.
            $request->user()->newQuery()->whereKey($request->user()->id)->whereNull('timezone')->update(['timezone' => $data['timezone']]);

            return response()->json(['timezone' => $request->user()->fresh()->timezone]);
        }
        $request->user()->update(['timezone' => $data['timezone']]);

        return redirect()->to(route('profile.edit').'#profile-timezone')->with('timezone_status', '默认时区已保存。新建课表与个人安排将使用此时区；已有课表请在下方逐一调整。');
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
