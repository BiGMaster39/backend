<?php namespace App\Models;

use CodeIgniter\Model;

class PushModel extends Model
{
    protected $table         = 'push';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'apple_enabled',
        'android_enabled',
        'os_app_id',
        'os_api_key',
        'sign_key'
    ];
    protected $returnType    = 'array';
}