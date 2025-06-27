    <?php

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Route;

    // Только тестовый маршрут, чтобы исключить другие проблемы
    Route::get('/test', function () {
        return response()->json(['message' => 'Test API route works! (minimal)'], 200);
    });
    