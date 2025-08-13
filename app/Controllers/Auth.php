<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Codeigniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth extends ResourceController

{
    protected $format = 'json';

    // Token JWT functions
    public function createToken()
	{
        // Lógica para verificar las credenciales del usuario y establecer la sesión como iniciada
        $username = $this->request->getPost('username');
        $clave = $this->request->getPost('clave');
        error_log("usernbame : ".$username);

        $this->db = \Config\Database::connect();
        $sql = 'SELECT * FROM usuario 
                WHERE username = ? AND estado = ? ';
        $results = $this->db->query($sql, [$username, 1])->getResult();

        if (!empty($results)) {
            // datos usuario
            $id = $results[0]->id;
            $nombre = $results[0]->nombre;
            $correo = $results[0]->correo;
            $hash = $results[0]->clave;

            // Verificar si la contraseña coincide con el hash
            if (password_verify($clave, $hash)) {
                $userData = [
                    'id' => $id,
                    'nombre' => $nombre,
                    'correo' => $correo,   
                ];
                $time = time();
                $key = Services::getSecretKey();

                $payload = [
                    // 'aud' => 'http://example.com',
                    'iat' => $time,
                    'exp' => $time + 260000, // sumar segundos
                    'data' => [
                        'id' => $id,
                        'nombre' => $nombre,
                        'correo' => $correo,   
                    ] 
                ];

                $jwt = JWT::encode($payload, $key, 'HS256');

			    return $this->respond(['error' => 0, 'token' => $jwt, 'message' => 'Token creado', 'data' => $userData], 200);

            } else {

                return $this->respond(['error' => 1, 'token' => null, 'message' => 'Clave invalida', 'data' => null], 200);
            
            }
            

        } else {
            // No se encontraron resultados para el nombre de usuario dado
            return $this->respond(['error' => 1, 'token' => null, 'message' => 'No se encontró el usuario '. $username], 200);
        }        

	}

    protected function validateToken($token)
    {
        try {
            $key = Services::getSecretKey();
            return JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function verifyToken()
    {
        error_log("validaciojn de tokeeeennn");
        $key = Services::getSecretKey();
        $token = $this->request->getPost('token');
        error_log($token);

        if ($this->validateToken($token) == false) {
            return $this->respond(['error' => 1, 'message' => 'Invalid tokeneeee'], 200);
        }else{
            $data = JWT::decode($token, new Key($key, 'HS256'));
            return $this->respond([
                'error' => 0,
                'message' => 'Token is validooo',
                'data' => $data
            ], 200);

        }
    }

    /*
    Login 
    */
    public function login()
    {
        if (!session()->get('isLoggedIn')) {
            // La sesión no está iniciada, redirigir al usuario al login
            return view('auth/login_frm');
        }
        return view('main/home');
    }

    public function login_submit()
    {
        return view('auth/login_frm');
    }

    public function iniciarSesion()
    {
        // Lógica para verificar las credenciales del usuario y establecer la sesión como iniciada
        $username = $this->request->getPost('username');
        $clave = $this->request->getPost('clave');

        $this->db = \Config\Database::connect();
        $sql = 'SELECT u_id, CONCAT(u_nombre, " ", u_apellido) AS u_nombre, u_correo, u_pass, u_idrol, r.nombre AS rol_nombre FROM usuario u 
                INNER JOIN rol r ON u_idrol = r.id
                WHERE u_correo = ? AND u_estado = ? ';
        $results = $this->db->query($sql, [$username, 1])->getResult();

        if (!empty($results)) {
            // datos usuario
            $id = $results[0]->u_id;
            $nombre = $results[0]->u_nombre;
            $correo = $results[0]->u_correo;
            $hash = $results[0]->u_pass;
            $rol = $results[0]->u_idrol;
            $rol_nombre = $results[0]->rol_nombre;

            // Verificar si la contraseña coincide con el hash
            if (password_verify($clave, $hash)) {
                session()->set('isLoggedIn', true);
                session()->set('id', $id);
                session()->set('rol', $rol);
                session()->set('rol_nombre', $rol_nombre);
                session()->set('username', $nombre);
                session()->set('usermail', $correo);
                return redirect()->to(base_url('/main'));
            } else {
                return view('auth/login_frm',["error" => "Contraseña Invalida."]);
            }
            

        } else {
            // No se encontraron resultados para el nombre de usuario dado
            return view('auth/login_frm',["error" => "No se encontró el usuario."]);
        }        
    }

    public function cerrarSesion()
    {
        // Cerramos la sesion
        session()->set('isLoggedIn', false);

        // Redirigir al usuario a la página principal
        return view('auth/login_frm');
    }

    
    /*
    Create new account 
    */
    public function newAccount()
    {
        return view('auth/new_account_frm');
    }

    public function newAccount_submit()
    {
        // get new user data


        // insert new user data

        // return 
        echo('newAccount_submit');
    }

    
    /*
    Forgot passpwrd 
    */
    public function forgotPassword()
    {
        return view('auth/forgot_password');
    }

    public function forgotPassword_submit()
    {
        echo('auth/forgotPassword_submit');
    }
    
    /*
    Current Position 
    */
        public function getCurrentPosition()
	{  
        $token = $this->request->getPost('token');
        if ($this->validateToken($token)) {
            $data = array();
            $counter = 0;
            $device = $this->request->getPost('ident');

            // flespi api
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://flespi.io/gw/devices/$device/telemetry/position.latitude%2Cposition.longitude");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $headers = array();
            $headers[] = 'Authorization: FlespiToken pTnx5RjERUxV47PmzHw6651qwUumAoxO5Jb0ois32PMUNp5aWr4TkT9GDrQ2FcEB';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                return $this->respond([
                    'error' => 1,
                    'message' => 'Operación fallida.',
                    'data' => json_encode($ch)
                ], 200);        

            }else{
                curl_close($ch);
                // Decodificar la cadena JSON en un array asociativo
                $data = json_decode($response, true);

                $latitude = $data['result'][0]['telemetry']['position.latitude']['value'];
                $longitude = $data['result'][0]['telemetry']['position.longitude']['value'];

                return $this->respond([
                    'error' => 0,
                    'message' => 'Ok.',
                    'data' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ]
                ], 200);         

            }
        }else{  
            return $this->respond([
                'error' => 1,
                'message' => 'Token invalido',
                'data' => 'token invalido'
            ], 200);       
        }
    }
}
