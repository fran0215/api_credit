<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Codeigniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RequestInterface;
use App\Config\Database;

class AppTracker extends Auth

{
    protected $db;

    public function getAllDevices()
    {
        $token = $this->request->getPost('token');

        if ($this->validateToken($token)) {
            $this->db = \Config\Database::connect();
            $sql = 'SELECT d_id, d_ident, d_imei, d_nombre, d_marca, d_modelo, d_placa, timestamp FROM device';
            $results = $this->db->query($sql)->getResult();
            return $this->respond(['error' => 0, 'mesage' => 'Ok', 'data' => $results], 200);            
        }else{
            return $this->respond(['error' => 1, 'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            

        }

    }

    public function hola()
    {
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $name = $id_user = $this->request->getPost('hola');

            return $this->respond(['error' => 0,'mesage' => 'Ok', 'data' => "hola $name"], 200);            
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 401);            

        }

    }

    public function getDevicesByUser()
	{ 
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $id_user = $this->request->getPost('id');
            $this->db = \Config\Database::connect();
            $sql = 'SELECT d_ident, d_imei, d_nombre, d_marca, d_modelo, d_placa FROM device d
                    INNER Join devices_by_user du ON d.d_id = du.device_id
                    INNER Join usuario u ON du.user_id = u.u_id
                    WHERE u.u_id = ?';
            $results = $this->db->query($sql, [$id_user])->getResult();

            $devices ="";

            for ($i=0; $i < count($results) ; $i++) {
                if ($devices == "") {
                    $devices .= $results[$i]->d_ident;
                }else{
                    $devices .= "%2C".$results[$i]->d_ident;
                }
            }

            // flespi api
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://flespi.io/gw/devices/$devices?fields=id%2Cname%2Cconnected%2Clast_active%2Ctelemetry");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $headers = array();
            $headers[] = 'Authorization: FlespiToken pTnx5RjERUxV47PmzHw6651qwUumAoxO5Jb0ois32PMUNp5aWr4TkT9GDrQ2FcEB';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
            }
            curl_close($ch);
            return $this->respond(['error' => 0,'mesage' => 'Ok', 'data' => ["api" => json_decode($result)->result, "devices" => $results]], 200);            
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            

        }
    }

    public function getTypeDevice()
    {
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $this->db = \Config\Database::connect();
            $sql = 'SELECT * FROM tipo_gps limit 25';
            $results = $this->db->query($sql)->getResult();
            return $this->respond(['error' => 0,'mesage' => 'Ok', 'data' => $results], 200);            
 
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }
    }

    public function createDevice()
    {
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $nombre = $this->request->getPost('nombre');
            $imei = $this->request->getPost('imei');
            $tipo_gps = $this->request->getPost('tipo_gps');
            $marca = $this->request->getPost('marca');
            $cel = $this->request->getPost('numero_celular');
            $modelo = $this->request->getPost('modelo');
            $anio = $this->request->getPost('año');
            $placa = $this->request->getPost('placa');
            error_log("nombre: $nombre, tipo: $tipo_gps, imei: $imei");

            // creamos dispositivo desde la api de flespi y en la bd
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://flespi.io/gw/devices?fields=id%2Cname');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "[{\"configuration\":{\"ident\":\"$imei\"},\"device_type_id\":$tipo_gps,\"name\":\"$nombre\",\"messages_ttl\":2419200}]");
            
            $headers = array();
            $headers[] = 'Authorization: FlespiToken pTnx5RjERUxV47PmzHw6651qwUumAoxO5Jb0ois32PMUNp5aWr4TkT9GDrQ2FcEB';
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);


            // $r =json_decode($resp);
            if (curl_errno($ch)) {
                return $this->respond(['error'=> 1, "mensaje" => curl_error($ch)]);
            }else{
                if (isset(json_decode($result)->errors)) {
                    $data = [
                        'd_nombre' => $nombre,
                        'd_imei' => $imei,
                        'd_modelo' => $modelo,                    
                    ];
                    $resp =  json_decode($result)->errors;
                }else{
                    $resp = json_decode($result)->result;
                    $id = $resp[0]->id;
                    // $id = 1;
                    $data = [
                        'd_nombre' => $nombre,
                        'd_imei' => $imei,
                        'd_modelo' => $modelo,
                        'd_anio' => $anio,
                        'd_placa' => $placa,
                        'd_marca' => $marca,
                        'd_cel' => $cel,
                        'd_tipo' => 1,
                        'd_ident' => $id,
                        
                    ];
                    $idcreado = $this->model->insert($data);
                }

                return $this->respond(['error' => 0,'mesage' => 'Dispositivo registrado', 'data' => $idcreado], 200);            

            }
            curl_close($ch);
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }
    }

    protected function codeGenerator()
    {

        $this->db = \Config\Database::connect();
        $sql = 'SELECT codigo FROM producto  ORDER BY id DESC limit 1';
        $results = $this->db->query($sql)->getResult();
        $ultimoCodigo = $results[0]->codigo;

        // Extrae el número de la parte final del código
        $numero = intval(substr($ultimoCodigo, 3)) + 1;

        // ea el nuevo número con ceros a la izquierda si es necesario
        $nuevoNumero = sprintf('%03d', $numero);

        // Combina el texto estático "CG-" con el nuevo número para obtener el nuevo código
        $nuevoCodigo = 'CG-' . $nuevoNumero;

        return $nuevoCodigo;
    }

     // power cut gps
     public function powerCut()
     {
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $ident = $this->request->getPost('ident');
            $option = $this->request->getPost('op');
            $op = "";
            $mensaje = "";
            if ($option == 1) {
                $op = "true";
                $mensaje = "Se ha enviado en comando para encender el motor.";
            }else{
                $op = "false";
                $mensaje = "Se ha enviado en comando para apagar el motor.";
            }
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://flespi.io/gw/devices/$ident/settings/block_engine");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

            curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"properties\":{\"cut_off\":$op},\"address\":\"connection\"}");

            $headers = array();
            $headers[] = 'Accept: application/json';
            $headers[] = 'Authorization: FlespiToken pTnx5RjERUxV47PmzHw6651qwUumAoxO5Jb0ois32PMUNp5aWr4TkT9GDrQ2FcEB';
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            
            error_log(json_encode($result));
            if (curl_errno($ch)) {
                curl_close($ch);
                return $this->respond(['error' => 1,'mesage' => 'Operación fallida.', 'data' => curl_error($ch)], 200);            

            }else{
                curl_close($ch);
                return $this->respond(['error' => 0,'mesage' => $mensaje, 'data' => json_encode($result)], 200);            
            }
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }    
    }

    // device arm - disarm
    public function armDisarm()
    {
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $ident = $this->request->getPost('ident');
            $option = $this->request->getPost('op');
            $op = "";
            $mensaje = "";
            if ($option == 1) {
                $op = "true";
                $mensaje = "Se ha enviado en comando para encender la alarma.";
            }else{
                $op = "false";
                $mensaje = "Se ha enviado en comando para apagar la alarma.";
            }
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://flespi.io/gw/devices/$ident/settings/arm");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

            curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"properties\":{\"arm\":$op},\"address\":\"connection\"}");;

            $headers = array();
            $headers[] = 'Accept: application/json';
            $headers[] = 'Authorization: FlespiToken pTnx5RjERUxV47PmzHw6651qwUumAoxO5Jb0ois32PMUNp5aWr4TkT9GDrQ2FcEB';
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                curl_close($ch);
                return $this->respond(['error' => 1,'mesage' => 'Operación fallida.', 'data' => curl_error($ch)], 200);            
            }else{
                curl_close($ch);
               return $this->respond(['error' => 0,'mesage' => $mensaje, 'data' => json_encode($result)], 200);            
            }
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }
    }

    public function getCurrentPosition()
	{  
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $data = array();
            $counter = 0;
            $ch = curl_init();
            $device = $this->request->getGet('device');

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://flespi.io/gw/devices/$device/telemetry/position.latitude%2Cposition.longitude");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: FlespiToken JeC7U3aguplarddgSAEseHG1fi2YV8ut2MfeERiaDZnlUDa2u9qEUBqAi7PiAd6h',
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                return $this->respond(['error' => 1,'mesage' => 'Operación fallida.', 'data' => json_encode($ch)], 200);            

            }else{
                curl_close($ch);
                // Decodificar la cadena JSON en un array asociativo
                $data = json_decode($response, true);
                return $this->respond(['error' => 0,'mesage' => 'Ok', 'data' => $data], 200);            

            }
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }
    }

    public function getHistory(){
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            date_default_timezone_set('America/Bogota');
            $fecha_actual = date('Y-m-d');

            $fecha_fin = date('H:i:s');
            $fecha_inicio = date('H:i:s');
            $imei = $this->request->getPost('imei');
            $hi = $this->request->getPost('hi') != null ? $this->request->getPost('hi') : $fecha_inicio;
            $hf = $this->request->getPost('hf') != null ? $this->request->getPost('hf') : $fecha_fin;
            $fecha = $this->request->getPost('fecha') != null ? $this->request->getPost('fecha') : $fecha_actual ;
            $fecha_iniciof = "$fecha $hi";
            $fecha_finf = "$fecha $hf";

            // error_log($fecha_finf);
            if ($this->request->getPost('hi') == null) {
                $f_inicio = date("Y-m-d H:i:s", strtotime('-10 hour', strtotime($fecha_iniciof)));
            }else{
                $f_inicio = $fecha_iniciof;
            }

            $db = \Config\Database::connect();
            $sql = "SELECT position_latitude as lat, position_longitude as lng 
            FROM coban_logs
            where position_latitude != '' AND position_longitude != ''
            AND fecha_reg >= ? AND fecha_reg <= ? 
            AND ident = ? 
            order by cl_id";
            $results = $db->query($sql, [$f_inicio, $fecha_finf, $imei])->getResult();

            if (!empty($results)) {
                return $this->respond(['error' => 0,'mesage' => 'Operación exitosa.', 'data' => $results], 200);            

            } else {
                return $this->respond(['error' => 1,'mesage' => 'No se encontraron resultados.', 'data' => null], 200);            

            }
        }else{
            return $this->respond(['error' => 1,'mesage' => 'Token invalido', 'data' => 'token invalido'], 200);            
        }
    }
}