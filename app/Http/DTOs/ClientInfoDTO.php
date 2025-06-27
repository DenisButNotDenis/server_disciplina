<?php

namespace App\Http\DTOs;

class ClientInfoDTO
{
    public function __construct(
        public string $ipAddress,
        public string $userAgent
    ) {}

    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}