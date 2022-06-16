<?php

namespace Framework\Modules\Base\Services;

class cookieService
{
    public function setCookie($name, $value, $overwrite = true): bool
    {
        if ($overwrite) {
            $_SESSION[$name] = $value;
        } else {
            if (!isset($_SESSION)) {
                $_SESSION[$name] = $value;
            }
        }
        return true;
    }

    public function issetCookie($name): bool
    {
        return isset($_SESSION[$name]);
    }

    public function getCookie($name)
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        }
        return null;
    }

    public function unsetCookie($name)
    {
        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }
}