?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\DTOs\Auth\UserResourceDTO;
use App\Http\DTOs\Auth\TokenResourceDTO;
use App\Http\DTOs\Auth\TwoFactorAuthTokenDTO; // Импортируем новый DTO для 2FA
use App\Http\Requests\TwoFactor\RequestTwoFactorCodeRequest; // Импортируем запрос 2FA кода
use App\Http\Requests\TwoFactor\VerifyTwoFactorCodeRequest; // Импортируем запрос подтверждения 2FA
use App\Http\Requests\TwoFactor\ToggleTwoFactorAuthRequest; // Импортируем запрос переключения 2FA
use App\Models\User;
use App\Models\AccessToken; // Модель для токенов доступа
use App\Models\UserRefreshToken; // Модель для токенов обновления
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache; // Для кэширования временных 2FA токенов
use Illuminate\Support\Facades\DB; // Для транзакций
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AuthController extends Controller
{
    /**
     * Метод для регистрации нового пользователя.
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
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 201);
        }

        return response()->json(['message' => 'Registration failed unexpectedly.'], 500);
    }

    /**
     * Метод для авторизации (входа) пользователя.
     * (Пункт 2, 12, 14)
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

        // Если 2FA включена для пользователя
        if ($user->twoFactorAuthActive()) {
            // Генерируем временный токен, разрешенный только для 2FA-операций
            // Этот токен будет храниться в кэше и связан с ID пользователя
            $twoFactorToken = Str::random(80);
            Cache::put('2fa_temp_token:' . $twoFactorToken, $user->id, now()->addMinutes(config('two_factor_auth.code_expiration_minutes') * 2)); // Срок действия в 2 раза дольше, чем код, чтобы дать время на запрос/перезапрос

            // Генерируем первый 2FA код
            $code = $user->generateTwoFactorCode(
                $request->ip(),
                $request->header('User-Agent'),
                config('two_factor_auth.code_expiration_minutes')
            );

            // Отправляем код (в реальном приложении это была бы отправка по SMS/Email)
            // Логируем для демонстрации
            \Log::info("2FA Code for user {$user->id} ({$user->email}): {$code} (Client: {$request->ip()}/{$request->header('User-Agent')})");

            // Возвращаем временный 2FA токен, который пользователь будет использовать для запроса/подтверждения кода
            return response()->json(new TwoFactorAuthTokenDTO(twoFactorToken: $twoFactorToken)->toArray(), 200);
        }

        // Если 2FA НЕ включена, выдаем обычные токены доступа
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
            return response()->json(['message' => 'Refresh token already used. All user tokens revoked for security.'], 401);
        }

        $refreshTokenRecord->update(['revoked' => true]);
        return $this->issueTokens($user);
    }

    /**
     * Метод для изменения пароля пользователя.
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
        // ID пользователя получен из временного токена 2FA в authorize() запроса
        /** @var User $user */
        $userId = $request->input('user_id_from_2fa_token');
        $user = User::find($userId);

        if (!$user || !$user->twoFactorAuthActive()) {
            throw new AccessDeniedHttpException('User not found or 2FA is not enabled.');
        }

        // --- Логика ограничения частоты запросов (Rate Limiting) ---
        $clientIp = $request->ip();
        $userAgent = $request->header('User-Agent');

        $clientThreshold = config('two_factor_auth.rate_limits.client.threshold');
        $clientDelay = config('two_factor_auth.rate_limits.client.delay_seconds');
        $globalThreshold = config('two_factor_auth.rate_limits.global.threshold');
        $globalDelay = config('two_factor_auth.rate_limits.global.delay_seconds');

        // Проверка по конкретному клиенту (IP + User-Agent)
        if ($user->two_factor_code_attempts >= $clientThreshold &&
            $user->two_factor_client_ip === $clientIp &&
            $user->two_factor_user_agent === $userAgent
        ) {
            // Если слишком много попыток с этого клиента, применяем задержку
            $lastRequestTime = $user->two_factor_last_code_requested_at;
            if ($lastRequestTime && $lastRequestTime->addSeconds($clientDelay)->isFuture()) {
                throw new BadRequestHttpException('Too many requests from this client. Please wait ' . $clientDelay . ' seconds.');
            }
        }

        // Проверка глобального лимита (для пользователя, без учета клиента)
        if ($user->two_factor_code_attempts >= $globalThreshold) {
            $lastRequestTime = $user->two_factor_last_code_requested_at;
            if ($lastRequestTime && $lastRequestTime->addSeconds($globalDelay)->isFuture()) {
                throw new BadRequestHttpException('Too many requests. Please wait ' . $globalDelay . ' seconds.');
            }
        }

        // Если предыдущий код был, его аннулируем (Пункт 9)
        if ($user->hasActiveTwoFactorCode()) {
            $user->invalidateTwoFactorCode(); // Аннулирует текущий код и сбрасывает связанные поля
        }

        // Генерируем и сохраняем новый код
        $code = $user->generateTwoFactorCode(
            $clientIp,
            $userAgent,
            config('two_factor_auth.code_expiration_minutes')
        );

        // Увеличиваем счетчик попыток
        $user->incrementTwoFactorCodeAttempts();

        // Отправляем код (в реальном приложении: SMS/Email)
        \Log::info("NEW 2FA Code for user {$user->id} ({$user->email}): {$code} (Client: {$clientIp}/{$userAgent})");

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
        // ID пользователя получен из временного токена 2FA в authorize() запроса
        /** @var User $user */
        $userId = $request->input('user_id_from_2fa_token');
        $user = User::find($userId);

        if (!$user || !$user->twoFactorAuthActive()) {
            throw new AccessDeniedHttpException('User not found or 2FA is not enabled.');
        }

        // Проверяем код
        if (!$user->verifyTwoFactorCode($request->two_factor_code)) {
            // Увеличиваем счетчик попыток, если код неверный
            $user->incrementTwoFactorCodeAttempts();
            throw new BadRequestHttpException('Invalid or expired 2FA code.');
        }

        // Код верен: отменяем 2FA код (Пункт 10) и сбрасываем счетчик попыток
        $user->invalidateTwoFactorCode();
        $user->resetTwoFactorCodeAttempts();

        // Выдаем обычные токены доступа
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

        // Валидация пароля и, если нужно, 2FA кода уже произошла в ToggleTwoFactorAuthRequest::authorize() и rules()

        // Обновляем статус 2FA
        $user->is_2fa_enabled = $targetState;
        $user->save();

        // Если 2FA была отключена, аннулируем текущий код и сбрасываем попытки
        if (!$targetState) {
            $user->invalidateTwoFactorCode();
            $user->resetTwoFactorCodeAttempts();
            return response()->json(['message' => 'Two-factor authentication disabled successfully.'], 200);
        }

        // Если 2FA была включена, сообщаем об этом
        return response()->json(['message' => 'Two-factor authentication enabled successfully.'], 200);
    }
}
