<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // Для работы с файловым хранилищем (Пункт 16)
use Illuminate\Support\Facades\DB; // Для транзакций
use App\Models\User;
use App\Models\File; // Модель для хранения информации о файлах
use App\Http\Requests\UploadProfilePictureRequest; // Запрос для загрузки
use App\Http\Requests\DeleteProfilePictureRequest; // Запрос для удаления
use App\Http\DTOs\User\UserResourceDTO; // Для возврата обновленного пользователя
use App\Http\DTOs\File\FileResourceDTO; // Для возврата информации о файле
use Intervention\Image\ImageManager; // Для работы с изображениями (Пунут 13)
use Intervention\Image\Drivers\Gd\Driver; // Выбор драйвера GD (можно использовать ImagickDriver)
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse; // Для скачивания файла
use ZipArchive; // Для создания архивов (Пункт 19)
use PhpOffice\PhpSpreadsheet\Spreadsheet; // Для работы с Excel (Пункт 21.c)
use PhpOffice\PhpSpreadSpreadsheet\Writer\Xlsx; // Для записи Excel (Пункт 21.c)


/**
 * Контроллер для управления фотографиями профиля пользователей.
 * (Пункт 7.a, 7.b, 10, 15, 19)
 */
class ProfilePictureController extends Controller
{
    /**
     * Менеджер изображений Intervention Image.
     * @var ImageManager
     */
    protected ImageManager $imageManager;

    public function __construct()
    {
        // Инициализируем ImageManager с драйвером GD
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Загружает фотографию профиля для пользователя.
     * (Пункт 7.a, 11, 12, 13, 16, 18)
     *
     * @param UploadProfilePictureRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function upload(UploadProfilePictureRequest $request, User $user): JsonResponse
    {
        // Проверка авторизации: пользователь может загружать фото только себе.
        // Или администратор может загружать фото любому пользователю (требуется отдельное разрешение,
        // но по ТЗ 7.а только пользователь может загружать фото К СВОЕМУ профилю)
        if (Auth::id() !== $user->id) {
            throw new AccessDeniedHttpException('Вы можете загружать фотографии только для своего профиля.');
        }

        return DB::transaction(function () use ($request, $user) {
            $uploadedFile = $request->file('profile_picture');
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            $size = $uploadedFile->getSize(); // Размер в байтах

            // Генерируем уникальное имя файла (Пункт 12)
            $generatedFileName = Str::uuid()->toString() . '.' . $extension; // Использование UUID для уникальности

            // Определяем путь для хранения: users/{user_id}/profile_pictures/
            // (Пункт 18: Удобная структура хранения файлов)
            $basePath = 'users/' . $user->id . '/profile_pictures/';
            $originalFilePath = $basePath . $generatedFileName;
            $avatarFilePath = $basePath . Str::replaceLast('.', '_avatar.', $generatedFileName); // Путь для аватара

            $disk = config('profile_pictures.storage_disk'); // Получаем диск из конфига (по умолчанию 'public')

            // Удаляем старую фотографию и файл, если она существует (Пункт 7.a - если пользователь загружает НОВУЮ)
            if ($user->profilePicture) {
                // Мягко удаляем запись о старом файле в БД
                $oldFile = $user->profilePicture;
                $oldFile->delete(); // Мягкое удаление записи о файле (softDelete)

                // Удаляем физические файлы (оригинал и аватар) со СТАРЫМИ путями
                if (Storage::disk($disk)->exists($oldFile->path)) {
                    Storage::disk($disk)->delete($oldFile->path); // Удаляем оригинальный файл
                }
                $oldAvatarPath = Str::replaceLast('.', '_avatar.', $oldFile->path);
                if (Storage::disk($disk)->exists($oldAvatarPath)) {
                    Storage::disk($disk)->delete($oldAvatarPath); // Удаляем файл аватара
                }
            }

            // Сохраняем оригинальный файл (Пункт 16 - средства фреймворка)
            // (Пункт 17: Защита от прямого доступа: файлы хранятся в storage/app/public,
            // но доступ к ним через /storage/ симлинк может быть ограничен,
            // если используется 'private' диск или соответствующая конфигурация web-сервера).
            // В данном случае, public диск доступен через симлинк, но мы могли бы использовать private
            // и отдавать через отдельный маршрут.
            Storage::disk($disk)->put($originalFilePath, file_get_contents($uploadedFile->getRealPath()));

            // Создаем копию изображения (аватар) размером 128x128px (Пункт 13)
            $avatarSize = config('profile_pictures.avatar_size');
            $image = $this->imageManager->read(Storage::disk($disk)->path($originalFilePath));
            $image->cover($avatarSize, $avatarSize); // Обрезка и изменение размера
            $image->save(Storage::disk($disk)->path($avatarFilePath));

            // Сохраняем информацию о файле в базе данных
            $file = File::create([
                'name' => $request->input('name', $originalName), // Используем предоставленное имя или оригинальное
                'description' => $request->input('description'),
                'format' => $extension,
                'size' => $size,
                'path' => $originalFilePath, // Путь к оригинальному файлу
            ]);

            // Обновляем ссылку на фотографию профиля пользователя (Пункт 6)
            $user->profile_picture_file_id = $file->id;
            $user->save();

            $user->load('profilePicture'); // Перезагружаем отношение для актуального DTO

            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
        });
    }

    /**
     * Удаляет фотографию профиля пользователя.
     * (Пункт 7.b, 16)
     *
     * @param DeleteProfilePictureRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function delete(DeleteProfilePictureRequest $request, User $user): JsonResponse
    {
        // Авторизация уже проверена в DeleteProfilePictureRequest::authorize()
        // и убедились, что пользователь удаляет СВОЕ фото.

        if (!$user->profilePicture) {
            throw new NotFoundHttpException('У пользователя нет фотографии профиля для удаления.');
        }

        return DB::transaction(function () use ($user) {
            $file = $user->profilePicture; // Получаем текущий файл пользователя

            // Сбрасываем ссылку на файл в профиле пользователя
            $user->profile_picture_file_id = null;
            $user->save();

            // Мягко удаляем запись о файле в базе данных
            $file->delete();

            // Удаляем физические файлы с диска (оригинал и аватар)
            $disk = config('profile_pictures.storage_disk');
            if (Storage::disk($disk)->exists($file->path)) {
                Storage::disk($disk)->delete($file->path); // Удаляем оригинальный файл
            }
            $avatarPath = Str::replaceLast('.', '_avatar.', $file->path);
            if (Storage::disk($disk)->exists($avatarPath)) {
                Storage::disk($disk)->delete($avatarPath); // Удаляем файл аватара
            }

            $user->load('profilePicture'); // Перезагружаем отношение для актуального DTO
            return response()->json(UserResourceDTO::fromModel($user)->toArray(), 200);
        });
    }

    /**
     * Скачивает оригинальное изображение пользователя.
     * (Пункт 15, 17)
     *
     * @param User $user
     * @return BinaryFileResponse
     * @throws NotFoundHttpException|AccessDeniedHttpException
     */
    public function downloadOriginal(User $user): BinaryFileResponse
    {
        // Для скачивания чужой фотографии может потребоваться разрешение,
        // но по ТЗ это просто метод скачивания оригинального изображения пользователя.
        // Допустим, каждый пользователь может скачать только СВОЕ фото.
        // Если же админ может скачивать чужие, нужно добавить проверку:
        // Auth::check() && (Auth::id() === $user->id || Auth::user()->hasPermission('download-any-profile-picture'))
        if (!Auth::check() || Auth::id() !== $user->id) {
            throw new AccessDeniedHttpException('Вы не авторизованы для скачивания этой фотографии.');
        }

        if (!$user->profilePicture) {
            throw new NotFoundHttpException('У пользователя нет фотографии профиля для скачивания.');
        }

        $file = $user->profilePicture;
        $disk = config('profile_pictures.storage_disk');

        // Проверяем существование файла на диске
        if (!Storage::disk($disk)->exists($file->path)) {
            throw new NotFoundHttpException('Файл не найден на сервере.');
        }

        // Возвращаем бинарные данные файла (Пункт 15)
        // Laravel's response()->download() автоматически добавляет заголовки для скачивания
        return response()->download(Storage::disk($disk)->path($file->path), $file->name);
    }

    /**
     * Скачивает аватар изображения пользователя.
     * (Опционально, если нужен отдельный маршрут для аватара)
     *
     * @param User $user
     * @return BinaryFileResponse
     * @throws NotFoundHttpException|AccessDeniedHttpException
     */
    public function downloadAvatar(User $user): BinaryFileResponse
    {
        // Аватары обычно доступны публично, но если нужно защитить:
        if (!Auth::check() || Auth::id() !== $user->id) {
             throw new AccessDeniedHttpException('Вы не авторизованы для скачивания этого аватара.');
        }

        if (!$user->profilePicture) {
            throw new NotFoundHttpException('У пользователя нет аватара профиля.');
        }

        $file = $user->profilePicture;
        $disk = config('profile_pictures.storage_disk');
        $avatarPath = Str::replaceLast('.', '_avatar.', $file->path);

        if (!Storage::disk($disk)->exists($avatarPath)) {
            throw new NotFoundHttpException('Файл аватара не найден на сервере.');
        }

        // Возвращаем бинарные данные файла
        return response()->file(Storage::disk($disk)->path($avatarPath), [
            'Content-Type' => Storage::disk($disk)->mimeType($avatarPath)
        ]);
    }

    /**
     * Метод администратора, позволяющий выгружать с сервера архив с фотографиями.
     * (Пункт 19, 20, 21)
     *
     * @param Request $request
     * @return BinaryFileResponse
     * @throws AccessDeniedHttpException
     * @throws \Exception
     */
    public function downloadPhotosArchive(Request $request): BinaryFileResponse
    {
        // Проверка разрешения: только администратор может выгружать архивы.
        if (!Auth::check() || !Auth::user()->hasPermission('get-profile-pictures-archive')) {
            throw new AccessDeniedHttpException('Необходимое разрешение: get-profile-pictures-archive');
        }

        return DB::transaction(function () {
            $disk = config('profile_pictures.storage_disk');
            $archiveDisk = config('profile_pictures.archive_export_disk');
            $archiveFileName = 'user_photos_archive_' . now()->format('Ymd_His') . '.zip';
            $archivePath = Storage::disk($archiveDisk)->path($archiveFileName); // Путь для временного архива

            // Инициализируем ZipArchive
            $zip = new ZipArchive();
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Не удалось создать архив ZIP.');
            }

            // Получаем только актуальные фотографии (которые не мягко удалены и связаны с пользователями)
            // Пункт 20: Архив фотографий содержит только актуальные фотографии.
            $files = File::whereHas('userProfilePicture', function($query) {
                                $query->whereNull('deleted_at'); // Пользователь не удален
                            })
                            ->whereNull('deleted_at') // Файл не мягко удален
                            ->with('userProfilePicture') // Загружаем связанные данные пользователя
                            ->get();

            // Создаем Excel-файл (Пункт 21.c)
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('User Photos Data');

            // Заголовки Excel-файла (Пункт 21.c.i-v)
            $sheet->setCellValue('A1', 'Идентификатор пользователя');
            $sheet->setCellValue('B1', 'Имя пользователя');
            $sheet->setCellValue('C1', 'Дата загрузки фотографии');
            $sheet->setCellValue('D1', 'Наименование оригинального файла в архиве');
            $sheet->setCellValue('E1', 'Наименование аватара в архиве');
            $sheet->setCellValue('F1', 'Путь до оригинального файла на сервере');
            $sheet->setCellValue('G1', 'Путь до аватара на сервере');

            $row = 2;
            foreach ($files as $file) {
                if (!$file->userProfilePicture) {
                    continue; // Пропускаем, если пользователь почему-то не найден (хотя whereHas должен это исключить)
                }

                $username = $file->userProfilePicture->username;
                $fileId = $file->id;
                $fileFormat = $file->format;

                // Формируем имена файлов в архиве (Пункт 21.a, 21.b)
                $originalArchiveName = "{$username}_{$fileId}.{$fileFormat}";
                $avatarArchiveName = "{$username}_{$fileId}_avatar.{$fileFormat}";

                // Добавляем оригинальный файл в архив
                $originalFullPath = Storage::disk($disk)->path($file->path);
                if (file_exists($originalFullPath)) {
                    $zip->addFile($originalFullPath, 'original_photos/' . $originalArchiveName);
                }

                // Добавляем аватар в архив
                $avatarFullPath = Str::replaceLast('.', '_avatar.', $originalFullPath); // Формируем путь к аватару на диске
                if (file_exists($avatarFullPath)) {
                    $zip->addFile($avatarFullPath, 'avatars/' . $avatarArchiveName);
                }

                // Заполняем строку в Excel
                $sheet->setCellValue('A' . $row, $file->userProfilePicture->id);
                $sheet->setCellValue('B' . $row, $username);
                $sheet->setCellValue('C' . $row, $file->created_at->format('Y-m-d H:i:s'));
                $sheet->setCellValue('D' . $row, $originalArchiveName);
                $sheet->setCellValue('E' . $row, $avatarArchiveName);
                $sheet->setCellValue('F' . $row, $file->path);
                $sheet->setCellValue('G' . $row, Str::replaceLast('.', '_avatar.', $file->path));
                $row++;
            }

            // Сохраняем Excel-файл во временную директорию
            $excelWriter = new Xlsx($spreadsheet);
            $excelTempPath = Storage::disk($archiveDisk)->path('user_photos_data.xlsx');
            $excelWriter->save($excelTempPath);

            // Добавляем Excel-файл в архив
            $zip->addFile($excelTempPath, 'user_photos_data.xlsx');

            $zip->close();

            // Удаляем временный Excel-файл
            Storage::disk($archiveDisk)->delete('user_photos_data.xlsx');

            // Возвращаем архив для скачивания
            return response()->download($archivePath, $archiveFileName)->deleteFileAfterSend(true);
        });
    }
}
