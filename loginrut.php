<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['nombre'] = $_POST['nombre'];
    $_SESSION['rut'] = $_POST['rut'];
    if ($_SESSION['role'] == 'prestador') {
        header("Location: prestador.php");
    } elseif ($_SESSION['role'] == 'solicitante') {
        header("Location: solicitud.php");
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso de Datos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="form-container">
        <h2>Ingresa tu Nombre y RUT</h2>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre Completo" required>
            <input type="text" name="rut" placeholder="RUT" required>
            <button type="submit">Continuar</button>
        </form>
    </div>
</body>
</html>
