CREATE TABLE endereco (
    id SERIAL PRIMARY KEY,
    rua VARCHAR(100) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    complemento VARCHAR(100),
    bairro VARCHAR(100) NOT NULL,
    cep VARCHAR(20) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(50) NOT NULL
);

CREATE TABLE fornecedor (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    telefone VARCHAR(20),
    email VARCHAR(100),
    senha VARCHAR(255) NOT NULL,
    endereco_id INTEGER NOT NULL UNIQUE,
    CONSTRAINT fk_fornecedor_endereco
        FOREIGN KEY (endereco_id)
        REFERENCES endereco(id)
);

CREATE TABLE cliente (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    cartao_credito VARCHAR(100),
    senha VARCHAR(255) NOT NULL,
    endereco_id INTEGER NOT NULL UNIQUE,
    CONSTRAINT fk_cliente_endereco
        FOREIGN KEY (endereco_id)
        REFERENCES endereco(id)
);

CREATE TABLE produto (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    foto BYTEA,
    fornecedor_id INTEGER NOT NULL,
    CONSTRAINT fk_produto_fornecedor
        FOREIGN KEY (fornecedor_id)
        REFERENCES fornecedor(id)
);

CREATE TABLE estoque (
    id SERIAL PRIMARY KEY,
    quantidade INTEGER NOT NULL,
    preco DOUBLE PRECISION NOT NULL,
    produto_id INTEGER NOT NULL UNIQUE,
    CONSTRAINT fk_estoque_produto
        FOREIGN KEY (produto_id)
        REFERENCES produto(id)
);

CREATE TABLE pedido (
    numero SERIAL PRIMARY KEY,
    data_pedido DATE NOT NULL,
    data_entrega DATE,
    situacao VARCHAR(20) NOT NULL CHECK (situacao IN ('NOVO', 'ENTREGUE', 'CANCELADO')),
    cliente_id INTEGER NOT NULL,
    CONSTRAINT fk_pedido_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES cliente(id)
);

CREATE TABLE item_pedido (
    pedido_numero INTEGER NOT NULL,
    produto_id INTEGER NOT NULL,
    quantidade INTEGER NOT NULL,
    preco DOUBLE PRECISION NOT NULL,
    PRIMARY KEY (pedido_numero, produto_id),
    CONSTRAINT fk_itempedido_pedido
        FOREIGN KEY (pedido_numero)
        REFERENCES pedido(numero),
    CONSTRAINT fk_itempedido_produto
        FOREIGN KEY (produto_id)
        REFERENCES produto(id)
);