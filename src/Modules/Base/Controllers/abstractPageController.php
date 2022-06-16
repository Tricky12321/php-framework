<?php

namespace Framework\Modules\Base\Controllers;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\NoReturn;
use JetBrains\PhpStorm\Pure;
use Framework\Core\core;
use Framework\Core\injection;
use Framework\Core\opentelemetry;
use Framework\Core\router;
use Framework\Model\Enum\requestTypes;
use Framework\Modules\Base\Services\messageService;

abstract class abstractPageController
{
    protected router $router;
    protected messageService $messageService;
    private const BASEPAGE = ROOT . "/Modules/Base/Pages/basePage.php";
    private array $endPageContent = [];
    private array $inlineUrlParameters = [];
    public static array $variables = [];

    public function __construct()
    {
        $span = opentelemetry::startSpan("pagecontroller.construct", true, $scope);
        $span->setAttribute("parent_class",get_class());
        $span->setAttribute("class",get_class($this));
        $this->router = injection::getClass(router::class);
        $this->messageService = injection::getClass(messageService::class);
        $this->init();
        $scope->detach();
        $span->end();
    }

    /**
     * @param array $variables // These are the variables that can be used in the Pages
     * @param string $file // This is the file to load the page HTML from
     * @param string $title
     */
    public function render(array $variables, string $file, string $title)
    {
        $this->lookup = [];
        $this->route = strtolower($_SERVER['REQUEST_URI']);
        foreach ($this->router->getRoutes() as $key => $route) {
            if (isset($route['id'])) {
                $this->lookup[$route['id']] = $key;
            }
        }
        extract($variables, EXTR_SKIP, "");
        if (file_exists($file)) {
            require self::BASEPAGE;
        } else {
            echo "No file: $file";
        }
    }

    /**
     * @throws Exception
     */
    public function isPost(): bool
    {
        return $this->getRequestType() == requestTypes::POST;
    }

    /**
     * @throws Exception
     */
    public function isGet(): bool
    {
        return $this->getRequestType() == requestTypes::GET;
    }

    public function getGet(): array
    {
        return $_GET;
    }

    public function getPost(): array
    {
        return $_POST;
    }

    public function getPostVal($val)
    {
        return $this->getPost()[$val] ?? null;
    }

    /**
     * Returns true of the device provided is a mobile device
     * @return bool
     */
    function isMobile(): bool
    {
        return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]) > 0;
    }

    /**
     * @throws Exception
     */
    public function getRequestType(): string
    {
        switch ($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                return requestTypes::GET;
            case "POST":
                return requestTypes::POST;
            case "UPDATE":
                return requestTypes::UPDATE;
            case "DELETE":
                return requestTypes::DELETE;
            case "PUT":
                return requestTypes::PUT;
            case "CONNECT":
                return requestTypes::CONNECT;
            case "OPTIONS":
                return requestTypes::OPTIONS;
            case "TRACE":
                return requestTypes::TRACE;
            case "PATCH":
                return requestTypes::PATCH;
        }
        throw new Exception("Unknown request type provided by client!");
    }

    public function getResource($filename, $filetype = "js"): string
    {
        return "/resources/$filename.$filetype";
    }

    public function getCustomResource($filename, $filetype = "css"): string
    {
        return "/custom/$filename.$filetype";

    }

    /**
     * @throws Exception
     */
    #[NoReturn] public function redirect($id, $params = [], $inLineParams = [])
    {
        core::redirectToId($id, $params, $inLineParams);
    }

    public function getFileUpload(): array
    {
        return $_FILES;
    }

    public function requirePHPFile($filename, $params = [])
    {
        $requireFile = opentelemetry::startSpan("file-system.require-file", true, $scope);
        $path = ROOT . "/" . $filename;
        $requireFile->updateName("file-system.require-file.".$filename);
        $requireFile->setAttribute("filepath", $path);
        extract($params, EXTR_SKIP, "");
        require_once $path;
        $scope->detach();
        $requireFile->end();
    }

    public function addEndPageContent($content, $first = false)
    {
        if ($first) {
            array_unshift($this->endPageContent, $content);
        } else {
            $this->endPageContent[] = $content;
        }
    }

    public function setInlineUrlParemeters(array $parameters)
    {
        $this->inlineUrlParameters = $parameters;
    }

    public function sendJson($variables)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($variables);
    }

    public function sendRaw($content)
    {
        header('Content-Type: text; charset=utf-8');
        echo $content;
    }

    /**
     * @throws Exception
     */
    public function getInlineUrlParameter($name)
    {
        if (isset($this->inlineUrlParameters[$name])) {
            return $this->inlineUrlParameters[$name];
        } else {
            throw new Exception("There is no inline Parameter with the name: $name");
        }
    }

    public function getInlineUrlParameters(): array
    {
        return $this->inlineUrlParameters;
    }

    public function getPageId()
    {
        return $this->router->currentUrlId();
    }

    public function getCurrentUrl()
    {
        return $this->router->getUrl($this->router->currentUrlId(), [], $this->getInlineUrlParameters());
    }

    public function init()
    {

    }

}
