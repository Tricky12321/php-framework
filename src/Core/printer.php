<?php

namespace Framework\Core;

use Framework\Model\Enum\printColor;
use Framework\Model\Enum\yesNoNone;

class printer
{
    public static function print($text, $color = printColor::default, $overwrite = false)
    {
        $return = $overwrite ? "\r" : "";
        printf($return . "\e[0;{$color}m %s", $text);
    }

    public static function printLn($text, $color = printColor::default, $overwrite = false)
    {
        $return = $overwrite ? "\r" : "";
        self::print($return . $text . "\n", $color);
    }

    public static function printDebug($text, $newline = true, $overwrite = false)
    {
        self::print("[DEBUG] \t", printColor::yellow);
        if ($newline) {
            self::printLn($text);
        } else {
            self::print($text);
        }
    }

    public static function printInfo($text, $newline = true)
    {
        self::print("[INFO] \t", printColor::green);
        if ($newline) {
            self::printLn($text);
        } else {
            self::print($text);
        }
    }

    public static function printError($text, $newline = true)
    {
        self::print("[ERROR] \t", printColor::red);
        if ($newline) {
            self::printLn($text);
        } else {
            self::print($text);
        }
    }

    public static function printWarning($text, $newline = true)
    {
        self::print("[WARNING] \t", printColor::yellow);
        if ($newline) {
            self::printLn($text);
        } else {
            self::print($text);
        }
    }

    public static function printDebugValue($value, $text, $newline = true)
    {
        self::print("[VALUE] \t", printColor::cyan);
        self::print($text);
        self::print(" = ");
        if ($newline) {
            self::printLn($value);
        } else {
            self::print($value);
        }
    }

    public static function printLine($i = 20, $char = "-", $color = printColor::default)
    {
        $line = "";
        for (; $i > 0; $i--) {
            $line .= $char;
        }
        self::printLn($line);
    }

    /**
     * allowEmpty will always be true if default is set
     *
     * @param $prompt
     * @param false $allowEmpty
     */
    public static function getInput($prompt, $allowEmpty = false, string $default = null)
    {
        $defaultPrint = "";
        if ($default !== null) {
            $defaultPrint = "[" . $default . "]";
            $allowEmpty = true;
        }

        do {
            $input = readline("{$prompt}{$defaultPrint}: ");
        } while (!$allowEmpty);
        if ($input === "" && $default != null) {
            return $default;
        }
        return $input;
    }

    public static function confirm($prompt, $default = yesNoNone::None): bool
    {
        $defaultPrint = "";
        switch ($default) {
            case (yesNoNone::YES) :
                $defaultPrint = "[Y/n]";
                break;
            case (yesNoNone::NO):
                $defaultPrint = "[y/N]";
                break;
            default:
                $defaultPrint = "[y/n]";
        }
        $correct = false;
        $yes = [
            "yes",
            "ja",
            "j",
            "y",
        ];
        $no = [
            "no",
            "nej",
            "n",
        ];
        $valid = array_merge($yes, $no);
        $output = null;
        do {
            printer::printInfo($prompt, false);
            $input = readline(" {$defaultPrint}: ");
            if ($default === yesNoNone::None) {
                $correct = in_array(strtolower($input), $valid);
                $output = in_array(strtolower($input), $yes);
            } elseif ($input === "") {
                switch ($default) {
                    case yesNoNone::YES:
                        $output = true;
                        $correct = true;
                        break;
                    case yesNoNone::NO:
                        $output = false;
                        $correct = true;
                        break;
                    default:
                        $correct = false;
                        break;
                }
            } else {
                $correct = in_array(strtolower($input), $valid);
                $output = in_array(strtolower($input), $yes);
            }
        } while (!$correct);
        return $output;
    }
}