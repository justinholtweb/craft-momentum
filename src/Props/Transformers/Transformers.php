<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Props\Transformers;

use CraftCms\Cms\Field\Addresses;
use CraftCms\Cms\Field\BaseOptionsField;
use CraftCms\Cms\Field\BaseRelationField;
use CraftCms\Cms\Field\Color;
use CraftCms\Cms\Field\ContentBlock;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Country;
use CraftCms\Cms\Field\Date;
use CraftCms\Cms\Field\Email;
use CraftCms\Cms\Field\Icon;
use CraftCms\Cms\Field\Json;
use CraftCms\Cms\Field\Lightswitch;
use CraftCms\Cms\Field\Link;
use CraftCms\Cms\Field\Markdown;
use CraftCms\Cms\Field\Matrix;
use CraftCms\Cms\Field\Money;
use CraftCms\Cms\Field\Number;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\Field\Range;
use CraftCms\Cms\Field\Table;
use CraftCms\Cms\Field\Time;
use CraftCms\HtmlField\HtmlField;
use Illuminate\Contracts\Events\Dispatcher;
use JustinHoltWeb\Momentum\Events\RegisterFieldTransformers;
use JustinHoltWeb\Momentum\Props\PropSerializer;

/**
 * The field class → transformer map. A field uses the entry for its own class,
 * else the nearest parent class's; fields with none fall back to
 * {@see PropSerializer::value()}.
 */
class Transformers
{
    /** @var array<class-string, FieldTransformer>|null */
    private ?array $map = null;

    /** @var array<class-string, FieldTransformer|null> */
    private array $resolved = [];

    public function __construct(private readonly Dispatcher $events) {}

    public function for(FieldInterface $field): ?FieldTransformer
    {
        $class = $field::class;

        if (array_key_exists($class, $this->resolved)) {
            return $this->resolved[$class];
        }

        $map = $this->map();

        for ($c = $class; $c !== false; $c = get_parent_class($c)) {
            if (isset($map[$c])) {
                return $this->resolved[$class] = $map[$c];
            }
        }

        return $this->resolved[$class] = null;
    }

    /**
     * @return array<class-string, FieldTransformer>
     */
    public function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $this->events->dispatch($event = new RegisterFieldTransformers(self::defaults()));

        return $this->map = $event->transformers;
    }

    /**
     * @return array<class-string, FieldTransformer>
     */
    public static function defaults(): array
    {
        $map = [
            PlainText::class => new ScalarTransformer('string | null'),
            Email::class => new ScalarTransformer('string | null'),
            Number::class => new ScalarTransformer('number | null'),
            Range::class => new ScalarTransformer('number | null'),
            Lightswitch::class => new ScalarTransformer('boolean'),
            Date::class => new ScalarTransformer('string | null'),
            Time::class => new ScalarTransformer('string | null'),
            Json::class => new JsonTransformer,
            BaseOptionsField::class => new OptionsTransformer,
            BaseRelationField::class => new RelationTransformer,
            Matrix::class => new NestedEntriesTransformer,
            ContentBlock::class => new ContentBlockTransformer,
            Link::class => new LinkTransformer,
            Money::class => new MoneyTransformer,
            Color::class => new ColorTransformer,
            Country::class => new CountryTransformer,
            Icon::class => new IconTransformer,
            Table::class => new TableTransformer,
            Markdown::class => new HtmlTransformer,
            // Addresses are personal data: never sent, whatever the layout says.
            Addresses::class => new OmitTransformer,
        ];

        // CKEditor (and any other HTML field built on craftcms/html-field) is optional.
        if (class_exists(HtmlField::class)) {
            $map[HtmlField::class] = new HtmlTransformer;
        }

        return $map;
    }
}
