<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth; // Для проверки авторизации
use Illuminate\Validation\Rules\File; // Для расширенных правил валидации файлов
use Illuminate\Validation\Validator; // Для создания кастомных правил

/**
 * Класс запроса для загрузки фотографии профиля пользователя.
 * (Пункт 7.a, 11)
 */
class UploadProfilePictureRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     * Пользователь может загружать фото только для себя.
     * (Пункт 7.a)
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Только авторизованный пользователь может загружать фотографии для своего профиля.
        // Дополнительная проверка на то, что ID пользователя в маршруте совпадает с текущим авторизованным
        // будет в контроллере.
        return Auth::check();
    }

    /**
     * Возвращает правила валидации, применимые к запросу.
     * (Пункт 11 - проверки размера файла и соотношения сторон)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Получаем настройки из конфиг-файла (config/profile_pictures.php)
        $maxSizeKb = config('profile_pictures.max_size_kb');
        $allowedMimes = config('profile_pictures.allowed_mimes');
        $minWidth = config('profile_pictures.min_width');
        $minHeight = config('profile_pictures.min_height');
        $aspectRatio = config('profile_pictures.aspect_ratio'); // Например, "1/1"

        // Преобразуем соотношение сторон для правила 'dimensions'
        $aspectRatioRule = '';
        if ($aspectRatio && str_contains($aspectRatio, '/')) {
            list($width, $height) = explode('/', $aspectRatio);
            if (is_numeric($width) && is_numeric($height) && $height != 0) {
                // Правило 'ratio' ожидает десятичное число, например 1 (для 1/1) или 0.5 (для 1/2)
                $aspectRatioRule = 'ratio=' . (float)($width / $height);
            }
        }

        return [
            'profile_picture' => [
                'required',
                File::image() // Проверяет, что файл является изображением (jpeg, png, bmp, gif, svg, webp)
                    ->max($maxSizeKb * 1024) // Максимальный размер в байтах (Пункт 11.a)
                    ->mimes($allowedMimes), // Разрешенные MIME-типы

                // Валидация размеров и соотношения сторон (Пункт 11.b)
                // 'dimensions:min_width=200,min_height=200,ratio=1/1'
                // Важно: 'dimensions' требует, чтобы изображение было загружено, поэтому оно должно идти после 'image'.
                'dimensions:' .
                    ($minWidth ? 'min_width=' . $minWidth . ',' : '') .
                    ($minHeight ? 'min_height=' . $minHeight . ',' : '') .
                    ($aspectRatioRule ? $aspectRatioRule : ''),
            ],
            'name' => 'nullable|string|max:255',       // Оригинальное имя файла (опционально)
            'description' => 'nullable|string|max:1000', // Описание файла (опционально)
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_picture.required' => 'Файл фотографии профиля обязателен для загрузки.',
            'profile_picture.image' => 'Загружаемый файл должен быть изображением (jpeg, png, webp, gif).',
            'profile_picture.max' => 'Размер файла не должен превышать ' . (config('profile_pictures.max_size_kb') / 1024) . ' МБ.',
            'profile_picture.mimes' => 'Формат файла не поддерживается. Разрешены: ' . implode(', ', config('profile_pictures.allowed_mimes')) . '.',
            'profile_picture.dimensions' => 'Размеры изображения или соотношение сторон не соответствуют требованиям. ' .
                                            'Минимальные размеры: ' . config('profile_pictures.min_width') . 'x' . config('profile_pictures.min_height') . 'px. ' .
                                            'Ожидаемое соотношение сторон: ' . config('profile_pictures.aspect_ratio') . '.',
        ];
    }

    /**
     * Обрабатывает пост-валидационные колбэки.
     * Используется для добавления более сложной логики валидации,
     * например, для проверки фактического соотношения сторон.
     * (Требование 11.b при выполнении работы с разделением на back/front,
     * когда ratio не может быть проверен на уровне `dimensions` для всех случаев)
     *
     * @param Validator $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('profile_picture') && $this->file('profile_picture')->isValid()) {
                $file = $this->file('profile_picture');
                list($width, $height) = getimagesize($file->getPathname());

                $expectedRatio = explode('/', config('profile_pictures.aspect_ratio'));
                if (count($expectedRatio) === 2 && is_numeric($expectedRatio[0]) && is_numeric($expectedRatio[1]) && $expectedRatio[1] != 0) {
                    $expectedRatioValue = (float)($expectedRatio[0] / $expectedRatio[1]);
                    $actualRatioValue = (float)($width / $height);

                    // Допустимая погрешность для сравнения чисел с плавающей точкой
                    $epsilon = 0.01; // 1% погрешность

                    if (abs($actualRatioValue - $expectedRatioValue) > $epsilon) {
                        $validator->errors()->add('profile_picture', 'Соотношение сторон изображения не соответствует ожидаемому ' . config('profile_pictures.aspect_ratio') . '.');
                    }
                }
            }
        });
    }
}

