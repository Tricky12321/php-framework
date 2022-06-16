<?php

namespace Framework\Modules\Base\Model;

use DateTime as DateTimePHP;

class datetime
{
    public DateTimePHP $time;

    const MYSQL_FORMAT = "Y-m-d H:i:s";
    const MYSQL_DATE_FORMAT= "Y-m-d";

    public static function fromMysqlDatetime($datetime): ?datetime
    {
        $dateTimeObject = DateTimePHP::createFromFormat(self::MYSQL_FORMAT, $datetime);
        if ($dateTimeObject != false) {
            $datetime = new datetime();
            $datetime->setTime($dateTimeObject);
            return $datetime;
        }
        return null;
    }
    public static function fromMysqlDate($datetime): ?datetime
    {
        $dateTimeObject = DateTimePHP::createFromFormat(self::MYSQL_DATE_FORMAT, $datetime);
        if ($dateTimeObject != false) {
            $datetime = new datetime();
            $datetime->setTime($dateTimeObject);
            return $datetime;
        }
        return null;
    }

    public static function fromDatetime($format) {
        $datetime = new datetime();
        $dateTimeObject = new DateTimePHP($format);
        $datetime->setTime($dateTimeObject);
        return $datetime;
    }

    public function toMysqlDatetimeFormat()
    {
        return $this->time->format(self::MYSQL_FORMAT);
    }

    public function toMysqlDateFormat()
    {
        return $this->time->format(self::MYSQL_DATE_FORMAT);
    }

    public static function fromPost(?string $datetime): ?datetime
    {
        $dateTimeObject = DateTimePHP::createFromFormat("Y-m-d\TH:i", $datetime);
        if ($dateTimeObject != false) {
            $datetime = new datetime();
            $datetime->setTime($dateTimeObject);
            return $datetime;
        }
        return null;
    }

    public function toPostFormat() {
        return $this->time->format("Y-m-d\TH:i");
    }

    public function setTime(DateTimePHP $time)
    {
        $this->time = $time;
    }

    public function render()
    {
        return $this->time->format("Y-m-d H:i");
    }

}