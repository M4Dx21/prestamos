<?php
session_start();
include 'db.php';

// Asegurarnos de que el usuario esté autenticado
if (!isset($_SESSION['nombre']) || !isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit();
}

// Filtrar solicitudes por el RUT del solicitante
$rut = $_SESSION['rut'];
$sql_check = "SELECT id, nombre_solicitante, rut, fecha_solicitud, motivo_solicitud, fecha_entrega, nro_serie_equipo 
              FROM solicitudes WHERE rut = ? AND fecha_entrega IS NULL";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("s", $rut);
$stmt->execute();
$result = $stmt->get_result();

$solicitudes_result = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_result[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $rut = $_POST['rut'];
    $nombre = $_POST['nombre'];
    $nro_serie = $_POST['nro_serie'];
    $motivo = $_POST['motivo'];

    $sql_insert = "INSERT INTO Solicitudes (rut, nombre_solicitante, nro_serie_equipo, motivo_solicitud, fecha_solicitud) 
                   VALUES ('$rut', '$nombre', '$nro_serie', '$motivo', NOW())";
    
    if ($conn->query($sql_insert) === TRUE) {
        $sql_update = "UPDATE equipos SET estado = 'no disponible' WHERE nro_serie = '$nro_serie'";
        if ($conn->query($sql_update) === TRUE) {
            echo "Solicitud realizada con éxito y el equipo está marcado como no disponible.";
        } else {
            echo "Error al actualizar el estado del equipo: " . $conn->error;
        }
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
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
            <div class="main-title">Solicitudes insumos TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <form method="POST" action="">
            <input type="text" name="rut" value="<?php echo $_SESSION['rut']; ?>" placeholder="RUT" required id="rut" readonly>
            <input type="text" name="nombre" value="<?php echo $_SESSION['nombre']; ?>" placeholder="Nombre Completo" required id="nombre" readonly>
            <select name="nro_serie" id="nro_serie" required>
                <option value="">Selecciona un equipo</option>
                <?php
                $sql = "SELECT id_equipo, nombre_equipo, nro_serie FROM equipos WHERE estado = 'disponible'";
                $equipos_result = $conn->query($sql);
                while ($equipo = $equipos_result->fetch_assoc()): ?>
                    <option value="<?php echo $equipo['nro_serie']; ?>">
                        <?php echo htmlspecialchars($equipo['nombre_equipo']) . " (Serie: " . htmlspecialchars($equipo['nro_serie']) . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select><br><br>
            <textarea name="motivo" placeholder="Motivo de ingreso (max 300 caracteres)" required></textarea>
            <button type="submit" name="solicitar">Registrar Ingreso</button>
        </form>

        <?php if (!empty($solicitudes_result)): ?>
            <h3>Solicitudes Realizadas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Equipo Solicitado</th>
                        <th>Motivo</th>
                        <th>Fecha de Solicitud</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result as $solicitud): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['nombre_solicitante']); ?></td>
                            <td><?php 
                                $nro_serie_equipo = $solicitud['nro_serie_equipo'];
                                $sql_equipo = "SELECT nombre_equipo FROM equipos WHERE nro_serie = '$nro_serie_equipo'";
                                $equipo_result = $conn->query($sql_equipo);
                                $equipo = $equipo_result->fetch_assoc();
                                echo htmlspecialchars($equipo['nombre_equipo']);
                            ?></td>
                            <td><?php echo htmlspecialchars($solicitud['motivo_solicitud']); ?></td>
                            <td><?php 
                                $fecha_solicitud = new DateTime($solicitud['fecha_solicitud']);
                                echo $fecha_solicitud->format('d/m/y');
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tienes solicitudes realizadas.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>
