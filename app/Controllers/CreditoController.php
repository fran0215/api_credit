<?php

namespace App\Controllers;

use App\Models\CreditoModel;
use App\Models\PagoModel;
use App\Models\ClienteModel;
use App\Models\TransaccionesModel;
use CodeIgniter\RESTful\ResourceController;

class CreditoController extends ResourceController
{
    protected $db;

    protected $modelName = 'App\Models\CreditoModel';
    protected $format    = 'json';

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function creditosCliente(){
        $cliente_id = $this->request->getPost('cliente');
        log_message('error', $cliente_id);
        log_message('error', "clienteeee");
        $this->db = \Config\Database::connect();
        $sql = "SELECT * FROM creditos 
                WHERE cliente_id = $cliente_id AND estado != 'eliminado'";
        $results = $this->db->query($sql)->getResult();
        
        log_message('error', $sql);
        if ($results) {
            return $this->respond(['error' => 0, 'data' => $results, 'message' => 'listado de creditos exitoso'], 200);
        }else{
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se haan encontrado creditos.'], 200);
        }
    }

    public function cobranzasCredito(){
        $credito = $this->request->getPost('credito');
        
        $this->db = \Config\Database::connect();
        $sql = "SELECT * FROM transacciones 
                WHERE id_credito = $credito";
        $results = $this->db->query($sql)->getResult();
        
        log_message('error', $sql);
        if ($results) {
            return $this->respond(['error' => 0, 'data' => $results, 'message' => 'listado de creditos exitoso'], 200);
        }else{
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se haan encontrado creditos.'], 200);
        }
    }

    public function crearCredito()
    {
        $creditoJson = $this->request->getPost('credito'); 
        // Decodificar el JSON a un array asociativo
        $credito = json_decode($creditoJson, true);
        error_log(json_encode($credito));
        // Validar datos
        if (!isset($credito['cliente_id']) || !isset($credito['monto']) || !isset($credito['plazo']) || !isset($credito['frecuencia'])) {
            return $this->fail('Datos incompletos');
        }


        // Guardar crédito
        $creditoModel = new CreditoModel();
        $creditoId = $creditoModel->insert([
            'cliente_id' => $credito['cliente_id'],
            'monto' => $credito['monto'],
            'interes' => $credito['totalPagar'] - $credito['monto'],
            'tasa_interes' => $credito['interes'],
            'plazo' => $credito['plazo'],
            'fecha_inicio' => $credito['fechaInicio'],
            'fecha_vencimiento' => date('Y-m-d', strtotime($credito['fechaInicio'] . " +{$credito['plazo']} days")),
            'total_pagar' => $credito['totalPagar'],
            'monto_restante' => $credito['totalPagar'],
            'frecuencia' => $credito['frecuencia'],
            'estado' => 'activo'
        ]);


        // Crear plan de pagos
        $planPagos = $credito['planPagos'];
        $pagoModel = new PagoModel();

        log_message("error", "Cantidad de pagos: " . count($planPagos));

        foreach ($planPagos as $pago) {
            log_message("error", "Fecha Pago: " . $pago['fecha']);
            log_message("error", "Monto Pago antes de conversión: " . $pago['monto']);

            // Aseguramos que el monto sea un número decimal válido
            $monto = floatval($pago['monto']);

            log_message("error", "Monto Pago después de conversión: " . $monto);

            $pagoModel->insert([
                'credito_id' => $creditoId,
                'fecha_pago' => $pago['fecha'],
                'monto_pago' => $monto, // Insertamos el monto convertido a número
                'monto_pendiente' => $monto, // Insertamos el monto convertido a número
                'estado' => 'pendiente'
            ]);
        }

        return $this->respondCreated(['mensaje' => 'Crédito creado correctamente']);
    }

    public function guardarPago()
    {
        $creditoId = $this->request->getPost('id_credito');
        $fecha = $this->request->getPost('fecha'); // <- corregido
        $monto = $this->request->getPost('monto');

        $transaccion = new TransaccionesModel();
    
        $data = [
            'id_credito' => $creditoId,
            'monto' => $monto,
            'fecha' => $fecha
        ];
    
        if ($transaccion->insert($data)) {
            // Obtener el crédito asociado
            $creditoModel = new CreditoModel();
            $credito = $creditoModel->find($creditoId); // <- corregido
    
            if (!$credito) {
                return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se encontró el crédito'], 200);
            }
    
            // Calcular nuevo valor restante
            $nuevoValorRestante = $credito['monto_restante'] - $monto;
            if ($nuevoValorRestante < 0) {
                $nuevoValorRestante = 0; // Evitar valores negativos
            }
    
            // Actualizar el crédito con el nuevo valor restante
            $creditoModel->update($creditoId, ['monto_restante' => $nuevoValorRestante]);
    
            return $this->respond([
                'error' => 0,
                'mensaje' => 'Pago realizado correctamente',
                'nuevo_monto_restante' => $nuevoValorRestante
            ]);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se ha realizado el pago'], 200);
        }
    }
    


    public function obtenerResumenNegocio()
    {
        $creditoModel = new CreditoModel();
        $clienteModel = new ClienteModel();
        $pagoModel = new TransaccionesModel();
    
        // Total créditos activos
        $totalCreditos = $creditoModel
            ->join('cliente', 'creditos.cliente_id = cliente.id')
            ->where('cliente.estado', '1')
            ->where('creditos.estado !=', 'eliminado')
            ->countAllResults();
    
        // Total clientes activos
        $totalClientes = $clienteModel
            ->where('estado', '1')
            ->countAllResults();
    
        // Monto total prestado
        $montoPrestadoRow = $creditoModel
            ->join('cliente', 'creditos.cliente_id = cliente.id')
            ->where('cliente.estado', '1')
            ->where('creditos.estado !=', 'eliminado')
            ->selectSum('creditos.monto')
            ->first();
        $montoPrestado = round(floatval($montoPrestadoRow['monto'] ?? 0), 2);
    
        // Monto restante por cobrar
        $montoRestanteRow = $creditoModel
            ->join('cliente', 'creditos.cliente_id = cliente.id')
            ->where('creditos.estado !=', 'eliminado')
            ->where('cliente.estado', '1')
            ->selectSum('creditos.monto_restante')
            ->first();
        $montoRestante = round(floatval($montoRestanteRow['monto_restante'] ?? 0), 2);
    
        // Total intereses ganados
        $totalInteresesRow = $creditoModel
            ->join('cliente', 'creditos.cliente_id = cliente.id')
            ->where('cliente.estado', '1')
            ->where('creditos.estado !=', 'eliminado')
            ->selectSum('creditos.interes')
            ->first();
        $totalIntereses = round(floatval($totalInteresesRow['interes'] ?? 0), 2);
    
        // Monto total cobrado (pagos)
        $montoCobradoRow = $pagoModel
            ->join('creditos', 'creditos.id = transacciones.id_credito')
            ->join('cliente', 'creditos.cliente_id = cliente.id')
            ->where('creditos.estado !=', 'eliminado')
            ->where('cliente.estado', '1')
            ->selectSum('transacciones.monto')
            ->first();
        $montoCobrado = round(floatval($montoCobradoRow['monto'] ?? 0), 2);
    
        // Porcentaje de ganancia
        $porcentajeGanancia = $montoPrestado > 0
            ? round(($totalIntereses / $montoPrestado) * 100, 2)
            : 0;
    
        return $this->respond([
            'total_creditos'      => $totalCreditos,
            'total_clientes'      => $totalClientes,
            'monto_prestado'      => $montoPrestado,
            'monto_cobrado'       => $montoCobrado,
            'monto_restante'      => $montoRestante,
            'intereses'      => $totalIntereses,
            'porcentaje_ganancia' => $porcentajeGanancia
        ]);
    }
    
    
    

    public function eliminarCredito()
    {
        $id_credito = $this->request->getPost('id_credito');
    
        if (!$id_credito) {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'Ha ocurrido un error'], 200);
        }
    
        $creditoModel = new creditoModel(); // Asegúrate de tener este modelo
    
        // Buscar el pago por ID
        $credito = $creditoModel->find($id_credito);
    
        if (!$credito) {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se encontro el credito'], 200);
        }
    
        // Actualizar estado del pago
        $data = ['estado' => 'eliminado'];
    
        if ($creditoModel->update($id_credito, $data)) {
            
            return $this->respond(['error' => 0, 'data' => null, 'message' => 'Se ha eliminado el credito'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se ha pudo eliminar el credito'], 200);
        }
    }

    public function eliminarCliente()
    {
        $id_cliente = $this->request->getPost('id_cliente');
    
        if (!$id_cliente) {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'Ha ocurrido un error'], 200);
        }
    
        $clienteModel = new clienteModel(); // Asegúrate de tener este modelo
    
        // Buscar el pago por ID
        $cliente = $clienteModel->find($id_cliente);
    
        if (!$cliente) {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se encontro el cliente'], 200);
        }
    
        // Actualizar estado del pago
        $data = ['estado' => 2];
    
        if ($clienteModel->update($id_cliente, $data)) {
            
            return $this->respond(['error' => 0, 'data' => null, 'message' => 'Se ha eliminado el cliente'], 200);
        } else {
            return $this->respond(['error' => 1, 'data' => null, 'message' => 'No se ha pudo eliminar el cliente'], 200);
        }
    }


    public function abonar()
{

    $creditoId = $this->request->getPost('id_credito');
    $monto = $this->request->getPost('monto');

    if (!$creditoId || !$monto || $monto <= 0) {
        return $this->respond(['error' => 1, 'data' => null, 'message' => 'Datos incompletos.'], 200);

    }

    $creditoModel = new \App\Models\CreditoModel();
    $cuotaModel = new \App\Models\PagoModel();

    // Aquí llamas la función que hicimos antes para procesar el abono
    $this->procesarAbono($creditoId, $monto);

    return $this->respond(['error' => 0, 'data' => null, 'message' => 'Abono procesado correctamente.'], 200);
}

public function procesarAbono($creditoId, $montoAbonado)
{
    $cuotaModel = new \App\Models\PagoModel();
    $abonoModel = new \App\Models\AbonoModel();
    $creditoModel = new \App\Models\CreditoModel();

    // Obtener cuotas pendientes
    $cuotas = $cuotaModel
        ->where('credito_id', $creditoId)
        ->where('estado', 'pendiente')
        ->orderBy('id', 'asc')
        ->findAll();

    $totalAbonado = 0;

    foreach ($cuotas as $cuota) {
        $montoPagado = $cuota['monto_pagado'] ?? 0;
        $montoCuota = $cuota['monto_pago'];
        $montoPendiente = $montoCuota - $montoPagado;

        if ($montoAbonado <= 0) {
            break;
        }

        if ($montoAbonado >= $montoPendiente) {
            // Pago completo
            $abonoModel->insert([
                'pago_id' => $cuota['id'],
                'monto_abono' => $montoPendiente,
                'fecha_abono' => date('Y-m-d')
            ]);

            $cuotaModel->update($cuota['id'], [
                'monto_pagado' => $montoCuota,
                'monto_pendiente' => 0,
                'estado' => 'pagado'
            ]);

            $montoAbonado -= $montoPendiente;
            $totalAbonado += $montoPendiente;

        } else {
            // Abono parcial
            $nuevoMontoPagado = $montoPagado + $montoAbonado;
            $nuevoPendiente = $montoCuota - $nuevoMontoPagado;

            $abonoModel->insert([
                'pago_id' => $cuota['id'],
                'monto_abono' => $montoAbonado,
                'fecha_abono' => date('Y-m-d')
            ]);

            $cuotaModel->update($cuota['id'], [
                'monto_pagado' => $nuevoMontoPagado,
                'monto_pendiente' => $nuevoPendiente,
                'estado' => 'pendiente'
            ]);

            $totalAbonado += $montoAbonado;
            $montoAbonado = 0;
            break;
        }
    }

    // Restar el total abonado del valor restante del crédito
    if ($totalAbonado > 0) {
        $credito = $creditoModel->find($creditoId);
        $valorRestante = $credito['monto_restante'] ?? 0;

        $nuevoValorRestante = max(0, $valorRestante - $totalAbonado);

        $creditoModel->update($creditoId, [
            'monto_restante' => $nuevoValorRestante
        ]);
    }

    return true;
}


public function eliminarPago()
{
    $id = $this->request->getPost('id_pago');

    if (!$id) {
        return $this->failNotFound('ID de transacción no proporcionado.');
    }

    $transaccion = $this->db->table('transacciones')->where('id', $id)->get()->getRow();

    if (!$transaccion) {
        return $this->failNotFound('Transacción no encontrada.');
    }

    $this->db->transStart();

    // Actualizar monto_restante
    $this->db->table('creditos')
        ->where('id', $transaccion->id_credito)
        ->set('monto_restante', 'monto_restante + ' . $transaccion->monto, false)
        ->update();

    // Eliminar transacción
    $this->db->table('transacciones')->delete(['id' => $id]);

    $this->db->transComplete();

    if ($this->db->transStatus() === false) {
        return $this->failServerError('Error al eliminar la transacción.');
    }

    return $this->respondDeleted(['message' => 'Transacción eliminada y crédito actualizado.']);
}






    
}