<?php
include 'db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$sql = "SELECT id_equipo, nombre_equipo, nro_serie FROM Equipos";
$result = $conn->query($sql);

$equipos_disponibles = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipos_disponibles[] = $row;
    }
}

function validarRUT($rut) {
    $rut = str_replace(".", "", $rut);

    if (strpos($rut, '-') === false) {
        $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
    }

    if (!preg_match("/^[0-9]{7,8}-[0-9kK]{1}$/", $rut)) {
        return false;
    }

    list($rut_numeros, $rut_dv) = explode("-", $rut);

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

    return strtoupper($dv_calculado) == strtoupper($rut_dv);
}

function formatearRUT($rut) {
    $rut = str_replace(array("."), "", $rut);
    $dv = strtoupper(substr($rut, -1));
    $rut = substr($rut, 0, -1);
    $rut = strrev(implode(".", str_split(strrev($rut), 3)));
    return $rut . '-' . $dv;
}

function rutExists($rut, $conn) {
    $sql_check = "SELECT 1 FROM usuarios WHERE rut = ?";
    if ($stmt = $conn->prepare($sql_check)) {
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ingresar'])) {
    $rut = $_POST['rut'];

    if (!validarRUT($rut)) {
        echo "El RUT ingresado no es válido.";
        exit();
    }

    $nombre = $_POST['nombre'];
    $pass = $_POST['pass'];
    $rol = $_POST['rol'];
    $correo = $_POST['correo'];

    if (rutExists($rut, $conn)) {
        $sql_update = "UPDATE usuarios 
                       SET nombre = ?, pass = ?, rol = ?, correo = ?
                       WHERE rut = ?";

        if ($stmt = $conn->prepare($sql_update)) {
            $stmt->bind_param("sssss", $nombre, $pass, $rol, $correo, $rut);

            if ($stmt->execute()) {
                echo "Usuario actualizado correctamente.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                echo "Error: " . $sql_update . "<br>" . $conn->error;
            }

            $stmt->close();
        } else {
            echo "Error en la preparación de la consulta: " . $conn->error;
        }
    } else {
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    $nro_serie = $_POST['nro_serie'];    
    $estado_actual = $_POST['estado_actual'];

    $nuevo_estado = ($estado_actual == 'disponible') ? 'no disponible' : 'disponible';

    $sql_update = "UPDATE equipos SET estado = ? WHERE nro_serie = ?";

    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("si", $nuevo_estado, $nro_serie);

        if ($stmt->execute()) {
        } else {
            echo "Error al actualizar el estado del equipo: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar-usuario'])) {
    $rut = $_POST['rut'];    
    $sql_delete = "DELETE FROM usuarios WHERE rut = ?";

    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("s", $rut);

        if ($stmt->execute()) {
        } else {
            echo "Error al eliminar el usuario: " . $stmt->error;
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

$sql1 = "SELECT rut, nombre, correo FROM usuarios WHERE rol = 'prestamista'";
$result1 = $conn->query($sql1);

$solicitudes_result1 = [];
if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $solicitudes_result1[] = $row;
    }
}

$sql2 = "SELECT rut, nombre, pass FROM usuarios WHERE rol = 'solicitante'";
$result2 = $conn->query($sql2);
    
$solicitudes_result2 = [];
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $solicitudes_result2[] = $row;
    }
}

$sql3 = "SELECT rut, nombre, rol FROM usuarios WHERE rol = 'admin'";
$result3 = $conn->query($sql3);
    
$solicitudes_result3 = [];
if ($result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $solicitudes_result3[] = $row;
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
            <div class="main-title">Administrador Prestamos Insumos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <form action="logout.php" method="POST">
            <button type="submit" class="logout-btn">Salir</button>
        </form>
    </div>
    <script>
        function toggleCorreo() {
            var rol = document.getElementById('rol').value;
            var correoInput = document.getElementById('correo');
            
            if (rol === 'solicitante') {
                correoInput.disabled = true;
            }
            else if (rol === 'admin') {
                correoInput.disabled = true;
            } else {
                correoInput.disabled = false;
            }
        }

        function limpiarRut() {
            const rutInput = document.getElementById("rut");
            let rut = rutInput.value;
            rut = rut.replace(/\./g, "");
            rutInput.value = rut;
        }

        window.onload = function() {
            toggleCorreo();
        };
    </script>
</head>
<body>
    <div class="container">
        <form method="POST" action="">
            <select name="rol" required id="rol" onchange="toggleCorreo()">
                <option value="prestamista">Prestamista</option>
                <option value="solicitante">Solicitante</option>
                <option value="admin">Admin</option>
            </select>
            <input type="text" name="rut" placeholder="RUT (sin puntos ni guion, solo con guion para ingresar usuario tipo administrador)" required id="rut" onblur="validarRUTInput()" oninput="limpiarRut()">
            <input type="text" name="nombre" placeholder="Nombre" required id="nombre">
            <input type="password" name="pass" placeholder="Contraseña" required id="pass">

            <input type="email" name="correo" placeholder="Correo" required id="correo">
            <button type="submit" name="ingresar">Registrar Usuario</button>
        </form>
        
        <form method="POST" action="">
            <input type="text" name="nombre_equipo" placeholder="Nombre del Equipo" required id="nombre_equipo">
            <input type="text" name="nro_serie" placeholder="Número de Serie" required id="nro_serie">
            
            <select name="estado" required id="estado">
                <option value="disponible">Disponible</option>
                <option value="no disponible">No Disponible</option>
            </select>

            <button type="submit" name="insertar">Agregar Equipo</button>
        </form>

        <?php if (!empty($solicitudes_result)): ?>
            <h3>Insumos Disponibles</h3>
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>Equipos</th>
                        <th>Nro° Serie</th>
                        <th>Estado</th>
                        <th>Actualizar Estado</th>
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
                                    <input type="hidden" name="estado_actual" value="<?php echo $solicitud['estado']; ?>">
                                    <button type="submit" name="cambiar_estado" class="btn-table">
                                        Cambiar a <?php echo ($solicitud['estado'] == 'disponible') ? 'No Disponible' : 'Disponible'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($solicitudes_result1)): ?>
            <h3>Prestadores:</h3>
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
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($solicitudes_result2)): ?>
            <h3>Solicitantes:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Contraseña</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result2 as $solicitud2): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud2['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud2['rut']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud2['pass']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud2['rut']; ?>">
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($solicitudes_result3)): ?>
            <h3>Administradores:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Rol</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result3 as $solicitud3): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud3['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud3['rut']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud3['rol']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud3['rut']; ?>">
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
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