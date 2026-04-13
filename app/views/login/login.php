<?php
session_start();

require_once __DIR__ . '/../../../config/Database.php';

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            $sqlFornecedor = "SELECT id, nome, email, senha, 'fornecedor' AS tipo
                              FROM fornecedor
                              WHERE email = :email
                              LIMIT 1";

            $stmtFornecedor = $conn->prepare($sqlFornecedor);
            $stmtFornecedor->execute([':email' => $email]);
            $usuario = $stmtFornecedor->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $sqlCliente = "SELECT id, nome, email, senha, 'cliente' AS tipo
                               FROM cliente
                               WHERE email = :email
                               LIMIT 1";

                $stmtCliente = $conn->prepare($sqlCliente);
                $stmtCliente->execute([':email' => $email]);
                $usuario = $stmtCliente->fetch(PDO::FETCH_ASSOC);
            }

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                header('Location: ../home/index.php');
                exit;
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao conectar com o banco de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Loja Virtual</title>
    <link rel="stylesheet" href="./login.css">
</head>
<body>

    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>
            <a href="../home/index.php" class="back-link">Voltar para a loja</a>
        </div>
    </header>

    <main class="page-content">
        <div class="login-card">
            <h1>Entrar</h1>
            <p>Faça login para acessar sua conta.</p>

            <?php if ($erro !== ''): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email); ?>"
                        placeholder="Digite seu e-mail"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        required
                    >
                </div>

                <button type="submit" class="btn-submit">Entrar</button>
            </form>

            <div class="register-link">
                Ainda não tem conta? <a href="../cadastro/cadastro.php">Cadastre-se</a>
            </div>
        </div>
    </main>

</body>
</html>