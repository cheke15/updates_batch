<?php
require 'vendor/autoload.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "safeouts_ABB_Demo";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

function actualizarProducto($conn, $id, $data) {

    $campos_permitidos = [
        'Codigo', 'Descripcion', 'CedulaDescuento', 'PrecioDeLista',
        'Encargado', 'LineaProducto', 'EntregaRyder', 'EntregaRDCMiani',
        'EntregaFabricacion'
    ];

    // Construir la consulta UPDATE
    $updates = [];
    $params = [];
    $types = "";

    foreach ($data as $campo => $valor) {
        if (in_array($campo, $campos_permitidos) && $valor !== "") {
            $updates[] = "$campo = ?";
            $params[] = $valor;
            $types .= "s";
        }
    }

    if (empty($updates)) {
        return ["success" => false, "message" => "No hay campos para actualizar"];
    }

    $sql = "UPDATE B_InventarioEquipos SET " . implode(", ", $updates) . " WHERE ID = ?";
    $types .= "i";
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ["success" => false, "message" => "Error preparando la consulta: " . $conn->error];
    }

    $bind_params = array_merge([$types], $params);
    $tmp = [];
    foreach($bind_params as $key => $value) $tmp[$key] = &$bind_params[$key];
    call_user_func_array([$stmt, 'bind_param'], $tmp);

    $result = $stmt->execute();
    if ($result) {
        $update_sql = "UPDATE B_InventarioEquipos SET verify_changes = 'sensible' WHERE ID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $id);
        $update_stmt->execute();

        return ["success" => true, "message" => "Producto ID $id actualizado correctamente"];
    } else {
        return ["success" => false, "message" => "Error actualizando el producto: " . $stmt->error];
    }
}


function procesarLote($conn, $file) {
    $resultados = ["exito" => [], "error" => []];

    try {
        $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file);
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();

        // Obtener los encabezados (primera fila)
        $highestColumn = $sheet->getHighestColumn();
        $encabezados = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $encabezados[$col] = $sheet->getCell($col . '1')->getValue();
        }

        // Encontrar la columna del ID
        $id_column = array_search('ID', $encabezados);
        if (!$id_column) {
            throw new Exception("No se encontró la columna ID en el archivo Excel");
        }

        // Procesar filas de datos (desde la fila 2)
        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $id = $sheet->getCell($id_column . $row)->getValue();
            if (!$id) continue; // Saltar filas sin ID

            $data = [];
            foreach ($encabezados as $col => $campo) {
                if ($campo != 'ID') {
                    $valor = $sheet->getCell($col . $row)->getValue();
                    if ($valor !== null && $valor !== '') {
                        $data[$campo] = $valor;
                    }
                }
            }

            if (!empty($data)) {
                $resultado = actualizarProducto($conn, $id, $data);
                if ($resultado["success"]) {
                    $resultados["exito"][] = "ID $id: " . $resultado["message"];
                } else {
                    $resultados["error"][] = "ID $id: " . $resultado["message"];
                }
            }
        }

    } catch (Exception $e) {
        $resultados["error"][] = "Error procesando archivo: " . $e->getMessage();
    }

    return $resultados;
}

function procesarSolicitudIndividual($conn, $id, $data) {
    if (!$id || $id <= 0) {
        return ["success" => false, "message" => "ID de producto inválido o no proporcionado"];
    }


    $check_sql = "SELECT ID FROM B_InventarioEquipos WHERE ID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows == 0) {
        return ["success" => false, "message" => "El producto con ID $id no existe"];
    }

    return actualizarProducto($conn, $id, $data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ["success" => false, "message" => "Operación no reconocida"];


    if (isset($_GET['mode'])) {
        $mode = $_GET['mode'];

        if ($mode === 'batch' && isset($_FILES['excel_file'])) {
            // Procesamiento por lotes
            if ($_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['excel_file']['tmp_name'];
                $response = procesarLote($conn, $tmp_name);
            } else {
                $response = ["success" => false, "message" => "Error al subir el archivo: " . $_FILES['excel_file']['error']];
            }
        } elseif ($mode === 'single') {

            $json_data = file_get_contents('php://input');
            $data = json_decode($json_data, true);

            if ($data && isset($data['id'])) {
                $id = intval($data['id']);
                unset($data['id']);
                $response = procesarSolicitudIndividual($conn, $id, $data);
            } else {
                $response = ["success" => false, "message" => "Datos JSON inválidos o ID no proporcionado"];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}


$conn->close();
?>
