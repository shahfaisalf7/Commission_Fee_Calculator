<?php

namespace App\Http\Controllers\file;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    static function uploadFile ($file, $path)
    {
        $extension = $file->getClientOriginalExtension() ? $file->getClientOriginalExtension() : "csv";
        $filename = self::generateFileName($path, $extension);
        Storage::disk('public')->put($path.$filename, file_get_contents($file));
        return $path.$filename;
    }

    static function getFileWithoutURL ($url) {
        return Str::after($url, '/storage');
    }

    static function deleteFile ($path)
    {
        $path = self::getFileWithoutURL($path);
        if ($path != "users/default.png")
            Storage::disk('public')->delete($path);
    }

    static protected function generateFileName ($path, $extension)
    {
        $filename = Str::random(20);
        // Makes sure the filename does not exist, if it does, just regenerate
        while (Storage::disk('public')->exists($path.$filename.'.'.$extension)) {
            $filename = Str::random(20);
        }
        return $filename.'.'.$extension;
    }
}
