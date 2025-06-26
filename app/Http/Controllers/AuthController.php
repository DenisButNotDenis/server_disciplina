<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest; // Используем наш класс запроса для входа
use App\Http\Requests\RegisterRequest; // Используем наш класс запроса для регистрации
use App\Http\DTOs\Auth\UserResourceDTO; // DTO для вывода информации о пользователе
use App\Http\DTOs\Auth\TokenResourceDTO; // DTO для вывода информации о токенах
use App\Models\User; // Модель пользователя
use Illuminate\Http\JsonResponse; // Тип возвращаемого значения для API ответов
use Illuminate\Http\Request; // Стандартный класс для работы с HTTP-запросом
use Illuminate\Support\Facades\Auth; // Фасад (упрощенный доступ) к системе аутентификации Laravel
use Illuminate\Support\Facades\Hash; // Для хэширования паролей и токенов
use Illuminate\Support\Str; // Для работы со строками
use Carbon\Carbon; // Для работы с датами и временем
use App\Models\UserRefreshToken; // Модель для токенов обновления
use App\Models\AccessToken; // Модель для токенов доступа

class AuthController extends Controller
{
    /**
     * Метод для регистрации нового пользователя.
     * Доступен для всех (неавторизованных) пользователей.
     *
     * @param RegisterRequest $request Объект запроса с проверенными данными для регистрации.
     * @return JsonResponse JSON-ответ с данными пользователя или сообщением об ошибке.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // 1. Получаем проверенные данные из объекта запроса.
        // Laravel автоматически выполнит валидацию здесь, благодаря инъекции RegisterRequest.
        $validatedData = $request->validated();

        // 2. Создаем нового пользователя в базе данных.
        $user = User::create([
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'], // Laravel сам захэширует, если в модели User есть $casts = ['password' => 'hashed'];
            'birthday' => $validatedData['birthday'],
        ]);

        if ($user) {
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 201);
        }

        return response()->json(['message' => 'Registration failed unexpectedly.'], 500);
    }

    /**
     * Метод для авторизации (входа) пользователя.
     * Доступен для всех.
     *
     * @param LoginRequest $request Объект запроса с проверенными учетными данными.
     * @return JsonResponse JSON-ответ с токенами или сообщению об ошибке.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // 1. Получаем проверенные учетные данные (username и password).
        $credentials = $request->validated();

        // 2. Пытаемся авторизовать пользователя с помощью встроенной системы Laravel.
        // Auth::attempt() проверит пароль и найдет пользователя.
        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials (username or password incorrect).'], 401);
        }

        // 3. Если авторизация успешна, получаем объект авторизованного пользователя.
        /** @var \App\Models\User $user */ // Это подсказка для вашей IDE, чтобы она знала тип $user
        $user = Auth::user();

        // 4. Проверяем лимит активных токенов доступа.
        // Читаем настройки из нашего конфиг-файла.
        $maxActiveTokens = config('auth_tokens.max_active_access_tokens');
        if ($maxActiveTokens > 0 && $user->accessTokens()->count() >= $maxActiveTokens) {
            // Если токенов слишком много, отзываем самый старый.
            // Можно также отозвать все старые, если это требуется по политике безопасности.
            $user->accessTokens()->oldest()->first()?->delete();
        }

        // 5. Генерируем новый токен доступа и токен обновления.
        $accessToken = $user->createAccessToken(config('auth_tokens.access_token_expiration_minutes'));
        $refreshToken = $user->createRefreshToken(config('auth_tokens.refresh_token_expiration_days'));

        // 6. Вычисляем срок жизни Access Token в секундах.
        $expiresInSeconds = config('auth_tokens.access_token_expiration_minutes') * 60;

        // 7. Создаем DTO для токенов и возвращаем его в JSON-ответе со статусом 200 (OK).
        $tokenDTO = new TokenResourceDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresInSeconds: $expiresInSeconds
        );

        return response()->json($tokenDTO->toArray(), 200);
    }

    /**
     * Метод для получения информации об авторизованном пользователе.
     * Доступен только авторизованным пользователям (защищен Middleware).
     *
     * @return JsonResponse JSON-ответ с данными пользователя.
     */
    public function me(): JsonResponse
    {
        // 1. Получаем объект текущего авторизованного пользователя.
        // Это работает благодаря моему Middleware `AuthenticateApiToken`.
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 2. Возвращаем данные пользователя через DTO со статусом 200.
        return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
    }

    /**
     * Метод для разлогирования (отзыва текущего используемого токена доступа).
     * Доступен только авторизованным пользователям.
     *
     * @param Request $request Объект запроса.
     * @return JsonResponse JSON-ответ с сообщением.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Извлекаем токен доступа из заголовка Authorization.
        $plainTextToken = Str::substr($request->header('Authorization'), 7); // Убираем "Bearer "

        // 2. Отзываем этот конкретный токен доступа.
        if ($user->revokeAccessToken($plainTextToken)) {
            return response()->json(['message' => 'Successfully logged out.'], 200);
        }

        // 3. Если что-то пошло не так (токен не найден или уже недействителен).
        return response()->json(['message' => 'Failed to log out. Token might be invalid or already revoked.'], 500);
    }

    /**
     * Метод для получения списка авторизованных токенов пользователя.
     * Доступен только авторизованным пользователям.
     *
     * @return JsonResponse JSON-ответ со списком токенов.
     */
    public function tokens(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Получаем все токены доступа пользователя из базы данных.
        // Возвращаем их ID и срок действия, чтобы не раскрывать хэши токенов.
        $accessTokens = $user->accessTokens()->get(['id', 'expires_at'])->toArray();

        return response()->json(['tokens' => $accessTokens], 200);
    }

    /**
     * Метод для разлогирования всех действующих токенов доступа пользователя.
     * Отзывает все Access и Refresh токены для текущего пользователя.
     * Доступен только авторизованным пользователям.
     *
     * @return JsonResponse JSON-ответ с сообщением.
     */
    public function logoutAll(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Отзываю все токены доступа.
        $user->revokeAllAccessTokens();
        // 2. Отзываю все токены обновления.
        $user->revokeAllRefreshTokens();

        return response()->json(['message' => 'All tokens have been revoked. Please log in again.'], 200);
    }

    /**
     * Метод для обновления токена доступа с помощью токена обновления.
     * Не требует Access Token в заголовке, но требует Refresh Token в теле запроса.
     *
     * @param Request $request Объект запроса, содержащий refresh_token.
     * @return JsonResponse JSON-ответ с новой парой токенов или ошибкой.
     */
    public function refresh(Request $request): JsonResponse
    {
        // 1. Валидирую, что refresh_token передан и является строкой.
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $plainTextRefreshToken = $request->input('refresh_token');

        // 2. Ищу токен обновления в базе данных, проверяя его валидность.
        /** @var \App\Models\UserRefreshToken|null $refreshTokenRecord */
        $refreshTokenRecord = UserRefreshToken::where('expires_at', '>', Carbon::now()) // Токен не просрочен
                                                ->where('revoked', false) // Токен не отозван
                                                ->get() // Получаю коллекцию для фильтрации по хэшу
                                                ->filter(function ($t) use ($plainTextRefreshToken) {
                                                    return Hash::check($plainTextRefreshToken, $t->token); // Проверяю хэш
                                                })->first();


        // 3. Если токен обновления не найден, просрочен или уже отозван.
        if (!$refreshTokenRecord) {
            return response()->json(['message' => 'Invalid or expired refresh token.'], 401);
        }

        // 4. Получаю пользователя, которому принадлежит этот токен обновления.
        /** @var \App\Models\User $user */
        $user = $refreshTokenRecord->user;

        // 5. Если токен обновления уже был использован (имеет статус revoked = true).
        // Это мера безопасности: если кто-то перехватил refresh-токен и использовал его,
        // а потом попытался использовать повторно, мы отзываю все токены пользователя.
        if ($refreshTokenRecord->revoked) {
            $user->revokeAllAccessTokens();
            $user->revokeAllRefreshTokens();
            return response()->json(['message' => 'Refresh token already used. All user tokens revoked for security.'], 401);
        }

        // 6. Помечаю текущий токен обновления как использованный/отозванный.
        // Это предотвращает его повторное использование.
        $refreshTokenRecord->update(['revoked' => true]);

        // 7. Генерирую новую пару токенов (Access Token и Refresh Token).
        $newAccessToken = $user->createAccessToken(config('auth_tokens.access_token_expiration_minutes'));
        $newRefreshToken = $user->createRefreshToken(config('auth_tokens.refresh_token_expiration_days'));

        // 8. Вычисляю срок жизни нового Access Token в секундах.
        $expiresInSeconds = config('auth_tokens.access_token_expiration_minutes') * 60;

        // 9. Возвращаю новую пару токенов через DTO со статусом 200.
        $tokenDTO = new TokenResourceDTO(
            accessToken: $newAccessToken,
            refreshToken: $newRefreshToken,
            expiresInSeconds: $expiresInSeconds
        );

        return response()->json($tokenDTO->toArray(), 200);
    }

    /**
     * Метод для изменения пароля пользователя.
     * Требует текущего пароля для подтверждения.
     * Доступен только авторизованным пользователям.
     *
     * @param Request $request Объект запроса, содержащий пароли.
     * @return JsonResponse JSON-ответ с сообщению.
     */
    public function changePassword(Request $request): JsonResponse
    {
        // 1. Валидация входных данных для смены пароля.
        $request->validate([
            'current_password' => 'required|string', // Текущий пароль пользователя
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'confirmed', // Проверяет, что new_password_confirmation совпадает
            ],
            'new_password_confirmation' => 'required', // Поле для подтверждения нового пароля
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 2. Проверяю, совпадает ли введенный текущий пароль с паролю в базе данных.
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 401);
        }

        // 3. Если текущий пароль верный, обновляю пароль пользователя.
        $user->password = Hash::make($request->new_password); // Хэширую новый пароль
        $user->save(); // Сохраняю изменения в базе данных

        // 4. (Опционально, но рекомендуется для безопасности)
        // После смены пароля, отзываю все действующие токены пользователя.
        // Это заставляет пользователя заново войти в систему с новым паролем.
        $user->revokeAllAccessTokens();
        $user->revokeAllRefreshTokens();

        return response()->json(['message' => 'Password changed successfully. All active tokens revoked. Please log in again.'], 200);
    }
}