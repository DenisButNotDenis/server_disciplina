<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\DTOs\Auth\UserResourceDTO;
use App\Http\DTOs\Auth\TokenResourceDTO;
use App\Http\DTOs\Auth\TwoFactorAuthTokenDTO;
use App\Http\Requests\TwoFactor\RequestTwoFactorCodeRequest;
use App\Http\Requests\TwoFactor\VerifyTwoFactorCodeRequest;
use App\Http\Requests\TwoFactor\ToggleTwoFactorAuthRequest;
use App\Models\User;
use App\Models\AccessToken;
use App\Models\UserRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthController extends Controller
{
    /**
     * Метод для регистрации нового пользователя.
     * (Пункт 12 - Уведомление при Авторизации/Изменении данных/Назначении ролей)
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $user = User::create([
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
            'birthday' => $validatedData['birthday'],
        ]);

        if ($user) {
            // Отправляем уведомление о регистрации (Пункт 12)
            $user->sendMessengerNotification("Новый пользователь '{$user->username}' успешно зарегистрирован.", 'user_registered');
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 201);
        }

        return response()->json(['message' => 'Registration failed unexpectedly.'], 500);
    }

    /**
     * Метод для авторизации (входа) пользователя.
     * (Пункт 12 - Уведомление при Авторизации)
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials (username or password incorrect).'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Отправляем уведомление о входе в систему (Пункт 12)
        $user->sendMessengerNotification("Пользователь '{$user->username}' успешно вошел в систему.", 'user_login');

        // Если 2FA включена для пользователя
        if ($user->twoFactorAuthActive()) {
            $twoFactorToken = Str::random(80);
            Cache::put('2fa_temp_token:' . $twoFactorToken, $user->id, now()->addMinutes(config('two_factor_auth.code_expiration_minutes') * 2));

            $code = $user->generateTwoFactorCode(
                $request->ip(),
                $request->header('User-Agent'),
                config('two_factor_auth.code_expiration_minutes')
            );

            \Log::info("2FA Code for user {$user->id} ({$user->email}): {$code} (Client: {$request->ip()}/{$request->header('User-Agent')})");

            return response()->json(new TwoFactorAuthTokenDTO(twoFactorToken: $twoFactorToken)->toArray(), 200);
        }

        return $this->issueTokens($user);
    }

    /**
     * Метод для получения информации об авторизованном пользователе.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
    }

    /**
     * Метод для разлогирования.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $plainTextToken = Str::substr($request->header('Authorization'), 7);

        if ($user->revokeAccessToken($plainTextToken)) {
            // Уведомление о выходе (опционально, т.к. пользователь сам инициировал)
            $user->sendMessengerNotification("Пользователь '{$user->username}' успешно вышел из системы.", 'user_logout');
            return response()->json(['message' => 'Successfully logged out.'], 200);
        }
        return response()->json(['message' => 'Failed to log out. Token might be invalid or already revoked.'], 500);
    }

    /**
     * Метод для получения списка авторизованных токенов пользователя.
     *
     * @return JsonResponse
     */
    public function tokens(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $accessTokens = $user->accessTokens()->get(['id', 'expires_at'])->toArray();
        return response()->json(['tokens' => $accessTokens], 200);
    }

    /**
     * Метод для разлогирования всех действующих токенов пользователя.
     *
     * @return JsonResponse
     */
    public function logoutAll(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->revokeAllAccessTokens();
        $user->revokeAllRefreshTokens();
        // Уведомление о выходе со всех устройств
        $user->sendMessengerNotification("Пользователь '{$user->username}' вышел из всех сессий.", 'user_logout_all');
        return response()->json(['message' => 'All tokens have been revoked. Please log in again.'], 200);
    }

    /**
     * Метод для обновления токена доступа с помощью токена обновления.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([ 'refresh_token' => 'required|string', ]);
        $plainTextRefreshToken = $request->input('refresh_token');

        /** @var \App\Models\UserRefreshToken|null $refreshTokenRecord */
        $refreshTokenRecord = UserRefreshToken::where('expires_at', '>', Carbon::now())
                                                ->where('revoked', false)
                                                ->get()
                                                ->filter(function ($t) use ($plainTextRefreshToken) {
                                                    return Hash::check($plainTextRefreshToken, $t->token);
                                                })->first();

        if (!$refreshTokenRecord) {
            return response()->json(['message' => 'Invalid or expired refresh token.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = $refreshTokenRecord->user;

        if ($refreshTokenRecord->revoked) {
            $user->revokeAllAccessTokens();
            $user->revokeAllRefreshTokens();
            $user->sendMessengerNotification("Обнаружена подозрительная активность: ваш токен обновления был использован повторно. Все ваши сессии аннулированы из соображений безопасности. Пожалуйста, войдите снова.", 'security_alert');
            return response()->json(['message' => 'Refresh token already used. All user tokens revoked for security.'], 401);
        }

        $refreshTokenRecord->update(['revoked' => true]);
        // При успешном обновлении токена, также можно отправить уведомление (опционально)
        $user->sendMessengerNotification("Ваши токены успешно обновлены.", 'tokens_refreshed');
        return $this->issueTokens($user);
    }

    /**
     * Метод для изменения пароля пользователя.
     * (Пункт 12 - Уведомление при Изменении данных)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'min:8', 'regex:/[0-9]/', 'regex:/[^a-zA-Z0-9]/', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'confirmed', ],
            'new_password_confirmation' => 'required',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 401);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        $user->revokeAllAccessTokens();
        $user->revokeAllRefreshTokens();

        // Отправляем уведомление об изменении пароля (Пункт 12)
        $user->sendMessengerNotification("Пароль для аккаунта '{$user->username}' был успешно изменен. Все активные сессии аннулированы.", 'password_changed');

        return response()->json(['message' => 'Password changed successfully. All active tokens revoked. Please log in again.'], 200);
    }

    /**
     * Вспомогательный метод для выдачи Access и Refresh токенов.
     * @param User $user
     * @return JsonResponse
     */
    private function issueTokens(User $user): JsonResponse
    {
        $maxActiveTokens = config('auth_tokens.max_active_access_tokens');
        if ($maxActiveTokens > 0 && $user->accessTokens()->count() >= $maxActiveTokens) {
            $user->accessTokens()->oldest()->first()?->delete();
        }

        $accessToken = $user->createAccessToken(config('auth_tokens.access_token_expiration_minutes'));
        $refreshToken = $user->createRefreshToken(config('auth_tokens.refresh_token_expiration_days'));

        $expiresInSeconds = config('auth_tokens.access_token_expiration_minutes') * 60;

        $tokenDTO = new TokenResourceDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresInSeconds: $expiresInSeconds
        );

        return response()->json($tokenDTO->toArray(), 200);
    }

    /**
     * Запрос нового кода подтверждения авторизации.
     * (Пункт 16.a, 5, 19, 20)
     * @param RequestTwoFactorCodeRequest $request
     * @return JsonResponse
     */
    public function requestTwoFactorCode(RequestTwoFactorCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $userId = $request->input('user_id_from_2fa_token');
        $user = User::find($userId);

        if (!$user || !$user->twoFactorAuthActive()) {
            throw new AccessDeniedHttpException('User not found or 2FA is not enabled.');
        }

        $clientIp = $request->ip();
        $userAgent = $request->header('User-Agent');

        $clientThreshold = config('two_factor_auth.rate_limits.client.threshold');
        $clientDelay = config('two_factor_auth.rate_limits.client.delay_seconds');
        $globalThreshold = config('two_factor_auth.rate_limits.global.threshold');
        $globalDelay = config('two_factor_auth.rate_limits.global.delay_seconds');

        if ($user->two_factor_code_attempts >= $clientThreshold &&
            $user->two_factor_client_ip === $clientIp &&
            $user->two_factor_user_agent === $userAgent
        ) {
            $lastRequestTime = $user->two_factor_last_code_requested_at;
            if ($lastRequestTime && $lastRequestTime->addSeconds($clientDelay)->isFuture()) {
                throw new BadRequestHttpException('Too many requests from this client. Please wait ' . $clientDelay . ' seconds.');
            }
        }

        if ($user->two_factor_code_attempts >= $globalThreshold) {
            $lastRequestTime = $user->two_factor_last_code_requested_at;
            if ($lastRequestTime && $lastRequestTime->addSeconds($globalDelay)->isFuture()) {
                throw new BadRequestHttpException('Too many requests. Please wait ' . $globalDelay . ' seconds.');
            }
        }

        if ($user->hasActiveTwoFactorCode()) {
            $user->invalidateTwoFactorCode();
        }

        $code = $user->generateTwoFactorCode(
            $clientIp,
            $userAgent,
            config('two_factor_auth.code_expiration_minutes')
        );

        $user->incrementTwoFactorCodeAttempts();

        \Log::info("NEW 2FA Code for user {$user->id} ({$user->email}): {$code} (Client: {$clientIp}/{$userAgent})");
        $user->sendMessengerNotification("Новый код двухфакторной авторизации: <b>{$code}</b>. Код действителен {$codeExpirationMinutes} минут. Не передавайте его никому!", 'new_2fa_code');

        return response()->json(['message' => 'New 2FA code sent.'], 200);
    }

    /**
     * Подтверждение кода двухфакторной авторизации.
     * (Пункт 16.b, 10, 12, 15)
     * @param VerifyTwoFactorCodeRequest $request
     * @return JsonResponse
     */
    public function verifyTwoFactorCode(VerifyTwoFactorCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $userId = $request->input('user_id_from_2fa_token');
        $user = User::find($userId);

        if (!$user || !$user->twoFactorAuthActive()) {
            throw new AccessDeniedHttpException('User not found or 2FA is not enabled.');
        }

        if (!$user->verifyTwoFactorCode($request->two_factor_code)) {
            $user->incrementTwoFactorCodeAttempts();
            throw new BadRequestHttpException('Invalid or expired 2FA code.');
        }

        $user->invalidateTwoFactorCode();
        $user->resetTwoFactorCodeAttempts();

        // Уведомление о успешной 2FA авторизации (Пункт 12)
        $user->sendMessengerNotification("Двухфакторная авторизация успешно пройдена для аккаунта '{$user->username}'.", '2fa_verified');

        return $this->issueTokens($user);
    }

    /**
     * Запрос включения/отключения двухфакторной авторизации пользователя.
     * (Пункт 16.c, 3, 4)
     * @param ToggleTwoFactorAuthRequest $request
     * @return JsonResponse
     */
    public function toggleTwoFactorAuth(ToggleTwoFactorAuthRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $targetState = $request->input('is_2fa_enabled_target');

        $user->is_2fa_enabled = $targetState;
        $user->save();

        if (!$targetState) {
            $user->invalidateTwoFactorCode();
            $user->resetTwoFactorCodeAttempts();
            $user->sendMessengerNotification("Двухфакторная авторизация для аккаунта '{$user->username}' была отключена.", '2fa_disabled');
            return response()->json(['message' => 'Two-factor authentication disabled successfully.'], 200);
        }

        $user->sendMessengerNotification("Двухфакторная авторизация для аккаунта '{$user->username}' была включена. Не забудьте подключить приложение-аутентификатор, если используете его!", '2fa_enabled');
        return response()->json(['message' => 'Two-factor authentication enabled successfully.'], 200);
    }
}
