<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use FFMpeg;
use Log;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        set_time_limit(0);

        $user = auth()->user();
        $urls = $request->all();
        foreach ($urls as $url){
            $ext = explode(".", $url)[1];
            if ($ext == "jpeg" || $ext == "jpg" || $ext == "png" || $ext == "heic" || $ext == "HEIC"){
                $type = 0;
            } else if ($ext == "mp4" || $ext == "mov" || $ext == "MOV"){
                $type = 1;
            }
            $media = $user->media()->create([
                'type' => $type,
                'extension' => $ext,
                'url' => "https://"
                    . env("AWS_BUCKET")
                    . ".s3."
                    . env("AWS_DEFAULT_REGION")
                    . ".amazonaws.com/media/"
                    . $url
            ]);
            if ($type == Media::TYPE_VIDEO) {
                $file->storeAs('tmp', $media->hash . '/media.' . $file->extension());
                $filepath = 'media/'.$media->hash. '/media.' . $file->extension();
                Storage::disk('s3')->put($filepath, file_get_contents($file));
                $mediaOpener = FFMpeg::open('tmp/' . $media->hash . '/media.' . $file->extension());
                $durationInSeconds = $mediaOpener->getDurationInSeconds();

                $num = 6;
                for ($i = 0; $i < $num; $i++) {
                    try {
                        $tstamp = round(($durationInSeconds / $num) * $i);
                        if ($tstamp < 1) $tstamp = 1;
                        if ($tstamp > $durationInSeconds - 1) $tstamp = $durationInSeconds - 1;
                        $mediaOpener = $mediaOpener->getFrameFromSeconds($tstamp)
                            ->export()
                            ->save("tmp/" . $media->hash . "/thumb_{$i}.png");
                    } catch (\Exception $e) {
                        //Log::debug($e->getMessage());
                        $mediaOpener = FFMpeg::open('tmp/' . $media->hash . '/media.' . $file->extension());
                    }
                }
            } else {
                $file->storeAs('tmp/', $media->hash . '/media.' . $file->extension());
                $filepath = 'media/'.$media->hash. '/media.' . $file->extension();
                Storage::disk('s3')->put($filepath, file_get_contents($file));
            }

        // if (strstr($mime, 'video') !== false) {
        //     $type = Media::TYPE_VIDEO;
        // } else if (strstr($mime, 'audio') !== false) {
        //     $type = Media::TYPE_AUDIO;
        // } else if (strstr($mime, 'image') !== false) {
        //     $type = Media::TYPE_IMAGE;
        // }

        // if ($type !== null) {
        //     $media = $user->media()->create([
        //         'type' => $type,
        //         'extension' => $file->extension()
        //     ]);
        //     if ($type == Media::TYPE_VIDEO) {
        //         $file->storeAs('tmp', $media->hash . '/media.' . $file->extension());
        //         $mediaOpener = FFMpeg::open('tmp/' . $media->hash . '/media.' . $file->extension());
        //         $durationInSeconds = $mediaOpener->getDurationInSeconds();

        //         $num = 6;
        //         for ($i = 0; $i < $num; $i++) {
        //             try {
        //                 $tstamp = round(($durationInSeconds / $num) * $i);
        //                 if ($tstamp < 1) $tstamp = 1;
        //                 if ($tstamp > $durationInSeconds - 1) $tstamp = $durationInSeconds - 1;
        //                 $mediaOpener = $mediaOpener->getFrameFromSeconds($tstamp)
        //                     ->export()
        //                     ->save("tmp/" . $media->hash . "/thumb_{$i}.png");
        //             } catch (\Exception $e) {
        //                 //Log::debug($e->getMessage());
        //                 $mediaOpener = FFMpeg::open('tmp/' . $media->hash . '/media.' . $file->extension());
        //             }
        //         }
        //     } else {
        //         $file->storeAs('tmp', $media->hash . '/media.' . $file->extension());
        //     }

        //     $media->append(['thumbs']);
        // }


        return response()->json($media);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Media  $media
     * @return \Illuminate\Http\Response
     */
    public function destroy(Media $media)
    {
        $this->authorize('delete', $media);
        $media->delete();
        return response()->json(['status' => true]);
    }
}
