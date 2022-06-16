<?php

namespace Framework\Modules\Base\Model;

use Framework\Core\core;
use Framework\Core\injection;
use Framework\Modules\Base\Services\databaseService;
use mysqli_stmt;
use Spatie\Async\Process\Runnable;

class query
{

    private databaseService $databaseService;
    private mysqli_stmt $statement;

    private bool $executed = false;

    private string $query;

    public function __construct($statement, $databaseService)
    {
        $this->statement = $statement;
        $this->databaseService = $databaseService;
    }

    public function setQuery(string $query) {
        $this->query = $query;
    }

    /**
     * @param array $params
     * @param int $mode
     * @return array
     */
    public function execute(array $params = [], int $mode = MYSQLI_BOTH): array
    {
        $statementType = substr($this->query,0,strpos($this->query," "));
        $executeSpan = opentelemetry::startSpan("database.execute.$statementType");
        $executeSpan->setAttribute("database.query",$this->query);
        $executeSpan->setAttribute("database.host",$this->databaseService->getConnection()->get_server_info());
        $paramsCount = count($params);
        if ($paramsCount > 0) {
            $types = str_repeat('s', count($params)); //types
            $this->statement->bind_param($types, ...$params);
        }

        $this->executed = $this->statement->execute();
        $result = $this->statement->get_result()->fetch_all($mode);
        $executeSpan->end();
        return $result;
    }

    /**
     * @param array $params
     * @param int $mode
     * @return array
     */
    public function executeOne(array $params = [], int $mode = MYSQLI_BOTH): ?array
    {
        $paramsCount = count($params);
        if ($paramsCount > 0) {
            $types = str_repeat('s', count($params)); //types
            $this->statement->bind_param($types, ...$params);
        }
        $this->executed = $this->statement->execute();
        return $this->statement->get_result()->fetch_array($mode);
    }

    /**
     * @param array $params
     * @param int $mode
     * @return array
     */
    public function insert(array $params = [], int $mode = MYSQLI_BOTH): int
    {
        $paramsCount = count($params);
        if ($paramsCount > 0) {
            $types = str_repeat('s', count($params)); //types
            $this->statement->bind_param($types, ...$params);
        }
        $this->executed = $this->statement->execute();
        $result = $this->databaseService->getConnection()->insert_id;
        return $result;
    }

    public function executeNoReturn(array $params = [])
    {
        $paramsCount = count($params);
        if ($paramsCount > 0) {
            $types = str_repeat('s', count($params)); //types
            $this->statement->bind_param($types, ...$params);
            $this->executed = $this->statement->execute();
        } else {
            $this->executed = $this->statement->execute();
        }
    }

    public function executeNoReturnAsync(array $params = []): Runnable
    {
        return $this->databaseService->asyncPool->add(function () use ($params) {
            $this->executeNoReturn($params);
        });
    }

    public function executeAsync(array $params = []): Runnable
    {
        return $this->databaseService->asyncPool->add(function () use ($params) {
            $this->execute($params);
        });
    }

    public function executeOneAsync(array $params = []): Runnable
    {
        return $this->databaseService->asyncPool->add(function () use ($params) {
            $this->executeOne($params);
        });
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }


}