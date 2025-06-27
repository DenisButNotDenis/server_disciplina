<?php

namespace App\Services\Messenger;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Сервис для взаимодействия с API Telegram.
 * (Пункт 9, 11)
 */
class TelegramMessengerService extends MessengerService
{
    public function __construct()
    {
        parent::__construct();
        $this->apiToken = config('messengers.telegram.token');
        $this->baseUrl = config('messengers.telegram.base_url') . $this->apiToken . '/';
    }

    /**
     * Отправляет текстовое сообщение в Telegram.
     *
     * @param string $chatId ID чата Telegram.
     * @param string $message Текст сообщения.
     * @return bool
     */
    public function sendMessage(string $chatId, string $message): bool
    {
        if (empty($this->apiToken)) {
            Log::warning('Telegram API token is not configured. Cannot send message.');
            return false;
        }

        try {
            $response = Http::post($this->baseUrl . 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML', // Можно использовать Markdown или HTML
            ]);

            $responseData = $response->json();

            if ($response->successful() && $responseData['ok'] === true) {
                Log::info("Telegram message sent successfully to chat ID: {$chatId}");
                return true;
            } else {
                Log::error("Failed to send Telegram message to chat ID: {$chatId}. Response: " . json_encode($responseData));
                return false;
            }
        } catch (RequestException $e) {
            Log::error("Telegram API request failed: " . $e->getMessage() . " for chat ID: {$chatId}");
            if ($e->hasResponse()) {
                Log::error("Telegram API error response: " . $e->getResponse()->getBody()->getContents());
            }
            return false;
        } catch (\Exception $e) {
            Log::error("An unexpected error occurred while sending Telegram message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверяет подтверждение связки с Telegram.
     * В Telegram нет прямого метода "подтверждения", как в OAuth.
     * Обычно это делается так: пользователь отправляет сообщение боту,
     * бот получает его chat_id и пользователь подтверждает этот chat_id в системе.
     * Для данного ЛР мы будем симулировать это, ожидая, что "verificationCode"
     * будет совпадать с "messengerUserId" (чат-айди), который пользователь "ввел" в форме.
     *
     * @param string $messengerUserId Ожидаемый Telegram Chat ID.
     * @param string $verificationCode Код, который пользователь якобы "ввел" в систему.
     * @return bool True, если ID совпадают, что симулирует подтверждение.
     */
    public function verifyConnection(string $messengerUserId, string $verificationCode = ''): bool
    {


        if (empty($this->apiToken)) {
            Log::warning('Telegram API token is not configured. Cannot verify connection.');
            return false;
        }

        if (empty($messengerUserId) || !is_numeric($messengerUserId)) {
            Log::warning("Invalid Telegram chat ID for verification: {$messengerUserId}");
            return false;
        }


        if (!empty($verificationCode) && $messengerUserId !== $verificationCode) {
            Log::warning("Verification code does not match messenger user ID for Telegram.");
            return false;
        }


        Log::info("Telegram connection simulated verification for chat ID: {$messengerUserId}.");
        return true; 
    }
}