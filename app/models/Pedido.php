<?php

require_once __DIR__ . '/../config/Database.php';

class Pedido
{
    private PDO $conn;
    private string $table = 'pedido';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (data_pedido, data_entrega, situacao, cliente_id)
                VALUES
                    (:data_pedido, :data_entrega, :situacao, :cliente_id)
                RETURNING numero";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':data_pedido' => $data['data_pedido'],
            ':data_entrega' => $data['data_entrega'] ?? null,
            ':situacao' => $data['situacao'],
            ':cliente_id' => $data['cliente_id']
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $numero, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET data_pedido = :data_pedido,
                    data_entrega = :data_entrega,
                    situacao = :situacao,
                    cliente_id = :cliente_id
                WHERE numero = :numero";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':data_pedido' => $data['data_pedido'],
            ':data_entrega' => $data['data_entrega'] ?? null,
            ':situacao' => $data['situacao'],
            ':cliente_id' => $data['cliente_id'],
            ':numero' => $numero
        ]);
    }

    public function delete(int $numero): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE numero = :numero";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':numero' => $numero]);
    }

    public function findByNumero(int $numero): ?array
    {
        $sql = "SELECT p.*, c.nome AS cliente_nome
                FROM {$this->table} p
                INNER JOIN cliente c ON c.id = p.cliente_id
                WHERE p.numero = :numero";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':numero' => $numero]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findAll(): array
    {
        $sql = "SELECT p.*, c.nome AS cliente_nome
                FROM {$this->table} p
                INNER JOIN cliente c ON c.id = p.cliente_id
                ORDER BY p.numero";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
}