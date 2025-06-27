amespace App\Http\Controllers;

use App\Models\Messenger;
use App\Http\Requests\Messenger\CreateMessengerRequest;
use App\Http\Requests\Messenger\UpdateMessengerRequest;
use App\Http\DTOs\Messenger\MessengerResourceDTO;
use App\Http\DTOs\Messenger\MessengerCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Для логирования
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Контроллер для управления сущностью Messenger.
 * (Пункт 9)
 */
class MessengerController extends Controller
{
    /**
     * Получить список всех мессенджеров.
     * (Пункт 9)
     * Только администраторы могут просматривать полный список.
     * Пользователи могут просматривать только те, что соответствуют их среде окружения.
     */
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentEnv = config('app.env'); // Текущая среда окружения (Пункт 6.b)

        if ($user->hasPermission('get-list-messenger')) { // Для админа
            $messengers = Messenger::all();
        } else {
            // Пункт 6.b: Пользователь может выбирать мессенджеры. Только из списка соответствующей ему среды окружения.
            $messengers = Messenger::where('environment', $currentEnv)->get();
        }

        return response()->json(MessengerCollectionDTO::collect($messengers)->toArray(), 200);
    }

    /**
     * Получить информацию об одном мессенджере по ID.
     * (Пункт 9)
     * Только администраторы могут просматривать любой мессенджер.
     * Пользователи могут просматривать только те, что соответствуют их среде окружения.
     */
    public function show(Messenger $messenger): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentEnv = config('app.env'); // Текущая среда окружения

        if ($user->hasPermission('read-messenger')) { // Для админа
            // Разрешено
        } elseif ($messenger->environment !== $currentEnv) {
            // Пункт 6.b: Если не админ и мессенджер не из текущей среды, отказ
            throw new AccessDeniedHttpException('Вы не можете просматривать мессенджеры из другой среды окружения.');
        }

        return response()->json(MessengerResourceDTO::fromModel($messenger)->toArray(), 200);
    }

    /**
     * Создать новый мессенджер.
     * (Пункт 9)
     */
    public function store(CreateMessengerRequest $request): JsonResponse
    {
        // Авторизация проверена в CreateMessengerRequest::authorize()
        $messenger = Messenger::create($request->validated());

        Log::info("Messenger '{$messenger->name}' created by user " . Auth::id());

        return response()->json(MessengerResourceDTO::fromModel($messenger)->toArray(), 201);
    }

    /**
     * Обновить существующий мессенджер.
     * (Пункт 9)
     */
    public function update(UpdateMessengerRequest $request, Messenger $messenger): JsonResponse
    {
        // Авторизация проверена в UpdateMessengerRequest::authorize()
        $messenger->update($request->validated());

        Log::info("Messenger '{$messenger->name}' updated by user " . Auth::id());

        return response()->json(MessengerResourceDTO::fromModel($messenger)->toArray(), 200);
    }

    /**
     * Удалить мессенджер.
     * (Пункт 9)
     */
    public function destroy(Messenger $messenger): JsonResponse
    {
        // Только администраторы могут удалять мессенджеры.
        if (!Auth::check() || !Auth::user()->hasPermission('delete-messenger')) {
            throw new AccessDeniedHttpException('Недостаточно прав. Необходимо разрешение "delete-messenger".');
        }

        $messenger->delete();

        Log::info("Messenger '{$messenger->name}' deleted by user " . Auth::id());

        return response()->json(['message' => 'Messenger deleted successfully.'], 200);
    }
}
