<?php
session_start();
include '../funtions.php';

// CONEXIÓN A DB
$mysqli = connect_mysqli();

$colaborador_id = $_SESSION['colaborador_id'];
$type = $_SESSION['type'];
$fechai = $_POST['fechai'];
$fechaf = $_POST['fechaf'];
$pacientesIDGrupo = $_POST['pacientesIDGrupo'];
$estado = $_POST['estado'];
$usuario = $_SESSION['colaborador_id'];

if ($estado == 1) {
    $in = 'IN(2,4)';
} else if ($estado == 4) {
    $in = 'IN(4)';
} else {
    $in = 'IN(3)';
}

$busqueda_paciente = '';

if ($pacientesIDGrupo != '') {
    $busqueda_paciente = "AND f.pacientes_id = '$pacientesIDGrupo'";
}

$consulta = "SELECT f.facturas_id AS 'facturas_id', f.fecha AS 'fecha', p.identidad AS 'identidad', CONCAT(p.nombre,' ',p.apellido) AS 'paciente', sc.prefijo AS 'prefijo', f.number AS 'numero', s.nombre AS 'servicio', CONCAT(c.nombre,'',c.apellido) AS 'profesional', sc.relleno AS 'relleno', DATE_FORMAT(f.fecha, '%d/%m/%Y') AS 'fecha1', f.pacientes_id AS 'pacientes_id', f.cierre AS 'cierre', (CASE WHEN f.tipo_factura = 1 THEN 'Contado' ELSE 'Crédito' END) AS 'tipo_documento', f.tipo_factura, m.number AS 'muestra'
FROM facturas AS f
INNER JOIN pacientes AS p ON f.pacientes_id = p.pacientes_id
INNER JOIN secuencia_facturacion AS sc ON f.secuencia_facturacion_id = sc.secuencia_facturacion_id
INNER JOIN servicios AS s ON f.servicio_id = s.servicio_id
INNER JOIN colaboradores AS c ON f.colaborador_id = c.colaborador_id
INNER JOIN muestras AS m ON f.muestras_id = m.muestras_id
WHERE f.fecha BETWEEN '$fechai' AND '$fechaf' AND f.estado " . $in . "
$busqueda_paciente
GROUP BY f.number
ORDER BY f.number DESC";

$result = $mysqli->query($consulta) or die($mysqli->error);

$arreglo = array('data' => []);

while ($data = $result->fetch_assoc()) {
    $facturas_id = $data['facturas_id'];

    $numero = $data['numero'] == 0 ? 'Aún no se ha generado' : $data['prefijo'] . rellenarDigitos($data['numero'], $data['relleno']);
    $data['factura'] = $numero;

    // Consultar detalle de facturación
    $query_detalle = "SELECT cantidad, precio, descuento, isv_valor FROM facturas_detalle WHERE facturas_id = '$facturas_id'";
    $result_detalles = $mysqli->query($query_detalle) or die($mysqli->error);

    $cantidad = $descuento = $precio = $total_precio = $neto_antes_isv = $isv_neto = $total = 0;

    while ($registrodetalles = $result_detalles->fetch_assoc()) {
        $precio += $registrodetalles['precio'];
        $cantidad += $registrodetalles['cantidad'];
        $descuento += $registrodetalles['descuento'];
        $total_precio = $registrodetalles['precio'] * $registrodetalles['cantidad'];
        $neto_antes_isv += $total_precio;
        $isv_neto += $registrodetalles['isv_valor'];
    }

    $total = ($neto_antes_isv + $isv_neto) - $descuento;

    // CONSULTAMOS EL TIPO DE PAGO DE LA FACTURA
    $query_pago = "SELECT tp.nombre AS 'TipoPago'
        FROM pagos AS p
        INNER JOIN pagos_detalles AS pd ON pd.pagos_id = p.pagos_id
        INNER JOIN tipo_pago AS tp ON pd.tipo_pago_id = tp.tipo_pago_id
        WHERE p.facturas_id = '$facturas_id'";

    $result_pago = $mysqli->query($query_pago) or die($mysqli->error);

    $tipoPago = '';  // Lowercase variable

    if ($result_pago->num_rows > 0) {
        $consulta_pago = $result_pago->fetch_assoc();  // Corrected variable
        $tipoPago = $consulta_pago['TipoPago'];  // Using lowercase $tipoPago
    }

    $data['TipoPago'] = $tipoPago;

    $data['precio'] = $precio;
    $data['cantidad'] = $cantidad;
    $data['descuento'] = $descuento;
    $data['total_precio'] = $total_precio;
    $data['neto_antes_isv'] = $neto_antes_isv;
    $data['isv_neto'] = $isv_neto;
    $data['total'] = $total;

    $estado_ = match ($estado) {
        1 => 'Borrador',
        2 => 'Pagada',
        3 => 'Cancelada',
        4 => 'Crédito',
        default => ''
    };

    $data['estado'] = $estado_;

    $arreglo['data'][] = $data;
}

// Consulta para obtener el total por tipo de pago
$consulta_pagos = "
SELECT 
    tp.nombre AS tipo_pago,
    SUM(p.importe) AS total_pago
FROM pagos AS p
INNER JOIN pagos_detalles AS pd ON p.pagos_id = pd.pagos_id
INNER JOIN tipo_pago AS tp ON pd.tipo_pago_id = tp.tipo_pago_id
WHERE p.facturas_id IN (SELECT facturas_id FROM facturas WHERE fecha BETWEEN '2024-12-01' AND '2024-12-31' AND estado = 2)
GROUP BY tp.nombre
UNION
SELECT 
    'Total',
    SUM(p.importe) AS total_pago
FROM pagos AS p
INNER JOIN pagos_detalles AS pd ON p.pagos_id = pd.pagos_id
INNER JOIN tipo_pago AS tp ON pd.tipo_pago_id = tp.tipo_pago_id
WHERE p.facturas_id IN (SELECT facturas_id FROM facturas WHERE fecha BETWEEN '2024-12-01' AND '2024-12-31' AND estado = 2)";

$resultados_pagos = $mysqli->query($consulta_pagos);
$tipos_de_pago = [];
while ($row = $resultados_pagos->fetch_assoc()) {
    $tipos_de_pago[$row['tipo_pago']] = $row['total_pago'];
}

// Devolver tanto las facturas como los totales por tipo de pago
echo json_encode([
    'data' => $arreglo['data'],
    'tipos_de_pago' => $tipos_de_pago  // Aquí estamos enviando el total por tipo de pago
]);

$result->free();
$resultados_pagos->free();
$mysqli->close();
