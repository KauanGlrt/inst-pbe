
<?php

// CONFIGURAÇÃO

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Permite requisições OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "config.php";

$rota = $_GET["rota"] ?? "";
$metodo = $_SERVER["REQUEST_METHOD"];

$corpo = json_decode(file_get_contents("php://input"), true) ?? [];

// FUNÇÃO DE RESPOSTA

function resposta($mensagem, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(["mensagem" => $mensagem]);
    exit;
}

// ========================================
// CRUD DE USUARIOS
// ========================================

// CREATE USUARIO

function createUsuario($con, $dados) {

    if (
        empty($dados["nome"]) ||
        empty($dados["username"]) ||
        empty($dados["email"]) ||
        empty($dados["senha"])
    ) {
        resposta("Preencha todos os campos obrigatórios!", 400);
    }

    $senhaHash = password_hash($dados["senha"], PASSWORD_DEFAULT);

    $stmt = $con->prepare(
        "INSERT INTO usuarios (nome, username, email, senha)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $dados["nome"],
        $dados["username"],
        $dados["email"],
        $senhaHash
    ]);

    resposta("Usuário criado com sucesso! ID: " . $con->lastInsertId(), 201);
}

// READ USUARIOS

function readUsuario($con) {

    $stmt = $con->query(
        "SELECT id_usuario, nome, username, email, data_cadastro
         FROM usuarios"
    );

    echo json_encode($stmt->fetchAll());
}

// UPDATE USUARIO

function updateUsuario($con, $id, $dados) {

    if (
        empty($dados["nome"]) ||
        empty($dados["username"]) ||
        empty($dados["email"])
    ) {
        resposta("Nome, username e email são obrigatórios!", 400);
    }

    if (!empty($dados["senha"])) {

        $senhaHash = password_hash($dados["senha"], PASSWORD_DEFAULT);

        $stmt = $con->prepare(
            "UPDATE usuarios
             SET nome = ?, username = ?, email = ?, senha = ?
             WHERE id_usuario = ?"
        );

        $stmt->execute([
            $dados["nome"],
            $dados["username"],
            $dados["email"],
            $senhaHash,
            $id
        ]);

    } else {

        $stmt = $con->prepare(
            "UPDATE usuarios
             SET nome = ?, username = ?, email = ?
             WHERE id_usuario = ?"
        );

        $stmt->execute([
            $dados["nome"],
            $dados["username"],
            $dados["email"],
            $id
        ]);
    }

    resposta("Usuário atualizado com sucesso!");
}

// DELETE USUARIO

function deleteUsuario($con, $id) {

    try {

        $con->beginTransaction();

        // Exclui curtidas feitas pelo usuário
        $stmt = $con->prepare(
            "DELETE FROM curtidas WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);

        // Exclui curtidas nas publicações desse usuário
        $stmt = $con->prepare(
            "DELETE FROM curtidas
             WHERE id_publicacao IN
             (SELECT id_publicacao FROM publicacoes WHERE id_usuario = ?)"
        );
        $stmt->execute([$id]);

        // Exclui publicações do usuário
        $stmt = $con->prepare(
            "DELETE FROM publicacoes WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);

        // Exclui o usuário
        $stmt = $con->prepare(
            "DELETE FROM usuarios WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);

        $con->commit();

        resposta("Usuário removido com sucesso!");

    } catch (Exception $e) {

        if ($con->inTransaction()) {
            $con->rollBack();
        }

        resposta("Erro ao remover usuário.", 500);
    }
}


// ========================================
// CRUD DE PUBLICACOES
// ========================================

// CREATE PUBLICACAO

function createPublicacao($con, $dados) {

    if (empty($dados["id_usuario"])) {
        resposta("Informe o ID do usuário!", 400);
    }

    $stmt = $con->prepare(
        "INSERT INTO publicacoes (id_usuario, legenda, imagem_url)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $dados["id_usuario"],
        $dados["legenda"] ?? null,
        $dados["imagem_url"] ?? null
    ]);

    resposta("Publicação criada com sucesso! ID: " . $con->lastInsertId(), 201);
}

// READ PUBLICACOES

function readPublicacao($con) {

    $stmt = $con->query(
        "SELECT
            publicacoes.id_publicacao,
            usuarios.nome,
            usuarios.username,
            publicacoes.id_usuario,
            publicacoes.legenda,
            publicacoes.imagem_url,
            publicacoes.data_publicacao
         FROM publicacoes
         INNER JOIN usuarios
         ON publicacoes.id_usuario = usuarios.id_usuario"
    );

    echo json_encode($stmt->fetchAll());
}

// UPDATE PUBLICACAO

function updatePublicacao($con, $id, $dados) {

    $stmt = $con->prepare(
        "UPDATE publicacoes
         SET legenda = ?, imagem_url = ?
         WHERE id_publicacao = ?"
    );

    $stmt->execute([
        $dados["legenda"] ?? null,
        $dados["imagem_url"] ?? null,
        $id
    ]);

    resposta("Publicação atualizada com sucesso!");
}

// DELETE PUBLICACAO

function deletePublicacao($con, $id) {

    $stmt = $con->prepare(
        "DELETE FROM publicacoes
         WHERE id_publicacao = ?"
    );

    $stmt->execute([$id]);

    resposta("Publicação removida com sucesso!");
}


// ========================================
// CRUD DE CURTIDAS
// ========================================

// CREATE CURTIDA

function createCurtida($con, $dados) {

    if (empty($dados["id_usuario"]) || empty($dados["id_publicacao"])) {
        resposta("Informe o ID do usuário e da publicação!", 400);
    }

    $stmt = $con->prepare(
        "INSERT INTO curtidas (id_usuario, id_publicacao)
         VALUES (?, ?)"
    );

    $stmt->execute([
        $dados["id_usuario"],
        $dados["id_publicacao"]
    ]);

    resposta("Curtida cadastrada com sucesso! ID: " . $con->lastInsertId(), 201);
}

// READ CURTIDAS

function readCurtida($con) {

    $stmt = $con->query(
        "SELECT
            curtidas.id_curtida,
            usuarios.nome,
            usuarios.username,
            curtidas.id_usuario,
            curtidas.id_publicacao,
            publicacoes.legenda,
            curtidas.data_curtida
         FROM curtidas
         INNER JOIN usuarios
         ON curtidas.id_usuario = usuarios.id_usuario
         INNER JOIN publicacoes
         ON curtidas.id_publicacao = publicacoes.id_publicacao"
    );

    echo json_encode($stmt->fetchAll());
}

// UPDATE CURTIDA

function updateCurtida($con, $id, $dados) {

    if (empty($dados["id_usuario"]) || empty($dados["id_publicacao"])) {
        resposta("Informe o ID do usuário e da publicação!", 400);
    }

    $stmt = $con->prepare(
        "UPDATE curtidas
         SET id_usuario = ?, id_publicacao = ?
         WHERE id_curtida = ?"
    );

    $stmt->execute([
        $dados["id_usuario"],
        $dados["id_publicacao"],
        $id
    ]);

    resposta("Curtida atualizada com sucesso!");
}

// DELETE CURTIDA

function deleteCurtida($con, $id) {

    $stmt = $con->prepare(
        "DELETE FROM curtidas
         WHERE id_curtida = ?"
    );

    $stmt->execute([$id]);

    resposta("Curtida removida com sucesso!");
}


// ========================================
// ROTAS NOMEADAS
// ========================================

try {

    // ROTA USUARIOS

    if ($rota === "usuarios") {

        switch ($metodo) {

            case "POST":
                createUsuario($con, $corpo);
                break;

            case "GET":
                readUsuario($con);
                break;

            case "PUT":
                updateUsuario($con, $_GET["id"] ?? 0, $corpo);
                break;

            case "DELETE":
                deleteUsuario($con, $_GET["id"] ?? 0);
                break;

            default:
                resposta("Método não permitido!", 405);
        }

    }

    // ROTA PUBLICACOES

    else if ($rota === "publicacoes") {

        switch ($metodo) {

            case "POST":
                createPublicacao($con, $corpo);
                break;

            case "GET":
                readPublicacao($con);
                break;

            case "PUT":
                updatePublicacao($con, $_GET["id"] ?? 0, $corpo);
                break;

            case "DELETE":
                deletePublicacao($con, $_GET["id"] ?? 0);
                break;

            default:
                resposta("Método não permitido!", 405);
        }

    }

    // ROTA CURTIDAS

    else if ($rota === "curtidas") {

        switch ($metodo) {

            case "POST":
                createCurtida($con, $corpo);
                break;

            case "GET":
                readCurtida($con);
                break;

            case "PUT":
                updateCurtida($con, $_GET["id"] ?? 0, $corpo);
                break;

            case "DELETE":
                deleteCurtida($con, $_GET["id"] ?? 0);
                break;

            default:
                resposta("Método não permitido!", 405);
        }

    }

    else {

        resposta("Rota não encontrada!", 404);

    }

} catch (PDOException $e) {

    http_response_code(400);

    echo json_encode([
        "erro" => "Erro ao executar a operação. Verifique os dados e os relacionamentos."
    ]);

}

?>
