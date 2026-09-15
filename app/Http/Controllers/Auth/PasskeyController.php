<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use App\Models\User;
use App\Services\PasskeyService;
use App\Services\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasskeyController extends Controller
{
    public function registerOptions(Request $request, PasskeyService $service)
    {
        $data = $request->validate(['password' => ['required', 'current_password'], 'name' => ['required', 'string', 'max:80']]);
        $config = $service->config($request);
        $user = DB::transaction(function () use ($request, $service) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->passkeys()->count() >= 20) {
                $service->fail('最多保存 20 个通行密钥，请先移除不再使用的密钥。');
            }
            if (! $user->passkey_handle) {
                $user->passkey_handle = bin2hex(random_bytes(32));
                $user->save();
            }

            return $user;
        });
        $server = $service->server($config);
        $exclude = $user->passkeys()->where('rp_id', $config['rpId'])->pluck('credential_id')->map(fn ($id) => PasskeyService::decode($id))->all();
        $options = $server->getCreateArgs(hex2bin($user->passkey_handle), $user->email, $user->name, 180, true, true, null, $exclude);

        return response()->json(['publicKey' => $options->publicKey, 'challenge_id' => $service->issue($request, 'register', $config, $server, ['name' => $data['name']])])->header('Cache-Control', 'no-store');
    }

    public function register(Request $request, PasskeyService $service)
    {
        $request->validate(['challenge_id' => ['required', 'string', 'size:64'], 'rawId' => ['required', 'string', 'max:2048'],
            'clientDataJSON' => ['required', 'string', 'max:12000'], 'attestationObject' => ['required', 'string', 'max:65536']]);
        $config = $service->config($request);
        $challenge = $service->consume($request, 'register', $config);
        try {
            $data = $service->server($config)->processCreate($service->clientData($request->input('clientDataJSON'), $config),
                PasskeyService::decode($request->input('attestationObject')), PasskeyService::decode($challenge['challenge']), true, true);
            if (! hash_equals($data->credentialId, PasskeyService::decode($request->input('rawId')))) {
                $service->fail();
            }
        } catch (Throwable) {
            $service->fail();
        }
        $credentialId = PasskeyService::encode($data->credentialId);
        DB::transaction(function () use ($request, $config, $challenge, $data, $credentialId, $service) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->isAccessSuspended() || (string) $user->auth_version !== (string) $challenge['auth_version']) {
                $service->fail();
            }
            if ($user->passkeys()->count() >= 20 || Passkey::where('credential_hash', hash('sha256', $data->credentialId))->exists()) {
                $service->fail('此密钥已添加，或已达到数量上限。');
            }
            $user->passkeys()->create(['name' => $challenge['name'], 'credential_hash' => hash('sha256', $data->credentialId),
                'credential_id' => $credentialId, 'public_key' => $data->credentialPublicKey, 'rp_id' => $config['rpId'], 'sign_count' => $data->signatureCounter ?? 0]);
        });

        return response()->json(['message' => '通行密钥已添加。']);
    }

    public function loginOptions(Request $request, PasskeyService $service, Turnstile $turnstile)
    {
        $config = $service->config($request);
        $turnstile->verify($request, 'login');
        $server = $service->server($config);
        $options = $server->getGetArgs([], 180, true, true, true, true, true, true);

        return response()->json(['publicKey' => $options->publicKey, 'challenge_id' => $service->issue($request, 'login', $config, $server, ['remember' => $request->boolean('remember')])])->header('Cache-Control', 'no-store');
    }

    public function login(Request $request, PasskeyService $service)
    {
        $request->validate(['challenge_id' => ['required', 'string', 'size:64'], 'rawId' => ['required', 'string', 'max:2048'],
            'clientDataJSON' => ['required', 'string', 'max:12000'], 'authenticatorData' => ['required', 'string', 'max:12000'],
            'signature' => ['required', 'string', 'max:4096'], 'userHandle' => ['required', 'string', 'max:256']]);
        $config = $service->config($request);
        $challenge = $service->consume($request, 'login', $config);
        $rawId = PasskeyService::decode($request->input('rawId'));
        $user = DB::transaction(function () use ($request, $service, $config, $challenge, $rawId) {
            $key = Passkey::query()->where('credential_hash', hash('sha256', $rawId))->where('rp_id', $config['rpId'])->lockForUpdate()->first();
            if (! $key) {
                $service->fail();
            }
            $user = User::query()->lockForUpdate()->findOrFail($key->user_id);
            try {
                if (! $user->passkey_handle || ! hash_equals(hex2bin($user->passkey_handle), PasskeyService::decode($request->input('userHandle')))) {
                    $service->fail();
                }
                $server = $service->server($config);
                $server->processGet($service->clientData($request->input('clientDataJSON'), $config), PasskeyService::decode($request->input('authenticatorData')),
                    PasskeyService::decode($request->input('signature')), $key->public_key, PasskeyService::decode($challenge['challenge']), $key->sign_count, true, true);
            } catch (Throwable) {
                $service->fail();
            }
            if ($user->isAccessSuspended()) {
                throw ValidationException::withMessages(['passkey' => $user->accessRestrictionMessage()]);
            }
            $key->update(['sign_count' => $server->getSignatureCounter() ?? 0, 'last_used_at' => now()]);

            return $user;
        });
        Auth::login($user, (bool) $challenge['remember']);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $user->auth_version);

        return response()->json(['redirect' => route('dashboard', [], false)]);
    }

    public function rename(Request $request, Passkey $passkey)
    {
        abort_unless($passkey->user_id === $request->user()->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'password' => ['required', 'current_password']]);
        $passkey->update(['name' => $data['name']]);

        return back()->with('passkey_status', '通行密钥名称已更新。');
    }

    public function destroy(Request $request, Passkey $passkey)
    {
        abort_unless($passkey->user_id === $request->user()->id,404);
        $request->validate(['password' => ['required', 'current_password']]);
        $passkey->delete();

        return back()->with('passkey_status','通行密钥已移除，该密钥不能再用于登录。');
    }
}
