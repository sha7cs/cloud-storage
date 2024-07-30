<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentStorage extends Model
{

    // protected $fillable = [
    //     'title',
    //     'department_id',
    //     'user_id',
    //     'category_id',
    //     'file_type_id',
    //     'file_path',
    // ];

    protected $guarded;
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function fileType()
    {
        return $this->belongsTo(FileType::class ,"file_type" ,"id");
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
