<?php


use Framework\Modules\Base\Controllers\basePageController;

return [
    "/"        => [
        'controller'   => basePageController::class,
        'title'        => 'Frontpage',
        'action'       => 'frontpage',
        /* @see basePageController::frontpageAction() */
        'id'           => 'frontpage',
        'requireLogin' => true,
    ],
];