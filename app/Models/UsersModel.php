<?php namespace App\Models;

use CodeIgniter\Model;

class UsersModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'email',
        'password',
        'status',
        'created_at',
        'updated_at',
        'deleted_at',
        'admin'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}