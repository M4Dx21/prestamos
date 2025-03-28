<?php
include 'db.php';

function validarRUT($rut) {
    $rut = str_replace(array(".", "-"), "", $rut);
    
    if (!preg_match("/^[0-9]{7,8}[0-9kK]{1}$/", $rut)) {
        return false;
    }

    $rut_numeros = substr($rut, 0, -1);
    $rut_dv = strtoupper(substr($rut, -1));

    $suma = 0;
    $factor = 2;
    for ($i = strlen($rut_numeros) - 1; $i >= 0; $i--) {
        $suma += $rut_numeros[$i] * $factor;
        $factor = ($factor == 7) ? 2 : $factor + 1;
    }

    $dv_calculado = 11 - ($suma % 11);
    if ($dv_calculado == 11) {
        $dv_calculado = '0';
    } elseif ($dv_calculado == 10) {
        $dv_calculado = 'K';
    }

    return $dv_calculado == $rut_dv;
}

function formatearRUT($rut) {
    $rut = str_replace(array(".", "-"), "", $rut);
    $dv = strtoupper(substr($rut, -1));
    $rut = substr($rut, 0, -1);
    $rut = strrev(implode(".", str_split(strrev($rut), 3)));
    return $rut . '-' . $dv;
}

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $rut = $_POST['rut'];
    $pass = $_POST['pass'];

    if (validarRUT($rut)) {
        $sql = "SELECT * FROM usuarios WHERE rut = '$rut' AND pass = '$pass'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $_SESSION['rut'] = $rut;
            $_SESSION['nombre'] = $result->fetch_assoc()['nombre'];
            header("Location: prestador.php");
            exit();
        } else {
            $error = "Credenciales incorrectas. Intenta nuevamente.";
        }
    } else {
        $error = "RUT no válido.";
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
            <div class="main-title">Solicitudes Insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <h2>Iniciar sesión</h2>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="rut" placeholder="RUT" required id="rut" onblur="validarRUTInput()">
            <input type="password" name="pass" placeholder="Contraseña" required>
            <button type="submit" name="solicitar">INGRESAR</button>
        </form>
    </div>
</body>
</html>
