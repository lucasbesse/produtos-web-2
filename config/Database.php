<?php

class Database
{
    private string $host = 'localhost';
    private string $port = '5432';
    private string $dbname = 'loja_virtual';
    private string $user = 'postgres';
    private string $password = 'ucs';

    private ?PDO $connection = null;

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->connection = new PDO($dsn, $this->user, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return $this->connection;
    }
}