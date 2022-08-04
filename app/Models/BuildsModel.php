<?php namespace App\Models;

use CodeIgniter\Model;

class BuildsModel extends Model
{
    protected $table         = 'builds';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'uid',
        'platform',
        'created_at',
        'updated_at',
        'status',
        'android_key_id',
        'ios_key_id',
        'version',
        'publish',
        'format',
        'fail',
        'build_id',
        'static',
        'message'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}