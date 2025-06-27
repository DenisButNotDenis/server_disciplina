<?php

namespace App\Services\Messenger;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Абстрактный базовый класс для сервисов мессенджеров.
 * Определяет общий интерфейс для отправки сообщений.
 * (Пункт 9, 11)
 */
abstract class MessengerService
{
    protected Client $httpClient;
    protected string $apiToken;
    protected string $baseUrl;

    public function __construct()
    {
        $this->httpClient = new Client([
            'verify' => false, 
            'timeout' => 10,  
        ]);
    }

    /**
     * Устанавливает токен API для мессенджера.
     * @param string $token
     */
    public function setApiToken(string $token): void
    {
        $this->apiToken = $token;
    }

    /**
     * Отправляет текстовое сообщение в мессенджер.
     * @param string $recipientId Идентификатор получателя в мессенджере (например, Chat ID).
     * @param string $message Текст сообщения.
     * @return bool Успешно ли отправлено сообщение.
     */
    abstract public function sendMessage(string $recipientId, string $message): bool;

    /**
     * Проверяет подтверждение связки с мессенджером.
     * Это может быть проверка чат-айди или другого уникального идентификатора.
     * (Пункт 11 - Подтверждение РЕАЛЬНОЕ)
     * @param string $messengerUserId Идентификатор пользователя в мессенджере.
     * @param string $verificationCode (Опционально) Код для подтверждения, если мессенджер его требует.
     * @return bool Успешно ли подтверждена связка.
     */
    abstract public function verifyConnection(string $messengerUserId, string $verificationCode = ''): bool;
}
