<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    use HasFactory;

    protected $fillable = [
        'change_date',
        'version',
        'type',
        'title',
        'description',
        'files_modified',
    ];

    // บอก Laravel ว่า 'files_modified' ควรเป็น Array (JSON)
    protected $casts = [
        'change_date' => 'date',
        'files_modified' => 'array', 
    ];
}