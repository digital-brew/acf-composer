<?php

namespace Rafflex\AcfComposer;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class Field
{
    /**
     * The field group.
     *
     * @var array
     */
    protected $fields;

    /**
     * Default field type settings.
     *
     * @return array
     */
    protected $defaults = [];

    /**
     * Compose the field.
     *
     * @return void
     */
    public function compose()
    {
        if (! $this->fields() || ! function_exists('acf')) {
            return;
        }

        $this->fields = $this->fields();

        $this->defaults = collect(
            config('acf.defaults')
        )->merge($this->defaults)->mapWithKeys(function ($value, $key) {
            return [Str::snake($key) => $value];
        });

        if (! empty($this->fields)) {
            add_action('init', function () {
                if ($this->defaults->has('field_group')) {
                    $this->fields = array_merge($this->fields, $this->defaults->get('field_group'));
                }

                acf_add_local_field_group($this->build());
            }, 20);
        }
    }

    /**
     * Build the field group with our default field type settings.
     *
     * @param  array $fields
     * @return array
     */
    protected function build($fields = [])
    {
        return collect($fields ?: $this->fields)->map(function ($value, $key) use ($fields) {
            if (
                ! Str::contains($key, ['fields', 'sub_fields', 'layouts']) ||
                (Str::is($key, 'type') && ! $this->defaults->has($value))
            ) {
                return $value;
            }

            return array_map(function ($field) {
                if (collect($field)->keys()->intersect(['fields', 'sub_fields', 'layouts'])->isNotEmpty()) {
                    return $this->build($field);
                }

                return array_merge($this->defaults->get($field['type'], []), $field);
            }, $value);
        })->all();
    }

    /**
     * Get field partial if it exists.
     *
     * @param  string $name
     * @return mixed
     */
    protected function get($name = '')
    {
        $name = strtr($name, [
            '.php' => '',
            '.' => '/'
        ]);

        return include app_path("Fields/{$name}.php");
    }

    /**
     * Fields to be attached to the field.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }
}
