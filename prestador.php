<?php
session_start();
include 'db.php';

if (!isset($_SESSION['rut'])) {
    header("Location: login.php");
    exit();
}

$resolucion_filtro = isset($_POST['resolucion']) ? $_POST['resolucion'] : '';

$sql_check = "SELECT id, nombre_solicitante, rut, fecha_solicitud, motivo_solicitud, fecha_entrega, nro_serie_equipo, estado FROM solicitudes WHERE 1";

if ($resolucion_filtro) {
    $sql_check .= " AND estado = '$resolucion_filtro'";
}

$sql_check .= " ORDER BY estado = 'en proceso' DESC, fecha_solicitud ASC";

$result = $conn->query($sql_check);
$solicitudes_result = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_result[] = $row;
    }
}

$sql = "SELECT id_equipo, nombre_equipo, nro_serie FROM equipos WHERE estado = 'disponible'";
$result = $conn->query($sql);

$equipos_disponibles = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipos_disponibles[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["aceptar"])) {
    $id = $_POST["id"]; 
    $nro_serie = $_POST["nro_serie"];

    if ($stmt = $conn->prepare("UPDATE solicitudes SET estado = 'aceptada' WHERE id = ?")) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $fecha_decision = date('Y-m-d H:i:s');
            $stmt_pedicion = $conn->prepare("INSERT INTO pedicion (id_solicitud, estado, fecha_decision) VALUES (?, 'aceptada', ?)");
            $stmt_pedicion->bind_param("is", $id, $fecha_decision);
            $stmt_pedicion->execute();
            
            $stmt_equipo = $conn->prepare("UPDATE equipos SET estado = 'no disponible' WHERE nro_serie = ?");
            $stmt_equipo->bind_param("s", $nro_serie);
            $stmt_equipo->execute();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensaje = "<div class='msg error'><span class='icon'>&#10060;</span> Error al aceptar la solicitud: " . $stmt->error . "</div>";
        }
    }    
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["rechazar"])) {
    $id = $_POST["id"];
    $motivo_rechazo = $_POST["motivo_rechazo"];
    $nro_serie = $_POST["nro_serie"];

    if ($stmt = $conn->prepare("UPDATE solicitudes SET estado = 'rechazada' WHERE id = ?")) { 
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $fecha_decision = date('Y-m-d H:i:s'); 
            $stmt_pedicion = $conn->prepare("INSERT INTO pedicion (id_solicitud, motivo_rechazo, estado, fecha_decision) VALUES (?, ?, 'rechazada', ?)");
            $stmt_pedicion->bind_param("iss", $id, $motivo_rechazo, $fecha_decision);
            $stmt_pedicion->execute();
            
            $stmt_equipo1 = $conn->prepare("UPDATE equipos SET estado = 'disponible' WHERE nro_serie = ?");
            $stmt_equipo1->bind_param("s", $nro_serie);
            $stmt_equipo1->execute();
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensaje = "<div class='msg error'><span class='icon'>&#10060;</span> Error al rechazar la solicitud: " . $stmt->error . "</div>";
        }
    }
}

if (isset($_POST['limpiar_filtros'])) {
    $resolucion_filtro = '';
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['query'])) {
    $query = $_GET['query'];

    $query = $conn->real_escape_string($query);

    $sql = "SELECT nombre_solicitante FROM solicitudes WHERE nombre_solicitante LIKE '%$query%' LIMIT 10";
    $result = $conn->query($sql);

    $suggestions = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row['nombre_solicitante'];
        }
    }
    echo json_encode($suggestions);
}

$sql_check = "SELECT id, nombre_solicitante, rut, fecha_solicitud, motivo_solicitud, fecha_entrega, nro_serie_equipo, estado FROM solicitudes WHERE 1";

if ($resolucion_filtro) {
    $sql_check .= " AND estado = '$resolucion_filtro'";
}
$sql_check .= " ORDER BY estado = 'en proceso' DESC, fecha_solicitud ASC";

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
            <div class="main-title">Solicitudes insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['rut']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <div class="filters">
            <form method="POST" action="">
                <label for="nombre_usuario">Nombre del Solicitante:</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" autocomplete="off" placeholder="Escribe el nombre del solicitante...">
                <ul id="suggestions" style="display: none; list-style-type: none; padding-left: 0;">
                    <!-- Las sugerencias aparecerán aquí -->
                </ul>
                <label for="resolucion">Filtrar por:</label>
                <select name="resolucion" id="resolucion">
                    <option value="">--Seleccionar--</option>
                    <option value="aceptada" <?php echo $resolucion_filtro == 'aceptada' ? 'selected' : ''; ?>>Aceptada</option>
                    <option value="rechazada" <?php echo $resolucion_filtro == 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                    <option value="en proceso" <?php echo $resolucion_filtro == 'en proceso' ? 'selected' : ''; ?>>En Proceso</option>
                    <option value="terminada" <?php echo $resolucion_filtro == 'terminada' ? 'selected' : ''; ?>>Terminada</option>
                </select>
                <button type="submit">Filtrar</button>
                <button type="submit" name="limpiar_filtros" class="limpiar-filtros-btn">Limpiar Filtros</button>
            </form>
        </div>

        <?php if (!empty($solicitudes_result)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Rut</th>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Nro° Serie</th>
                        <th>Fecha Solicitud</th>
                        <th>Estado</th>
                        <th>Resolución</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result as $solicitud): ?>
                        <?php 
                            $estado_class = '';
                            switch ($solicitud['estado']) {
                                case 'en proceso':
                                    $estado_class = 'estado-en-proceso';
                                    break;
                                case 'terminada':
                                    $estado_class = 'estado-aceptada';
                                    break;
                                case 'aceptada':
                                    $estado_class = 'estado-rechazada';
                                    break;
                            }
                        ?>
                        <tr class="<?php echo $estado_class; ?>">
                            <td><?php echo htmlspecialchars($solicitud['rut']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['nombre_solicitante']); ?></td>
                            <td><?php 
                                $nro_serie_equipo = $solicitud['nro_serie_equipo'];
                                $sql_equipo = "SELECT nombre_equipo FROM equipos WHERE nro_serie = '$nro_serie_equipo'";
                                $equipo_result = $conn->query($sql_equipo);
                                $equipo = $equipo_result->fetch_assoc();
                                echo htmlspecialchars($equipo['nombre_equipo']);
                            ?></td>
                            <td><?php echo htmlspecialchars($solicitud['nro_serie_equipo']); ?></td>
                            <td><?php 
                                $fecha_solicitud = new DateTime($solicitud['fecha_solicitud']);
                                echo $fecha_solicitud->format('d/m/y');
                            ?></td>
                            <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <?php if ($solicitud['estado'] == 'en proceso'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                        <input type="hidden" name="nro_serie" value="<?php echo $solicitud['nro_serie_equipo']; ?>">
                                        <button type="submit" name="aceptar" class="aceptar-btn-table">Aceptar</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $solicitud['id']; ?>">
                                        <input type="hidden" name="nro_serie" value="<?php echo $solicitud['nro_serie_equipo']; ?>">
                                        <input type="text" name="motivo_rechazo" placeholder="Motivo de rechazo" required>
                                        <button type="submit" name="rechazar" class="rechazar-btn-table">Rechazar</button>
                                    </form>
                                <?php elseif ($solicitud['estado'] == 'rechazada'): ?>
                                    <div class="rechazo-motivo"><?php 
                                            $sql_motivo_rechazo = "SELECT motivo_rechazo FROM pedicion WHERE id_solicitud = ".$solicitud['id']." AND estado = 'rechazada' LIMIT 1";
                                            $result_motivo = $conn->query($sql_motivo_rechazo);
                                            if ($result_motivo->num_rows > 0) {
                                                $row_motivo = $result_motivo->fetch_assoc();
                                                echo htmlspecialchars($row_motivo['motivo_rechazo']);
                                            } else {
                                                echo "Motivo no disponible.";
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay solicitudes disponibles.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleAccountInfo() {
            var accountInfo = document.getElementById('accountInfo');
            if (accountInfo.style.display === "none") {
                accountInfo.style.display = "block";
            } else {
                accountInfo.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
