<?php
session_start();
include "../funtions.php";

//CONEXION A DB
$mysqli = connect_mysqli();

//CONSULTA LOS DATOS DE LA ENTIDAD CORPORACION
$consulta = "SELECT *
	FROM categoria_producto
	WHERE nombre NOT IN ('Servicio')";
$result = $mysqli->query($consulta) or die($mysqli->error);

if($result->num_rows>0){
	while($consulta2 = $result->fetch_assoc()){
		echo '<option value="'.$consulta2['categoria_producto_id'].'">'.$consulta2['nombre'].'</option>';
	}
}else{
		echo '<option value="">Seleccione</option>';
}

$result->free();//LIMPIAR RESULTADO
$mysqli->close();//CERRAR CONEXIÓN
?>