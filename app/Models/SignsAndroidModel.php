<?php namespace App\Models;

use CodeIgniter\Model;

class SignsAndroidModel extends Model
{
    protected $table         = 'signs_android';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id',
        'uid',
        'name',
        'created_at',
        'updated_at',
        'alias',
        'keystore_password',
        'key_password',
        'file'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}