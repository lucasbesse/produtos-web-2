<?php

require_once __DIR__ . '/../config/Database.php';

class Fornecedor
{
    private PDO $conn;
    private string $table = 'fornecedor';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table}
                    (nome, descricao, telefone, email, senha, endereco_id)
                VALUES
                    (:nome, :descricao, :telefone, :email, :senha, :endereco_id)
                RETURNING id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':senha' => password_hash($data['senha'], PASSWORD_DEFAULT),
            ':endereco_id' => $data['endereco_id']
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE {$this->table}
                SET nome = :nome,
                    descricao = :descricao,
                    telefone = :telefone,
                    email = :email,
                    endereco_id = :endereco_id
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':endereco_id' => $data['endereco_id'],
            ':id' => $id
        ]);
    }

    public function updateSenha(int $id, string $senha): bool
    {
        $sql = "UPDATE {$this->table} SET senha = :senha WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':senha' => password_hash($senha, PASSWORD_DEFAULT),
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
        $sql = "SELECT f.*, e.rua, e.numero, e.complemento, e.bairro, e.cep, e.cidade, e.estado
                FROM {$this->table} f
                INNER JOIN endereco e ON e.id = f.endereco_id
                WHERE f.id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByNome(string $nome): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE nome ILIKE :nome
                ORDER BY nome";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome' => '%' . $nome . '%'
        ]);

        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }

    public function autenticar(string $email, string $senha): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);

        $fornecedor = $stmt->fetch();

        if ($fornecedor && password_verify($senha, $fornecedor['senha'])) {
            return $fornecedor;
        }

        return null;
    }
}