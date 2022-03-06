<?php

namespace Rafflex\AcfComposer;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

abstract class Block
{
    /**
     * Default field type settings.
     *
     * @return array
     */
    protected $defaults = [];

    /**
     * The display title of the block.
     *
     * @var string
     */
    protected $title = '';

    /**
     * The display name of the block.
     *
     * @var string
     */
    protected $name = '';

    /**
     * The slug of the block.
     *
     * @var string
     */
    protected $slug = '';

    /**
     * The description of the block.
     *
     * @var string
     */
    protected $description = '';

    /**
     * The category this block belongs to.
     *
     * @var string
     */
    protected $category = '';

    /**
     * The icon of this block.
     *
     * @var string|array
     */
    protected $icon = '';

    /**
     * An array of keywords the block will be found under.
     *
     * @var array
     */
    protected $keywords = [];

    /**
     * An array of post types the block will be available to.
     *
     * @var array
     */
    protected $post_types = ['post', 'page'];

    /**
     * The default display mode of the block that is shown to the user.
     *
     * @var string
     */
    protected $mode = 'edit';

    /**
     * The block alignment class.
     *
     * @var string
     */
    protected $align = '';

    /**
     * Features supported by the block.
     *
     * @var array
     */
    protected $supports = [];

    /**
     * Styles supported by the block.
     *
     * @var array
     */
    protected $styles = [];

    /**
     * Assets enqueued when the block is shown.
     *
     * @var array
     */
    protected $assets = [];

    /**
     * The blocks status.
     *
     * @var boolean
     */
    protected $enabled = true;

    /**
     * The block field groups.
     *
     * @var array
     */
    protected $fields;

    /**
     * The block namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The block prefix.
     *
     * @var string
     */
    protected $prefix = 'acf/';

    /**
     * The block properties.
     *
     * @var array
     */
    protected $block;

    /**
     * The block content.
     *
     * @var string
     */
    protected $content;

    /**
     * The block preview status.
     *
     * @var bool
     */
    protected $preview;

    /**
     * The current post ID.
     *
     * @param int
     */
    protected $post;

    protected $classname;

    /**
     * Compose the block.
     *
     * @return void
     */
    public function compose()
    {
        if (!$this->register() || !function_exists('acf')) {
            return;
        }

        collect($this->register())->each(function ($value, $name) {
            $this->{$name} = $value;
        });

        $this->defaults = collect(
            config('acf.defaults')
        )->merge($this->defaults)->mapWithKeys(function ($value, $key) {
            return [Str::snake($key) => $value];
        });

        $this->slug = Str::slug($this->name);
        $this->namespace = $this->prefix . $this->slug;
        $this->fields = $this->fields();

        if (!$this->enabled) {
            return;
        }

        add_action('init', function () {
            acf_register_block([
                'title'           => $this->title,
                'name'            => $this->slug,
                'description'     => $this->description,
                'category'        => $this->category,
                'icon'            => $this->icon,
                'keywords'        => $this->keywords,
                'post_types'      => $this->post_types,
                'mode'            => $this->mode,
                'align'           => $this->align,
                'supports'        => $this->supports,
                'styles'          => $this->styles,
                'className'       => $this->classname,
                'enqueue_assets'  => [$this, 'assets'],
                'render_callback' => function ($block, $content = '', $preview = false, $post = 0) {
                    $this->block = (object) $block;
                    $this->content = $content;
                    $this->preview = $preview;
                    $this->post = $post;

                    echo $this->view();
                }
            ]);

            if (!empty($this->fields)) {
                if ($this->defaults->has('field_group')) {
                    $this->fields = array_merge($this->fields, $this->defaults->get('field_group'));
                }

                if (!Arr::has($this->fields, 'location.0.0')) {
                    Arr::set($this->fields, 'location.0.0', [
                        'param' => 'block',
                        'operator' => '==',
                        'value' => $this->namespace,
                    ]);
                }

                acf_add_local_field_group($this->build());
            }
        }, 20);
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
                !Str::contains($key, ['fields', 'sub_fields', 'layouts']) ||
                (Str::is($key, 'type') && !$this->defaults->has($value))
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
     * Path for the block.
     *
     * @return string
     */
    protected function path()
    {
        return dirname((new \ReflectionClass($this))->getFileName());
    }

    protected function blockPath()
    {
//        return (config('theme.views')[1]);
        return app('wp.theme')->getUrl('resources/Blocks');
//        return base_path('app/Domain/Blocks');
    }

    /**
     * View used for rendering the block.
     *
     * @return string
     */
    public function view()
    {
        if (view($view = "blocks/{$this->slug}")) {
            return view(
                $view,
                array_merge($this->with(), ['block' => $this->block])
            )->render();
        }

        return view(
            __DIR__ . '/../resources/views/view-404.blade.php',
            ['view' => $view]
        )->render();
    }

    /**
     * Assets used when rendering the block.
     *
     * @return void
     */
    public function assets($theme)
    {
        $asset_file =  app('wp.theme')->getUrl("resources/Blocks/{$this->name}/assets/{$this->slug}");

        if (file_exists($style = $this->path() . "/assets/{$this->slug}.css")) {
            wp_enqueue_style($this->prefix . $this->slug, $asset_file . '.css', false, null);
        }

        if (file_exists($script = $this->path() . "/assets/{$this->slug}.js")) {
            wp_enqueue_script($this->prefix . $this->slug, $asset_file . '.js', null, null, true);
        }
    }

    /**
     * Data to be passed to the block before registering.
     *
     * @return array
     */
    public function register()
    {
        return [];
    }

    /**
     * Fields to be attached to the block.
     *
     * @return array
     */
    public function fields()
    {
        return [];
    }

    /**
     * Data to be passed to the rendered block.
     *
     * @return array
     */
    public function with()
    {
        return [];
    }
}
