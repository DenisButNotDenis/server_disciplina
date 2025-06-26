<?php

namespace App\Http\DTOs;

use Illuminate\Support\Facades\DB;

class DatabaseInfoDTO
{
    public ?string $driver = null;
    public ?string $host = null;
    public ?string $database = null;

    public function __construct()
    {
        try {
            $connection = DB::connection();
            $this->driver = $connection->getDriverName();
            $this->host = config('database.connections.' . $connection->getName() . '.host');
            $this->database = config('database.connections.' . $connection->getName() . '.database');
        } catch (\Exception $e) {
            $this->driver = 'N/A (Error)';
            $this->host = 'N/A (Error)';
            $this->database = 'N/A (Error)';
        }
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'database' => $this->database,
        ];
    }
}