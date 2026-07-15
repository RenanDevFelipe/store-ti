<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StorefrontMediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $file = $data['image'];
        $directory = public_path('uploads/storefront');

        File::ensureDirectoryExists($directory);

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return response()->json([
            'url' => url('/uploads/storefront/'.$filename),
        ], 201);
    }
}
