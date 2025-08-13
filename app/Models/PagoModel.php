<?php

namespace App\Models;

use CodeIgniter\Model;

class PagoModel extends Model
{
    protected $table = 'pagos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['credito_id', 'fecha_pago', 'monto_pago', 'monto_pagado', 'monto_pendiente', 'estado'];
}
