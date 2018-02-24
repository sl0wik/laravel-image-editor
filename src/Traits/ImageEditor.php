<?php
namespace Sl0wik\LaravelImageEditor\Traits;

use Storage;
use Response;

trait ImageEditor
{
    protected $todo = [];

    protected $watermark_path;

    protected $extension;

    /**
     * Resize image to specific width.
     *
     * @param integer $x Image width in px
     * @return \Intervention\Image\Facades\Image
     */
    public function resizeX($x)
    {
        return $this->handle()->resize($x, null, function ($constraint) {
            $constraint->aspectRatio();
        });
    }

    /**
     * Resize image to specific height.
     *
     * @param integer $y Image height in px
     * @return \Intervention\Image\Facades\Image
     */
    public function resizeY($y)
    {
        $this->handle()->resize(null, $y, function ($constraint) {
            $constraint->aspectRatio();
        });
    }

    /**
     * Resize image to fit specific resolution.
     *
     * @param integer $x Width in px
     * @param integer $y Height in px
     * @return \Intervention\Image\Facades\Image
     */
    public function resizeXY($x, $y)
    {
        return $this->handle()->fit($x, $y);
    }

    /**
     * Resize image to, whats greater width or height.
     *
     * @param integer $x Width in px
     * @param integer $y Height in px
     * @return \Intervention\Image\Facades\Image
     */
    public function resizeXorY($x, $y)
    {
        if ($x > $y) {
            $this->resizeX($this->handle(), $x);
        } else {
            $this->resizeY($this->handle(), $y);
        }
    }

    /**
     * Check if extension is allowed. If yes return extension.
     *
     * @param type $extension File extension
     * @return extension|abort
     */
    public function checkExtension($extension)
    {
        if (in_array($extension, config('images.allowed_extensions', ['jpg']))) {
            return $extension;
        } else {
            abort(403, 'Illegal file extension.');
        }
    }

    /**
     * Set or get extension.
     *
     * @param string $extension File extension
     * @return string|this File extension
     */
    public function extension($extension = null)
    {
        if (is_null($extension)) {
            if (!empty($this->extension)) {
                return $this->extension;
            }
            if (isset($this->attributes['extension'])) {
                return $this->attributes['extension'];
            }
        }
        $this->extension = $this->checkExtension($extension);
        return $this;
    }

    /**
     * Set file size (resolution), that will be applied on save.
     *
     * @param string|null $size File size ex 800x600
     * @return this
     */
    public function size($size = null)
    {
        /* To do: function should be called ratio() */
        if (is_null($size)) {
            if (isset($this->size_attribute)) {
                return $this->size_attribute;
            } else {
                return null;
            }
        }
        $size = $this->parseSize($size)->raw;
        $this->size_attribute = $size;
        $this->todo('resize');
        return $this;
    }


    /**
     * Resize image.
     *
     * Options:
     * x{x}
     * y{y}
     * {x}x{y} (fixed size)
     * {x}o{y} (portrait or horizontal, o = or)
     * If parameter is null size will come from $this->size()
     *
     * @param string|null $size Size string ex.: x100,y100,800x600,800o600
     * @return \Intervention\Image\Facades\Image
     */
    public function resize($size = null)
    {
        if (is_null($size)) {
            $size = $this->size();
        }
        $size = $this->parseSize($size);
        switch ($size->method) {
            case 'x{x}':
                $this->resizeX($size->x);
                break;
            case 'y{y}':
                $this->resizeY($size->y);
                break;
            case '{x}x{y}':
                $this->resizeXY($size->x, $size->y);
                break;
            case '{x}o{y}':
                $this->resizeXorY($size->x, $size->y);
                break;
            default:
                abort(403, 'Undefined resize method.');
                break;
        }
        return $this;
    }

    /**
     * Check if size is correct.
     *
     * Possible options:
     * x{x}
     * y{y}
     * {x}x{y} (fixed size)
     * {x}o{y} (portrait or horizontal)
     *
     * @param type $size Size, for example x100, y100, 800x600, 800o600
     * @return type
     */
    public function parseSize($size)
    {
        if (ctype_alnum($size)) {
            if (preg_match('/^([0-9]{1,4})x([0-9]{1,4})$/', $size, $output)) {
                return (object) [
                    'raw' => $output[0],
                    'method' => '{x}x{y}',
                    'x' => intval($output[1]),
                    'y' => intval($output[2]),
                ];
            } elseif (preg_match('/^([0-9]{1,4})o([0-9]{1,4})$/', $size, $output)) {
                return (object) [
                    'raw' => $output[0],
                    'method' => '{x}o{y}',
                    'x' => intval($output[1]),
                    'y' => intval($output[2]),
                ];
            } elseif (preg_match('/^x([0-9]{1,4})$/', $size, $output)) {
                return (object) [
                    'raw' => $output[0],
                    'method' => 'x{x}',
                    'x' => intval($output[1]),
                ];
            } elseif (preg_match('/^y([0-9]{1,4})$/', $size, $output)) {
                return (object) [
                    'raw' => $output[0],
                    'method' => 'y{y}',
                    'y' => intval($output[1]),
                ];
            } else {
                dd('Illegal size. Supported: {x}x{y},{x}o{y},x{x},y{y}');
            }
        } else {
            abort(403, 'Illegal size.');
        }
    }

    public function todo($method_name)
    {
        if (method_exists($this, $method_name)) {
            $this->todo[] = $method_name;
        }
    }

    public function apply()
    {
        if (empty($this->todo)) {
            return false;
        }
        foreach ($this->todo as $method_name) {
            $method_name = camel_case('apply'.ucfirst($method_name));
            if (method_exists($this, $method_name)) {
                $this->$method_name();
            }
        }
    }

    /**
     * Apply resize filter.
     *
     * @return void
     */
    public function applyResize()
    {
        $this->resize();
    }

    /**
     * Apply watermark filter.
     *
     * @return void
     */
    public function applyWatermark()
    {
        $this->addWatermark();
    }

    /**
     * Set or get watermark path.
     *
     * @param string|null $path File path or null
     * @return this|string
     */
    public function watermarkPath($path = null)
    {
        if (is_null($path)) {
            if (isset($this->watermark_path)) {
                return $this->watermark_path;
            }
            return null;
        }
        $this->watermark_path = $path;
    }

    /**
     * Include watermark in thumbnail.
     *
     * @param type|null $path Path to watermark
     * @return this
     */
    public function watermark($path = null)
    {
        if (is_null($path)) {
            $this->watermarkPath(config('images.watermark_path'));
        }
        $this->watermarkPath($path);
        $this->todo('watermark');
        return $this;
    }

    /**
     * This function calculate what should be the thumbnail size.
     *
     * @param string $width Width in %
     * @param string $height Height in %
     * @param \Intervention\Image\Facades\Image $watermark Handle to watermark
     * @param \Intervention\Image\Facades\Image $handle Handle to image
     * @return object Object with width and height
     */
    public function calculateWatermarkResolution($width, $height, $watermark, $handle)
    {
        $width = (int) str_replace('%', '', $width) / 100;
        $height = (int) str_replace('%', '', $height) / 100;

        $width_px = (int) round($handle->width() * $width);
        $height_px = (int) round($handle->height() * $height);

        return (object)[
            'width' => $width_px,
            'height' => $height_px
        ];
    }

    /**
     * Add watermark to image. Opacity should be set in watermark file.
     *
     * @param string $watermark_path Path to watermark image file
     * @return this
     */
    public function addWatermark($watermark_path = null)
    {
        if (is_null($watermark_path)) {
            $watermark_path = $this->watermarkPath();
        }
        $watermark = \Intervention\Image\Facades\Image::make($watermark_path);
        $watermark_width = config('images.watermark_width');
        $watermark_height = config('images.watermark_height');

        /* % to px */
        $watermark_resolution = $this->calculateWatermarkResolution(
            $watermark_width,
            $watermark_height,
            $watermark,
            $this->handle()
        );

        /* fit watermark */
        $watermark->resize($watermark_resolution->width, $watermark_resolution->height, function ($constraint) {
            $constraint->aspectRatio();
        });

        $this->handle()->insert($watermark, config('images.watermark_position'));
    }

    /**
     * Make image.intervention.io image object.
     *
     * @param string $image According to make() from image.intervention.io
     * @return \Intervention\Image\Facades\Image
     */
    public function makeImage($image)
    {
        return \Intervention\Image\Facades\Image::make($image);
    }

    /**
     * Get image handle (http://image.intervention.io/).
     *
     * @return \Intervention\Image\Facades\Image
     */
    public function handle($handle = null)
    {
        if (!is_null($handle)) {
            $this->handle_attribute = $handle;
        }
        if (isset($this->handle_attribute)) {
            return $this->handle_attribute;
        }
        return $this->handle($this->loadImageHandle());
    }

    /**
     * Load image handle from cached or original file.
     *
     * @return \Intervention\Image\Facades\Image
     */
    public function loadImageHandle()
    {
        if (method_exists($this, 'cacheable') && $this->cacheable()) {
            return $this->getHandleFromCache();
        }

        return $this->makeImage($this->getOriginalImageFile());
    }

    /**
     * Generate laravel response.
     *
     * @param type $path File path
     * @param string $extension Extension, jpg by default
     * @return response
     */
    public function response($extension = null)
    {
        if (!is_null($extension)) {
            $this->extension($extension);
        }
        if (method_exists($this, 'cacheable') && $this->cacheable()) {
            if (!$this->cached()) {
                $this->apply();
                $this->cache($this->handle());
            }
            $age = config('images.cache_age', '86400');
            return $this->cached()
                        ->response($extension)
                        ->setLastModified(new \DateTime("now"))
                        ->setExpires(new \DateTime("@".(time() + $age)));
        }
        $this->apply();
        return $this->handle()->response($extension);
    }
}
