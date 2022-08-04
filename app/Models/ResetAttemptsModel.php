<?php namespace App\Models;

use CodeIgniter\Model;

class ResetAttemptsModel extends Model
{
    protected $table         = 'reset_attempts';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'email',
        'status',
        'token',
        'created_at',
        'updated_at'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}