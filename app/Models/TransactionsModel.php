<?php namespace App\Models;

use CodeIgniter\Model;

class TransactionsModel extends Model
{
    protected $table         = 'transactions';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'uid',
        'user_id',
        'amount',
        'created_at',
        'updated_at',
        'app_id',
        'status',
        'method_id',
        'quantity'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}