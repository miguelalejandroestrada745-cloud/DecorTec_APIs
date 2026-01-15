<?php
class newCola {
    private $cotizaciones = [];

    // Método para encolar
    public function enqueue($cotizacion){
        array_push($this->cotizaciones, $cotizacion);
    }

    // Método para desencolar
    public function dequeue(){
        if($this->isEmpty()){
            return null;
        }
        return array_shift($this->cotizaciones);
    }

    // Método para mostrar la cotización inicial de la cola
    public function front(){
        return $this->isEmpty() ? null : $this->cotizaciones[0]; 
    }

    // Método para determinar si la cola está vacía
    public function isEmpty(){
        return count($this->cotizaciones) === 0;
    }

    // Método para contar las cotizaciones dentro de la cola
    public function size(){
        return count($this->cotizaciones);
    }

    // Método para obtener todos los datos de la cola
    public function getCola() {
        return $this->cotizaciones;
    }

    // Método para obtener las cotizaciones de la base de datos
    public function cargarCotizacionesBD($database){
        try{
            $conn = $database->getConnection();

            $sql = "SELECT 
                    c.ID_cotizacion,
                    c.ID_cliente,
                    CONCAT(cli.Nombre, ' ', cli.ApellidoP, ' ', cli.ApellidoM) AS Cliente,
                    
                    -- Información del producto
                    CASE 
                        WHEN c.ID_producto IS NOT NULL THEN p.Descripcion
                        WHEN c.ID_carrito IS NOT NULL THEN 'Varios productos del carrito'
                        ELSE 'Sin producto específico'
                    END AS Producto,
                    
                    -- Dimensiones y color
                    c.Ancho,
                    c.Largo,
                    c.Color,
                    
                    -- Cantidad y precio
                    c.Cantidad,
                    c.PrecioCotizacion AS Precio,
                    
                    -- Estado y fecha (los datos que solicitaste)
                    c.Estado,
                    c.Cantidad AS 'Cantidad de productos',
                    DATE_FORMAT(c.FechaCotizacion, '%Y-%m-%d %H:%i:%s') AS 'Fecha en que se realizó',
                    c.FechaCotizacion AS FechaCotizacion,
                    
                    -- Información adicional
                    CASE 
                        WHEN c.ID_carrito IS NOT NULL THEN 'Desde carrito'
                        ELSE 'Directa desde producto'
                    END AS TipoCotizacion
                    
                FROM Cotizacion c
                INNER JOIN Cliente cli ON c.ID_cliente = cli.ID_cliente
                LEFT JOIN Producto p ON c.ID_producto = p.ID_producto
                WHERE c.Estado IN ('Pendiente', 'Aprobada', 'Modificada')
                ORDER BY c.FechaCotizacion ASC; -- Las cotizaciones más antiguas primero";

            $stmt = $conn->prepare($sql);
            
            // Ejecutar consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la consulta: " . $stmt->error);
            }

            // Obtener resultados de la consulta
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                // Agregar posición en la cola
                $row['posicion_cola'] = count($this->cotizaciones) + 1;
                $this->enqueue($row);
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            throw new Exception("Error al cargar cotizaciones: " . $e->getMessage());
        }
    }
}
?>