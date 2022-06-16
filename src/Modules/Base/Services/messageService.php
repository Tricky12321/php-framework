<?php

namespace Framework\Modules\Base\Services;

use Framework\Core\injection;

class messageService
{
    private cookieService $cookieService;
    private const COOKIE_ID = "messages";

    public function __construct()
    {
        $this->cookieService = injection::getClass(cookieService::class);
    }

    public function saveMessage($messageId, $message, $type)
    {
        $data = $this->cookieService->getCookie(self::COOKIE_ID);
        $data[$messageId] = [
            'message' => $message,
            'type' => $type,
        ];
        $this->cookieService->setCookie(self::COOKIE_ID, $data);
    }

    public function getMessage($messageId, $deleteMessage = false)
    {
        $data = $this->cookieService->getCookie("messages");
        $information = $data[$messageId];
        if ($deleteMessage) {
            $this->deleteMessage($messageId);
        }
        return $information;
    }

    public function showMessage($messageId, $deleteMessage = false): string
    {
        $data = $this->getMessage($messageId, $deleteMessage);
        $type = $data['type'];
        $message = $data['message'];
        $output = <<<eol
         <div class="alert alert-$type" role="alert">
              $message
            </div>
        eol;
        if ($deleteMessage) {
            $this->deleteMessage($messageId);
        }
        return $output;
    }

    public function showMessageIfSet($messageId, $deleteMessage = false): string
    {
        if ($this->issetMessage($messageId)) {
            return $this->showMessage($messageId, $deleteMessage);
        }
        return "";
    }

    public function deleteMessage($messageId)
    {
        $data = $this->cookieService->getCookie("messages");
        unset($data[$messageId]);
        $this->cookieService->setCookie(self::COOKIE_ID, $data);
    }

    public function issetMessage($messageId, $deleteMessage = false): bool
    {
        $data = $this->cookieService->getCookie("messages");
        if ($deleteMessage) {
            $this->deleteMessage($messageId);
        }
        return isset($data[$messageId]);
    }
}