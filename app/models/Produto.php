<?php

require_once __DIR__ . '/../config/Database.php';

class Produto
{
    private PDO $conn;
    private string $table = 'produto';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (nome, descricao, foto, fornecedor_id)
                VALUES
                    (:nome, :descricao, :foto, :fornecedor_id)
                RETURNING id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':foto' => $data['foto'] ?? null,
            ':fornecedor_id' => $data['fornecedor_id']
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET nome = :nome,
                    descricao = :descricao,
                    foto = :foto,
                    fornecedor_id = :fornecedor_id
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':foto' => $data['foto'] ?? null,
            ':fornecedor_id' => $data['fornecedor_id'],
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
        $sql = "SELECT p.*, f.nome AS fornecedor_nome
                FROM {$this->table} p
                INNER JOIN fornecedor f ON f.id = p.fornecedor_id
                WHERE p.id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByNome(string $nome): array
    {
        $sql = "SELECT p.*, f.nome AS fornecedor_nome
                FROM {$this->table} p
                INNER JOIN fornecedor f ON f.id = p.fornecedor_id
                WHERE p.nome ILIKE :nome
                ORDER BY p.nome";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome' => '%' . $nome . '%'
        ]);

        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        $sql = "SELECT p.*, f.nome AS fornecedor_nome
                FROM {$this->table} p
                INNER JOIN fornecedor f ON f.id = p.fornecedor_id
                ORDER BY p.id";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
}