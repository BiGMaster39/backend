<?php namespace App\Models;

use CodeIgniter\Model;

class SignsIosModel extends Model
{
    protected $table         = 'signs_ios';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id',
        'uid',
        'name',
        'created_at',
        'updated_at',
        'issuer_id',
        'key_id',
        'file'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}