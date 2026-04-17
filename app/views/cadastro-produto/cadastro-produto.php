<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/Database.php';

if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['usuario_tipo']) ||
    $_SESSION['usuario_tipo'] !== 'fornecedor'
) {
    session_unset();
    session_destroy();
    header('Location: ../login/login.php');
    exit;
}

$erro = '';
$nome = '';
$descricao = '';
$quantidade = '';
$preco = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = trim($_POST['quantidade'] ?? '');
    $preco = trim($_POST['preco'] ?? '');
    $fornecedorId = (int) $_SESSION['usuario_id'];

    if ($nome === '') {
        $erro = 'Preencha o nome do produto.';
    } elseif ($quantidade === '' || $preco === '') {
        $erro = 'Preencha quantidade e preço.';
    } elseif (!ctype_digit($quantidade)) {
        $erro = 'A quantidade deve ser um número inteiro positivo.';
    } else {
        $precoNormalizado = str_replace('.', '', $preco);
        $precoNormalizado = str_replace(',', '.', $precoNormalizado);

        if (!is_numeric($precoNormalizado) || (float) $precoNormalizado < 0) {
            $erro = 'Preço inválido.';
        } else {
            try {
                $database = new Database();
                $conn = $database->getConnection();

                $conn->beginTransaction();

                $sqlProduto = "INSERT INTO produto (nome, descricao, foto, fornecedor_id)
                               VALUES (:nome, :descricao, :foto, :fornecedor_id)
                               RETURNING id";

                $stmtProduto = $conn->prepare($sqlProduto);
                $stmtProduto->execute([
                    ':nome' => $nome,
                    ':descricao' => $descricao !== '' ? $descricao : null,
                    ':foto' => null,
                    ':fornecedor_id' => $fornecedorId
                ]);

                $produtoId = (int) $stmtProduto->fetchColumn();

                $sqlEstoque = "INSERT INTO estoque (quantidade, preco, produto_id)
                               VALUES (:quantidade, :preco, :produto_id)";

                $stmtEstoque = $conn->prepare($sqlEstoque);
                $stmtEstoque->execute([
                    ':quantidade' => (int) $quantidade,
                    ':preco' => (float) $precoNormalizado,
                    ':produto_id' => $produtoId
                ]);

                $conn->commit();

                header('Location: ../home/index.php?produto_criado=1');
                exit;
            } catch (PDOException $e) {
                if (isset($conn) && $conn->inTransaction()) {
                    $conn->rollBack();
                }

                $erro = 'Erro ao cadastrar produto.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Produto - Loja Virtual</title>
    <link rel="stylesheet" href="./cadastro-produto.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-content">
            <a href="../home/index.php" class="logo">Minha Loja</a>

            <div class="header-actions">
                <a href="../home/index.php" class="btn-back">Voltar</a>
            </div>
        </div>
    </header>

    <main class="page-content">
        <div class="cadastro-card">
            <div class="card-top">
                <h1>Cadastrar produto</h1>
                <p>Preencha os dados abaixo para cadastrar um novo produto.</p>
            </div>

            <?php if ($erro !== ''): ?>
                <div class="message error-message">
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nome">Nome do produto *</label>
                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            value="<?php echo htmlspecialchars($nome); ?>"
                            required
                        >
                    </div>

                    <div class="form-group full-width">
                        <label for="descricao">Descrição</label>
                        <textarea
                            id="descricao"
                            name="descricao"
                            rows="5"
                        ><?php echo htmlspecialchars($descricao); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="quantidade">Quantidade *</label>
                        <input
                            type="number"
                            id="quantidade"
                            name="quantidade"
                            min="0"
                            value="<?php echo htmlspecialchars($quantidade); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="preco">Preço *</label>
                        <input
                            type="text"
                            id="preco"
                            name="preco"
                            value="<?php echo htmlspecialchars($preco); ?>"
                            placeholder="0,00"
                            required
                        >
                        <small class="field-help">Digite por exemplo 10,50</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="foto">Imagem do produto</label>
                        <input
                            type="file"
                            id="foto"
                            name="foto"
                            disabled
                        >
                        <small class="field-help">
                            Upload ainda não implementado nesta etapa.
                        </small>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Cadastrar produto</button>
            </form>
        </div>
    </main>

    <script>
        const precoInput = document.getElementById('preco');

        function apenasNumeros(valor) {
            return valor.replace(/\D/g, '');
        }

        function aplicarMascaraPreco(valor) {
            valor = apenasNumeros(valor);

            if (!valor) {
                return '';
            }

            while (valor.length < 3) {
                valor = '0' + valor;
            }

            const centavos = valor.slice(-2);
            let inteiro = valor.slice(0, -2);

            inteiro = inteiro.replace(/^0+(?=\d)/, '');

            return `${inteiro},${centavos}`;
        }

        precoInput.addEventListener('input', function () {
            this.value = aplicarMascaraPreco(this.value);
        });
    </script>
</body>
</html>