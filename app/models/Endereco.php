<?php

require_once __DIR__ . '/../config/Database.php';

class Endereco
{
    private PDO $conn;
    private string $table = 'endereco';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} 
                    (rua, numero, complemento, bairro, cep, cidade, estado)
                VALUES
                    (:rua, :numero, :complemento, :bairro, :cep, :cidade, :estado)
                RETURNING id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':rua' => $data['rua'],
            ':numero' => $data['numero'],
            ':complemento' => $data['complemento'] ?? null,
            ':bairro' => $data['bairro'],
            ':cep' => $data['cep'],
            ':cidade' => $data['cidade'],
            ':estado' => $data['estado']
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET rua = :rua,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cep = :cep,
                    cidade = :cidade,
                    estado = :estado
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':rua' => $data['rua'],
            ':numero' => $data['numero'],
            ':complemento' => $data['complemento'] ?? null,
            ':bairro' => $data['bairro'],
            ':cep' => $data['cep'],
            ':cidade' => $data['cidade'],
            ':estado' => $data['estado'],
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
}