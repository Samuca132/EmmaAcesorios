<?php
// Configuración de encabezados HTTP
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Content-Type: application/json; charset=UTF-8");

// Conexión a la base de datos
$host = "localhost";
$user = "root";
$pass = "";
$database = "emmaaccesorios";
$conn = mysqli_connect($host, $user, $pass, $database);

if (!$conn) {
    die(json_encode(["status" => "error", "message" => "Error en la conexión con la base de datos"]));
}

/* =========================
   BLOQUE: VENTAS
   ========================= */
if (isset($_POST["IDVenta"])) {
    $IDCliente = $_POST["IDCliente"];
    $IDProducto = $_POST["IDProducto"];
    $CantidadProducto = $_POST["CantidadProducto"];
    $profit = $_POST["profit"];
    $fechaVenta = $_POST["fechaVenta"];
    $Total = $_POST["Total"];

    $sql = "INSERT INTO venta (IDCliente, IDProducto, CantidadProducto, profit, fechaVenta, Total) 
            VALUES ('$IDCliente', '$IDProducto', '$CantidadProducto', '$profit', '$fechaVenta', '$Total')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $sqlStock = "UPDATE producto SET stock = stock - $CantidadProducto WHERE IDProducto = '$IDProducto'";
        mysqli_query($conn, $sqlStock);
        echo json_encode(["status" => "success", "message" => "Venta registrada"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al registrar la venta"]);
    }
}

/* =========================
   BLOQUE: CANJES
   ========================= */
if (isset($_POST["IDCanje"])) {
    $IDCliente = $_POST["IDCliente"];
    $IDProducto = $_POST["IDProducto"];
    $IDInsumo = $_POST["IDInsumo"];
    $CantidadProducto = $_POST["CantidadProducto"];
    $CantidadInsumo = $_POST["CantidadInsumo"];
    $fechaCanje = $_POST["fechaCanje"];

    $sql = "INSERT INTO canje (IDCliente, IDProducto, IDInsumo, CantidadProducto, CantidadInsumo, fechaCanje) 
            VALUES ('$IDCliente', '$IDProducto', '$IDInsumo', '$CantidadProducto', '$CantidadInsumo', '$fechaCanje')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $sqlUpdateProducto = "UPDATE producto SET stock = stock - $CantidadProducto WHERE IDProducto = '$IDProducto'";
        $sqlUpdateInsumo = "UPDATE insumo SET stock = stock - $CantidadInsumo WHERE IDInsumo = '$IDInsumo'";
        mysqli_query($conn, $sqlUpdateProducto);
        mysqli_query($conn, $sqlUpdateInsumo);
        echo json_encode(["status" => "success", "message" => "Canje registrado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al registrar el canje"]);
    }
}

/* =========================
   BLOQUE: COMPRAS
   ========================= */
if (isset($_POST["IDCompra"])) {
    $IDProveedor = $_POST["IDProveedor"];
    $IDInsumo = $_POST["IDInsumo"];
    $Cantidad = $_POST["Cantidad"];
    $fechaCompra = $_POST["fechaCompra"];

    $sql = "INSERT INTO compra (IDProveedor, IDInsumo, Cantidad, fechaCompra) 
            VALUES ('$IDProveedor', '$IDInsumo', '$Cantidad', '$fechaCompra')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $sqlUpdateStock = "UPDATE insumo SET stock = stock + $Cantidad WHERE IDInsumo = '$IDInsumo'";
        mysqli_query($conn, $sqlUpdateStock);
        echo json_encode(["status" => "success", "message" => "Compra registrada"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al registrar la compra"]);
    }
}

/* =========================
   BLOQUE: PRODUCTOS
   ========================= */
if (isset($_POST["AgregarProducto"])) {
    $NombreProducto = $_POST["NombreProducto"];
    $ImagenProducto = $_POST["ImagenProducto"];
    $CosteProduccion = $_POST["CosteProduccion"];
    $Precio = $_POST["Precio"];
    $Stock = $_POST["Stock"];

    $sql = "INSERT INTO producto (NombreProducto, ImagenProducto, CosteProduccion, Precio, Stock, visibility) 
            VALUES ('$NombreProducto', '$ImagenProducto', '$CosteProduccion', '$Precio', '$Stock', 1)";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Producto agregado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al agregar el producto"]);
    }
}

/* =========================
   BLOQUE: CLIENTES
   ========================= */
if (isset($_POST["IDClienteNuevo"])) {
    $NombreCliente = $_POST["NombreCliente"];
    $Telefono = $_POST["Telefono"];
    $Direccion = $_POST["Direccion"];

    $sql = "INSERT INTO cliente (NombreCliente, Telefono, Direccion) 
            VALUES ('$NombreCliente', '$Telefono', '$Direccion')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Cliente agregado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al agregar el cliente"]);
    }
}

/* =========================
   BLOQUE: CIUDADES
   ========================= */
if (isset($_POST["AgregarCiudad"])) {
    $NombreCiudad = $_POST["NombreCiudad"];

    $sql = "INSERT INTO ciudad (NombreCiudad) VALUES ('$NombreCiudad')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Ciudad agregada"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al agregar la ciudad"]);
    }
}

/* =========================
   BLOQUE: PROVEEDORES
   ========================= */
if (isset($_POST["AgregarProveedor"])) {
    $NombreProveedor = $_POST["NombreProveedor"];
    $Contacto = $_POST["Contacto"];
    $Direccion = $_POST["Direccion"];

    $sql = "INSERT INTO proveedor (NombreProveedor, Contacto, Direccion) 
            VALUES ('$NombreProveedor', '$Contacto', '$Direccion')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Proveedor agregado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al agregar el proveedor"]);
    }
}

/* =========================
   BLOQUE: INSUMOS
   ========================= */
if (isset($_POST["AgregarInsumo"])) {
    $NombreInsumo = $_POST["NombreInsumo"];
    $Stock = $_POST["Stock"];

    $sql = "INSERT INTO insumo (NombreInsumo, Stock) VALUES ('$NombreInsumo', '$Stock')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Insumo agregado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al agregar el insumo"]);
    }
}

// Cierre de la conexión
mysqli_close($conn);



//Login.........................................................................................................


if(isset($_GET["login"])){

    $data = json_decode(file_get_contents("php://input"));
    $email = $data->email;
    $password = $data->password;

    $sql = "SELECT * FROM usuario WHERE UsuarioEmail='$email' AND PaswordUsuario='$password'";
    $result = mysqli_query($conexionBD, $sql);
    
    if(mysqli_num_rows($result) > 0){
        $key = bin2hex(random_bytes(32)); 
        $token = generateJWT($email, $key);
        echo json_encode(["success"=>1, "message"=>"Inicio de sesión exitoso", "token"=>$token]);
    } else {
        echo json_encode(["success"=>0, "message"=>"Usuario o contraseña incorrectos"]);
    }
    exit();
}


/*    
if(isset($_GET["listarEmpleados"])){
    $sqlEmpleaados = mysqli_query($conexionBD,"SELECT venta.IDVenta, cliente.nombreCliente AS nombreCliente, 
    producto.NombreProducto AS NombreProducto, venta.CantidadProducto, venta.profit, venta.fechaVenta 
    FROM venta LEFT JOIN cliente ON venta.IDCliente = cliente.IDCliente 
    LEFT JOIN producto ON venta.IDProducto = producto.IDProducto;");
    if(mysqli_num_rows($sqlEmpleaados) > 0){
    $empleaados = mysqli_fetch_all($sqlEmpleaados,MYSQLI_ASSOC);
    echo json_encode($empleaados);
    }
    else{ 
        cho json_encode([["success"=>0]]); 
    }}
    */
    function generateJWT($email, $key, $expirationHours = 1) {
        // Cabecera del token (alg: algoritmo y typ: tipo)
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $header = base64_encode($header);
    
        // Contenido del token (datos del usuario)
        $payload = json_encode(['email' => $email, 'exp' => time() + ($expirationHours * + 30)]);
        $payload = base64_encode($payload);
    
        // Firma del token
        $signature = hash_hmac('sha256', "$header.$payload", $key, true);
        $signature = base64_encode($signature);
    
        // Token final
        $token = "$header.$payload.$signature";
    
        return $token;
    }
?>
