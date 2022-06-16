<?php


/**
 * @var String $file
 * @var String $title
 * @var abstractPageController $this
 */

use Framework\Core\opentelemetry;
use Framework\Modules\Base\Controllers\abstractPageController;

$basePage = opentelemetry::startSpan("session.router.render.base-page", true, $scope);
?>
    <html lang="en">
    <head>
        <?php
        $headerSpan = opentelemetry::startSpan("session.router.render.header", true, $headerScope);
        $this->requirePHPFile("Modules/Base/Pages/header.php", ['title' => $title]);
        $headerScope->detach();
        $headerSpan->end();
        ?>
    </head>
    <body>
    <?php
    $fileSpan = opentelemetry::startSpan("session.router.render.page", true, $fileScope);
    if (!$this->router->isPurePage()) : ?>
        <div class="app-content content">
            <div class="content-wrapper">
                <?php require $file ?>
            </div>
        </div>
    <?php else: ?>
        <?php require $file ?>
    <?php endif;
    $fileScope->detach();
    $fileSpan->end();
    ?>
    <footer>
        <?php
        $footerSpan = opentelemetry::startSpan("session.router.render.footer", true, $footerScope);
        $this->requirePHPFile("Modules/Base/Pages/footer.php");
        $footerScope->detach();
        $footerSpan->end();
        ?>
    </footer>
    </body>
    </html>

<?php
$scope->detach();
$basePage->end();