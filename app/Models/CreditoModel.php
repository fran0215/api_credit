<?php

namespace App\Models;

use CodeIgniter\Model;

class CreditoModel extends Model
{
    protected $table = 'creditos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['cliente_id', 
                                'monto', 
                                'interes', 
                                'tasa_interes', 
                                'plazo', 
                                'fecha_inicio', 
                                'fecha_vencimiento',
                                'abono_pendiente', 
                                'total_pagar', 
                                'monto_restante', 
                                'frecuencia', 
                                'estado'
                            ];
}