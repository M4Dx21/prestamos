<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    if ($usuario === 'admin.hfbc' && $contrasena === 'ti.2025') {
        $_SESSION['role'] = 'admin';

        if ($_SESSION['role'] == 'admin') {
            header("Location: admin.php");
            exit();
        } elseif ($_SESSION['role'] == 'prestador') {
            header("Location: prestador.php");
            exit();
        } elseif ($_SESSION['role'] == 'solicitante') {
            header("Location: solicitud.php");
            exit();
        }
    } else {
        $error = "Credenciales incorrectas. Intenta nuevamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="styles.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Ingreso Solicitudes Insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <form action="logout.php" method="POST">
            <button type="submit" class="volver-btn">Volver</button>
        </form>
    </div>
</head>
<body>
    <div class="container">
        <h2>Ingresa tu Usuario y Contraseña</h2>
        <?php if (!empty($error)): ?>
            <div id="error-message" class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit">Continuar</button>
        </form>
    </div>

    <style>
        .error-message {
            color: red;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</body>
</html>
