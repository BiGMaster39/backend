<?php namespace App\Models;

use CodeIgniter\Model;

class DrawersModel extends Model
{
    protected $table         = 'drawers';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'mode',
        'color',
        'theme',
        'logo_enabled',
        'title',
        'subtitle',
        'logo',
        'background'
    ];
    protected $returnType    = 'array';
}