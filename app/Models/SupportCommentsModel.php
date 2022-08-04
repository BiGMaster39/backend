<?php namespace App\Models;

use CodeIgniter\Model;

class SupportCommentsModel extends Model
{
    protected $table         = 'support_comments';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id',
        'message',
        'estimation',
        'created_at',
        'updated_at',
        'uid',
        'ticket_id'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}