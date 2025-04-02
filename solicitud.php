<?php
session_start();
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

function getEstadoClass($estado) {
    switch($estado) {
        case 'rechazada':
            return 'estado-rechazada';
        case 'aceptada':
            return 'estado-aceptada';
        default:
            return 'estado-en-proceso';
    }
}

if (!isset($_SESSION['nombre']) || !isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit();
}

$rut = $_SESSION['rut'];
$sql_check = "SELECT id, nombre_solicitante, rut, fecha_solicitud, motivo_solicitud, fecha_entrega, nro_serie_equipo, estado 
              FROM solicitudes WHERE rut = ? AND fecha_solicitud IS NOT NULL";
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

if (isset($_GET['nombre_equipo'])) {
    $nombre_equipo = $_GET['nombre_equipo'];

    $sql = "SELECT nro_serie FROM equipos WHERE nombre_equipo = ? AND estado = 'disponible'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre_equipo);
    $stmt->execute();
    $result = $stmt->get_result();

    $numeros_serie = [];
    while ($row = $result->fetch_assoc()) {
        $numeros_serie[] = $row['nro_serie'];
    }

    echo json_encode($numeros_serie);
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

            $sql_correos = "SELECT correo FROM usuarios";
            $result_correos = $conn->query($sql_correos);
            
            if ($result_correos->num_rows > 0) {
                while ($row = $result_correos->fetch_assoc()) {
                    $correo = $row['correo'];

                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'valdiviaalejandro2001@gmail.com';
                        $mail->Password = 'huyu fyqt jdll ayrh';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('valdiviaalejandro2001@gmail.com', 'Sistema de Solicitudes TI');
                        $mail->addAddress($correo);

                        $mail->Subject = "Nueva Solicitud Registrada";
                        $mail->Body    = "Se ha registrado una nueva solicitud de equipo. Detalles:\n\n";
                        $mail->Body   .= "Solicitante: $nombre\n";
                        $mail->Body   .= "RUT: $rut\n";
                        $mail->Body   .= "Número de serie del equipo: $nro_serie\n";
                        $mail->Body   .= "Motivo de la solicitud: $motivo\n";
                        $mail->Body   .= "Fecha de solicitud: " . date("d/m/Y") . "\n\n";
                        $mail->Body   .= "Atentamente,\nSistema de Solicitudes TI";

                        $mail->send();
                    } catch (Exception $e) {
                        echo "Error al enviar el correo a: $correo. Error: {$mail->ErrorInfo}\n";
                    }
                }
            } else {
                echo "No se encontraron usuarios con correo registrado.";
            }

            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error al actualizar el estado del equipo: " . $conn->error;
        }
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['devolver'])) {
    $id_solicitud = $_POST['id_solicitud'];
    $nro_serie = $_POST['nro_serie'];

    $fecha_entrega = date('Y-m-d H:i:s');

    $sql_update_estado = "UPDATE solicitudes SET estado = 'terminada', fecha_entrega = ? WHERE id = ?";
    $stmt = $conn->prepare($sql_update_estado);
    $stmt->bind_param("si", $fecha_entrega, $id_solicitud);
    if ($stmt->execute()) {
        $sql_update_equipo = "UPDATE equipos SET estado = 'disponible' WHERE nro_serie = ?";
        $stmt = $conn->prepare($sql_update_equipo);
        $stmt->bind_param("s", $nro_serie);
        $stmt->execute();
        header("Location: ".$_SERVER['PHP_SELF']);
            exit();
    } else {
        echo "Error al actualizar el estado de la solicitud.";
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
            <div class="main-title">Solicitar insumos de TI</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()">Sesion</button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['rut']; ?></p>
            <p><strong>Nombre: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
        </div>
    </div>
</head>
<body>
<div class="container">
    <div class="main-content">
    <form method="POST" action="">
    <input type="text" name="rut" value="<?php echo $_SESSION['rut']; ?>" placeholder="RUT" required id="rut" readonly>
    <input type="text" name="nombre" value="<?php echo $_SESSION['nombre']; ?>" placeholder="Nombre Completo" required id="nombre" readonly>
    
    <select name="nombre_equipo" id="nombre_equipo" onchange="cargarNumerosSerie()" required>
        <option value="">Selecciona un equipo</option>
        <?php
            $sql = "SELECT DISTINCT nombre_equipo FROM equipos WHERE estado = 'disponible'";
            $equipos_result = $conn->query($sql);
            while ($equipo = $equipos_result->fetch_assoc()):
        ?>
            <option value="<?php echo $equipo['nombre_equipo']; ?>">
                <?php echo htmlspecialchars($equipo['nombre_equipo']); ?>
            </option>
        <?php endwhile; ?>
    </select><br><br>
    
    <select name="nro_serie" id="nro_serie" required>
        <option value="">Selecciona un número de serie</option>
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
                        <th>Fecha Solicitud</th>
                        <th>Estado Solicitud</th>
                        <th>Acciones</th>
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
                                echo $fecha_solicitud->format('d/m/y');?></td>
                            <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <?php if ($solicitud['estado'] == 'rechazada'): ?>
                                    <div class="rechazo-info">
                                        <p><strong>Motivo:</strong> 
                                            <?php 
                                                $id = $solicitud['id'];
                                                $sql_rechazo = "SELECT motivo_rechazo FROM pedicion WHERE id_solicitud = '$id'";
                                                $pedicion_result = $conn->query($sql_rechazo);
                                                $pedicion = $pedicion_result->fetch_assoc();
                                                echo htmlspecialchars($pedicion['motivo_rechazo']);
                                            ?>
                                        </p>
                                    </div>
                                    <?php elseif ($solicitud['estado'] == 'aceptada'): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="nro_serie" value="<?php echo $solicitud['nro_serie_equipo']; ?>">
                                        <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id']; ?>">
                                        <button type="submit" name="devolver" class="rechazar-btn-table">Devolver Equipo</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tienes solicitudes realizadas.</p>
        <?php endif; ?>
    </div>
</div>
    <div id="rechazoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeRechazoModal()">&times;</span>
            <p id="motivo_rechazo"></p>
        </div>
    </div>
    <script>
        function showRechazoModal(motivo) {
            document.getElementById('motivo_rechazo').innerText = motivo;
            document.getElementById('rechazoModal').style.display = 'block';
        }

        function closeRechazoModal() {
            document.getElementById('rechazoModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('rechazoModal')) {
                closeRechazoModal();
            }
        }
    </script>
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