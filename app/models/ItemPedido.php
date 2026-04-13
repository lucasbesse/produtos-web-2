<?php

require_once __DIR__ . '/../config/Database.php';

class ItemPedido
{
    private PDO $conn;
    private string $table = 'item_pedido';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): bool
    {
        $sql = "INSERT INTO {$this->table}
                    (pedido_numero, produto_id, quantidade, preco)
                VALUES
                    (:pedido_numero, :produto_id, :quantidade, :preco)";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':pedido_numero' => $data['pedido_numero'],
            ':produto_id' => $data['produto_id'],
            ':quantidade' => $data['quantidade'],
            ':preco' => $data['preco']
        ]);
    }

    public function update(int $pedidoNumero, int $produtoId, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET quantidade = :quantidade,
                    preco = :preco
                WHERE pedido_numero = :pedido_numero
                  AND produto_id = :produto_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':quantidade' => $data['quantidade'],
            ':preco' => $data['preco'],
            ':pedido_numero' => $pedidoNumero,
            ':produto_id' => $produtoId
        ]);
    }

    public function delete(int $pedidoNumero, int $produtoId): bool
    {
        $sql = "DELETE FROM {$this->table}
                WHERE pedido_numero = :pedido_numero
                  AND produto_id = :produto_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':pedido_numero' => $pedidoNumero,
            ':produto_id' => $produtoId
        ]);
    }

    public function findByPedido(int $pedidoNumero): array
    {
        $sql = "SELECT ip.*, p.nome AS produto_nome
                FROM {$this->table} ip
                INNER JOIN produto p ON p.id = ip.produto_id
                WHERE ip.pedido_numero = :pedido_numero
                ORDER BY p.nome";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':pedido_numero' => $pedidoNumero
        ]);

        return $stmt->fetchAll();
    }
}