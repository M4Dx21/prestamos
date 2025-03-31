<?php
include 'db.php';

$sql = "SELECT id_equipo, nombre_equipo, nro_serie FROM Equipos";
$result = $conn->query($sql);

$equipos_disponibles = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipos_disponibles[] = $row;
    }
}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ingresar'])) {
    $rut = $_POST['rut'];
    $nombre = $_POST['nombre'];
    $pass = $_POST['pass'];
    $rol = 'prestamista';
    $correo = $_POST['correo'];

    $sql_insert = "INSERT INTO usuarios (rut, nombre, pass, rol, correo) 
                   VALUES ('$rut', '$nombre', '$pass', '$rol', '$correo')";

    if ($conn->query($sql_insert) === TRUE) {
        echo "Usuario registrado correctamente.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['insertar'])) {
    $nro_serie = $_POST['nro_serie'];
    $nombre = $_POST['nombre_equipo'];
    $estado = $_POST['estado'];

    $sql_insert = "INSERT INTO equipos (estado, nombre_equipo, nro_serie) 
                   VALUES ('$estado', '$nombre', '$nro_serie')";

    if ($conn->query($sql_insert) === TRUE) {
        echo "Equipo registrado correctamente.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    $nro_serie = $_POST['nro_serie'];    
    $sql_delete = "DELETE FROM equipos WHERE nro_serie = ?";

    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("i", $nro_serie);

        if ($stmt->execute()) {
            echo "El equipo ha sido eliminado correctamente.";
        } else {
            echo "Error al eliminar el equipo: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
    }
}

$sql = "SELECT id_equipo, nombre_equipo, nro_serie, estado FROM Equipos";
$result = $conn->query($sql);
$solicitudes_result = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_result[] = $row;
    }
}


$sql1 = "SELECT rut, nombre, correo FROM usuarios";
$result1 = $conn->query($sql1);

$solicitudes_result1 = [];
if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $solicitudes_result1[] = $row;
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
            <div class="main-title">Admin prestacion insumos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <form method="POST" action="">
            <input type="text" name="rut" placeholder="RUT" required id="rut" onblur="validarRUTInput()">
            <input type="text" name="nombre" placeholder="Nombre" required id="nombre">
            <input type="password" name="pass" placeholder="Contraseña" required id="pass">
            <input type="email" name="correo" placeholder="Correo" required id="correo">
            <button type="submit" name="ingresar">Registrar Usuario</button>
        </form>
        
        <form method="POST" action="">
            <input type="text" name="nombre_equipo" placeholder="Nombre del Equipo" required id="nombre_equipo">
            <input type="text" name="nro_serie" placeholder="Número de Serie" required id="nro_serie">
            <input type="text" name="estado" placeholder="Estado (disponible | no disponible)" required id="estado">
            <button type="submit" name="insertar">Agregar Equipo</button>
        </form>

        <?php if (!empty($solicitudes_result)): ?>
            <h3>Insumos Disponibles</h3>
            <table>
                <thead>
                    <tr>
                        <th>Equipos</th>
                        <th>Nro° Serie</th>
                        <th>Estado</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result as $solicitud): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['nombre_equipo']);?></td>
                            <td><?php echo htmlspecialchars($solicitud['nro_serie']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="nro_serie" value="<?php echo $solicitud['nro_serie']; ?>">
                                    <button type="submit" name="eliminar" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if (!empty($solicitudes_result1)): ?>
            <h3>Usuarios Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Correo</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result1 as $solicitud1): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud1['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud1['rut']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud1['correo']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud1['rut']; ?>">
                                    <button type="submit" name="eliminar_usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>
