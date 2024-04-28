<?php

namespace Xcs\Helper;

class Html
{

    /**
     * @param string $name
     * @param array $arr
     * @param mixed $selected
     * @param mixed $extra
     * @return string
     */
    public static function dropdown_list(string $name, array $arr, mixed $selected = null, mixed $extra = null): string
    {
        $str = "<select name=\"{$name}\" {$extra} >\n";
        foreach ($arr as $value => $title) {
            $str .= '<option value="' . htmlspecialchars($value) . '"';
            if ($selected == $value) {
                $str .= ' selected';
            }
            $str .= '>' . htmlspecialchars($title) . "&nbsp;&nbsp;</option>\n";
        }
        $str .= "</select>\n";
        return $str;
    }

    /**
     * @param string $name
     * @param array $arr
     * @param mixed $checked
     * @param string $separator
     * @param mixed|null $extra
     * @return string
     */
    public static function radio_group(string $name, array $arr, mixed $checked = null, string $separator = '', mixed $extra = null): string
    {
        $ix = 0;
        $str = "";
        foreach ($arr as $value => $title) {
            $value_h = htmlspecialchars($value);
            $title = htmlspecialchars($title);
            $str .= "<input name=\"{$name}\" type=\"radio\" id=\"{$name}_{$ix}\" value=\"{$value_h}\" ";
            if ($value == $checked) {
                $str .= "checked=\"checked\"";
            }
            $str .= " {$extra} />";
            $str .= "<label for=\"{$name}_{$ix}\">{$title}</label>";
            $str .= $separator;
            $ix++;
            $str .= "\n";
        }
        return $str;
    }

    /**
     * @param string $name
     * @param array $arr
     * @param array $selected
     * @param string $separator
     * @param mixed|null $extra
     * @return string
     */
    public static function checkbox_group(string $name, array $arr, array $selected = [], string $separator = '', mixed $extra = null): string
    {
        $ix = 0;
        if (!is_array($selected)) {
            $selected = [$selected];
        }
        $str = "";
        foreach ($arr as $value => $title) {
            $value_h = htmlspecialchars_decode($value);
            $title = htmlspecialchars_decode($title);
            $str .= "<input name=\"{$name}[]\" type=\"checkbox\" id=\"{$name}_{$ix}\" value=\"{$value_h}\" ";
            if (in_array($value, $selected)) {
                $str .= "checked=\"checked\"";
            }
            $str .= " {$extra} />";
            $str .= "<label for=\"{$name}_{$ix}\">{$title}</label>";
            $str .= $separator;
            $ix++;
            $str .= "\n";
        }
        return $str;
    }

    /**
     * @param string $name
     * @param int $value
     * @param bool $checked
     * @param string $label
     * @param mixed|null $extra
     * @return string
     */
    public static function checkbox(string $name, int $value = 1, bool $checked = false, string $label = '', mixed $extra = null): string
    {
        $str = "<input name=\"{$name}\" type=\"checkbox\" id=\"{$name}_1\" value=\"{$value}\"";
        if ($checked) {
            $str .= " checked";
        }
        $str .= " {$extra} />\n";
        if ($label) {
            $str .= "<label for=\"{$name}_1\">" . htmlspecialchars($label) . "</label>\n";
        }
        return $str;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int|null $width
     * @param int|null $maxLength
     * @param mixed $extra
     * @return string
     */
    public static function textBox(string $name, string $value = '', int $width = null, int $maxLength = null, mixed $extra = null): string
    {
        $str = "<input name=\"{$name}\" type=\"text\" value=\"" . htmlspecialchars($value) . "\" ";
        if ($width) {
            $str .= "size=\"{$width}\" ";
        }
        if ($maxLength) {
            $str .= "maxlength=\"{$maxLength}\" ";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int|null $width
     * @param int|null $maxLength
     * @param mixed $extra
     * @return string
     */
    public static function password(string $name, string $value = '', int $width = null, int $maxLength = null, mixed $extra = null): string
    {
        $str = "<input name=\"{$name}\" type=\"password\" value=\"" . htmlspecialchars($value) . "\" ";
        if ($width) {
            $str .= "size=\"{$width}\" ";
        }
        if ($maxLength) {
            $str .= "maxlength=\"{$maxLength}\" ";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int|null $width
     * @param int|null $height
     * @param mixed $extra
     * @return string
     */
    public static function textarea(string $name, string $value = '', int $width = null, int $height = null, mixed $extra = null): string
    {
        $str = "<textarea name=\"{$name}\"";
        if ($width) {
            $str .= "cols=\"{$width}\" ";
        }
        if ($height) {
            $str .= "rows=\"{$height}\" ";
        }
        $str .= " {$extra} >";
        $str .= htmlspecialchars($value);
        $str .= "</textarea>\n";
        return $str;
    }

    /**
     * @param string $name
     * @param string $value
     * @param mixed $extra
     * @return string
     */
    public static function hidden(string $name, string $value = '', mixed $extra = null): string
    {
        $str = "<input name=\"{$name}\" type=\"hidden\" value=\"";
        $str .= htmlspecialchars($value);
        $str .= "\" {$extra} />\n";
        return $str;
    }

    /**
     * @param string $name
     * @param int|null $width
     * @param mixed $extra
     * @return string
     */
    public static function fileField(string $name, int $width = null, mixed $extra = null): string
    {
        $str = "<input name=\"{$name}\" type=\"file\"";
        if ($width) {
            $str .= " size=\"{$width}\"";
        }
        $str .= " {$extra} />\n";
        return $str;
    }

    /**
     * @param string $name
     * @param string $action
     * @param string $method
     * @param string $onsubmit
     * @param mixed $extra
     * @return string
     */
    public static function form_open(string $name, string $action, string $method = 'post', string $onsubmit = '', mixed $extra = null): string
    {
        $str = "<form name=\"{$name}\" action=\"{$action}\" method=\"{$method}\" ";
        if ($onsubmit) {
            $str .= "onsubmit=\"{$onsubmit}\"";
        }
        $str .= " {$extra} >\n";
        return $str;
    }

    /**
     * @return string
     */
    public static function form_close(): string
    {
        return "</form>\n";
    }

}
