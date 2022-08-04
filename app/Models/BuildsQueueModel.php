<?php namespace App\Models;

use CodeIgniter\Model;

class BuildsQueueModel extends Model
{
    protected $table         = 'builds_queue';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'build',
        'status'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}