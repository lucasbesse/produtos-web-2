<?php

require_once __DIR__ . '/../config/Database.php';

class Estoque
{
    private PDO $conn;
    private string $table = 'estoque';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (quantidade, preco, produto_id)
                VALUES
                    (:quantidade, :preco, :produto_id)
                RETURNING id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':quantidade' => $data['quantidade'],
            ':preco' => $data['preco'],
            ':produto_id' => $data['produto_id']
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET quantidade = :quantidade,
                    preco = :preco,
                    produto_id = :produto_id
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':quantidade' => $data['quantidade'],
            ':preco' => $data['preco'],
            ':produto_id' => $data['produto_id'],
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
        $sql = "SELECT e.*, p.nome AS produto_nome
                FROM {$this->table} e
                INNER JOIN produto p ON p.id = e.produto_id
                WHERE e.id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByProdutoId(int $produtoId): ?array
    {
        $sql = "SELECT e.*, p.nome AS produto_nome
                FROM {$this->table} e
                INNER JOIN produto p ON p.id = e.produto_id
                WHERE e.produto_id = :produto_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':produto_id' => $produtoId]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAll(): array
    {
        $sql = "SELECT e.*, p.nome AS produto_nome
                FROM {$this->table} e
                INNER JOIN produto p ON p.id = e.produto_id
                ORDER BY e.id";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
}