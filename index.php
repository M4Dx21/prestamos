<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <h2>Selecciona tu rol</h2>
        <form method="post">
            <button type="submit" name="role" value="prestador">Prestador</button>
            <button type="submit" name="role" value="solicitante">Solicitante</button>
        </form>
        <?php
        if (isset($_POST['role'])) {
            session_start();
            $_SESSION['role'] = $_POST['role'];

            if ($_POST['role'] == 'prestador') {
                header("Location: login_form.php?role=prestador");
            } elseif ($_POST['role'] == 'solicitante') {
                header("Location: loginrut.php?role=solicitante");
            }
        }
        ?>
    </div>
</body>
</html>
