<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AudioFileController extends Controller
{
    public function saveFile(Request $request)
    {
        if ($request->hasFile('files')) {
            $files = array();
        
            foreach ($request->file('files') as $file) {
                if ($file->isValid()) {
                    var_dump($file);
                    $name = time() . str_random(5) . '.' . $file->getClientOriginalExtension();
                    
                    // Storage::disk('public')->put($name, $file);
                    // $files[] = $name;
                }
            }
        
            // if (count($files) > 0) {
            //     $response->assets = json_encode($files);
            // }
        }
    }
}
