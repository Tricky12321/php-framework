<?php


namespace Framework\Core;


use Exception;
use Framework\Modules\Base\Controllers\abstractPageController;
use OpenTelemetry\API\Trace\SpanInterface;
use RuntimeException;

class router
{
    private string $route;
    private array $routes = [];
    private array $routesLookup = [];
    private array $routesCountLookup = [];
    private ?array $pageParams = null;


    private bool $outputFileSet = false;
    private string $outputFile = "";

    private abstractPageController $pageController;

    public function __construct()
    {
        $routerSpan = opentelemetry::startSpan('session.router');
        $scope = $routerSpan->activate();
        $routerSpan->setAttribute('remote_ip', $_SERVER["REMOTE_ADDR"]);
        
        // Get the route without parameters /login?foo=bar will become /login
        $this->route = explode("?", $_SERVER['REQUEST_URI'])[0];

        // Import all the route files
        $this->importRoutes();
        $orderRoutesSpan = opentelemetry::startSpan('session.router.order-routes');
        foreach ($this->routes as $key => $route) {
            if (isset($route['id'])) {
                $this->routesLookup[$route['id']] = $key;
            }
        }
        $orderRoutesSpan->end();
        $actionSpan = opentelemetry::startSpan("session.router.action");
        if ($this->checkRoute($params)) {
            opentelemetry::$rootSpan->setStatus(200);
            opentelemetry::$rootSpan->setAttribute("http.status_code", 200);
            /** @var SpanInterface $actionSpan */
            $action = $this->getRouteAction() . "Action";
            $actionSpan->updateName("router.action." . $action);
            $actionSpan->setAttribute('session.action', $action);
            $url = $this->getCurrentRoute();
            opentelemetry::$rootSpan->updateName($url);
            $actionSpan->setAttribute("inlineVariables", $this->pageParams);
            $actionSpan->setAttribute('router.url', $url);
            $controller = $this->getRouteController();
            $actionSpan->setAttribute('router.controller', $controller);
        } else {
            opentelemetry::$rootSpan->setStatus(404);
            opentelemetry::$rootSpan->setAttribute("http.status_code", 404);
            #appdynamics_start_transaction("Error::PageNotFound(404)", AD_MVC);
            header("HTTP/1.1 404 Not Found");
            $url = $this->getCurrentRoute();
            echo "404 Page not found!\n" . $url;
            $actionSpan->recordException(new Exception("Page error 404 - Not found"));
        }
        $actionSpan->end();
        $routerSpan->end();
        $scope->detach();
    }

    private function scanDir()
    {
        $scanDirSpan = opentelemetry::startSpan('session.router.check-for-files.scan');
        $directories = scandir(ROOT . "/Modules/");
        $result = preg_grep('/^([^.])/', $directories);
        $scanDirSpan->end();
        return $result;
    }

    private function importRoutes()
    {
        $importRoutesSpan = opentelemetry::startSpan('session.router.import-routes', true, $importRoutesScope);
        $checkForFiles = opentelemetry::startSpan('session.router.import-routes.check-for-files', true, $checkforFilesScope);
        $folders = $this->scanDir();
        $routeFiles = [];
        foreach ($folders as $folder) {
            $routeFile = ROOT . "/Modules/$folder/Config/routes.php";
            if (file_exists($routeFile)) {
                $routeFiles[] = $routeFile;
            }
        }
        $checkforFilesScope->detach();
        $checkForFiles->end();
        $processRouteFiles = opentelemetry::startSpan('session.router.import-routes.process-routes');
        foreach ($routeFiles as $routeFile) {
            $routeFileContent = require $routeFile;
            $this->routes = array_merge($this->routes, $routeFileContent);
            array_map(function ($route) {
                $levelsCount = substr_count($route, "/");
                $this->routesCountLookup[$levelsCount][$route] = $this->routes[$route];
            }, array_keys($this->routes));
        }
        $processRouteFiles->end();
        $importRoutesScope->detach();
        $importRoutesSpan->end();
    }


    public function getUrl($id, $params = [], $inLineParams = []): string
    {
        $url = $this->routesLookup[$id];
        $urlParameters = "";
        // Check if the url has inline parameters
        if (preg_match("/\/\{.*\}/", $url)) {
            if (!empty($inLineParams)) {
                foreach ($inLineParams as $key => $inLineParam) {
                    $url = str_replace("{" . $key . "}", $inLineParam, $url);
                }
            } else {
                throw new RuntimeException("Error: url $url, needs to have in-line url parameters set!");
            }
        }

        if (!empty($params)) {
            $parameters = array_map(function ($key, $value) {
                return $key . "=" . urlencode($value);
            }, array_keys($params), $params);
            $urlParameters = "?" . implode("&", $parameters);
        }

        $url .= $urlParameters;
        return $url;
    }


    public function currentUrlId()
    {
        return $this->routes[$this->route]['id'];
    }

    public function getActiveStatus($pageID, $params = [], $inlinePrams = []): string
    {
        return $this->isActive($pageID, $params, $inlinePrams) ? "active" : "";
    }

    public function getAnyActiveStatus($pageIDs, $params = [], $inlinePrams = []): string
    {
        foreach ($pageIDs as $pageID) {
            if ($this->isActive($pageID, $params, $inlinePrams)) {
                return "active";
            }
        }
        return "";
    }

    public function isActive($pageID, $params = [], $inlineParams = []): bool
    {
        if ($this->getRoutes()[$this->getCurrentRoute()]["id"] === $pageID) {
            $hasParams = true;
            if (!empty($params)) {
                foreach ($params as $key => $param) {
                    if (isset($_GET[$key]) && $_GET[$key] != $param) {
                        $hasParams = false;
                    }
                }
            }
            if (!empty($inlineParams)) {
                foreach ($inlineParams as $key => $param) {
                    if ($this->pageController->getInlineUrlParameter($key) != $param) {
                        $hasParams = false;
                    }
                }
            }
            return $hasParams;
        }
        return false;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getCurrentRoute(): string
    {
        return $this->route;
    }

    /**
     * @return string|null
     */
    private function getRouteAction(): ?string
    {
        if (isset($this->routes[$this->route]['action'])) {
            return $this->routes[$this->route]['action'];
        }
        throw new Exception("This route does not have an action!");
    }

    private function getFilename()
    {
        return $this->routes[$this->route]['file'] ?? $this->getRouteAction() . ".phtml";
    }

    private function getRouteController()
    {
        return $this->routes[$this->route]['controller'];
    }

    private function getPageTitle()
    {
        return $this->routes[$this->route]['title'];
    }

    public function execute()
    {
        $executeSpan = opentelemetry::startSpan("session.router.execute");
        $scope = $executeSpan->activate();
        if ($this->checkRoute($params)) {
            $action = $this->getRouteAction() . "Action";
            $controller = $this->getRouteController();
            if (is_callable([
                                $controller,
                                $action,
                            ], true)) {

                $this->pageController = new $controller();
                $pageController = $this->pageController;
                $pageController->setInlineUrlParemeters($params);
                $routeInformation = $this->getRouteInformation();


                $folders = explode("\\", $controller);
                $file = ROOT . "/Modules/" . $folders[2] . "/Pages/" . $this->getFolder() . $this->getFilename();
                $variables = null;
                if ($this->isApi()) {
                    // If it is an API we close the session because API's are not allowed to write to the session and it can be blocking
                    session_write_close();
                }

                if ($action !== "Action") {
                    $variables = $pageController->$action();
                }

                if ($variables === null) {
                    $variables = [];
                }
                if ($this->isApi() && $this->isJson()) {
                    $pageController->sendJson($variables);
                } elseif ($this->isApi() && !$this->isJson()) {
                    $pageController->sendRaw($variables);
                } else {
                    $renderSpan = opentelemetry::startSpan("session.router.render", true, $renderScope);
                    $title = $this->getPageTitle();
                    $renderSpan->setAttribute("page.title", $title);
                    if ($this->outputFileSet) {
                        $pageController->render($variables, $this->outputFile, $title);
                    } else {
                        $pageController->render($variables, $file, $title);
                    }
                    $renderScope->detach();
                    $renderSpan->end();
                }
            } else {
                $exception = new Exception("Method $action does not exist in $controller");
                $executeSpan->recordException($exception);
                throw $exception;
            }
        }
        $scope->detach();
        $executeSpan->end();
    }


    public function checkRoute(&$params): bool
    {
        if ($this->pageParams == null) {
            $params = [];
            $depth = substr_count($this->route, "/");
            if (isset($this->routes[$this->route])) {
                $this->pageParams = $params;
                return true;
            } elseif (isset($this->routesCountLookup[$depth])) {
                $parts = explode("/", $this->route);
                // Loop though all the urls with the depth
                foreach ($this->routesCountLookup[$depth] as $key => $val) {
                    $keyParts = explode("/", $key);
                    $matches = 0;
                    foreach ($keyParts as $index => $value) {
                        // The parts are the same, continue
                        if ($value === $parts[$index]) {
                            $matches++;
                        } elseif (strlen($value) > 2 && $value[0] === "{" && $value[-1] === "}") { // Check if this is a inLineUrl parameter
                            $matches++;
                            // Store the parameters
                            $params[trim($value, "{}")] = $parts[$index];
                        }

                        if ($matches === count($parts)) {
                            // Set the route as the key to allow it to be extracted from the id pile
                            $this->route = $key;
                            $this->pageParams = $params;
                            return true;
                        }
                    }
                }
            }
        } else {
            $params = $this->pageParams;
            return true;
        }

        return false;
    }

    public function isApi(): bool
    {
        return isset($this->routes[$this->route]['api']) && $this->routes[$this->route]['api'] == true;
    }

    public function getRouteInformation()
    {
        return $this->routes[$this->route];
    }

    public function isJson(): bool
    {
        if (isset($this->routes[$this->route]['json'])) {
            return $this->routes[$this->route]['json'];
        } else {
            return true;
        }
    }

    public function getFolder(): string
    {
        $folder = isset($this->routes[$this->route]['folder']) ? "/" . $this->routes[$this->route]['folder'] . "/" : "";;
        return $folder;
    }

    /**
     * @return bool
     */
    public function isPurePage(): bool
    {
        if (isset($_GET["pure"]) && $_GET["pure"] == true) {
            return true;
        }
        return $this->routes[$this->route]['pure'] ?? false;
    }

    public function setOutputFile($filename, $module)
    {
        $module = ROOT . "/Modules/" . $module . "/Pages/";
        if (file_exists($module . "/" . $filename)) {
            $this->outputFile = $module . "/" . $filename;
            $this->outputFileSet = true;
        } else {
            throw new Exception("The requested output file does not exist");
        }
    }

}