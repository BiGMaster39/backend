<?php namespace App\Models;

use CodeIgniter\Model;

class SupportTicketsModel extends Model
{
    protected $table         = 'support_tickets';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'uid',
        'title',
        'user_id',
        'created_at',
        'updated_at',
        'status'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}