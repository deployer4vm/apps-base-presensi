<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
     * initiate language resource for vue apps
     *
     * @param \Illuminate\Http\Request $request *semua optional
     *      lang : lang id nya
     *      item : item nya jika diperlukan
     * @return \Illuminate\Http\Response JSON Response
     */
    public function upload(Request $request)
    {
        //File save directory path
        $save_path = storage_path('app/upload/editor/');
        //File save directory URL
        $save_url = url('upload/editor') . '/';
        //Define file extensions that are allowed to upload
        $ext_arr = [
            'image' => ['gif', 'jpg', 'jpeg', 'png', 'bmp'],
            'flash' => ['swf', 'flv'],
            'media' => ['swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'],
            'file' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2'],
        ];
        //Maximum file size
        $max_size = 1000000;

        $save_path = realpath($save_path) . '/';

        //PHP upload failed
        if (!empty($_FILES['imgFile']['error'])) {
            switch ($_FILES['imgFile']['error']) {
                case '1':
                    $error = 'Exceeded the size allowed by php.ini. ';
                    break;
                case '2':
                    $error = 'Exceeded the size allowed by the form. ';
                    break;
                case '3':
                    $error = 'Only part of the image was uploaded. ';
                    break;
                case '4':
                    $error = 'Please select an image. ';
                    break;
                case '6':
                    $error = 'Temporary directory not found. ';
                    break;
                case '7':
                    $error = 'Error writing file to hard disk. ';
                    break;
                case '8':
                    $error = 'File upload stopped by extension. ';
                    break;
                case '999':
                default:
                    $error = 'Unknown error. ';
            }
            return $this->uploadAlert($error);
        }

        // When uploading files
        if (empty($_FILES) === false) {
            // Original file name
            $file_name = $_FILES['imgFile']['name'];
            // Temporary file name on the server
            $tmp_name = $_FILES['imgFile']['tmp_name'];
            //File size
            $file_size = $_FILES['imgFile']['size'];
            // Check the file name
            if (!$file_name) {
                return $this->uploadAlert("Please select a file.");
            }
            // Check the directory
            if (@is_dir($save_path) === false) {
                return $this->uploadAlert("The upload directory does not exist.");
            }
            // Check the directory write permission
            if (@is_writable($save_path) === false) {
                return $this->uploadAlert("The upload directory does not have write permission.");
            }
            // Check if it has been uploaded
            if (@is_uploaded_file($tmp_name) === false) {
                return $this->uploadAlert("Upload failed.");
            }
            //Check file size
            if ($file_size > $max_size) {
                return $this->uploadAlert("The upload file size exceeds the limit.");
            }
            //Check directory name
            $dir_name = $request->input('dir', 'image');
            if (empty($ext_arr[$dir_name])) {
                return $this->uploadAlert("The directory name is incorrect.");
            }
            //Get file extension
            $temp_arr = explode(".", $file_name);
            $file_ext = array_pop($temp_arr);
            $file_ext = trim($file_ext);
            $file_ext = strtolower($file_ext);
            //Check extension
            if (in_array($file_ext, $ext_arr[$dir_name]) === false) {
                return $this->uploadAlert("Upload file extension is not allowed. \n Only allowed"
                    . implode(",", $ext_arr[$dir_name])
                    . "format.");
            }
            //Create Folder
            if ($dir_name !== '') {
                $save_path .= $dir_name . "/";
                $save_url .= $dir_name . "/";
                if (!file_exists($save_path)) {
                    mkdir($save_path, 0766, true);
                }
            }
            $ymd = date("Ymd");
            $save_path .= $ymd . "/";
            $save_url .= $ymd . "/";
            if (!file_exists($save_path)) {
                mkdir($save_path, 0766, true);
            }
            //New file name
            $new_file_name = date("YmdHis") . '_' . rand(10000, 99999) . '.' . $file_ext;
            //Move file
            $file_path = $save_path . $new_file_name;
            if (move_uploaded_file($tmp_name, $file_path) === false) {
                return $this->uploadAlert("File upload failed.");
            }
            @chmod($file_path, 0644);
            $file_url = $save_url . $new_file_name;

            return response()->json(array('error' => 0, 'url' => $file_url));
        }
        return $this->uploadAlert("File upload failed.");
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
     * GROUP_APP/api/sys/editor/fileManager'
     */
    public function filemanager(Request $request)
    {

        // Root directory path, you can specify an absolute path, such as / var / www / attached /
        $root_path = storage_path('app/upload/editor') . DIRECTORY_SEPARATOR;

        // Root directory URL, you can specify an absolute path, such as http://www.yoursite.com/attached/
        $root_url = url('upload/editor') . '/';

        //Picture extension
        $ext_arr = array('gif', 'jpg', 'jpeg', 'png', 'bmp');

        //Directory name
        $dir_name = $request->input('dir');
        if (!in_array($dir_name, array('', 'image', 'flash', 'media', 'file'))) {
            echo "Invalid Directory name.";
            exit;
        }
        if ($dir_name !== '') {
            $root_path .= $dir_name . "/";
            $root_url .= $dir_name . "/";
            if (!file_exists($root_path)) {
                mkdir($root_path, 0766, true);
            }
        }

        //According to the path parameter, set each path and URL
        if (!$request->input('path')) {
            $current_path = realpath($root_path) . '/';
            $current_url = $root_url;
            $current_dir_path = '';
            $moveup_dir_path = '';
        } else {
            $current_path = realpath($root_path) . '/' . $request->input('path');
            $current_url = $root_url . $request->input('path');
            $current_dir_path = $request->input('path');
            $moveup_dir_path = preg_replace('/(.*?)[^\/]+\/$/', '$1', $current_dir_path);
        }
        //echo realpath($root_path);
        //Sorting form, name or size or type
        $order = $request->input('order', 'name');

        //Not allowed to use: move to the previous directory
        if (preg_match('/\.\./', $current_path)) {
            echo 'Access is not allowed.';
            exit;
        }
        //The last character is not /
        if (!preg_match('/\/$/', $current_path)) {
            echo 'Parameter is not valid.';
            exit;
        }
        //Directory does not exist or is not a directory
        if (!file_exists($current_path) || !is_dir($current_path)) {
            echo 'Directory does not exist.';
            exit;
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
