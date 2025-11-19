<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * ✅ บรรทัดนี้คือหัวใจสำคัญ
     * Trait 'AuthorizesRequests' คือสิ่งที่ทำให้ Controller ลูกๆ
     * สามารถเรียกใช้ $this->middleware() ได้
     */
    use AuthorizesRequests, ValidatesRequests;
}