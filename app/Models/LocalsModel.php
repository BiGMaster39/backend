<?php namespace App\Models;

use CodeIgniter\Model;

class LocalsModel extends Model
{
    protected $table         = 'locals';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'string_1',
        'string_2',
        'string_3',
        'string_4',
        'string_5',
        'string_6',
        'string_7',
        'string_8',
        'error_image',
        'offline_image',
        'app_id'
    ];
    protected $returnType    = 'array';
}