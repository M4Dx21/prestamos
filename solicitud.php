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
        case 'terminada':
            return 'estado-terminada';
        case 'en devolucion':
            return 'estado-devolucion';
        case 'devuelto':
            return 'estado-devuelto';
        default:
            return 'estado-en-proceso';
    }
}

if (!isset($_SESSION['nombre']) || !isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit();
}

$rut = $_SESSION['rut'];
$sql_check = "SELECT s.id, s.nombre_solicitante, s.rut, s.fecha_solicitud, s.motivo_solicitud, s.fecha_entrega, s.nro_serie_equipo, s.estado, p.motivo_rechazo
              FROM solicitudes s
              LEFT JOIN pedicion p ON s.id = p.id_solicitud
              WHERE s.rut = ? AND s.fecha_solicitud IS NOT NULL
              ORDER BY FIELD(s.estado, 'en proceso', 'aceptada', 'en devolucion', 'rechazada', 'terminada')";
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rut'], $_POST['nombre'], $_POST['motivo'], $_POST['nro_serie'])) {
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
                        $mail->Host = 'smtp.office365.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'manuel.arrano@redsalud.gob.cl';
                        $mail->Password = 'Z)230902217716ot';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('manuel.arrano@redsalud.gob.cl', 'Sistema de Solicitudes TI');
                        $mail->addAddress($correo);

                        $mail->Subject = "NUEVA SOLICITUD DE PRESTAMO DE EQUIPO TI";
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
                $_SESSION['mensaje'] = "Solicitud enviada correctamente";
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

    $sql_update_estado = "UPDATE solicitudes SET estado = 'en devolucion' WHERE id = ?";
    $stmt = $conn->prepare($sql_update_estado);
    $stmt->bind_param("s", $id_solicitud);
    if ($stmt->execute()) {
        $sql_update_equipo = "UPDATE equipos SET estado = 'no disponible' WHERE nro_serie = ?";
        $stmt = $conn->prepare($sql_update_equipo);
        $stmt->bind_param("s", $nro_serie);
        $stmt->execute();

        $sql_correos = "SELECT correo FROM usuarios";
        $result_correos = $conn->query($sql_correos);

        if ($result_correos->num_rows > 0) {
            while ($row = $result_correos->fetch_assoc()) {
                $correo = $row['correo'];

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.office365.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'manuel.arrano@redsalud.gob.cl';
                    $mail->Password = 'Z)230902217716ot';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('manuel.arrano@redsalud.gob.cl', 'Sistema de Solicitudes TI');
                    $mail->addAddress($correo);

                    $mail->Subject = "DEVOLUCION DE EQUIPO TI";
                    $mail->Body    = "Se ha registrado una devolución de equipo.\n\n";
                    $mail->Body   .= "Número de serie del equipo devuelto: $nro_serie\n";
                    $mail->Body   .= "Fecha de devolución: " . date("d/m/Y H:i:s") . "\n\n";
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
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['rut']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
        </div>
    </div>
</head>
<body class="prestador-page">
<div class="container">
    <div class="main-content">
    <form method="POST" action="" id="solicitudForm">
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
            <textarea name="motivo" placeholder="Motivo de solicitud (max 300 caracteres)" required></textarea>
            <button type="submit" name="solicitar">Solicitar equipo</button>
        </form>
        <?php if (!empty($solicitudes_result)): ?>
            <h3>Solicitudes Realizadas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Equipo</th>
                        <th>Motivo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result as $solicitud): ?>
                        <?php 
                        $estado_class = getEstadoClass(strtolower(trim($solicitud['estado']))); 
                        ?>
                        <tr class="<?php echo $estado_class; ?>">
                            <td><?php echo htmlspecialchars($solicitud['nombre_solicitante']); ?></td>
                            <td><?php 
                                $nro_serie_equipo = $solicitud['nro_serie_equipo'];
                                $sql_equipo = "SELECT nombre_equipo, nro_serie FROM equipos WHERE nro_serie = '$nro_serie_equipo'";
                                $equipo_result = $conn->query($sql_equipo);
                                $equipo = $equipo_result->fetch_assoc();
                                echo htmlspecialchars($equipo['nombre_equipo']) . " (Serie: " . htmlspecialchars($equipo['nro_serie']) . ")";
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
                                            <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($solicitud['estado'] == 'aceptada'): ?>
                                    <?php 
                                        $id = $solicitud['id'];
                                    ?>
                                    <?php if ($solicitud['motivo_rechazo']): ?>
                                        <div class="rechazo-info">
                                            <p><strong>Observación:</strong> <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?></p>
                                        </div>
                                    <?php endif; ?>
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
        function toggleAccountInfo() {
            var accountInfo = document.getElementById('accountInfo');
            if (accountInfo.style.display === "none") {
                accountInfo.style.display = "block";
            } else {
                accountInfo.style.display = "none";
            }
        }

        function fillForm(rut, nombre, nro_serie, motivo, id_solicitud) {
            document.getElementById('rut').value = rut;
            document.getElementById('nombre').value = nombre;
            document.getElementById('nro_serie').value = nro_serie;
            document.getElementsByName('motivo')[0].value = motivo;
        }
        document.getElementById("solicitudForm").addEventListener("submit", function(event) {
            event.preventDefault(); // Detiene el envío normal

            document.getElementById("loadingModal").style.display = "flex";

            // Espera al repaint y luego envía el formulario
            setTimeout(() => {
                event.target.submit(); // Envía el formulario real
            }, 1000); // Puedes aumentar a 500 si 300ms es muy poco
        });
        </script>

        <div id="loadingModal" class="modal" style="display: none;">
            <div class="modal-content">
                <p>Enviando solicitud...</p>
            </div>
        </div>

</body>
</html>
<?php
$conn->close();
?>
