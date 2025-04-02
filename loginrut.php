<?php
session_start();

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
<link rel="stylesheet" href="styles.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Ingreso de Solicitudes de Insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <form action="logout.php" method="POST">
        <button type="submit" class="volver-btn">Volver</button>
        </form>
    </div>
    <script>
        function mostrarError(message) {
            const errorMessage = document.getElementById("error-message");
            errorMessage.textContent = message;
            errorMessage.style.display = "block";
        }

        function validarRUTInput() {
            const rutInput = document.getElementById("rut").value;
            const rut = rutInput.replace(/\./g, "").replace("-", "");
            
            const regex = /^[0-9]{7,8}[0-9kK]{1}$/;
            if (!regex.test(rut)) {
                mostrarError("El RUT ingresado no tiene un formato válido.");
                return false;
            }

            const rut_numeros = rut.slice(0, -1);
            const rut_dv = rut.slice(-1).toUpperCase();
            
            let suma = 0;
            let factor = 2;
            for (let i = rut_numeros.length - 1; i >= 0; i--) {
                suma += parseInt(rut_numeros.charAt(i)) * factor;
                factor = (factor === 7) ? 2 : factor + 1;
            }

            const dv_calculado = 11 - (suma % 11);
            let dv_final;
            if (dv_calculado === 11) {
                dv_final = '0';
            } else if (dv_calculado === 10) {
                dv_final = 'K';
            } else {
                dv_final = dv_calculado.toString();
            }

            if (dv_final !== rut_dv) {
                mostrarError("El RUT ingresado es incorrecto.");
                return false;
            }
            return true;
        }

        function validarFormulario(event) {
            if (!validarRUTInput()) {
                event.preventDefault();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Ingresa tu Nombre y RUT</h2>
        
        <div id="error-message" class="error-message" style="display: none;"></div>

        <form method="POST" onsubmit="validarFormulario(event)">
            <input type="text" name="nombre" placeholder="Nombre Completo" required>
            <input type="text" name="rut" id="rut" placeholder="RUT" required onblur="validarRUTInput()">
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
