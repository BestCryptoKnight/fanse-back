<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AudioFileController extends Controller
{
    public function saveFile(Request $request)
    {
        $generated_new_name = time() . '.' . $request->file->getClientOriginalExtension();
        $request->file->storeAs('tmp', $generated_new_name);

        $s3_filepath = 'bio_voice/' . $generated_new_name;
        Storage::disk('s3')->put($s3_filepath, file_get_contents($request->file));

        return response()->json([
            'audio' => "/bio_voice/" . $generated_new_name
        ]);
    }

    public function deleteFile(Request $request)
    {
        $file_name = str_replace("\\", "", $request->fileName);
        if(substr($file_name, 0, strlen("/bio_voice/")) === "/bio_voice/")
        {
            Storage::disk('s3')->delete($file_name);
            return response()->json([
                'response' => $file_name
            ]);
        }

        return response()->json([
            'response' => "Unauthorized Request"
        ]);
    }
}
