<?php namespace App\Models;

use CodeIgniter\Model;

class EmailConfigModel extends Model
{
    protected $table         = 'email_config';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'status',
        'host',
        'user',
        'port',
        'timeout',
        'charset',
        'sender',
        'password'
    ];
    protected $returnType    = 'array';
}