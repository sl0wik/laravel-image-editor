<?php
namespace Sl0wik\LaravelImageEditor\Traits;

use Storage;
use Response;

trait ImageCache
{
    /** @var string Disk used for caching. */
    protected $cache_disk = 'local';

    /** @var string Image extension used in cache. */
    protected $cache_extension = 'jpg';

    /* getCacheID should generate unique id like "gallery/33" */
    abstract public function getCacheID();

    /* Is caching active */
    public function cacheable()
    {
        if (request('nocache')) {
            return false;
        }
        return true;
    }

    /**
     * Check if image is cache, if yes return Image handle.
     *
     * @return \Intervention\Image\Facades\Image
     */
    public function cached()
    {
        $fileID = $this->getCacheID();
        $storage = Storage::disk($this->cache_disk);
        if ($storage->exists($this->generateCacheFilePath($fileID, true))) {
            return $this->makeImage(
                $this->storagePath(
                    $this->generateCacheFilePath($fileID, true)
                )
            );
        }
    }

    /**
     * Save image to cache.
     *
     * @param \Intervention\Image\Facades\Image $handle Image handle
     * @return void
     */
    public function cache($handle)
    {
        $fileID = $this->getCacheID();
        $storage = Storage::disk($this->cache_disk);
        $storage->put(
            $this->generateCacheFilePath($fileID, true),
            $handle->encode($this->extension(), config('images.image_quality', 90))
        );
    }

    /**
     * Get handle to original file from cache.
     *
     * @return \Intervention\Image\Facades\Image
     */
    public function getHandleFromCache()
    {
        $fileID = $this->getCacheID();
        if (!$this->isCached($fileID)) {
            $this->saveOriginalImageToCache($fileID);
        }
        return $this->makeImage(
            $this->storagePath(
                $this->generateCacheFilePath($fileID)
            )
        );
    }

    /**
     * Save original image to cache.
     *
     * @param string $fileID Unique file id
     * @return void
     */
    public function saveOriginalImageToCache($fileID)
    {
        Storage::disk($this->cache_disk)->put($this->generateCacheFilePath($fileID), $this->getOriginalImageFile());
    }

    /**
     * Check if file exists in cache.
     *
     * @return boolean
     */
    public function isCached($fileID)
    {
        if (Storage::disk($this->cache_disk)->exists($this->generateCacheFilePath($fileID))) {
            return true;
        }
    }

    /**
     * Generate path to cache file.
     *
     * @param string $fileID Unique file id
     * @param bool $add_parameters Should cache file include parameters (if not its original file)
     * @return string Parth to cache file
     */
    public function generateCacheFilePath($fileID, $add_parameters = false)
    {
        /* To do: fileID should be better secured */
        $fileID = str_replace('.', '', $fileID);

        $parameters = ['i'];
        if ($add_parameters) {
            if ($this->size()) {
                $parameters[] = $this->size();
            }
            if ($this->watermarkPath()) {
                $parameters[] = "w";
            }
        }
        $path = config('images.cache_path');
        $path .= "images/{$fileID}/".implode('-', $parameters);
        $path .= '.'.config('images.cache_extension');
        return $path;
    }

    /**
     * Return storage path.
     *
     * @param string $path Path to image
     * @return string Path with storage path
     */
    public function storagePath($path)
    {
        return storage_path('app/'.$path);
    }
}
