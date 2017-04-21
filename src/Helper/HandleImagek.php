<?php

namespace Xcs\Helper;

class HandleImagek {

    protected $_handle = null;

    public function __construct($handle) {
        $this->_handle = $handle;
    }

    public function __destruct() {
        $this->destroy();
    }

    private function destroy() {
        if (!$this->_handle) {
            $this->_handle->destroy();
        }
        $this->_handle = null;
    }

    /*
      函数说明：切割图片
      参数:
     */

    public function crop($width, $height, $options = array()) {
        if (is_null($this->_handle)) {
            return $this;
        }
        $default_options = array(
            'bestfit' => true,
            'fill' => false
        );
        $options = array_merge($default_options, $options);
        $this->_handle->thumbnailImage($width, $height, $options['bestfit'], $options['fill']);
    }

    public function watermark($waterImage, $pos = 5, $Opacity = 0.2) {
        $water = new \Imagick($waterImage);
        $water->setImageOpacity($Opacity);
        $dw = new \ImagickDraw();
        $dw->setGravity($pos);
        $dw->composite($water->getImageCompose(), 0, 0, 50, 0, $water);
        $this->_handle->drawImage($dw);
        $water->destroy();
        $dw->destroy();
    }

    /*
      函数说明：切割图片
      参数: $filepath:生成的缩略图位置 ,string
     */

    public function saveAsJpeg($filepath) {
        $this->_handle->writeImage($filepath);
    }

    /*
      利用Imagick模块处理图像的方法
     */

    /*
      函数说明：对比度处理
      函数参数：
      $type:表示增加或减少对比度,逻辑型,true:增加; false:减少
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图片位置,string型
      $dst:处理后的目标图片存储位置,string型
     */

    public function contrast($type, $apply, $w = 0, $h = 0, $x = 0, $y = 0) {
        if ($type) {
            $s = 9;
        } else {
            $s = 0;
        }
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->contrastImage($s);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->contrastImage($s);
        }
    }

    /*
      函数说明：将字母和数字生成png图片
      函数参数：
      $text:需要生成图片的文字,string型
      $color:文字颜色,string型
      $szie:文字大小,int型
      $font:字体,string型
      $type:返回类型,逻辑型,true:返回图片地址; false:返回图片资源
      $src:保存图片的地址,string型
     */

    public function text($text, $color, $size, $font = 'FetteSteinschrift') {
        $font = APPPATH . "vendor/captcha/fonts/en/" . $font . ".ttf";
        $draw = new \ImagickDraw();
        $draw->setGravity(\Imagick::GRAVITY_CENTER);
        $draw->setFont($font);
        $draw->setFontSize($size);
        $draw->setFillColor(new \ImagickPixel($color));

        $im = new \imagick();
        $properties = $im->queryFontMetrics($draw, $text);
        $im->newImage(intval($properties['textWidth'] + 5), intval($properties['textHeight'] + 5), new \ImagickPixel('transparent'));
        $im->setImageFormat('png');
        $im->annotateImage($draw, 0, 0, 0, $text);
        return $im;
    }

    /*
      函数说明：加水印
      函数参数：
      $text:水印文字,string型
      $color:文字颜色,string型
      $szie:文字大小,int型
      $font:字体,string型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $x,$y:水印位置,int型
     */

    public function mark($text, $color, $size, $font, $x, $y) {
        $im = $this->text($text, $color, $size, $font);
        $this->_handle->compositeImage($im, \Imagick::COMPOSITE_OVER, $x, $y);
        $im->destroy();
    }

    /*
      函数说明：模糊处理
      函数参数：
      $radius:模糊程度,int型
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function gaussianblur($radius, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->blurImage($radius, $radius);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->blurImage($radius, $radius);
        }
    }

    /*
      函数说明：锐化处理
      函数参数：
      $radius:锐化程度,int型
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function sharpen($radius, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->sharpenImage($radius, $radius);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->sharpenImage($radius, $radius);
        }
    }

    /*
      函数说明：突起效果
      函数参数：
      $raise:突起度,int型
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function raise($raise, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        if ($apply) {
            if ($w > (2 * $raise) && $h > (2 * $raise)) {
                $region = $this->_handle->getImageRegion($w, $h, $x, $y);
                $region->raiseImage($raise, $raise, 0, 0, true);
                $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
                $region->destroy();
            }
        } else {
            $info = $this->_handle->getImageGeometry();
            if ($info["width"] > (2 * $raise) && $info["height"] > (2 * $raise)) {
                $this->_handle->raiseImage($raise, $raise, 0, 0, true);
            }
        }
    }

    /*
      函数说明：边框效果
      函数参数：
      $frame_width:边框宽度,int型
      $frame_height:边框宽度,int型
      $bevel:边框角度,int型
      $color:边框颜色,string型
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function frame($frame_width, $frame_height, $bevel, $color, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        $framecolor = new \ImagickPixel($color);
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->frameImage($framecolor, $frame_width, $frame_height, $bevel, $bevel);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->frameImage($framecolor, $frame_width, $frame_height, $bevel, $bevel);
        }
        $framecolor->destroy();
    }

    /*
      函数说明：油画效果
      函数参数：
      $radius:油画效果参数
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function oilpaint($radius, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->oilPaintImage($radius);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->oilPaintImage($radius);
        }
    }

    /*
      函数说明：发散效果
      函数参数：
      $radius:发散效果参数
      $apply:表示作用区域,逻辑型,true:局部作用; false:全局作用
      $w,$h,$x,$y:当$apply为true,来确定区域坐标,int型
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function spread($radius, $apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }

        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $region->spreadImage($radius);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $this->_handle->spreadImage($radius);
        }
    }

    /*
      函数说明：倾斜效果
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $color:背景颜色,string型
      $angle:倾斜角度,int型
     */

    public function polaroidEffect($src, $color, $angle = 0) {
        if (15 != abs($angle)) {
            $srcs = array($src, $src, $src, $src);
            $bg = new \ImagickDraw();
            $images = new \Imagick($srcs);
            $format = $images->getImageFormat();

            $maxwidth = 0;
            $maxheight = 0;

            foreach ($images as $key => $im) {
                $im->setImageFormat("png");
                $im->setImageBackgroundColor(new \ImagickPixel("black"));

                $angle = mt_rand(-20, 20);
                if ($angle == 0) {
                    $angle = -1;
                }

                $im->polaroidImage($bg, $angle);
                $info = $im->getImageGeometry();

                $maxwidth = max($maxwidth, $info["width"]);
                $maxheight = max($maxheight, $info["height"]);
            }
            $image = new \Imagick();
            $image->newImage($maxwidth, $maxheight, new \ImagickPixel($color));

            foreach ($images as $key => $im) {
                $image->compositeImage($im, $im->getImageCompose(), 0, 0);
            }
            $image->setImageFormat($format);
            $bg->destroy();
            $images->destroy();
        } else {
            $image = new \Imagick($src);
            $format = $image->getImageFormat();
            $image->frameImage(new \ImagickPixel("white"), 6, 6, 0, 0);
            $image->frameImage(new \ImagickPixel("gray"), 1, 1, 0, 0);
            $image->setImageFormat("png");
            $shadow = $image->clone();
            $shadow->setImageBackgroundColor(new \ImagickPixel("black"));
            $shadow->shadowImage(50, 3, 0, 0);
            $shadow->compositeImage($image, $image->getImageCompose(), 0, 0);

            $shadow->rotateImage(new \ImagickPixel($color), $angle);
            $info = $shadow->getImageGeometry();

            $image->destroy();
            $image = new \Imagick();
            $image->newImage($info["width"], $info["height"], new \ImagickPixel($color));
            $image->compositeImage($shadow, $shadow->getImageCompose(), 0, 0);
            $image->setImageFormat($format);
            $shadow->destroy();
        }
        $this->_handle = $image;
    }

    /*
      函数说明：生成手绘图片
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $color:画笔背景颜色,string型
      $size:画笔尺寸,int型
      $brushpath:画笔轨迹,array型
     */

    public function brushpng($color, $size, $brushpath) {
        $info = $this->_handle->getImageGeometry();
        $image = new \Imagick();
        $image->newImage($info["width"], $info["height"], "transparent", "png");
        //$image->setImageFormat("png");
        $draw = new \ImagickDraw();
        $pixel = new \ImagickPixel();
        $pixel->setColor("transparent");
        $draw->setFillColor($pixel);
        $pixel->setColor($color);
        $draw->setStrokeColor($pixel);
        $draw->setStrokeWidth($size);
        $draw->setStrokeLineCap(\imagick::LINECAP_ROUND);
        $draw->setStrokeLineJoin(\imagick::LINEJOIN_ROUND);
        $draw->polyline($brushpath);

        $image->drawImage($draw);
        $pixel->destroy();
        $draw->destroy();
        $this->_handle = $image;
    }

    /*
      函数说明：合并图片
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $png:需要合并的png图片地址,string型
     */

    public function dobrush($png) {
        if (is_file($png)) {
            $imagepng = new \Imagick($png);
            $imagepng->setImageFormat("png");
            $this->_handle->compositeImage($imagepng, $imagepng->getImageCompose(), 0, 0);
            $imagepng->destroy();
        }
    }

    /*
      函数说明：旋转图片
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $angle:旋转角度,int型
     */

    public function rotate($angle) {
        $this->_handle->rotateImage(new \ImagickPixel(), $angle);
    }

    /*
      函数说明：图片亮度处理
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $n:亮度比,float型
      $s_x,$s_y,$e_x,$e_y:起始点和结束点,int型
      $type:true表示存储图片,false表示返回处理后的Imagick对象
     */

    public function brightness($n, $s_x = 0, $e_x = 0, $s_y = 0, $e_y = 0) {
        $info = $this->_handle->getImageGeometry();
        $w = $info["width"];
        $h = $info["height"];
        $format = $this->_handle->getImageFormat();

        if ($s_x == 0 && $s_y == 0 && $e_x == 0 && $e_y == 0) {
            $e_x = $w;
            $e_y = $h;
        }

        $image = new \Imagick();
        $image->newImage($w, $h, "transparent");

        $draw = new \ImagickDraw();

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $p = $image->getImagePixelColor($x, $y);
                $rgb = $p->getColor();
                if ($x >= $s_x && $x < $e_x && $y >= $s_y && $y < $e_y) {
                    $rgb["r"] = $rgb["r"] + $rgb["r"] * $n;
                    $rgb["g"] = $rgb["g"] + $rgb["g"] * $n;
                    $rgb["b"] = $rgb["b"] + $rgb["b"] * $n;

                    $rgb["r"] = min(255, $rgb["r"]);
                    $rgb["r"] = max(0, $rgb["r"]);
                    $rgb["g"] = min(255, $rgb["g"]);
                    $rgb["g"] = max(0, $rgb["g"]);
                    $rgb["b"] = min(255, $rgb["b"]);
                    $rgb["b"] = max(0, $rgb["b"]);
                }
                $p->setColor("rgb({$rgb["r"]},{$rgb["g"]},{$rgb["b"]})");
                $draw->setFillColor($p);
                $draw->point($x, $y);
            }
        }

        $image->drawImage($draw);
        $image->setImageFormat($format);
        $this->_handle = $image;
        $draw->destroy();
    }

    /*
      函数说明：图片灰度处理
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
     */

    public function grayscale($apply, $x = 0, $y = 0, $w = 0, $h = 0) {
        if ($apply && $x == 0 && $y == 0 && $w == 0 && $h == 0) {
            $apply = false;
        }
        if ($apply) {
            $region = $this->_handle->getImageRegion($w, $h, $x, $y);
            $clone = $region->clone();
            $clone = $region->fximage('p{0,0}');
            $region->compositeImage($clone, \imagick::COMPOSITE_DIFFERENCE, 0, 0);
            $region->modulateImage(100, 0, 0);
            $this->_handle->compositeImage($region, $region->getImageCompose(), $x, $y);
            $region->destroy();
        } else {
            $clone = $this->_handle->clone();
            $clone = $this->_handle->clone();
            $clone = $this->_handle->fximage('p{0,0}');
            $this->_handle->compositeImage($clone, \imagick::COMPOSITE_DIFFERENCE, 0, 0);
            $this->_handle->modulateImage(100, 0, 0);
        }
        $clone = null;
        $this->_handle->clear();
    }

    /*
      函数说明：jpg质量压缩
      参数说明：
      $src:原图地址,string型
      $dst:保存图片的地址,string型
      $q:压缩比率
      此函数在安全模式下不能运行
     */

    public function prequality($src, $dst, $q) {
        exec("convert -quality {$q} {$src} {$dst}");
    }

}