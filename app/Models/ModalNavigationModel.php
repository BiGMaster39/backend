<?php namespace App\Models;

use CodeIgniter\Model;

class ModalNavigationModel extends Model
{
    protected $table         = 'modal_navigation';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'type',
        'icon',
        'link',
        'name'
    ];
    protected $returnType    = 'array';
}