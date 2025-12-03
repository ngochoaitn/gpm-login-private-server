<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends BaseController
{
    public function store(Request $request)
    {
        if ($files = $request->file('file')) {
            try {
                if($request->file('file')->getSize() > 0) {
                    $file = $request->file->storeAs('public/profiles', $request->file_name);

                    // If upload success -> $file = public\/profiles\/uOfKHHiGOgtn6rYygUrCsbRjHj7ypsm989oWpji0.mp4
                    $fileName = str_replace("public/profiles/", "", $file);
                    return $this->getJsonResponse(true, 'Thành công', ['path' => 'storage/profiles', 'file_name' => $fileName]);
                }else {
                    return $this->getJsonResponse(false, 'Thất bại', ['message' => 'File rỗng']);
                }
            } catch (\Exception $ex){
                return $this->getJsonResponse(false, 'Thất bại', $ex);
            }
            // store file into profile folder
        }
        return $this->getJsonResponse(false, 'Thất bại', []);
    }

    public function delete(Request $request) {
        $fullLocation = 'public/profiles/'.$request->file;
        Storage::delete($fullLocation);
        return $this->getJsonResponse(true, 'Thành công', []);
    }
}
