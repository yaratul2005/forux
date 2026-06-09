<?php

namespace App\Controllers;

use Core\Response;
use Core\Request;
use Modules\Auth\Services\AuthService;
use App\Services\Storage\StorageServiceInterface;

/**
 * Controller to handle client-side paste-to-upload files
 */
class UploadController
{
    protected AuthService $auth;
    protected StorageServiceInterface $storage;
    protected Request $request;

    /**
     * Create a new UploadController instance.
     */
    public function __construct(AuthService $auth, StorageServiceInterface $storage, Request $request)
    {
        $this->auth = $auth;
        $this->storage = $storage;
        $this->request = $request;
    }

    /**
     * Handle the POST upload request.
     */
    public function upload(): Response
    {
        // 1. Authenticate user
        if (!$this->auth->check()) {
            return Response::json(['error' => 'Unauthorized. Please log in first.'], 401);
        }

        // 2. Resolve file
        $file = $this->request->file('image') ?? $this->request->file('file');
        if (!$file || empty($file['tmp_name']) || (!is_uploaded_file($file['tmp_name']) && php_sapi_name() !== 'cli')) {
            return Response::json(['error' => 'No file uploaded or invalid upload.'], 400);
        }

        // 3. Strict MIME type check
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        }
        if (empty($mime)) {
            $mime = $file['type'] ?? '';
        }

        if (!in_array(strtolower($mime), $allowedMimes)) {
            return Response::json(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WEBP images are allowed.'], 400);
        }

        // 4. File size check (5 MB limit)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return Response::json(['error' => 'File size exceeds the 5MB limit.'], 400);
        }

        // 4.5. Image resizing using GD if needed
        $resizedTmpFile = null;
        if (extension_loaded('gd')) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo) {
                list($origWidth, $origHeight, $imageType) = $imageInfo;
                if ($origWidth > 1200 || $origHeight > 1200) {
                    $ratio = $origWidth / $origHeight;
                    if ($origWidth > $origHeight) {
                        $newWidth = 1200;
                        $newHeight = (int)round(1200 / $ratio);
                    } else {
                        $newHeight = 1200;
                        $newWidth = (int)round(1200 * $ratio);
                    }

                    $srcImg = null;
                    switch ($imageType) {
                        case IMAGETYPE_JPEG:
                            $srcImg = @imagecreatefromjpeg($file['tmp_name']);
                            break;
                        case IMAGETYPE_PNG:
                            $srcImg = @imagecreatefrompng($file['tmp_name']);
                            break;
                        case IMAGETYPE_GIF:
                            $srcImg = @imagecreatefromgif($file['tmp_name']);
                            break;
                        case IMAGETYPE_WEBP:
                            if (function_exists('imagecreatefromwebp')) {
                                $srcImg = @imagecreatefromwebp($file['tmp_name']);
                            }
                            break;
                    }

                    if ($srcImg) {
                        $dstImg = imagecreatetruecolor($newWidth, $newHeight);
                        if ($dstImg) {
                            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF || $imageType == IMAGETYPE_WEBP) {
                                imagealphablending($dstImg, false);
                                imagesavealpha($dstImg, true);
                                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                                imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
                            }

                            if (imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
                                $tempResized = tempnam(sys_get_temp_dir(), 'resized_img_');
                                $saved = false;
                                switch ($imageType) {
                                    case IMAGETYPE_JPEG:
                                        $saved = @imagejpeg($dstImg, $tempResized, 85);
                                        break;
                                    case IMAGETYPE_PNG:
                                        $saved = @imagepng($dstImg, $tempResized, 6);
                                        break;
                                    case IMAGETYPE_GIF:
                                        $saved = @imagegif($dstImg, $tempResized);
                                        break;
                                    case IMAGETYPE_WEBP:
                                        if (function_exists('imagewebp')) {
                                            $saved = @imagewebp($dstImg, $tempResized, 85);
                                        }
                                        break;
                                }

                                if ($saved) {
                                    $resizedTmpFile = $tempResized;
                                    $file['tmp_name'] = $resizedTmpFile;
                                    $file['size'] = filesize($resizedTmpFile);
                                } else {
                                    @unlink($tempResized);
                                }
                            }
                            imagedestroy($dstImg);
                        }
                        imagedestroy($srcImg);
                    }
                }
            }
        }

        // 5. Generate safe unique file name
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            // fallback based on mime
            $mimeParts = explode('/', $mime);
            $ext = end($mimeParts) ?: 'png';
            if ($ext === 'jpeg') $ext = 'jpg';
        }
        
        $filename = md5(uniqid(microtime(true), true)) . '.' . $ext;
        $path = $filename; // Store directly under base bucket/directory or uploads subfolder

        // 6. Save file
        $success = $this->storage->putFile($path, $file['tmp_name']);

        // Clean up temporary resized file if one was created
        if ($resizedTmpFile && file_exists($resizedTmpFile)) {
            @unlink($resizedTmpFile);
        }

        if (!$success) {
            return Response::json(['error' => 'Failed to save the uploaded file to storage.'], 500);
        }

        // 7. Return URL
        return Response::json([
            'url' => $this->storage->url($path)
        ]);
    }
}
