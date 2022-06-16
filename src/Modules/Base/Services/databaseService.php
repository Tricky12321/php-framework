<?php

namespace Framework\Modules\Base\Services;


use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Framework\Core\core;
use Framework\Modules\Base\Model\query;
use mysqli;
use Spatie\Async\Pool;
use SSP;

class databaseService
{

    private string $username;
    private string $password;
    private string $host;
    private string $db;
    private string $port;

    protected mysqli $connection;

    private bool $isConnected = false;

    public Pool $asyncPool;
    public SSP $ssp;

    public function __construct()
    {
        $this->asyncPool = Pool::create();
        $DBInformation = require core::getConfiguration()["database"];
        $this->host = $DBInformation["host"];
        $this->username = $DBInformation["username"];
        $this->password = $DBInformation["password"];
        $this->db = $DBInformation["database"];
        $this->port = $DBInformation["port"] ?? "3306";
        $this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->db, $this->port);
        $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, MYSQLI_TIMEOUT);
        $this->connection->set_charset("utf8");
        $this->isConnected = $this->connection->get_connection_stats() != false;
    }

    public function __destruct() {
        $this->connection->close();
    }

    public function query($query): query
    {
        $statement = $this->connection->prepare($query);
        if ($statement !== false) {
            $intermediateQuery = new query($statement, $this);
            $intermediateQuery->setQuery($query);
            return $intermediateQuery;
        } else {
            throw new Exception("Query Error: {$this->connection->error}");
        }
    }

    public function clearStoredResults()
    {
        do {
            if (count($this->connection->error_list) != 0) {
                echo "something went wrong!";
            }
            echo "";
            if ($res = $this->connection->store_result()) {
                if (count($this->connection->error_list) != 0) {
                    echo "something went wrong!";
                }
                $res->free();
            }

            $more = $this->connection->more_results();
            $next = $this->connection->next_result();
            echo "";
        } while ($more && $next);
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * @return mysqli
     */
    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    #[ArrayShape([
        "user" => "string",
        "pass" => "string",
        "db"   => "string",
        "host" => "string"
    ])] public function getConnectionCredentialsSSP() {
        return [
            "user" => $this->username,
            "pass" => $this->password,
            "db" => $this->db,
            "host" => $this->host
        ];
    }


}