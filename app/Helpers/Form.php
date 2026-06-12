<?php

namespace App\Helpers;

use Illuminate\Support\HtmlString;

class Form
{
    /**
     * Open a form tag.
     */
    public static function open(array $options = []): HtmlString
    {
        $method = strtoupper($options['method'] ?? 'POST');
        $action = '';

        if (isset($options['route'])) {
            $route = $options['route'];
            if (is_array($route)) {
                $action = route($route[0], array_slice($route, 1));
            } else {
                $action = route($route);
            }
        } elseif (isset($options['url'])) {
            $action = $options['url'];
        } elseif (isset($options['action'])) {
            $action = action($options['action']);
        }

        $id = isset($options['id']) ? ' id="'.e($options['id']).'"' : '';
        $class = isset($options['class']) ? ' class="'.e($options['class']).'"' : '';
        $enctype = isset($options['files']) && $options['files'] ? ' enctype="multipart/form-data"' : '';

        $html = '<form method="'.($method === 'GET' ? 'GET' : 'POST').'" action="'.e($action).'"'.$id.$class.$enctype.'>';
        $html .= csrf_field();

        if (! in_array($method, ['GET', 'POST'])) {
            $html .= method_field($method);
        }

        return new HtmlString($html);
    }

    /**
     * Open a form tag with model binding.
     */
    public static function model($model, array $options = []): HtmlString
    {
        return self::open($options);
    }

    /**
     * Close a form tag.
     */
    public static function close(): HtmlString
    {
        return new HtmlString('</form>');
    }

    /**
     * Create a label element.
     */
    public static function label(string $name, ?string $value = null, array $options = []): HtmlString
    {
        $value = $value ?? ucfirst(str_replace('_', ' ', $name));
        $class = '';

        if (isset($options['class'])) {
            $class = ' class="'.e($options['class']).'"';
        } elseif (! empty($options) && ! isset($options[0])) {
            // Handle case where class is passed as first element
            $class = ' class="'.e(implode(' ', $options)).'"';
        } elseif (isset($options[0])) {
            $class = ' class="'.e($options[0]).'"';
        }

        return new HtmlString('<label for="'.e($name).'"'.$class.'>'.e($value).'</label>');
    }

    /**
     * Create a text input.
     */
    public static function text(string $name, $value = null, array $options = []): HtmlString
    {
        return self::input('text', $name, $value, $options);
    }

    /**
     * Create a password input.
     */
    public static function password(string $name, array $options = []): HtmlString
    {
        return self::input('password', $name, null, $options);
    }

    /**
     * Create a hidden input.
     */
    public static function hidden(string $name, $value = null, array $options = []): HtmlString
    {
        return self::input('hidden', $name, $value, $options);
    }

    /**
     * Create an email input.
     */
    public static function email(string $name, $value = null, array $options = []): HtmlString
    {
        return self::input('email', $name, $value, $options);
    }

    /**
     * Create a number input.
     */
    public static function number(string $name, $value = null, array $options = []): HtmlString
    {
        return self::input('number', $name, $value, $options);
    }

    /**
     * Create a date input.
     */
    public static function date(string $name, $value = null, array $options = []): HtmlString
    {
        return self::input('date', $name, $value, $options);
    }

    /**
     * Create a file input.
     */
    public static function file(string $name, array $options = []): HtmlString
    {
        return self::input('file', $name, null, $options);
    }

    /**
     * Create an input element.
     */
    public static function input(string $type, string $name, $value = null, array $options = []): HtmlString
    {
        $attributes = self::buildAttributes($options);

        // Handle old input value
        $value = old($name, $value);
        $valueAttr = $value !== null ? ' value="'.e($value).'"' : '';

        return new HtmlString('<input type="'.e($type).'" name="'.e($name).'" id="'.e($name).'"'.$valueAttr.$attributes.'>');
    }

    /**
     * Create a textarea element.
     */
    public static function textarea(string $name, $value = null, array $options = []): HtmlString
    {
        $attributes = self::buildAttributes($options);
        $value = old($name, $value);

        return new HtmlString('<textarea name="'.e($name).'" id="'.e($name).'"'.$attributes.'>'.e($value ?? '').'</textarea>');
    }

    /**
     * Create a select element.
     */
    public static function select(string $name, array $list = [], $selected = null, array $options = []): HtmlString
    {
        $attributes = self::buildAttributes($options);
        $selected = old($name, $selected);

        $html = '<select name="'.e($name).'" id="'.e($name).'"'.$attributes.'>';

        foreach ($list as $value => $display) {
            $isSelected = self::isSelected($value, $selected) ? ' selected' : '';
            $html .= '<option value="'.e($value).'"'.$isSelected.'>'.e($display).'</option>';
        }

        $html .= '</select>';

        return new HtmlString($html);
    }

    /**
     * Create a checkbox input.
     */
    public static function checkbox(string $name, $value = 1, $checked = null, array $options = []): HtmlString
    {
        $attributes = self::buildAttributes($options);
        $checkedAttr = $checked ? ' checked' : '';

        return new HtmlString('<input type="checkbox" name="'.e($name).'" id="'.e($name).'" value="'.e($value).'"'.$checkedAttr.$attributes.'>');
    }

    /**
     * Create a radio input.
     */
    public static function radio(string $name, $value = null, $checked = null, array $options = []): HtmlString
    {
        $attributes = self::buildAttributes($options);
        $checkedAttr = $checked ? ' checked' : '';

        return new HtmlString('<input type="radio" name="'.e($name).'" value="'.e($value).'"'.$checkedAttr.$attributes.'>');
    }

    /**
     * Create a submit button.
     */
    public static function submit(?string $value = null, array $options = []): HtmlString
    {
        $value = $value ?? 'Submit';
        $attributes = self::buildAttributes($options);

        return new HtmlString('<button type="submit"'.$attributes.'>'.e($value).'</button>');
    }

    /**
     * Create a button element.
     */
    public static function button(?string $value = null, array $options = []): HtmlString
    {
        $value = $value ?? 'Button';
        $type = $options['type'] ?? 'button';
        unset($options['type']);
        $attributes = self::buildAttributes($options);

        return new HtmlString('<button type="'.e($type).'"'.$attributes.'>'.e($value).'</button>');
    }

    /**
     * Create a reset button.
     */
    public static function reset(?string $value = null, array $options = []): HtmlString
    {
        $value = $value ?? 'Reset';
        $attributes = self::buildAttributes($options);

        return new HtmlString('<button type="reset"'.$attributes.'>'.e($value).'</button>');
    }

    /**
     * Build HTML attributes from array.
     */
    protected static function buildAttributes(array $options): string
    {
        $html = '';

        foreach ($options as $key => $value) {
            if (is_numeric($key)) {
                // Handle boolean attributes like 'disabled', 'readonly', etc.
                if (is_string($value) && $value !== '') {
                    $html .= ' '.e($value);
                }
            } elseif ($value === true) {
                $html .= ' '.e($key);
            } elseif ($value !== false && $value !== null) {
                $html .= ' '.e($key).'="'.e($value).'"';
            }
        }

        return $html;
    }

    /**
     * Check if a value is selected.
     */
    protected static function isSelected($value, $selected): bool
    {
        if (is_array($selected)) {
            return in_array($value, $selected);
        }

        return (string) $value === (string) $selected;
    }
}
