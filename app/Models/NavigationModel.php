<?php namespace App\Models;

use CodeIgniter\Model;

class NavigationModel extends Model
{
    protected $table         = 'navigation';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'name',
        'type',
        'icon',
        'link'
    ];
    protected $returnType    = 'array';
}