<?php


const ROOT = __DIR__;
require ROOT . "/../vendor/autoload.php";
const CONFIG = ROOT . "/Config";
const CREDENTIALS = CONFIG . "/Credentials";
const VERSION = "1.0.0";
const MYSQLI_TIMEOUT = 10;
const TESTHOSTS = [];
const DEVHOSTS = ["localhost","10.8.0.1"];
const PRODUCTIONHOSTS = [];
const NO_INTERNET_HOSTS = [];
const APPLICATION_NAME = "";