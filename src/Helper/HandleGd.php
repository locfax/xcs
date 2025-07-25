<?php

namespace Xcs\Helper;

class HandleGd
{

    protected $_handle;

    public function __construct($handle)
    {
        $this->_handle = $handle;
    }

    public function __destruct()
    {
        $this->destroy();
    }

    /**
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function resize(int $width, int $height): HandleGd
    {
        //低质量
        if (is_null($this->_handle)) {
            return $this;
        }
        $dest = imagecreatetruecolor($width, $height);
        imagecopyresized($dest, $this->_handle, 0, 0, 0, 0, $width, $height, imagesx($this->_handle), imagesy($this->_handle));
        imagedestroy($this->_handle);
        $this->_handle = $dest;
        return $this;
    }

    /**
     * @param int $width
     * @return $this
     */
    public function autoresize(int $width): HandleGd
    {
        if (is_null($this->_handle)) {
            return $this;
        }

        $full_w = imagesx($this->_handle);
        $full_h = imagesy($this->_handle);

        if ($width >= $full_w) {
            $_width = $full_w;
            $_height = $full_h;
        } else {
            $_width = $width;
            $ratio = doubleval($width) / doubleval($full_w);
            $_height = $full_h * $ratio;
        }

        $dest = imagecreatetruecolor($_width, $_height);
        imagecopyresampled($dest, $this->_handle, 0, 0, 0, 0, $_width, $_height, $full_w, $full_h);
        imagedestroy($this->_handle);
        $this->_handle = $dest;
        return $this;
    }

    /**
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function resampled(int $width, int $height): HandleGd
    {
        //高质量
        if (is_null($this->_handle)) {
            return $this;
        }
        $dest = imagecreatetruecolor($width, $height);
        imagecopyresampled($dest, $this->_handle, 0, 0, 0, 0, $width, $height, imagesx($this->_handle), imagesy($this->_handle));
        imagedestroy($this->_handle);
        $this->_handle = $dest;
        return $this;
    }

    /**
     * @param int $width
     * @param int $height
     * @param string $pos
     * @param string $bgcolor
     * @return $this
     */
    public function canvas(int $width, int $height, string $pos = 'center', string $bgcolor = '0xffffff'): HandleGd
    {
        if (is_null($this->_handle)) {
            return $this;
        }
        $dest = imagecreatetruecolor($width, $height);
        $sx = imagesx($this->_handle);
        $sy = imagesy($this->_handle);

        switch (strtolower($pos)) {
            case 'left':
                $ox = 0;
                $oy = ($height - $sy) / 2;
                break;
            case 'right':
                $ox = $width - $sx;
                $oy = ($height - $sy) / 2;
                break;
            case 'top':
                $ox = ($width - $sx) / 2;
                $oy = 0;
                break;
            case 'bottom':
                $ox = ($width - $sx) / 2;
                $oy = $height - $sy;
                break;
            case 'top-left':
            case 'left-top':
                $ox = $oy = 0;
                break;
            case 'top-right':
            case 'right-top':
                $ox = $width - $sx;
                $oy = 0;
                break;
            case 'bottom-left':
            case 'left-bottom':
                $ox = 0;
                $oy = $height - $sy;
                break;
            case 'bottom-right':
            case 'right-bottom':
                $ox = $width - $sx;
                $oy = $height - $sy;
                break;
            default:
                $ox = ($width - $sx) / 2;
                $oy = ($height - $sy) / 2;
        }

        list ($r, $g, $b) = ImageGd::hex2rgb($bgcolor, '0xffffff');
        $bgcolor = imagecolorallocate($dest, $r, $g, $b);
        imagefilledrectangle($dest, 0, 0, $width, $height, $bgcolor);
        imagecolordeallocate($dest, $bgcolor);

        imagecopy($dest, $this->_handle, $ox, $oy, 0, 0, $sx, $sy);
        imagedestroy($this->_handle);
        $this->_handle = $dest;

        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function cut(array $options = []): HandleGd
    {
        if (is_null($this->_handle)) {
            return $this;
        }
        $default_options = [
            'pos' => ['lux' => 0, 'luy' => 0, 'ldx' => 0, 'ldy' => 100, 'rux' => 100, 'rdx' => 100, 'rdy' => 100],
            'bgcolor' => '0xfff',
            'border' => 0
        ];
        $options = array_merge($default_options, $options);

        $dst_w = round($options['pos']['rux'] - $options['pos']['lux']);
        $dst_h = round($options['pos']['ldy'] - $options['pos']['luy']);

        //for image border
        if ($options['border']) {
            $bgw = round($options['pos']['rux'] - $options['pos']['lux']) + $options['border'] * 2;
            $bgh = round($options['pos']['ldy'] - $options['pos']['luy']) + $options['border'] * 2;
            $dest = imagecreatetruecolor($bgw, $bgh);
            list ($r, $g, $b) = ImageGd::hex2rgb($options['bgcolor'], '0xffffff');
            $bgcolor = imagecolorallocate($dest, $r, $g, $b);
            imagefilledrectangle($dest, 0, 0, $bgw, $bgh, $bgcolor);
        } else {
            //for no background images
            $dest = imagecreatetruecolor($dst_w, $dst_h);
            list ($r, $g, $b) = ImageGd::hex2rgb($options['bgcolor'], '0xffffff');
            $bgcolor = imagecolorallocate($dest, $r, $g, $b);
            imagefilledrectangle($dest, 0, 0, $dst_w, $dst_h, $bgcolor);
        }
        imagecolordeallocate($dest, $bgcolor);

        $dst_x = $options['border'];
        $dst_y = $options['border'];
        $src_x = round($options['pos']['lux']);
        $src_y = round($options['pos']['luy']);
        $full_w = imagesx($this->_handle);
        $full_h = imagesy($this->_handle);

        imagecopyresampled($dest, $this->_handle, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $full_w, $full_h);
        imagedestroy($this->_handle);
        $this->_handle = $dest;

        return $this;
    }

    /**
     * @param int $width
     * @param int $height
     * @param array $options
     * @return $this
     */
    public function crop(int $width, int $height, array $options = []): HandleGd
    {
        if (is_null($this->_handle)) {
            return $this;
        }
        $default_options = [
            'fullimage' => true,
            'pos' => 'center',
            'bgcolor' => '0xfff',
            'enlarge' => true,
            'reduce' => true,
            'border' => 0
        ];
        $options = array_merge($default_options, $options);
        $dest = imagecreatetruecolor($width, $height);

        list ($r, $g, $b) = ImageGd::hex2rgb($options['bgcolor'], '0xffffff');
        $bgcolor = imagecolorallocate($dest, $r, $g, $b);
        imagefilledrectangle($dest, 0, 0, $width, $height, $bgcolor);
        imagecolordeallocate($dest, $bgcolor);

        $full_w = imagesx($this->_handle);
        $full_h = imagesy($this->_handle);
        $ratio_w = doubleval($width) / doubleval($full_w);
        $ratio_h = doubleval($height) / doubleval($full_h);

        if ($options['fullimage']) {
            $ratio = min($ratio_w, $ratio_h);
        } else {
            $ratio = max($ratio_w, $ratio_h);
        }

        if (!$options['enlarge'] && $ratio > 1) {
            $ratio = 1;
        }
        if (!$options['reduce'] && $ratio < 1) {
            $ratio = 1;
        }

        $dst_w = $full_w * $ratio;
        $dst_h = $full_h * $ratio;

        switch (strtolower($options['pos'])) {
            case 'left':
                $dst_x = 0;
                $dst_y = ($height - $dst_h) / 2;
                break;
            case 'right':
                $dst_x = $width - $dst_w;
                $dst_y = ($height - $dst_h) / 2;
                break;
            case 'top':
                $dst_x = ($width - $dst_w) / 2;
                $dst_y = 0;
                break;
            case 'bottom':
                $dst_x = ($width - $dst_w) / 2;
                $dst_y = $height - $dst_h;
                break;
            case 'top-left':
            case 'left-top':
                $dst_x = $dst_y = 0;
                break;
            case 'top-right':
            case 'right-top':
                $dst_x = $width - $dst_w;
                $dst_y = 0;
                break;
            case 'bottom-left':
            case 'left-bottom':
                $dst_x = 0;
                $dst_y = $height - $dst_h;
                break;
            case 'bottom-right':
            case 'right-bottom':
                $dst_x = $width - $dst_w;
                $dst_y = $height - $dst_h;
                break;
            case 'center':
            default:
                $dst_x = ($width - $dst_w) / 2;
                $dst_y = ($height - $dst_h) / 2;
        }

        imagecopyresampled($dest, $this->_handle, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $full_w, $full_h);
        imagedestroy($this->_handle);
        $this->_handle = $dest;

        return $this;
    }

    /**
     * @param string $filename
     * @param int $quality
     * @return void
     */
    public function saveAsJpeg(string $filename, int $quality = 80): void
    {
        imagejpeg($this->_handle, $filename, $quality);
    }

    /**
     * @param string $filename
     * @return void
     */
    public function saveAsPng(string $filename): void
    {
        imagepng($this->_handle, $filename);
    }

    /**
     * @param string $filename
     * @return void
     */
    function saveAsGif(string $filename): void
    {
        imagegif($this->_handle, $filename);
    }

    function destroy(): void
    {
        if (!$this->_handle) {
            imagedestroy($this->_handle);
        }
        $this->_handle = null;
    }


    /**
     * @param string $fname
     * @return false|\GdImage|resource
     */
    function imageCreateFromBmp(string $fname)
    {
        $buf = file_get_contents($fname);
        if (strlen($buf) < 54) {
            return false;
        }
        $file_header = unpack("sbfType/LbfSize/sbfReserved1/sbfReserved2/LbfOffBits", substr($buf, 0, 14));

        if (19778 != $file_header["bfType"]) {
            return false;
        }
        $info_header = unpack("LbiSize/lbiWidth/lbiHeight/sbiPlanes/sbiBitCountLbiCompression/LbiSizeImage/lbiXPelsPerMeter/lbiYPelsPerMeter/LbiClrUsed/LbiClrImportant", substr($buf, 14, 40));
        if ($info_header["biBitCountLbiCompression"] == 2) {
            return false;
        }
        $line_len = round($info_header["biWidth"] * $info_header["biBitCountLbiCompression"] / 8);
        $x = $line_len % 4;
        if ($x > 0) {
            $line_len += 4 - $x;
        }
        $img = imagecreatetruecolor($info_header["biWidth"], $info_header["biHeight"]);
        switch ($info_header["biBitCountLbiCompression"]) {
            case 4:
                $colorset = unpack("L*", substr($buf, 54, 64));
                for ($y = 0; $y < $info_header["biHeight"]; $y++) {
                    $colors = [];
                    $y_pos = $y * $line_len + $file_header["bfOffBits"];
                    for ($x = 0; $x < $info_header["biWidth"]; $x++) {
                        if ($x % 2)
                            $colors[] = $colorset[(ord($buf[$y_pos + ($x + 1) / 2]) & 0xf) + 1];
                        else
                            $colors[] = $colorset[((ord($buf[$y_pos + $x / 2 + 1]) >> 4) & 0xf) + 1];
                    }
                    imagesetstyle($img, $colors);
                    imageline($img, 0, $info_header["biHeight"] - $y - 1, $info_header["biWidth"], $info_header["biHeight"] - $y - 1, IMG_COLOR_STYLED);
                }
                break;
            case 8:
                $colorset = unpack("L*", substr($buf, 54, 1024));
                for ($y = 0; $y < $info_header["biHeight"]; $y++) {
                    $colors = [];
                    $y_pos = $y * $line_len + $file_header["bfOffBits"];
                    for ($x = 0; $x < $info_header["biWidth"]; $x++) {
                        $colors[] = $colorset[ord($buf[$y_pos + $x]) + 1];
                    }
                    imagesetstyle($img, $colors);
                    imageline($img, 0, $info_header["biHeight"] - $y - 1, $info_header["biWidth"], $info_header["biHeight"] - $y - 1, IMG_COLOR_STYLED);
                }
                break;
            case 16:
                for ($y = 0; $y < $info_header["biHeight"]; $y++) {
                    $colors = [];
                    $y_pos = $y * $line_len + $file_header["bfOffBits"];
                    for ($x = 0; $x < $info_header["biWidth"]; $x++) {
                        $i = $x * 2;
                        $color = ord($buf[$y_pos + $i]) | (ord($buf[$y_pos + $i + 1]) << 8);
                        $colors[] = imagecolorallocate($img, (($color >> 10) & 0x1f) * 0xff / 0x1f, (($color >> 5) & 0x1f) * 0xff / 0x1f, ($color & 0x1f) * 0xff / 0x1f);
                    }
                    imagesetstyle($img, $colors);
                    imageline($img, 0, $info_header["biHeight"] - $y - 1, $info_header["biWidth"], $info_header["biHeight"] - $y - 1, IMG_COLOR_STYLED);
                }
                break;
            case 24:
                for ($y = 0; $y < $info_header["biHeight"]; $y++) {
                    $colors = [];
                    $y_pos = $y * $line_len + $file_header["bfOffBits"];
                    for ($x = 0; $x < $info_header["biWidth"]; $x++) {
                        $i = $x * 3;
                        $colors[] = imagecolorallocate($img, ord($buf[$y_pos + $i + 2]), ord($buf[$y_pos + $i + 1]), ord($buf[$y_pos + $i]));
                    }
                    imagesetstyle($img, $colors);
                    imageline($img, 0, $info_header["biHeight"] - $y - 1, $info_header["biWidth"], $info_header["biHeight"] - $y - 1, IMG_COLOR_STYLED);
                }
                break;
            default:
                return false;
        }
        return $img;
    }
}