<?php

namespace Framework\Modules\Base\Model;

interface databaseInterface
{
    public static function getById($id);
    public static function fromDatabaseOutput($object, $prefix = "");
}