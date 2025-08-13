<?php

namespace App\Models;

use CodeIgniter\Model;

class TransaccionesModel extends Model
{
    protected $table = 'transacciones';
    protected $primaryKey = 'id';
    protected $allowedFields = ['id_credito', 'monto', 'fecha', 'fecha_systema'];
}
