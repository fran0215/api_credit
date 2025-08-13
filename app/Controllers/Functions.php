<?php

namespace App\Controllers;
use CodeIgniter\RESTful\ResourceController;

class Functions extends Auth
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function index(): string
    {
        return view('welcome_message');
    }

    public function getClientes()
    {
        $sql = 'SELECT * FROM cliente WHERE estado = 1';
        $results = $this->db->query($sql)->getResult();  

        if ($results) {
            return $this->respond(['error' => 0, 'data' => $results, 'message' => 'Listado clientes'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'Ha ocurrido un error.'], 200);
        }
    }

    public function guardarCliente()
    {
        $clienteJson = $this->request->getPost('cliente'); // Obtener el JSON del cliente desde FormData
        $token = $this->request->getPost('token'); // Obtener el token

        // Verificar si los datos fueron enviados
        if (!$clienteJson || !$token) {
            return $this->respond(['error' => 1, 'message' => 'Datos incompletos'], 200);
        }

        // Decodificar el JSON a un array asociativo
        $clienteData = json_decode($clienteJson, true);

        if (!$clienteData) {
            return $this->respond(['error' => 1, 'message' => 'Error al decodificar JSON'], 200);
        }

        // Extraer los valores del array
        $nombre = $clienteData['nombre'] ?? null;
        $celular = $clienteData['celular'] ?? null;
        $correo = $clienteData['correo'] ?? null;

        // Validar que los datos no sean nulos
        if (!$nombre || !$celular || !$correo) {
            return $this->respond(['error' => 1, 'message' => 'Faltan datos obligatorios'], 200);
        }

        // Conectar a la base de datos
        $db = \Config\Database::connect();
        $builder = $db->table('cliente');

        // Insertar en la base de datos
        $insertData = [
            'nombre' => $nombre,
            'celular' => $celular,
            'correo' => $correo,
        ];

        $builder->insert($insertData);

        // Verificar si la inserción fue exitosa
        if ($db->affectedRows() > 0) {
            return $this->respond(['error' => 0, 'message' => 'Cliente agregado correctamente'], 200);
        } else {
            return $this->respond(['error' => 1, 'message' => 'Error al guardar cliente'], 200);
        }
    }

    public function getCreditos()
    {
        $this->db = \Config\Database::connect();
        $sql = 'SELECT c.*, cl.nombre as cliente_nombre
                FROM creditos c
                Inner Join cliente cl On c.cliente_id = cl.id
                WHERE c.estado = "activo"';
        $results = $this->db->query($sql)->getResult();

        if ($results) {
            return $this->respond(['error' => 0, 'data' => $results, 'message' => 'Créditos activos'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No hay créditos'], 200);
        }
    }
    public function getCreditosXid()
    {
        $credito_id = $this->request->getPost('id_credito'); 
        $this->db = \Config\Database::connect();
        $sql = 'SELECT c.*, cl.nombre as cliente_nombre
                FROM creditos c
                Inner Join cliente cl On c.cliente_id = cl.id
                WHERE c.estado = "activo" AND c.id = '. $credito_id;
        $results = $this->db->query($sql)->getResult()[0];

        $sqlPagos = 'SELECT *
        FROM pagos p
        WHERE p.credito_id = '. $credito_id;
        $planPagos = $this->db->query($sqlPagos)->getResult();

        $data = [
            'credito' => $results,
            'plan_pagos' => $planPagos
        ];

        if ($results) {
            return $this->respond(['error' => 0, 'data' => $data, 'message' => 'Crédito listado'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se encontro el credito'], 200);
        }
    }

    public function crearCredito()
    {
        $data = $this->request->getPost();
        
        if (!isset($data['cliente_id']) || !isset($data['monto']) || !isset($data['plazo'])) {
            return $this->respond(['error' => 1, 'message' => 'Datos incompletos'], 400);
        }

        $cliente_id = $data['cliente_id'];
        $monto = (float) $data['monto'];
        $plazo = (int) $data['plazo'];

        // Determinar interés
        $interes = ($plazo > 45) ? 40 : 20;
        $monto_total = $monto + ($monto * ($interes / 100));

        // Fecha de vencimiento
        $fecha_inicio = date('Y-m-d');
        $fecha_vencimiento = date('Y-m-d', strtotime("+$plazo days"));

        // Insertar crédito
        $this->model->insert([
            'cliente_id' => $cliente_id,
            'monto' => $monto,
            'interes' => $interes,
            'plazo' => $plazo,
            'fecha_inicio' => $fecha_inicio,
            'fecha_vencimiento' => $fecha_vencimiento,
            'monto_restante' => $monto_total
        ]);

        return $this->respond(['error' => 0, 'message' => 'Crédito registrado con éxito']);
    }

    public function planPagos(){
        $credito_id = $this->request->getPost('id_credito'); 
        $this->db = \Config\Database::connect();
        $sqlPagos = 'SELECT *
        FROM pagos p
        WHERE p.credito_id = '. $credito_id;
        $results = $this->db->query($sqlPagos)->getResult();

        if ($results) {
            return $this->respond(['error' => 0, 'data' => $results, 'message' => 'Crédito listado'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se encontro plan de pagos'], 200);
        }
    }

}