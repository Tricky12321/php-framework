<?php /** @noinspection ALL */

namespace Framework\Core;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;
use Framework\AppDynamics\Exporter;
use Framework\Modules\Base\Services\databaseService;
use mysqli;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Jaeger\Exporter as JaegerExporter;
use OpenTelemetry\Contrib\OtlpHttp\Exporter as OTLPExporter;
use OpenTelemetry\Contrib\Zipkin\Exporter as ZipkinExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Environment\Variables as Env;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\Tracer;
use OpenTelemetry\SDK\Trace\TracerProvider;


class core
{
    public static router $router;
    private static string $environment;

    private const SQL_FILES_PATH = ROOT . "/database/SQL";
    private const DUMMY_FILES_PATH = ROOT . "/database/DummyData";



    public static function getCredentials($fileName)
    {
        /** @noinspection PhpIncludeInspection */
        return include CREDENTIALS . "/" . $fileName . ".php";
    }


    /**
     * @throws Exception
     */
    public static function init()
    {
        opentelemetry::openTelemetry();
        if (self::isProduction()) {
            self::$environment = CONFIG . "/config.prod.php";
        } elseif (self::isTesting()) {
            self::$environment = CONFIG . "/config.test.php";
        } elseif (self::isDevelopment()) {
            self::$environment = CONFIG . "/config.dev.php";
        } else {
            die("Invalid environment or URL");
        }
        self::createDatabase();
        if (self::isDevelopment()) {
            self::fillDummyData();
        }
        self::$router = injection::getClass(router::class);
        self::startSession();
        self::$router->execute();
        self::$rootSpan->end();
    }

    public static function hasInternet(): bool
    {
        return !in_array($_SERVER["HTTP_HOST"], NO_INTERNET_HOSTS);
    }

    #[Pure] public static function isTesting(): bool
    {
        return in_array($_SERVER["HTTP_HOST"], TESTHOSTS);
    }

    #[Pure] public static function isProduction(): bool
    {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
        return in_array($_SERVER["HTTP_HOST"], PRODUCTIONHOSTS);
    }

    #[Pure] public static function isDevelopment(): bool
    {
        $host = explode(":",$_SERVER["HTTP_HOST"])[0];
        if ( in_array($host, DEVHOSTS)) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            return true;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    private static function createDatabase()
    {
        if (!file_exists(ROOT . "/.database")) {
            $DBInformation = require core::getConfiguration()["database"];
            $initContent = file_get_contents(ROOT . "/database/init.sql");
            $port = $DBInformation["port"] ?? "3306";
            $mysqliClient = mysqli_init();
            $mysqliClient->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $mysqliClient->connect($DBInformation["host"], $DBInformation["username"], $DBInformation["password"], null, $port);
            $mysqliClient->query("CREATE DATABASE IF NOT EXISTS " . $DBInformation["database"]);
            $init = new mysqli($DBInformation["host"], $DBInformation["username"], $DBInformation["password"], $DBInformation["database"], $DBInformation["port"] ?? "3306");
            $init->query($initContent);
            /** @var databaseService $db */
            $db = injection::getClass(databaseService::class);
            $files = preg_grep('/^([^.])/', scandir(self::SQL_FILES_PATH));
            $filesSorted = [];
            foreach ($files as $file) {
                $num = explode(".", $file)[0];
                $filesSorted[$num] = $file;
            }
            foreach ($filesSorted as $key => $file) {
                $result = $db->query("SELECT * FROM database_patch WHERE name = ?;")->executeOne([$file]);
                if ($result === null) {
                    $content = file_get_contents(self::SQL_FILES_PATH . "/" . $file);
                    $result = $db->getConnection()->multi_query($content);
                    $db->clearStoredResults($file);
                    if ($result !== false) {
                        $db->query("INSERT INTO `database_patch` (`name`) VALUES (?);")->executeNoReturn([$file]);
                    }
                }
            }
            touch(ROOT . "/.database");
        }
    }

    /**
     * @throws Exception
     */
    private static function fillDummyData()
    {
        if (!file_exists(ROOT . "/.dummy_data")) {
            /** @var databaseService $db */
            $db = injection::getClass(databaseService::class);
            $files = preg_grep('/^([^.])/', scandir(self::DUMMY_FILES_PATH));
            foreach ($files as $file) {
                $dummyName = "dummy_" . $file;
                $result = $db->query("SELECT * FROM database_patch WHERE name = ?;")->executeOne([$dummyName]);
                if ($result === null) {
                    $content = str_replace("\n", "", file_get_contents(self::DUMMY_FILES_PATH . "/" . $file));
                    $db->getConnection()->multi_query($content);
                    $db->clearStoredResults();
                    $db->query("INSERT INTO `database_patch` (`name`) VALUES (?);")->executeNoReturn([$dummyName]);
                }
            }
            touch(ROOT . "/.dummy_data");
        }
    }

    private static function startSession()
    {
        header('P3P: CP="CAO PSA OUR"');
        $cookieParams = session_get_cookie_params();
        $cookieParams["samesite"] = "None";
        $cookieParams["secure"] = "true";
        session_set_cookie_params($cookieParams);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    #[NoReturn] public static function redirect($url)
    {
        header('Location: ' . $url);
        exit();
    }

    public static function redirectToCaller()
    {
        core::redirect($_SERVER["HTTP_REFERER"]);
    }

    /**
     * @throws Exception
     */
    #[NoReturn] public static function redirectToId($id, $params = [], $inLineParams = [])
    {
        /** @var router $router */
        $router = injection::getClass(router::class);
        $url = $router->getUrl($id, $params, $inLineParams);
        header('Location: ' . $url);
        exit();
    }

    #[NoReturn] public static function redirectToRoot()
    {
        header('Location: ' . self::getUrl());
        exit();
    }

    public static function getUrl(): string
    {
        $hostNoPort = explode(":", $_SERVER["HTTP_HOST"])[0];
        // If it is not localhost, or test server, force SSL
        if ($hostNoPort !== "localhost" && $hostNoPort !== "127.0.0.1" && $hostNoPort !== "10.8.0.1") {
            return "https://" . $_SERVER["HTTP_HOST"] . "/";
        }
        return $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/";
    }

    public static function getFullUrl(): string
    {
        // If it is not localhost, force SSL
        if ($_SERVER["HTTP_HOST"] !== "localhost" && $_SERVER["HTTP_HOST"] !== "127.0.0.1") {
            return "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        }
        return $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    }

    public static function getConfiguration()
    {
        return require self::$environment;
    }


    #[NoReturn] public static function returnExcelSheetFile($filename)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length:" . filesize($filename));
        header("Content-Disposition: attachment; $filename");
        readfile($filename);
        die();
    }

    public static function sendFileToClient($filePath, $filename)
    {
        session_write_close();
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: filename=\"$filename\"");
        header('Cache-control: private');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($filePath));
        flush();
        $file = fopen($filePath, "rb", false);
        while (!feof($file)) {
            $s = fread($file, 4096 * 4);
            if ($s === false) {
                break;
            }  //Crude
            echo $s;
            @ob_flush();
            @flush();
        }
        fclose($file);
    }

    public static function sendFileToClientFromString($string, $filename)
    {
        session_write_close();
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: filename=\"$filename\"");
        header('Cache-control: private');
        header('Content-Type: application/octet-stream');
        flush();
        echo $string;
        ob_flush();
        flush();
    }

    /**
     * @return string
     */
    public static function getIP()
    {
        $ip = $_SERVER["REMOTE_ADDR"];
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $forwardInformation = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $remoteIp = $forwardInformation[0];
        }
        return $ip;
    }
}