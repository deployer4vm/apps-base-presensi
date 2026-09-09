<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Base\BaseController;

class KindeditorController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * GROUP_APP/api/sys/editor/
     * Upload dari kindeditor
     *
     * @param \Illuminate\Http\Request $request *semua optional
     * @return \Illuminate\Http\Response JSON Response
     */
    public function upload(Request $request)
    {
        if ($request->input('dir', 'image') !== 'image') {
            return $this->uploadAlert('Only image uploads are allowed.');
        }

        $validator = Validator::make($request->all(), [
            'imgFile' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return $this->uploadAlert($validator->errors()->first('imgFile'));
        }

        $file = $request->file('imgFile');
        $directory = 'editor/image/' . now()->format('Ymd');
        $filename = Str::random(40) . '.' . strtolower($file->extension());
        Storage::putFileAs($directory, $file, $filename);

        return response()->json([
            'error' => 0,
            'url' => url(Storage::url($directory . '/' . $filename)),
        ]);
    }

    /**
     * Return JSON Response
     *
     * @param string $msg
     * @return \Illuminate\Http\Response
     */
    private function uploadAlert($msg)
    {
        return response()->json(array('error' => 1, 'message' => $msg));
    }

    /**
     * GROUP_APP/api/sys/editor/fileManager
     */
    public function filemanager(Request $request)
    {

        // Root directory path, you can specify an absolute path, such as / var / www / attached /
        Storage::makeDirectory('editor/image');
        $root_path = realpath(Storage::path('editor/image')) . DIRECTORY_SEPARATOR;

        // Root directory URL, you can specify an absolute path, such as http://www.yoursite.com/attached/
        $root_url = url(Storage::url('editor/image')).'/';

        //Picture extension
        $ext_arr = array('gif', 'jpg', 'jpeg', 'png', 'bmp');

        //Directory name
        $dir_name = $request->input('dir', 'image');
        if (!in_array($dir_name, ['', 'image'], true)) {
            return response()->json(['error' => 'Invalid directory name.'], 422);
        }

        //According to the path parameter, set each path and URL
        $relativePath = (string) $request->input('path', '');
        if ($relativePath !== '' && !preg_match('#^[A-Za-z0-9_/-]+$#', $relativePath)) {
            return response()->json(['error' => 'Invalid path.'], 422);
        }

        if (!$relativePath) {
            $current_path = $root_path;
            $current_url = $root_url;
            $current_dir_path = '';
            $moveup_dir_path = '';
        } else {
            $resolvedPath = realpath($root_path . $relativePath);
            if (!$resolvedPath || !str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $root_path)) {
                return response()->json(['error' => 'Access is not allowed.'], 403);
            }
            $current_path = $resolvedPath . DIRECTORY_SEPARATOR;
            $current_url = $root_url . $relativePath;
            $current_dir_path = $relativePath;
            $moveup_dir_path = preg_replace('/(.*?)[^\/]+\/$/', '$1', $current_dir_path);
        }
        //echo realpath($root_path);
        //Sorting form, name or size or type
        $order = $request->input('order', 'name');

        //Not allowed to use: move to the previous directory
        if (str_contains($relativePath, '..')) {
            return response()->json(['error' => 'Access is not allowed.'], 403);
        }
        //The last character is not /
        if (!preg_match('/\/$/', $current_path)) {
            return response()->json(['error' => 'Parameter is not valid.'], 422);
        }
        //Directory does not exist or is not a directory
        if (!file_exists($current_path) || !is_dir($current_path)) {
            return response()->json(['error' => 'Directory does not exist.'], 404);
        }

        //Traverse the directory to get file information
        $file_list = array();
        if ($handle = opendir($current_path)) {
            $i = 0;
            while (false !== ($filename = readdir($handle))) {
                if ($filename[0] == '.') continue;
                $file = $current_path . $filename;
                if (is_dir($file)) {
                    $file_list[$i]['is_dir'] = true; //Whether is folder
                    $file_list[$i]['has_file'] = (count(scandir($file)) > 2); //Whether the folder contains files
                    $file_list[$i]['filesize'] = 0; //File size
                    $file_list[$i]['is_photo'] = false; //Whether the picture
                    $file_list[$i]['filetype'] = ''; //File type, judge by extension
                } else {
                    $file_list[$i]['is_dir'] = false;
                    $file_list[$i]['has_file'] = false;
                    $file_list[$i]['filesize'] = filesize($file);
                    $file_list[$i]['dir_path'] = '';
                    $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $file_list[$i]['is_photo'] = in_array($file_ext, $ext_arr);
                    $file_list[$i]['filetype'] = $file_ext;
                }
                $file_list[$i]['filename'] = $filename; //File name, including extension
                $file_list[$i]['datetime'] = date('Y-m-d H:i:s', filemtime($file)); //File last modified time
                $i++;
            }
            closedir($handle);
        }

        //Sort


        usort($file_list, function ($a, $b) use ($order) {
            if ($a['is_dir'] && !$b['is_dir']) {
                return -1;
            } else if (!$a['is_dir'] && $b['is_dir']) {
                return 1;
            } else {
                if ($order == 'size') {
                    if ($a['filesize'] > $b['filesize']) {
                        return 1;
                    } else if ($a['filesize'] < $b['filesize']) {
                        return -1;
                    } else {
                        return 0;
                    }
                } else if ($order == 'type') {
                    return strcmp($a['filetype'], $b['filetype']);
                } else {
                    return strcmp($a['filename'], $b['filename']);
                }
            }
        });

        $result = array();
        //Relative to the root directory
        $result['moveup_dir_path'] = $moveup_dir_path;
        //The current directory relative to the root directory
        $result['current_dir_path'] = $current_dir_path;
        //URL of current directory
        $result['current_url'] = $current_url;
        //Number of files
        $result['total_count'] = count($file_list);
        //File list array
        $result['file_list'] = $file_list;

        return response()->json($result);
    }
}
