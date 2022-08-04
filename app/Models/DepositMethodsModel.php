<?php namespace App\Models;

use CodeIgniter\Model;

class DepositMethodsModel extends Model
{
    protected $table         = 'deposit_methods';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'logo',
        'status',
        'api_value_1',
        'api_value_2',
        'api_value_3',
    ];
    protected $returnType    = 'array';
}