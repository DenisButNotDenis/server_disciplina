<?php

namespace App\Http\Controllers;

use App\Http\DTOs\ServerInfoDTO;
use App\Http\DTOs\ClientInfoDTO;
use App\Http\DTOs\DatabaseInfoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB; // Для получения информации о БД

class InfoController extends Controller
{
    /**
     * Возвращает информацию о версии PHP.
     *
     * @return JsonResponse
     */
    public function serverInfo(): JsonResponse
    {
        $dto = new ServerInfoDTO(phpversion());
        return response()->json($dto->toArray());
    }

    /**
     * Возвращает информацию о клиенте (IP и User-Agent).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clientInfo(Request $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');

        $dto = new ClientInfoDTO($ipAddress, $userAgent);
        return response()->json($dto->toArray());
    }

    /**
     * Возвращает информацию об используемой базе данных.
     * Требуется настроенное подключение к БД в .env и config/database.php.
     *
     * @return JsonResponse
     */
    public function databaseInfo(): JsonResponse
    {
        $dto = new DatabaseInfoDTO();
        return response()->json($dto->toArray());
    }
}