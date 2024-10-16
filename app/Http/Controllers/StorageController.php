<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

use App\Base\BaseController;

/**
 * handle serving file di storage
 */
class StorageController extends BaseController
{
    private $filemtime = '';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * serve uplaod file di /storage/app/files/*
     * urlnya nya /storage/*
     *
     * @param Request $request *semua optional
     *      size : id/key size nya
     */
    public function index(Request $request)
    {
        $segment = $request->segments();
        array_shift($segment); // buang segment "storage"

        if (empty($segment)) {
            return abort(404);
        }
        
        $fullFilePath = implode('/', $segment);
        // dd(Storage::path($fullFilePath));
        //isi segement di /storage/*
        switch ($segment[0]) {
            case 'editor': // handle file yang diupload dari kind editor
                return $this->editor($request, $segment, $fullFilePath);
                break;
            case 'private': //jika akses file yang perlu akses token [SOON]
                if (Storage::exists($fullFilePath)) {
                    $fileName = end($segment);
                    if ($request->input('download', false)) {
                        return $this->serverDownload(
                            $fullFilePath,
                            $fileName,
                            $request->input('size', false)
                        );
                    } else {
                        return $this->serverFile(
                            $fullFilePath,
                            $fileName,
                            $request->input('size', false)
                        );
                    }
                }
                break;
            case 'content': //handle sementara file pengumuman untuk di mobile, nanti perbaiki mobile nya
                $fileName = end($segment);
                return $this->serverFileAlltenant($fullFilePath, $fileName, $request->input('size', false));
                break;
            case '': // lainnya
            case 'image': // handle file image
            case 'images': // handle file image
            default: // jika tidak dihandle khusus maka langsung didownload saja
                if (Storage::exists($fullFilePath)) {
                    $fileName = end($segment);
                    if ($request->input('download', false)) {
                        return $this->serverDownload($fullFilePath, $fileName, $request->input('size', false));
                    } else {
                        return $this->serverFile($fullFilePath, $fileName, $request->input('size', false));
                    }
                }
                break;
        }
        return $this->serveNotFound($fullFilePath);
    }

    private function serveNotFound($path)
    {
        // jika gambar maka serve default
        if($this->isImage($path)){
            $fileName = 'image-not-found.jpg';
            $fullFilePathTmp = resource_path('assets/images/default/default.jpg');

            $mime = $this->getMime($fileName);
            $hash = sha1($fullFilePathTmp);
            $this->filemtime = filemtime($fullFilePathTmp);
            $gmtMtime = gmdate('D, d M Y H:i:s', $this->filemtime) . ' GMT';
    
            $this->setHeader($hash, $gmtMtime);
    
            header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
            header("Content-disposition: inline; filename=" . $fileName);
            header("Content-type: " . $mime);

            exit(file_get_contents($fullFilePathTmp));
        }else{
            abort(404);
        }
    }

    private function isImage($path)
    {
        return strpos(strtolower($path),'image')!==false 
            || strpos(strtolower($path),'.jpg')!==false 
            || strpos(strtolower($path),'.jpeg')!==false 
            || strpos(strtolower($path),'.gif')!==false 
            || strpos(strtolower($path),'.png')!==false;
    }

    /**
     * jika file dari KindEditor
     *
     * @param \Illuminate\Http\Request $request
     * @param array $segment
     * @param string $fullFilePath
     * @return true|string string = file not found
     */
    private function editor($request, $segment, $fullFilePath)
    {
        $fileName = end($segment);
        if (Storage::exists($fullFilePath)) {
            if ($segment[1] == 'image' || $segment[1] == 'images') {
                return $this->serverFile($fullFilePath, $fileName, $request->input('size', false));
            } else {
                return $this->serverDownload($fullFilePath, $fileName, $request->input('size', false));
            }
        }

        return $this->serveNotFound($fullFilePath);
    }

    /**
     * Set Default Header
     *
     * @param string $hash ETag Hash
     * @param string $gmtMtime File Modified Time
     * @return void
     */
    private function setHeader($hash, $gmtMtime)
    {
        header("Cache-Control: public, max-age: 2592000");
        header("Last-Modified: " . $gmtMtime);
        header("ETag: " . $hash);
        header("Accept-Ranges: bytes");
        //header_remove("X-Powered-By");

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $d = new \DateTime($_SERVER['HTTP_IF_MODIFIED_SINCE'], new \DateTimeZone('UTC'));
            if ($this->filemtime == $d->format('U')) {
                header('HTTP/1.1 304 Not Modified');
                die();
            }
        }

        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            if ($_SERVER['HTTP_IF_NONE_MATCH'] == $hash) {
                header('HTTP/1.1 304 Not Modified');
                die();
            }
        }

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && !empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $tmp = explode(';', $_SERVER['HTTP_IF_NONE_MATCH']); // IE fix!
            if (!empty($tmp[0]) && strtotime($tmp[0]) == strtotime($gmtMtime)) {
                header('HTTP/1.1 304 Not Modified');
                die();
            }
        }

        //header("Content-Transfer-Encoding: binary");
        header("Pragma: public");
    }

    /**
     * Parse/View the file
     *
     * @param string $fullFilePath
     * @param string $fileName Filename to be sent to browser
     * @param boolean $size new resolution if image, and if given
     * @return true|void
     */
    private function serverFile($fullFilePath, $fileName, $size = false)
    {
        $fullFilePathTmp = Storage::path($fullFilePath);
        $mime = $this->getMime($fileName);
        $hash = sha1($fullFilePathTmp);
        $this->filemtime = filemtime($fullFilePathTmp);
        $gmtMtime = gmdate('D, d M Y H:i:s', $this->filemtime) . ' GMT';

        $this->setHeader($hash, $gmtMtime);

        header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
        header("Content-disposition: inline; filename=" . $fileName);
        header("Content-type: " . $mime);

        if ($size) {
            $this->resizeImage($fullFilePath, $size);
            return true;
        } else {
            exit(Storage::get($fullFilePath));
        }
    }

    /**
     * By Pass sementara akses all tenant
     **/
    private function serverFileAlltenant($fullFilePath, $fileName, $size = false)
    {
        $fullFilePathTmp = Storage::disk('alltenant')->path($fullFilePath);
        $mime = $this->getMime($fileName);
        $hash = sha1($fullFilePathTmp);
        $this->filemtime = filemtime($fullFilePathTmp);
        $gmtMtime = gmdate('D, d M Y H:i:s', $this->filemtime) . ' GMT';

        $this->setHeader($hash, $gmtMtime);

        header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
        header("Content-disposition: inline; filename=" . $fileName);
        header("Content-type: " . $mime);

        if ($size) {
            $this->resizeImage($fullFilePath, $size);
            return true;
        } else {
            exit(Storage::disk('alltenant')->get($fullFilePath));
        }
    }

    private function serverDownload($fullFilePath, $fileName, $size = false)
    {
        $fullFilePathTmp = Storage::path($fullFilePath);
        $mime = $this->getMime($fileName);
        $size   = filesize($fullFilePathTmp);
        $hash = sha1($fullFilePathTmp);
        $this->filemtime = filemtime($fullFilePathTmp);
        $gmtMtime = gmdate('D, d M Y H:i:s', $this->filemtime) . ' GMT';

        $this->setHeader($hash, $gmtMtime);

        header("Expires: " . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
        header("Content-disposition: attachment; filename=" . $fileName);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);

        if ($size) {
            $this->resizeImage($fullFilePath, $size);
            return true;
        } else {
            exit(Storage::get($fullFilePath));
        }
    }

    /**
     * SOON - image dengan fungsi resize
     */
    private function resizeImage($fullFilePath, $size)
    {
        if (config('AppConfig.system.upload.size.' . $size)) {
        }
        exit(Storage::get($fullFilePath));
    }

    /**
     * HELPER
     * -------------------------------------------------------------------------
     */
    private $ext = array(
        'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'),
        'flash' => array('swf', 'flv'),
        'media' => array(
            'swf', 'flv', 'mp3', 'mp4', 'wav', 'wma', 'wmv', 'mid', 'midi', 'avi',
            'mpg', 'mpeg', 'asf', 'rm', 'rmvb'
        ),
        'file' => array(
            'css', 'xml', 'doc', 'docx', 'rtf', 'pdf', 'xls', 'xlsx', 'ppt', 'pps',
            'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2'
        ),
        'download' => array(
            'gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'swf', 'flv', 'mp3', 'mp4',
            'wav', 'wma', 'wmv', 'mid', 'midi', 'avi', 'mpg', 'mpeg', 'asf', 'rm',
            'rmvb', 'css', 'xml', 'doc', 'docx', 'rtf', 'pdf', 'xls', 'xlsx', 'ppt',
            'pps', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2'
        ),
        'fileauto' => array('brehoh')
    );

    /**
     * Get Extension from filename
     *
     * @param string $filename
     * @return string
     */
    private function getExt($filename)
    {
        $ext = strtolower(ltrim(strrchr($filename, '.'), '.'));
        return $ext;
    }

    /**
     * get mime by extention from filename
     *
     * @param string $filename
     * @return string
     */
    private function getMime($filename)
    {
        $ext = $this->getExt($filename);
        return $this->mime()[$ext] ?? 'application/octet-stream';
    }

    /**
     * Get a predefined MIME Types array
     *
     * @return array
     */
    private function mime()
    {
        //font
        $mime['eot'] = 'application/vnd.ms-fontobject';
        $mime['otf'] = 'application/vnd.oasis.opendocument.formula-template';
        $mime['ttf'] = 'text/plain';
        $mime['svg'] = 'image/svg+xml';

        //image
        $mime['gif'] = 'image/gif';
        $mime['jpg'] = 'image/jpeg';
        $mime['jpeg'] = 'image/jpeg';
        $mime['png'] = 'image/png';
        $mime['bmp'] = 'image/bmp';
        $mime['ico'] = 'image/x-icon';

        //flash
        $mime['swf'] = 'application/x-shockwave-flash';
        $mime['flv'] = 'video/x-flv';

        //file
        $mime['doc'] = 'application/msword';
        $mime['docx'] = 'application/msword';
        $mime['rtf'] = 'application/msword';
        $mime['pdf'] = 'application/pdf';
        $mime['xls'] = 'application/vnd.ms-excel';
        $mime['xlsx'] = 'application/vnd.ms-excel';
        $mime['ppt'] = 'application/vnd.ms-powerpoint';
        $mime['pps'] = 'application/vnd.ms-powerpoint';
        $mime['htm'] = 'text/html';
        $mime['html'] = 'text/html';
        $mime['txt'] = 'text/plain';
        $mime['zip'] = 'application/octet-stream';
        $mime['rar'] = 'application/octet-stream';
        $mime['gz'] = 'application/octet-stream';
        $mime['bz2'] = 'application/octet-stream';

        //media ('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb')
        $mime['swf'] = 'application/x-shockwave-flash';
        $mime['flv'] = 'video/x-flv';
        $mime['mp3'] = 'audio/mpeg';
        $mime['mp4'] = 'video/mp4';
        $mime['wav'] = 'audio/x-wav';
        $mime['wma'] = 'audio/x-ms-wma';
        $mime['wmv'] = 'audio/x-ms-wmv';
        $mime['mid'] = 'audio/midi';
        $mime['midi'] = 'audio/midi';
        $mime['avi'] = 'video/msvideo';
        $mime['mpg'] = 'video/mpeg';
        $mime['mpeg'] = 'video/mpeg';
        $mime['asf'] = 'video/x-ms-asf';
        $mime['rm'] = 'application/vnd.rn-realmedia';
        $mime['rmvb'] = 'application/vnd.rn-realmedia-vbr';

        //other
        $mime['js'] = 'application/javascript';
        $mime['css'] = 'text/css';
        $mime['xml'] = 'application/xml';
        $mime['php'] = 'php';

        return $mime;
    }
}
