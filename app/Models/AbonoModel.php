<?php

namespace App\Models;

use CodeIgniter\Model;

class AbonoModel extends Model
{
    protected $table = 'abonos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['pago_id', 'monto_abono', 'fecha_abono'];
}
