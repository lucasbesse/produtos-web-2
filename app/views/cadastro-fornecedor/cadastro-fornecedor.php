<?php
session_start();

require_once __DIR__ . '/../../../config/Database.php';

$erro = '';
$sucesso = '';

$nome = '';
$descricao = '';
$telefone = '';
$email = '';
$cep = '';
$rua = '';
$numero = '';
$complemento = '';
$bairro = '';
$cidade = '';
$estado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $rua = trim($_POST['rua'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    if (
        $nome === '' || $email === '' || $senha === '' || $confirmar_senha === '' ||
        $cep === '' || $rua === '' || $numero === '' || $bairro === '' ||
        $cidade === '' || $estado === ''
    ) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            $checkSql = "SELECT id FROM fornecedor WHERE email = :email LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([':email' => $email]);

            if ($checkStmt->fetch()) {
                $erro = 'Já existe um fornecedor cadastrado com este e-mail.';
            } else {
                $conn->beginTransaction();

                $sqlEndereco = "INSERT INTO endereco
                    (rua, numero, complemento, bairro, cep, cidade, estado)
                    VALUES
                    (:rua, :numero, :complemento, :bairro, :cep, :cidade, :estado)
                    RETURNING id";

                $stmtEndereco = $conn->prepare($sqlEndereco);
                $stmtEndereco->execute([
                    ':rua' => $rua,
                    ':numero' => $numero,
                    ':complemento' => $complemento !== '' ? $complemento : null,
                    ':bairro' => $bairro,
                    ':cep' => $cep,
                    ':cidade' => $cidade,
                    ':estado' => strtoupper($estado)
                ]);

                $enderecoId = $stmtEndereco->fetchColumn();

                $sqlFornecedor = "INSERT INTO fornecedor
                    (nome, descricao, telefone, email, senha, endereco_id)
                    VALUES
                    (:nome, :descricao, :telefone, :email, :senha, :endereco_id)";

                $stmtFornecedor = $conn->prepare($sqlFornecedor);
                $stmtFornecedor->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao !== '' ? $descricao : null,
                    ':telefone' => $telefone !== '' ? $telefone : null,
                    ':email' => $email,
                    ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                    ':endereco_id' => $enderecoId
                ]);

                $conn->commit();

                $sucesso = 'Cadastro realizado com sucesso.';
                $nome = '';
                $descricao = '';
                $telefone = '';
                $email = '';
                $cep = '';
                $rua = '';
                $numero = '';
                $complemento = '';
                $bairro = '';
                $cidade = '';
                $estado = '';
            }
        } catch (PDOException $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $erro = 'Erro ao cadastrar fornecedor.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Fornecedor - Loja Virtual</title>
    <link rel="stylesheet" href="./cadastro-fornecedor.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>
            <div class="header-actions">
                <a href="../login/login.php" class="btn-login">Login</a>
                <a href="../home/index.php" class="btn-back">Voltar</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <div class="cadastro-card">
            <div class="card-top">
                <h1>Cadastro de fornecedor</h1>
                <p>Preencha os dados abaixo para criar sua conta de fornecedor.</p>
                <div class="switch-type">
                    Quer comprar produtos? <a href="../cadastro-cliente/cadastro-cliente.php">Cadastre-se como cliente</a>
                </div>
            </div>

            <?php if ($erro !== ''): ?>
                <div class="message error-message"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="message success-message"><?php echo htmlspecialchars($sucesso); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="section-title">Dados do fornecedor</div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nome">Nome *</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="descricao">Descrição</label>
                        <input type="text" id="descricao" name="descricao" value="<?php echo htmlspecialchars($descricao); ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input
                            type="text"
                            id="telefone"
                            name="telefone"
                            value="<?php echo htmlspecialchars($telefone); ?>"
                            maxlength="15"
                            placeholder="(54) 99999-9999"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                </div>

                <div class="section-title">Endereço</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="cep">CEP *</label>
                        <input
                            type="text"
                            id="cep"
                            name="cep"
                            value="<?php echo htmlspecialchars($cep); ?>"
                            maxlength="9"
                            placeholder="00000-000"
                            required
                        >
                        <small id="cep-status" class="field-help"></small>
                    </div>

                    <div class="form-group">
                        <label for="numero">Número *</label>
                        <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($numero); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="rua">Rua *</label>
                        <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($rua); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="complemento">Complemento</label>
                        <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($complemento); ?>">
                    </div>

                    <div class="form-group">
                        <label for="bairro">Bairro *</label>
                        <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($bairro); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cidade">Cidade *</label>
                        <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado *</label>
                        <input type="text" id="estado" name="estado" maxlength="2" value="<?php echo htmlspecialchars($estado); ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Cadastrar fornecedor</button>
            </form>
        </div>
    </main>

    <script src="./cadastro-fornecedor.js"></script>
</body>
</html>