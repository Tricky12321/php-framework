<?php

namespace Framework\Modules\Base\Controllers;


use Framework\Core\injection;
use Framework\Model\Enum\messageTypes;

class basePageController extends abstractPageController
{
    public function init()
    {

    }

    public function frontpageAction(): array
    {
        return [];
    }
}
