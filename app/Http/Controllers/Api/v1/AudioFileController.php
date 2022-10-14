<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AudioFileController extends Controller
{
    public function saveFile(Request $request)
    {
        $upload_path = public_path('audio');
        $file_name = $request->file->getClientOriginalName();
        $generated_new_name = time() . '.' . $request->file->getClientOriginalExtension();
        $request->file->move($upload_path, $generated_new_name);
        return response()->json([
            'audio' => "/audio/" . $generated_new_name   
        ]);
    }
}
