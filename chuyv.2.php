<?php


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "safeouts_ABB_Demo";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

function actualizarInventario($conn)
{

    $sql = "SELECT id_code_xls, id_inventario_equipos, field_change, dest_value 
            FROM cambios_inventario_batch 
            WHERE process_status = 'Procesar'";

    $result = mysqli_query($conn, $sql);

    // Verificar si hay registros a procesar
    if (mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_assoc($result)) {
            $id_reg = $row['id_code_xls'];
            $id_inventario_equipos_qry = $row['id_inventario_equipos'];
            $field_change_qry = $row['field_change'];
            $valor_destino = $row['dest_value'];

            $update_sql = "UPDATE B_InventarioEquipos 
                           SET $field_change_qry = '$valor_destino', verify_changes = 'sensible' 
                           WHERE ID = $id_inventario_equipos_qry";

            if (mysqli_query($conn, $update_sql)) {

                $update_status_sql = "UPDATE cambios_inventario_batch 
                                      SET process_status = 'Procesado' 
                                      WHERE id_code_xls = $id_reg";


                if (!mysqli_query($conn, $update_status_sql)) {
                    echo "Error al actualizar el estado del registro: " . mysqli_error($conn);
                }
            } else {
                echo "Error al actualizar el inventario: " . mysqli_error($conn);
            }
        }
    } else {
        echo "No hay registros para procesar.";
    }
}


actualizarInventario($conn);


mysqli_close($conn);
?>

