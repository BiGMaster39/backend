<?php namespace App\Models;

use CodeIgniter\Model;

class AppsModel extends Model
{
    protected $table         = 'apps';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'uid',
        'name',
        'status',
        'created_at',
        'updated_at',
        'user',
        'link',
        'color_theme',
        'color_title',
        'template',
        'balance',
        'app_id',
        'user_agent',
        'orientation',
        'loader',
        'pull_to_refresh',
        'loader_color',
        'gps',
        'language',
        'camera',
        'microphone',
        'email',
        'display_title',
        'icon_color',
        'active_color',
        'deleted_at'
    ];
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'int';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}