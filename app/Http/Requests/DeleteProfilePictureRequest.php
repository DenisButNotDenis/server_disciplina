<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth; // Для проверки авторизации
use App\Models\User; // Для подсказки IDE

/**
 * Класс запроса для удаления фотографии профиля пользователя.
 * (Пункт 7.b)
 */
class DeleteProfilePictureRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Пользователь может удалить только свою фотографию профиля.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Только авторизованный пользователь может удалять фотографии.
        // Дополнительно: пользователь должен удалять СВОЮ фотографию.
        /** @var User $userInRoute */
        $userInRoute = $this->route('user'); // Получаем пользователя из маршрута

        return Auth::check() && Auth::id() === $userInRoute->id;
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * Для удаления фотографии профиля, нет дополнительных полей, которые нужно валидировать.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
        ];
    }
}