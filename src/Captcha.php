<?php

namespace Spliff\Utils;

use Exception;

class Captcha
{
    private const PERMITTED_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private static Captcha $instance;

    public string $doNotAskTime;

    public array $fonts;

    public int $length;

    public function newInstance(): Captcha
    {
        if (empty(self::$instance)) {
            $c = new Captcha;
            $c->length = 5;
            $c->doNotAskTime = '5 min';
            $c->DefaultFonts();
            self::$instance = $c;
        }

        return self::$instance;
    }

    public function DefaultFonts(): Captcha
    {
        $path = __DIR__.'/../fonts/';
        $fs = array_values(array_diff(scandir($path), ['..', '.']));
        foreach ($fs as $f) {
            $this->AddFont($path.$f);
        }

        return $this;
    }

    public function AddFont(string $file): Captcha
    {
        // $fileSize = filesize($file);
        if (! file_exists($file)) {
            throw new CaptchaException('font not present at '.$file);
        }
        $mimeType = mime_content_type($file);
        if (! in_array($mimeType, ['application/x-font-ttf', 'font/ttf', 'font/sfnt'])) {
            throw new CaptchaException('attempted to load non-ttf font');
        }
        $this->fonts[] = $file;

        return $this;
    }

    public function Image(string $permitted = ''): string
    {
        $str = $this->genString($this->length, $permitted);
        $_SESSION['captcha_text'] = $str;

        return $this->getImage($str);
    }

    private function getImage(string $captcha): string
    {
        if (empty($captcha)) {
            throw new CaptchaException('captcha can not be empty');
        }
        if (empty($this->fonts)) {
            throw new CaptchaException('no fonts loaded');
        }
        $image = imagecreatetruecolor(200, 50);
        imageantialias($image, true);
        $colors = [];
        $red = rand(100, 165);
        $green = rand(100, 165);
        $blue = rand(100, 165);

        for ($i = 0; $i < 5; $i++) {
            $colors[] = imagecolorallocate($image, $red - 15 * $i, $green - 15 * $i, $blue - 15 * $i);
        }
        // fill bg
        imagefill($image, 0, 0, $colors[0]);

        for ($i = 0; $i < 10; $i++) {
            imagesetthickness($image, rand(2, 10));
            $line_color = $colors[rand(1, 4)];
            imagerectangle($image, rand(-10, 190), rand(-10, 10), rand(-10, 190), rand(40, 60), $line_color);
        }
        $fgColor = rand(0, 30);
        $bgColor = rand(230, 255);
        $textColors = [
            imagecolorallocate($image, $fgColor, $fgColor, $fgColor),
            imagecolorallocate($image, $bgColor, $bgColor, $bgColor),
        ];
        for ($i = 0; $i < strlen($captcha); $i++) {
            $letter_space = 170 / strlen($captcha);
            $initial = 15;
            imagettftext($image, 24, rand(-15, 15),
                $initial + $i * $letter_space, rand(25, 45),
                $textColors[rand(0, 1)], $this->fonts[array_rand($this->fonts)], $captcha[$i]);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();

        imagedestroy($image);

        return $imageData;
    }

    public function hasPassed(): bool
    {
        if (
            isset($_SESSION['captcha_valid_till'])
            && (strtotime($_SESSION['captcha_valid_till']) >= time())
        ) {
            return true;
        }
        if (isset($_POST['captcha_challenge'])
            && $_POST['captcha_challenge'] == $_SESSION['captcha_text']) {
            unset($_SESSION['captcha_text']);
            $_SESSION['captcha_valid_till'] = strtotime(date('Y-m-d H:i:s').' +'.$this->doNotAskTime);

            return true;
        }

        return false;
    }

    private function genString(int $len = 5, string $input = ''): string
    {
        if (empty($input)) {
            $input = PERMITTED_CHARS;
        }

        return substr(str_shuffle(str_repeat($input, ceil($len / strlen($input)))), 1, $len);
    }
}

class CaptchaException extends Exception {}
